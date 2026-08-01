<?php
/**
 * SolarTechonolgy — plantilla de wp-config.php para hosting con cPanel.
 *
 * `deploy/build.ps1` rellena los marcadores {{...}} y deja el archivo listo
 * dentro del paquete. Si prefieres hacerlo a mano, copia este archivo a la
 * raíz del sitio como `wp-config.php` y reemplaza cada {{MARCADOR}}.
 *
 * @package SolarTechonolgy
 */

/* -------------------------------------------------------------------------
 *  Base de datos (cPanel → MySQL® Databases)
 * ---------------------------------------------------------------------- */
define( 'DB_NAME', '{{DB_NAME}}' );
define( 'DB_USER', '{{DB_USER}}' );
define( 'DB_PASSWORD', '{{DB_PASSWORD}}' );
define( 'DB_HOST', '{{DB_HOST}}' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = '{{TABLE_PREFIX}}';

/* -------------------------------------------------------------------------
 *  Claves únicas de autenticación — generadas para esta instalación
 * ---------------------------------------------------------------------- */
{{SALTS}}

/* -------------------------------------------------------------------------
 *  URL del sitio
 *  Fijarlas evita los bucles de redirección tras migrar. Para cambiar de
 *  dominio hay que editarlas aquí (el panel las muestra en solo lectura).
 * ---------------------------------------------------------------------- */
define( 'WP_HOME', '{{SITE_URL}}' );
define( 'WP_SITEURL', '{{SITE_URL}}' );

/* -------------------------------------------------------------------------
 *  Configuración de SolarTechonolgy
 *  Reemplaza a las variables de entorno del docker-compose (ver mu-plugin
 *  01-config.php). Si dejas una vacía se usa la opción guardada en la BD.
 * ---------------------------------------------------------------------- */
define( 'ST_LOGIN_SLUG', '{{LOGIN_SLUG}}' );          // Login oculto: /{{LOGIN_SLUG}}
define( 'ST_WHATSAPP_NUMBER', '{{WHATSAPP_NUMBER}}' );
define( 'ST_CONTACT_EMAIL', '{{CONTACT_EMAIL}}' );
define( 'ST_RECAPTCHA_SITE_KEY', '{{RECAPTCHA_SITE_KEY}}' );
define( 'ST_RECAPTCHA_SECRET_KEY', '{{RECAPTCHA_SECRET_KEY}}' );
// Solo si desactivas el modo catálogo y reabres la tienda con pagos:
// define( 'ST_FLOW_API_KEY', '' );
// define( 'ST_FLOW_SECRET_KEY', '' );
// define( 'ST_FLOW_SANDBOX', 'no' );

/* -------------------------------------------------------------------------
 *  Endurecimiento (equivale a WORDPRESS_CONFIG_EXTRA del docker-compose)
 * ---------------------------------------------------------------------- */
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_UNFILTERED_HTML', true );
define( 'WP_POST_REVISIONS', 5 );
define( 'AUTOMATIC_UPDATER_DISABLED', false );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'EMPTY_TRASH_DAYS', 7 );
define( 'FORCE_SSL_ADMIN', true );

// Máxima dureza: bloquea instalar/actualizar plugins desde el panel.
// Actívalo cuando el sitio esté estable.
// define( 'DISALLOW_FILE_MODS', true );

/* -------------------------------------------------------------------------
 *  Producción: sin errores en pantalla
 * ---------------------------------------------------------------------- */
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', false );
@ini_set( 'display_errors', 0 );

/* -------------------------------------------------------------------------
 *  Cron por el cron real de cPanel (ver DEPLOY-CPANEL.md → "Después")
 * ---------------------------------------------------------------------- */
define( 'DISABLE_WP_CRON', true );

/* -------------------------------------------------------------------------
 *  HTTPS detrás del proxy de cPanel
 *  Sin esto WordPress puede generar URLs http:// y provocar contenido mixto.
 * ---------------------------------------------------------------------- */
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	$_SERVER['HTTPS'] = 'on';
}

/* -------------------------------------------------------------------------
 *  Fin de la configuración — no editar más abajo
 * ---------------------------------------------------------------------- */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
