<#
.SYNOPSIS
    Arma el paquete listo para subir a un hosting con cPanel.

.DESCRIPTION
    Exporta el sitio que corre en Docker y deja en dist/ tres cosas:
      · solartech-<dominio>.zip  → se descomprime dentro de public_html
      · database.sql            → se importa en phpMyAdmin
      · LEEME-SUBIR.txt         → los pasos exactos, con tus datos ya puestos

    El script hace por ti todo lo delicado: el search-replace de la URL (y lo
    revierte para que el local siga funcionando), el wp-config.php con claves
    nuevas y las constantes ST_* que sustituyen a las variables de entorno.

.EXAMPLE
    .\deploy\build.ps1 -Domain solartechonolgy.cl

.EXAMPLE
    .\deploy\build.ps1 -Domain solartechonolgy.cl -DbName usuario_solartech -DbUser usuario_st_user
#>
[CmdletBinding()]
param(
    # Dominio de producción, con o sin https:// (ej. solartechonolgy.cl).
    [Parameter(Mandatory = $true)]
    [string] $Domain,

    # Datos de la base de datos que creaste en cPanel → MySQL® Databases.
    [string] $DbName,
    [string] $DbUser,
    [string] $DbPassword,
    [string] $DbHost = 'localhost',

    # Prefijo de tablas. Por defecto, el DB_PREFIX del .env (stwp_).
    [string] $TablePrefix,

    # Reutiliza el dist/database.sql anterior en vez de volver a exportar.
    [switch] $SkipDatabase,

    # Deja la carpeta dist/site sin comprimir (útil para subir por FTP).
    [switch] $NoZip
)

$ErrorActionPreference = 'Stop'
# Dos ensamblados distintos: ZipArchive/ZipArchiveMode están en el primero,
# ZipFileExtensions::CreateEntryFromFile en el segundo.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$RepoRoot = Split-Path -Parent $PSScriptRoot
$DistDir  = Join-Path $RepoRoot 'dist'
$SiteDir  = Join-Path $DistDir 'site'
$EnvFile  = Join-Path $RepoRoot '.env'

# --------------------------------------------------------------------------
#  Utilidades
# --------------------------------------------------------------------------
function Write-Step  { param([string]$m) Write-Host "`n==> $m" -ForegroundColor Yellow }
function Write-Ok    { param([string]$m) Write-Host "    OK  $m" -ForegroundColor Green }
function Write-Warn2 { param([string]$m) Write-Host "    !   $m" -ForegroundColor Magenta }

function Read-EnvFile {
    param([string]$Path)
    $map = @{}
    if (-not (Test-Path $Path)) { return $map }
    foreach ($line in (Get-Content -Path $Path -Encoding UTF8)) {
        $t = $line.Trim()
        if ($t -eq '' -or $t.StartsWith('#')) { continue }
        $i = $t.IndexOf('=')
        if ($i -lt 1) { continue }
        $map[$t.Substring(0, $i).Trim()] = $t.Substring($i + 1).Trim().Trim('"')
    }
    return $map
}

function Get-Env {
    param([hashtable]$Map, [string]$Key, [string]$Default = '')
    if ($Map.ContainsKey($Key) -and $Map[$Key] -ne '') { return $Map[$Key] }
    return $Default
}

function New-WpSalt {
    # Sin comillas simples, barras invertidas ni acentos graves: el valor va
    # dentro de una cadena PHP entre comillas simples.
    $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#%^&*()-_=+[]{}<>:?,./|~'
    $bytes  = New-Object 'System.Byte[]' 64
    $rng    = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $rng.GetBytes($bytes)
    $sb = New-Object System.Text.StringBuilder
    foreach ($b in $bytes) { [void]$sb.Append($chars[$b % $chars.Length]) }
    return $sb.ToString()
}

function Save-Utf8NoBom {
    param([string]$Path, [string]$Content)
    # Sin BOM: un BOM en wp-config.php rompe WordPress ("headers already sent").
    [System.IO.File]::WriteAllText($Path, $Content, (New-Object System.Text.UTF8Encoding($false)))
}

function New-SiteZip {
    param([string]$SourceDir, [string]$ZipPath)
    # NO usar [ZipFile]::CreateFromDirectory: en .NET Framework escribe los nombres
    # de entrada con BARRA INVERTIDA, que el estándar ZIP (APPNOTE 4.4.17.1) no
    # permite. Al extraer en el Linux de cPanel, "wp-content\mu-plugins\01.php" se
    # toma como UN nombre de archivo y el sitio queda como miles de archivos
    # sueltos en la raíz. Aquí cada entrada se normaliza a "/".
    $src = (Resolve-Path $SourceDir).Path.TrimEnd('\')
    if (Test-Path $ZipPath) { Remove-Item -Force $ZipPath }

    $fs = [System.IO.File]::Open($ZipPath, [System.IO.FileMode]::CreateNew)
    try {
        $zip = New-Object System.IO.Compression.ZipArchive(
            $fs, [System.IO.Compression.ZipArchiveMode]::Create)
        try {
            # Las carpetas van explícitas para no perder las que están vacías
            # (wp-content/upgrade, que WordPress necesita para actualizarse).
            foreach ($dir in [System.IO.Directory]::EnumerateDirectories(
                        $src, '*', [System.IO.SearchOption]::AllDirectories)) {
                $rel = $dir.Substring($src.Length + 1).Replace('\', '/') + '/'
                [void]$zip.CreateEntry($rel)
            }
            foreach ($file in [System.IO.Directory]::EnumerateFiles(
                        $src, '*', [System.IO.SearchOption]::AllDirectories)) {
                $rel = $file.Substring($src.Length + 1).Replace('\', '/')
                [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                    $zip, $file, $rel)
            }
        }
        finally { $zip.Dispose() }
    }
    finally { $fs.Dispose() }
}

function Invoke-WpCli {
    param([string]$Command)
    $dockerArgs = @('compose', 'run', '--rm', '--entrypoint', '/bin/sh', 'wpcli', '-c', $Command)
    # OJO: nada de "2>&1" aquí. En Windows PowerShell 5.1 redirigir el stderr de un
    # ejecutable nativo envuelve cada línea en un ErrorRecord (NativeCommandError);
    # con $ErrorActionPreference = 'Stop' un aviso tan inocente como
    # "Container st_wp Running" aborta el script. El stderr de docker va directo a
    # la consola y el resultado real se juzga por $LASTEXITCODE.
    return (& docker @dockerArgs)
}

# --------------------------------------------------------------------------
#  1. Datos de entrada
# --------------------------------------------------------------------------
Write-Step 'Preparando datos'

$envMap = Read-EnvFile -Path $EnvFile
if ($envMap.Count -eq 0) { Write-Warn2 'No se encontró .env — se usarán valores por defecto.' }

$Domain  = $Domain -replace '^https?://', ''
$Domain  = $Domain.TrimEnd('/')
$SiteUrl = "https://$Domain"
$LocalUrl = Get-Env $envMap 'SITE_URL' 'http://localhost:8080'

if (-not $TablePrefix) { $TablePrefix = Get-Env $envMap 'DB_PREFIX' 'stwp_' }
if (-not $DbName)      { $DbName      = Read-Host 'Nombre de la BD en cPanel (ej. usuario_solartech)' }
if (-not $DbUser)      { $DbUser      = Read-Host 'Usuario de la BD en cPanel (ej. usuario_st_user)' }
if (-not $DbPassword)  { $DbPassword  = Read-Host 'Contraseña de ese usuario' }

$LoginSlug   = Get-Env $envMap 'LOGIN_SLUG' 'acceso-solar'
$Whatsapp    = Get-Env $envMap 'WHATSAPP_NUMBER' '56974089594'
$ContactMail = Get-Env $envMap 'CONTACT_EMAIL' ''
$RecaptchaSite   = Get-Env $envMap 'RECAPTCHA_SITE_KEY' ''
$RecaptchaSecret = Get-Env $envMap 'RECAPTCHA_SECRET_KEY' ''

Write-Ok "Sitio de destino: $SiteUrl"
Write-Ok "Prefijo de tablas: $TablePrefix"

Push-Location $RepoRoot
try {

    # ----------------------------------------------------------------------
    #  2. Exportar la base de datos con la URL de producción
    # ----------------------------------------------------------------------
    New-Item -ItemType Directory -Force -Path $DistDir | Out-Null
    $SqlOut = Join-Path $DistDir 'database.sql'

    if ($SkipDatabase) {
        if (-not (Test-Path $SqlOut)) { throw "-SkipDatabase pero no existe $SqlOut" }
        Write-Step 'Base de datos: se reutiliza dist/database.sql'
    }
    else {
        Write-Step 'Levantando el entorno local'
        & docker compose up -d db wordpress
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo levantar Docker. ¿Está Docker Desktop en marcha?' }

        Write-Host '    Esperando a la base de datos...'
        $ready = $false
        for ($i = 0; $i -lt 40; $i++) {
            # Comillas SIMPLES: PowerShell 5.1 se come las dobles al pasar
            # argumentos a un .exe, y a MySQL le llegaría "SELECT" sin el "1".
            Invoke-WpCli "wp --path=/var/www/html db query 'SELECT 1'" | Out-Null
            if ($LASTEXITCODE -eq 0) { $ready = $true; break }
            Start-Sleep -Seconds 3
        }
        if (-not $ready) { throw 'La base de datos no respondió a tiempo.' }
        Write-Ok 'Base de datos lista'

        Write-Step "Exportando la BD con las URLs apuntando a $SiteUrl"
        $replaced = $false
        try {
            $cmd = "wp --path=/var/www/html option update home '$SiteUrl' && " +
                   "wp --path=/var/www/html option update siteurl '$SiteUrl' && " +
                   "wp --path=/var/www/html search-replace '$LocalUrl' '$SiteUrl' --all-tables --precise --skip-columns=guid"
            Invoke-WpCli $cmd | Write-Host
            if ($LASTEXITCODE -ne 0) { throw 'Falló el search-replace.' }
            $replaced = $true

            Invoke-WpCli 'wp --path=/var/www/html db export /seed/_deploy.sql --add-drop-table' | Write-Host
            if ($LASTEXITCODE -ne 0) { throw 'Falló la exportación de la base de datos.' }
        }
        finally {
            # Pase lo que pase, el entorno local vuelve a su URL de siempre.
            if ($replaced) {
                Write-Host '    Devolviendo el entorno local a su URL...'
                $back = "wp --path=/var/www/html search-replace '$SiteUrl' '$LocalUrl' --all-tables --precise --skip-columns=guid && " +
                        "wp --path=/var/www/html option update home '$LocalUrl' && " +
                        "wp --path=/var/www/html option update siteurl '$LocalUrl'"
                Invoke-WpCli $back | Out-Null
                if ($LASTEXITCODE -eq 0) { Write-Ok "Local restaurado en $LocalUrl" }
                else { Write-Warn2 "No se pudo restaurar el local. Ejecuta: docker compose run --rm --entrypoint /bin/sh wpcli -c `"$back`"" }
            }
        }

        $SqlSrc = Join-Path $RepoRoot 'seed\_deploy.sql'
        if (-not (Test-Path $SqlSrc)) { throw "No se generó el dump ($SqlSrc)." }
        Move-Item -Path $SqlSrc -Destination $SqlOut -Force
        $sqlMb = [math]::Round((Get-Item $SqlOut).Length / 1MB, 1)
        Write-Ok "dist/database.sql ($sqlMb MB)"
    }

    # ----------------------------------------------------------------------
    #  3. Copiar el sitio desde el contenedor
    # ----------------------------------------------------------------------
    Write-Step 'Copiando los archivos del sitio'
    if (Test-Path $SiteDir) { Remove-Item -Recurse -Force $SiteDir }
    & docker cp 'st_wp:/var/www/html' $SiteDir
    if ($LASTEXITCODE -ne 0) { throw 'Falló "docker cp". ¿Está corriendo el contenedor st_wp?' }
    Write-Ok 'Core, WooCommerce, theme, mu-plugins y uploads copiados'

    # ----------------------------------------------------------------------
    #  4. Limpiar lo que no debe viajar al servidor
    # ----------------------------------------------------------------------
    Write-Step 'Limpiando el paquete'
    $borrar = @(
        'wp-config.php',            # apunta a db:3306 — se genera uno nuevo
        'wp-content/debug.log',
        'wp-content/cache',
        'wp-content/upgrade',
        'wp-content/plugins/hello.php',
        'readme.html',
        'license.txt'
    )
    foreach ($rel in $borrar) {
        $p = Join-Path $SiteDir $rel
        if (Test-Path $p) { Remove-Item -Recurse -Force $p; Write-Host "    - $rel" }
    }
    New-Item -ItemType Directory -Force -Path (Join-Path $SiteDir 'wp-content/upgrade') | Out-Null

    # ----------------------------------------------------------------------
    #  5. Archivos propios del hosting
    # ----------------------------------------------------------------------
    Write-Step 'Añadiendo configuración de cPanel'

    Copy-Item -Path (Join-Path $RepoRoot '.htaccess') -Destination (Join-Path $SiteDir '.htaccess') -Force
    Write-Ok '.htaccess (HTTPS forzado + HSTS + bloqueos)'

    Copy-Item -Path (Join-Path $PSScriptRoot 'user.ini') -Destination (Join-Path $SiteDir '.user.ini') -Force
    Write-Ok '.user.ini (límites de PHP)'

    $uploadsDir = Join-Path $SiteDir 'wp-content/uploads'
    New-Item -ItemType Directory -Force -Path $uploadsDir | Out-Null
    Copy-Item -Path (Join-Path $PSScriptRoot 'uploads.htaccess') -Destination (Join-Path $uploadsDir '.htaccess') -Force
    Write-Ok 'wp-content/uploads/.htaccess (PHP no se ejecuta ahí)'

    # ----------------------------------------------------------------------
    #  6. wp-config.php con claves nuevas
    # ----------------------------------------------------------------------
    Write-Step 'Generando wp-config.php'
    $saltNames = @('AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY',
                   'AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT')
    $saltLines = foreach ($n in $saltNames) { "define( '$n', '$(New-WpSalt)' );" }
    $saltBlock = $saltLines -join "`r`n"

    $tpl = Get-Content -Path (Join-Path $PSScriptRoot 'wp-config-cpanel.php') -Raw -Encoding UTF8
    $tpl = $tpl.Replace('{{DB_NAME}}', $DbName).
                Replace('{{DB_USER}}', $DbUser).
                Replace('{{DB_PASSWORD}}', $DbPassword).
                Replace('{{DB_HOST}}', $DbHost).
                Replace('{{TABLE_PREFIX}}', $TablePrefix).
                Replace('{{SALTS}}', $saltBlock).
                Replace('{{SITE_URL}}', $SiteUrl).
                Replace('{{LOGIN_SLUG}}', $LoginSlug).
                Replace('{{WHATSAPP_NUMBER}}', $Whatsapp).
                Replace('{{CONTACT_EMAIL}}', $ContactMail).
                Replace('{{RECAPTCHA_SITE_KEY}}', $RecaptchaSite).
                Replace('{{RECAPTCHA_SECRET_KEY}}', $RecaptchaSecret)

    Save-Utf8NoBom -Path (Join-Path $SiteDir 'wp-config.php') -Content $tpl
    Write-Ok 'wp-config.php con 8 claves nuevas y las constantes ST_*'

    if ($RecaptchaSite -eq '' -or $RecaptchaSecret -eq '') {
        Write-Warn2 'Sin claves de reCAPTCHA en .env: la validación quedará desactivada.'
    }

    # ----------------------------------------------------------------------
    #  7. Comprimir
    # ----------------------------------------------------------------------
    $ZipPath = Join-Path $DistDir "solartech-$Domain.zip"
    if ($NoZip) {
        Write-Step 'Sin comprimir (-NoZip): sube el contenido de dist/site por FTP'
        $ZipPath = ''
    }
    else {
        Write-Step 'Comprimiendo'
        New-SiteZip -SourceDir $SiteDir -ZipPath $ZipPath
        $zipMb = [math]::Round((Get-Item $ZipPath).Length / 1MB, 1)
        Write-Ok "$(Split-Path -Leaf $ZipPath) ($zipMb MB)"
    }

    # ----------------------------------------------------------------------
    #  8. Instrucciones con los datos ya rellenados
    # ----------------------------------------------------------------------
    $zipName = 'dist/site (sin comprimir)'
    if ($ZipPath -ne '') { $zipName = Split-Path -Leaf $ZipPath }

    $leeme = @"
SolarTechonolgy — subir a cPanel
================================
Generado para: $SiteUrl

QUE HAY EN dist/
  $zipName   -> los archivos del sitio
  database.sql          -> la base de datos

PASOS
  1. cPanel > MySQL(R) Databases
     Crea la BD y el usuario EXACTAMENTE con estos nombres, y asígnale
     ALL PRIVILEGES (si ya existen, salta este paso):
        Base de datos : $DbName
        Usuario       : $DbUser
     La contraseña ya está escrita en wp-config.php dentro del paquete.

  2. cPanel > phpMyAdmin
     Selecciona la BD "$DbName" > Importar > sube dist/database.sql
     Si pesa más de ~50 MB, comprímelo a .sql.gz o impórtalo por SSH:
        mysql -u $DbUser -p $DbName < database.sql

  3. cPanel > Administrador de archivos > carpeta public_html
     Borra el index.html por defecto, sube $zipName y usa "Extraer".
     (En un subdominio o addon domain, usa su document root, no public_html.)

  4. cPanel > Seleccionar versión de PHP
     Versión 8.1 o superior. Extensiones activas: mysqli, curl, mbstring,
     gd (o imagick), zip, intl, exif, xml, json, openssl, fileinfo, sodium.
     Los límites (memoria, subidas) ya van en el .user.ini del paquete.

  5. cPanel > SSL/TLS Status > Run AutoSSL
     Espera a que el certificado quede válido antes de abrir el sitio: el
     .htaccess ya fuerza HTTPS.

  6. Abre $SiteUrl y entra por $SiteUrl/$LoginSlug
     (wp-admin y wp-login.php devuelven 404 a propósito.)

  7. Ajustes > Enlaces permanentes > Guardar cambios
     Regenera las reglas de reescritura. Sin esto, las URLs internas fallan.

  8. cPanel > Cron Jobs — cada 15 minutos (wp-config ya trae DISABLE_WP_CRON):
        cd /home/USUARIO/public_html && /usr/local/bin/php -q wp-cron.php >/dev/null 2>&1

COMPROBACIONES FINALES
  [ ] $SiteUrl carga con candado y sin contenido mixto
  [ ] $SiteUrl/catalogo/ muestra los 24 productos con precio + IVA
  [ ] El botón "Cotizar por WhatsApp" abre el chat al +$Whatsapp
  [ ] /tienda/, /carrito/, /finalizar-compra/ y /mi-cuenta/ redirigen al catálogo
  [ ] $SiteUrl/wp-admin devuelve 404
  [ ] $SiteUrl/.env y $SiteUrl/wp-config.php devuelven 403
  [ ] wp-admin > Seguridad: el monitor no muestra avisos rojos

DESPUES DE PUBLICAR
  - Cambia la contraseña del admin (la del .env es de desarrollo).
  - Configura SMTP (WP Mail SMTP) con una cuenta de cPanel; el mail() del
    hosting compartido suele acabar en spam.
  - Permisos: carpetas 755, archivos 644, wp-config.php 640.
  - disable_functions en MultiPHP INI Editor (ver comentario en .user.ini).
  - Activa los backups del hosting.

OJO: dist/ contiene la contraseña de la base de datos. No lo subas a git
(ya está en .gitignore) ni lo compartas.
"@

    Save-Utf8NoBom -Path (Join-Path $DistDir 'LEEME-SUBIR.txt') -Content $leeme

    Write-Host "`n============================================================" -ForegroundColor Cyan
    Write-Host " Paquete listo en dist/" -ForegroundColor Cyan
    Write-Host "============================================================" -ForegroundColor Cyan
    if ($ZipPath -ne '') { Write-Host "  $(Split-Path -Leaf $ZipPath)" }
    else { Write-Host '  site/  (sin comprimir)' }
    Write-Host '  database.sql'
    Write-Host '  LEEME-SUBIR.txt   <- los pasos, con tus datos ya puestos'
    Write-Host ''
}
finally {
    Pop-Location
}
