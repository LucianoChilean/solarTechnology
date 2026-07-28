<?php
/**
 * Plugin Name: SolarTech · 00 Seguridad base
 * Description: XML-RPC off, anti-enumeración de usuarios, cabeceras de seguridad, App Passwords off y bloqueo de subida de ejecutables.
 * Version: 1.0.0
 * Author: SolarTechonolgy
 *
 * @package SolarTechSecurity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* --- Ocultar versión de WP ------------------------------------------------ */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
add_filter( 'style_loader_src', 'st_sec_strip_ver', 9999 );
add_filter( 'script_loader_src', 'st_sec_strip_ver', 9999 );
/**
 * Quita el `?ver=` solo cuando delata la versión de WordPress.
 *
 * No se puede borrar siempre: el `ver` de los assets propios (theme, mu-plugins)
 * es lo que invalida la caché del navegador cuando se publica un cambio de CSS
 * o JS. Sin él, un visitante recurrente seguiría viendo los archivos antiguos.
 *
 * @param string $src URL del asset.
 * @return string
 */
function st_sec_strip_ver( $src ) {
	if ( ! $src || false === strpos( $src, 'ver=' ) ) {
		return $src;
	}

	$query = (string) wp_parse_url( $src, PHP_URL_QUERY );
	parse_str( $query, $args );

	// Solo se oculta si el valor coincide con la versión del core.
	if ( isset( $args['ver'] ) && get_bloginfo( 'version' ) === $args['ver'] ) {
		$src = remove_query_arg( 'ver', $src );
	}

	return $src;
}

/* --- XML-RPC completamente desactivado ------------------------------------ */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );
add_filter( 'pings_open', '__return_false' );

/* --- Anti-enumeración de usuarios ----------------------------------------- */
// Bloquear ?author=N
add_action( 'template_redirect', function () {
	if ( is_admin() ) {
		return;
	}
	if ( isset( $_GET['author'] ) && ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

// Desactivar el endpoint REST de usuarios para no autenticados.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );

// Mensaje de error de login genérico (no revela si el usuario existe).
add_filter( 'login_errors', function () {
	return __( 'Credenciales incorrectas.', 'solartechonolgy' );
} );

/* --- App Passwords off ---------------------------------------------------- */
add_filter( 'wp_is_application_passwords_available', '__return_false' );

/* --- Cabeceras de seguridad (complementa .htaccess) ----------------------- */
add_action( 'send_headers', function () {
	if ( headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
	header_remove( 'X-Powered-By' );
} );

/* --- Bloquear subida de archivos ejecutables ------------------------------ */
add_filter( 'upload_mimes', function ( $mimes ) {
	// Nos quedamos solo con tipos seguros; eliminamos cualquier variante php/ejecutable.
	$blocked = array( 'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'pht', 'phar', 'exe', 'sh', 'bat', 'cmd', 'com', 'cgi', 'pl', 'jsp', 'asp', 'aspx', 'htaccess', 'js' );
	foreach ( $mimes as $ext => $mime ) {
		foreach ( explode( '|', $ext ) as $single ) {
			if ( in_array( strtolower( $single ), $blocked, true ) ) {
				unset( $mimes[ $ext ] );
			}
		}
	}
	return $mimes;
}, 999 );

// Verificación real del contenido subido (doble extensión / disfraz).
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
	if ( preg_match( '/\.(php|php\d|phtml|pht|phar|exe|sh|bat|cmd|cgi|pl|jsp|asp|aspx)(\.|$)/i', $filename ) ) {
		$data['ext']  = false;
		$data['type'] = false;
	}
	return $data;
}, 10, 3 );

/* --- Desactivar edición de archivos desde el panel (por si falta en wp-config) */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}
