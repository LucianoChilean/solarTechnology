# SolarTechonolgy — WordPress + WooCommerce (Docker, endurecido)

Sitio de la empresa solar chilena **SolarTechonolgy**, reconstruido desde el ejemplo
`Solartechonolgy.html` como WordPress con theme propio, catálogo WooCommerce,
monitor de seguridad y endurecimiento contra backdoors.

> **El sitio funciona en modo catálogo**: muestra los productos y sus precios, pero
> no tiene carrito, checkout, cuentas de cliente ni pasarelas de pago. Cada producto
> se cotiza por WhatsApp. La tienda y el pago con Flow.cl están implementados y se
> pueden reactivar cuando haga falta — ver [Modo catálogo](#modo-catálogo).

## Requisitos
- Docker Desktop (Docker + Docker Compose v2).

## Levantar el sitio

```sh
# 1. Levantar base de datos + WordPress + phpMyAdmin
docker compose up -d db wordpress phpmyadmin

# 2. Provisionar todo (WP, idioma es_CL, WooCommerce, IVA, 24 productos, theme, seguridad)
docker compose run --rm --entrypoint /bin/sh wpcli -c "sh /seed/setup.sh"
```

> En Windows/Git Bash antepón `MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL='*'` al comando
> `docker compose run` para que no se conviertan las rutas `/seed/...`.

El script es idempotente: se puede volver a ejecutar tras cambiar el theme o el seed.

### URLs
| Qué | URL |
|-----|-----|
| Sitio | http://localhost:8080 |
| Catálogo | http://localhost:8080/catalogo/ |
| **Login (oculto)** | http://localhost:8080/acceso-solar |
| Monitor de seguridad | wp-admin → menú **Seguridad** (tras iniciar sesión) |
| phpMyAdmin | http://localhost:8081 |

`/tienda/`, `/carrito/`, `/finalizar-compra/` y `/mi-cuenta/` redirigen al catálogo.

`wp-admin` y `wp-login.php` devuelven **404** a propósito. Entra siempre por el slug secreto.

Credenciales de admin y todos los secretos están en **`.env`** (cámbialos antes de producción).

## Estructura
```
docker-compose.yml     # mysql:8 + wordpress:php8.3-apache + wpcli + phpmyadmin
.env                   # credenciales / claves (placeholder) — NO subir a git
.htaccess              # reglas de seguridad Apache (montado en la raíz de WP)
uploads.ini            # límites PHP + disable_functions peligrosas
seed/
  setup.sh             # provisión con WP-CLI (idempotente)
  products.csv         # 24 productos (fuente)
  import-products.php   # importador WooCommerce
wp-content/
  themes/solartechonolgy/  # theme clásico a medida (diseño del ejemplo)
    inc/catalog-mode.php   # desactiva compra, carrito, checkout y pagos
    template-parts/catalog.php  # grilla del catálogo + filtros por categoría
  mu-plugins/              # seguridad + Flow + reembolsos (must-use, sin plugins del repo)
```

## Modo catálogo
Activo por defecto. Mientras lo esté: nada es comprable, no se cargan pasarelas y
las páginas de carrito / checkout / mi cuenta redirigen al catálogo. Cada producto
ofrece **Cotizar por WhatsApp** con el nombre y el enlace del producto en el mensaje.

| Opción | Valor | Qué hace |
|--------|-------|----------|
| `st_catalog_mode` | `yes` (def.) / `no` | `no` reabre la tienda completa (carrito, checkout, Flow) |
| `st_catalog_prices` | `yes` (def.) / `no` | `no` oculta los precios y deja solo la cotización |

```sh
# Reabrir la tienda con pagos:
docker compose run --rm --entrypoint /bin/sh wpcli -c "wp --path=/var/www/html option update st_catalog_mode no"
```

El código de tienda y pagos (`60-flow-gateway.php`, `61-refunds.php`) sigue en el
repositorio y vuelve a entrar en juego al desactivar el modo catálogo.

### mu-plugins (código propio, no son plugins del repositorio)
| Archivo | Función |
|---------|---------|
| `00-security.php` | XML-RPC off, anti-enumeración, headers, no subir ejecutables |
| `10-hide-login.php` | Login oculto (`/acceso-solar`), wp-admin/wp-login → 404 |
| `20-login-throttle.php` | Límite de intentos de login por IP |
| `30-recaptcha.php` | reCAPTCHA v3 (login, checkout, contacto) |
| `40-monitor.php` | **Monitor**: salud, lista negra IP, log de visitas, detección de ataques |
| `60-flow-gateway.php` | **Pasarela Flow.cl** (create/getStatus/refund) |
| `61-refunds.php` | **Reembolsos**: solicitud desde "Mi cuenta" + reembolso nativo → Flow |

## Pagos y reembolsos (Flow.cl) — inactivos en modo catálogo
Solo aplican si se desactiva el modo catálogo.

- Gateway **propio** (sin plugin externo) que integra la API de Flow: crea el pago,
  confirma por webhook y consulta estado. Medios: tarjetas y transferencia.
- **Reembolsos**: el cliente pulsa *Solicitar reembolso* en **Mi cuenta → Pedidos**
  (el pedido pasa a estado "Reembolso solicitado" y se avisa al admin). El admin lo
  aprueba con el botón *Reembolso* del pedido, que llama a la API de reembolso de Flow.

## Monitor de seguridad (wp-admin → Seguridad)
- **Estado de salud**: PHP/WP, HTTPS, edición de archivos, XML-RPC, updates, DB.
- **Lista negra de IP**: bloquea IPs (403); agregar/quitar desde el panel.
- **Visitantes por IP**: registro de accesos recientes.
- **Detección de ataques**: SQLi, XSS, path traversal, acceso a config, RCE, escaneos
  y herramientas ofensivas (sqlmap, nikto, wpscan…). Auto-bloqueo tras varios intentos.

## Seguridad implementada (anti-backdoor)
- **Login oculto**: `wp-admin` / `wp-login.php` → 404; acceso solo por slug secreto (`.env` `LOGIN_SLUG`).
- **`.htaccess`**: bloquea `wp-config.php`, `xmlrpc.php`, `.env`, `.sql`, dotfiles; sin listado de
  directorios; **PHP no se ejecuta en `wp-content/uploads/`** (principal vector de shells).
- **mu-plugins**: XML-RPC off, sin enumeración de usuarios (`?author=N`, REST users),
  versión de WP oculta, límite de intentos de login por IP, App Passwords off,
  bloqueo de subida de archivos ejecutables.
- **wp-config**: `DISALLOW_FILE_EDIT`, `DISALLOW_UNFILTERED_HTML`, revisiones limitadas.
- **reCAPTCHA v3** en login, checkout y contacto (se activa al poner claves reales).

## Pendiente antes de producción
1. **Imágenes de producto**: los 24 productos no traen foto y se ven con un marcador
   de posición. Súbelas desde wp-admin → Productos.
2. **reCAPTCHA**: pon tus claves reales en `.env` (`RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`).
   Con placeholders la validación queda desactivada para no bloquearte.
3. **Contraseñas**: cambia todas las de `.env` (DB y admin).
4. **HTTPS**: al servir con TLS, descomenta `FORCE_SSL_ADMIN` (compose) y `HSTS` (.htaccess).
5. Opcional máxima dureza tras el setup: activar `DISALLOW_FILE_MODS` (bloquea instalar/actualizar
   plugins desde el panel).
6. Solo si reactivas la tienda — **Flow**: registra tu comercio en https://sandbox.flow.cl
   (pruebas) o https://www.flow.cl (producción) y pon `FLOW_API_KEY` / `FLOW_SECRET_KEY`
   en `.env`. Para producción, `FLOW_SANDBOX=no`. Sin claves, el checkout avisa que
   falta configuración.

## Apagar / reiniciar
```sh
docker compose down            # detiene (conserva datos)
docker compose down -v         # BORRA base de datos y core (empezar de cero)
```
