# Despliegue en hosting con cPanel

Guía para llevar SolarTechonolgy de Docker (local) a un hosting compartido con cPanel.

Hay tres caminos. **Usa la Ruta A**: un script arma el paquete completo y deja las
instrucciones con tus datos ya escritos. Las otras dos son para cuando no puedes
ejecutar el script o no tienes el entorno local.

| Ruta | Cuándo | Esfuerzo |
|------|--------|----------|
| **A — Automática** (`deploy\build.ps1`) | Tienes Docker y el sitio local funcionando | 1 comando + subir 2 archivos |
| **B — Manual desde Docker** | Docker sí, pero no puedes correr PowerShell | ~20 pasos |
| **C — Instalación limpia** | No tienes el entorno local | ~1 hora, hay que rehacer el contenido |

> Reemplaza en todos los comandos:
> - `tudominio.cl` → tu dominio real
> - `usuario` → tu usuario de cPanel

---

## 0. Requisitos del hosting (verifícalos antes de contratar/empezar)

| Requisito | Dónde se ve en cPanel | Valor mínimo |
|-----------|----------------------|--------------|
| PHP | *Select PHP Version* / *MultiPHP Manager* | **8.1+** (ideal 8.2 u 8.3) |
| Extensiones PHP | *Select PHP Version → Extensions* | `mysqli`, `curl`, `mbstring`, `gd` (o `imagick`), `zip`, `intl`, `exif`, `xml`, `json`, `openssl`, `fileinfo`, `sodium` |
| MySQL / MariaDB | *MySQL Databases* | MySQL 5.7+ / MariaDB 10.4+ |
| Apache con `mod_rewrite` y `AllowOverride All` | — | necesario para permalinks y el `.htaccess` |
| SSL | *SSL/TLS Status* (AutoSSL / Let's Encrypt) | obligatorio |
| Acceso SSH (opcional pero recomendado) | *Terminal* / *SSH Access* | facilita import de BD grande |

---

## Cómo se configura el sitio en producción

En Docker la configuración llega por variables de entorno del `docker-compose.yml`.
En cPanel no hay entorno que definir, así que el mu-plugin `01-config.php` resuelve
cada valor en este orden:

1. **Constante de `wp-config.php`** con prefijo `ST_` → `define( 'ST_LOGIN_SLUG', 'acceso-solar' );`
2. **Variable de entorno** (solo aplica en Docker)
3. **Opción de la base de datos** (`st_login_slug`, `st_whatsapp_number`, …), que viaja en el dump

Por eso el mismo código funciona en local y en el hosting sin tocar nada: en
producción manda `wp-config.php`, y si una constante queda vacía se usa lo que ya
está guardado en la BD.

Claves que entiende: `ST_LOGIN_SLUG`, `ST_WHATSAPP_NUMBER`, `ST_CONTACT_EMAIL`,
`ST_RECAPTCHA_SITE_KEY`, `ST_RECAPTCHA_SECRET_KEY`, `ST_FLOW_API_KEY`,
`ST_FLOW_SECRET_KEY`, `ST_FLOW_SANDBOX`.

---

## Ruta A — Automática (RECOMENDADA)

`deploy\build.ps1` exporta el sitio que corre en Docker y deja en `dist/` todo lo
que hay que subir. Conserva los 24 productos, el IVA, el menú, las páginas de
WooCommerce con slugs en español, las opciones `st_*` y las imágenes subidas.

### A1. Crear la base de datos en cPanel

1. **MySQL® Databases** → *Create New Database*: `solartech` → queda como `usuario_solartech`.
2. *Add New User*: `st_user` → queda como `usuario_st_user`. Contraseña larga; guárdala.
3. *Add User To Database* → marca **ALL PRIVILEGES**.

Anota los tres datos: nombre completo de la BD, usuario completo y contraseña.

### A2. Ejecutar el script

Con Docker Desktop en marcha, desde la raíz del repo (PowerShell):

```powershell
.\deploy\build.ps1 -Domain tudominio.cl `
                   -DbName usuario_solartech `
                   -DbUser usuario_st_user `
                   -DbPassword 'la-contraseña-de-A1'
```

Si omites los datos de la BD te los pregunta uno por uno.

El script:

- levanta `db` y `wordpress` y espera a que la BD responda;
- cambia `home`/`siteurl` y hace `search-replace` de `http://localhost:8080` → `https://tudominio.cl`;
- exporta el dump y **devuelve el local a `localhost:8080`** (aunque algo falle a mitad);
- copia el sitio del contenedor y borra lo que no debe viajar (`wp-config.php` de Docker, `debug.log`, caché, `hello.php`, `readme.html`, `license.txt`);
- añade `.htaccess`, `.user.ini` y `wp-content/uploads/.htaccess`;
- genera un `wp-config.php` con **8 claves de seguridad nuevas**, tus datos de BD y las constantes `ST_*` tomadas del `.env`;
- comprime todo y escribe las instrucciones.

Opciones útiles:

| Parámetro | Para qué |
|-----------|----------|
| `-TablePrefix stwp_` | Si tu `.env` no tiene `DB_PREFIX` |
| `-DbHost localhost` | Algunos hostings usan otro host de MySQL |
| `-SkipDatabase` | Reusa `dist/database.sql` (rearmar solo los archivos) |
| `-NoZip` | Deja `dist/site/` sin comprimir, para subir por FTP |

### A3. Qué queda en `dist/`

```
dist/
  solartech-tudominio.cl.zip   → se descomprime dentro de public_html
  database.sql                 → se importa en phpMyAdmin
  LEEME-SUBIR.txt              → los pasos con tus datos ya rellenados
```

> ⚠️ `dist/` contiene la contraseña de la base de datos en texto plano dentro de
> `wp-config.php`. Ya está en `.gitignore`: no lo subas a git ni lo compartas.

### A4. Subir

1. **phpMyAdmin** → selecciona `usuario_solartech` → *Importar* → `dist/database.sql`.
   Si pesa más de ~50 MB, comprímelo a `.sql.gz` o impórtalo por SSH:
   ```sh
   mysql -u usuario_st_user -p usuario_solartech < database.sql
   ```
2. **File Manager** → `public_html` → borra el `index.html` por defecto → *Upload* del
   zip → clic derecho → *Extract* → borra el zip.
   (En subdominio o addon domain, la carpeta destino es su *document root*.)
   Por SSH:
   ```sh
   scp dist/solartech-tudominio.cl.zip usuario@tudominio.cl:~/public_html/
   ssh usuario@tudominio.cl "cd public_html && unzip -o solartech-tudominio.cl.zip && rm solartech-tudominio.cl.zip"
   ```
3. **Select PHP Version** → 8.1+ y las extensiones de la tabla del punto 0.
   Los límites (memoria, subidas) ya van en el `.user.ini` del paquete.
4. **SSL/TLS Status** → *Run AutoSSL* y **espera al certificado válido**: el
   `.htaccess` fuerza HTTPS, así que sin certificado el sitio no se ve.
5. Abre `https://tudominio.cl` y entra por `https://tudominio.cl/acceso-solar`.
   (`wp-admin` y `wp-login.php` devuelven 404 a propósito.)
6. **Ajustes → Enlaces permanentes** → *Guardar cambios*. Sin esto las URLs internas
   pueden dar 404.

Sigue con [Después de publicar](#después-de-publicar).

---

## Ruta B — Migración manual desde Docker

Los mismos pasos que hace el script, a mano.

### B1. Exportar la base de datos

```sh
docker compose up -d db wordpress
```

Apunta el sitio al dominio de producción **antes** de exportar (así los menús, las
opciones y todo lo serializado quedan con la URL correcta):

```sh
# En Windows/Git Bash antepón: MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL='*'
docker compose run --rm --entrypoint /bin/sh wpcli -c "\
  wp --path=/var/www/html option update home 'https://tudominio.cl' && \
  wp --path=/var/www/html option update siteurl 'https://tudominio.cl' && \
  wp --path=/var/www/html search-replace 'http://localhost:8080' 'https://tudominio.cl' --all-tables --precise --skip-columns=guid && \
  wp --path=/var/www/html db export /seed/solartech.sql --add-drop-table"
```

El dump queda en `seed/solartech.sql`.

**Deja el local como estaba** (para poder seguir trabajando en Docker):

```sh
docker compose run --rm --entrypoint /bin/sh wpcli -c "\
  wp --path=/var/www/html search-replace 'https://tudominio.cl' 'http://localhost:8080' --all-tables --precise --skip-columns=guid && \
  wp --path=/var/www/html option update home 'http://localhost:8080' && \
  wp --path=/var/www/html option update siteurl 'http://localhost:8080'"
```

### B2. Sacar los archivos del contenedor

```sh
docker cp st_wp:/var/www/html ./deploy-site
rm -f ./deploy-site/wp-config.php        # apunta a db:3306 — se rehace en el server
rm -rf ./deploy-site/wp-content/cache ./deploy-site/wp-content/debug.log
rm -f ./deploy-site/wp-content/plugins/hello.php ./deploy-site/readme.html ./deploy-site/license.txt
```

Añade los archivos del hosting:

```sh
cp deploy/user.ini            ./deploy-site/.user.ini
cp deploy/uploads.htaccess    ./deploy-site/wp-content/uploads/.htaccess
```

El `.htaccess` de la raíz ya viene en el paquete (sale del repo) y **ya incluye**
la redirección a HTTPS y el HSTS condicionado a `env=HTTPS`; no hay que tocarlo.

Comprime el **contenido** de `deploy-site/` (no la carpeta) en `solartech.zip`.

### B3. Crear la BD, subir e importar

Igual que A1 y A4 (puntos 1 y 2).

### B4. Crear `wp-config.php` en el servidor

Copia `deploy/wp-config-cpanel.php` a la raíz del sitio como `wp-config.php` y
reemplaza los marcadores `{{...}}`:

| Marcador | Valor |
|----------|-------|
| `{{DB_NAME}}` `{{DB_USER}}` `{{DB_PASSWORD}}` | los de B3 |
| `{{DB_HOST}}` | `localhost` (salvo que tu hosting diga otro) |
| `{{TABLE_PREFIX}}` | el mismo `DB_PREFIX` del `.env` (`stwp_`) |
| `{{SALTS}}` | el bloque de https://api.wordpress.org/secret-key/1.1/salt/ |
| `{{SITE_URL}}` | `https://tudominio.cl` |
| `{{LOGIN_SLUG}}` `{{WHATSAPP_NUMBER}}` `{{CONTACT_EMAIL}}` `{{RECAPTCHA_*}}` | los del `.env` |

Esa plantilla ya trae el endurecimiento que en Docker venía de
`WORDPRESS_CONFIG_EXTRA` (`DISALLOW_FILE_EDIT`, `FORCE_SSL_ADMIN`,
`DISABLE_WP_CRON`, el arreglo de `HTTP_X_FORWARDED_PROTO`, etc.).

Si dejas alguna constante `ST_*` vacía, el sitio usa la opción equivalente de la BD
(que viaja en el dump), así que no se rompe nada:

| Opción en la BD | Constante | Valor esperado |
|-----------------|-----------|----------------|
| `st_login_slug` | `ST_LOGIN_SLUG` | `acceso-solar` |
| `st_whatsapp_number` | `ST_WHATSAPP_NUMBER` | `56974089594` |
| `st_contact_email` | `ST_CONTACT_EMAIL` | tu correo |
| `st_recaptcha_site_key` / `st_recaptcha_secret_key` | `ST_RECAPTCHA_*` | tus claves reales |
| `st_catalog_mode` / `st_catalog_prices` | — | `yes` (solo en la BD) |

Las credenciales de Flow también se pueden cargar desde
**WooCommerce → Ajustes → Pagos → Flow**, y solo hacen falta si desactivas el modo
catálogo.

### B5. Límites de PHP

El `.user.ini` del paquete ya fija subidas, memoria y ocultar la versión de PHP.
Lo único que **no** se puede poner ahí es `disable_functions` (es `PHP_INI_SYSTEM`):
va en *MultiPHP INI Editor*.

⚠️ La lista de `uploads.ini` (con `proc_open`, `exec` y `curl_multi_exec`) es para el
contenedor; en hosting compartido suele romper herramientas del panel y las
actualizaciones. Usa esta:

```
disable_functions = passthru,shell_exec,system,popen,show_source,pcntl_exec,dl
```

Y verifica después que WordPress actualice plugins y que WooCommerce funcione.

### B6. SSL y primer arranque

Igual que A4 (puntos 4 a 6).

---

## Ruta C — Instalación limpia en cPanel (sin Docker)

Úsala solo si no puedes levantar el entorno local. Requiere reconstruir a mano lo
que hace `seed/setup.sh`.

1. **WordPress Toolkit** o **Softaculous → WordPress**: instala WP en el dominio con
   prefijo de tablas `stwp_`, idioma **Español de Chile**, sin plugins extra.
2. Sube por File Manager/FTP:
   - `wp-content/themes/solartechonolgy/` → misma ruta en el servidor
   - `wp-content/mu-plugins/` (los 8 archivos) → misma ruta (se activan solos)
   - `.htaccess` del repo → raíz
   - `deploy/user.ini` → raíz, como `.user.ini`
   - `deploy/uploads.htaccess` → `wp-content/uploads/.htaccess`
3. **Apariencia → Temas** → activa *SolarTechonolgy*.
4. **Plugins** → instala y activa **WooCommerce**. En el asistente: país Chile,
   moneda **CLP**.
5. **WooCommerce → Ajustes → General**: moneda CLP, separador de miles `.`,
   decimal `,`, **0 decimales**; activa *Habilitar impuestos*.
6. **Impuestos → Tarifas estándar**: país `CL`, tasa `19.0000`, nombre `IVA`,
   marcar *Envío*. En *Ajustes → Impuestos*: precios **sin** impuestos incluidos,
   sufijo de precio `+ IVA`.
7. **Productos → Importar** → sube `seed/products.csv` y completa el mapeo de
   columnas.
8. **Páginas**: cambia los slugs a `catalogo` (la página de tienda, título
   "Catálogo"), `carrito`, `finalizar-compra`, `mi-cuenta`.
9. **Ajustes → Enlaces permanentes** → estructura *Nombre de la entrada*.
10. Las opciones `st_*` no tienen pantalla de ajustes. Las de configuración van como
    constantes `ST_*` en el `wp-config.php` (paso B4); las que solo viven en la BD
    créalas con un mu-plugin temporal y **bórralo después**:

    ```php
    // wp-content/mu-plugins/99-tmp-config.php  ← BORRAR TRAS UNA CARGA
    add_action( 'init', function () {
        update_option( 'st_hero_badge',      'Oferta del Mes' );
        update_option( 'st_catalog_mode',    'yes' );
        update_option( 'st_catalog_prices',  'yes' );
        update_option( 'users_can_register', 0 );
        update_option( 'timezone_string',    'America/Santiago' );
        update_option( 'date_format',        'd/m/Y' );
    } );
    ```
11. **Apariencia → Menús**: crea el menú *Principal* con Catálogo, Beneficios,
    Cómo funciona, Contacto y asígnalo a la ubicación *primary*.
12. Sigue con B4 (`wp-config.php`), B5 (PHP) y B6 (SSL).

---

## Después de publicar

1. **Cambia la contraseña del admin** — la del `.env` es de desarrollo.
2. **Cron real** (el `wp-config.php` ya trae `DISABLE_WP_CRON`). *Cron Jobs* → cada 15 min:
   ```
   cd /home/usuario/public_html && /usr/local/bin/php -q wp-cron.php >/dev/null 2>&1
   ```
3. **Correo saliente**: `mail()` de hosting compartido suele ir a spam. Crea una
   cuenta en *Email Accounts* (`no-reply@tudominio.cl`) y configura un plugin SMTP
   (WP Mail SMTP) apuntando a `mail.tudominio.cl`, puerto 465/SSL.
4. **Permisos**: carpetas `755`, archivos `644`, `wp-config.php` a `640` (File
   Manager → *Change Permissions*).
5. **Backups**: activa los backups del hosting y/o *Backup Wizard*. Antes de cada
   actualización grande, exporta BD + `wp-content`.
6. **Imágenes de producto**: los 24 productos no traen foto (pendiente del README).
   Súbelas desde *Productos*.
7. Cuando el sitio esté estable, descomenta `DISALLOW_FILE_MODS` en `wp-config.php`
   (bloquea instalar/actualizar plugins desde el panel).
8. **Verificación final**:
   - [ ] `https://tudominio.cl` carga con candado y sin contenido mixto
   - [ ] `http://tudominio.cl` redirige a `https://`
   - [ ] `/catalogo/` lista los 24 productos con precio `+ IVA`
   - [ ] Botón *Cotizar por WhatsApp* abre el chat con el nombre del producto
   - [ ] `/tienda/`, `/carrito/`, `/finalizar-compra/`, `/mi-cuenta/` redirigen al catálogo
   - [ ] `/wp-admin` y `/wp-login.php` devuelven **404**
   - [ ] `/acceso-solar` muestra el login
   - [ ] `https://tudominio.cl/.env` y `/wp-config.php` devuelven **403**
   - [ ] wp-admin → **Seguridad** muestra el monitor sin avisos rojos

---

## Volver a desplegar (cambios posteriores)

Para actualizar solo el **código** (theme o mu-plugins), no hace falta rearmar todo:
sube por File Manager/FTP la carpeta modificada. La BD y los uploads del servidor
no se tocan.

Si necesitas volver a llevar contenido del local al servidor, repite la Ruta A —
pero ten en cuenta que `database.sql` **reemplaza** la base del servidor: se pierde
lo que se haya cargado allá (pedidos, productos nuevos, ajustes). Antes de importar,
haz un respaldo desde *Backup Wizard*.
