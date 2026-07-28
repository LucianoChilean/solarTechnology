<?php
/**
 * Plugin Name: SolarTech · 10 Login oculto
 * Description: Mueve el login a un slug secreto (LOGIN_SLUG, por defecto /acceso-solar). wp-admin y wp-login.php devuelven 404.
 * Version: 1.0.0
 * Author: SolarTechonolgy
 *
 * @package SolarTechSecurity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Devuelve el slug secreto de acceso.
 */
function st_login_slug() {
	$slug = getenv( 'LOGIN_SLUG' );
	if ( ! $slug ) {
		$slug = get_option( 'st_login_slug', 'acceso-solar' );
	}
	return sanitize_title( $slug );
}

/**
 * Muestra un 404 "limpio" y termina.
 */
function st_send_404() {
	global $wp_query;
	if ( $wp_query instanceof WP_Query ) {
		$wp_query->set_404();
	}
	status_header( 404 );
	nocache_headers();
	$tpl = get_404_template();
	if ( $tpl ) {
		include $tpl;
	} else {
		echo '<h1>404</h1>';
	}
	exit;
}

/* -------------------------------------------------------------------------
 *  Interceptar el acceso muy temprano.
 * ---------------------------------------------------------------------- */
add_action( 'plugins_loaded', function () {
	$slug        = st_login_slug();
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
	$request_uri = untrailingslashit( $request_uri );
	$self        = isset( $_SERVER['PHP_SELF'] ) ? basename( $_SERVER['PHP_SELF'] ) : '';
	$script      = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( $_SERVER['SCRIPT_NAME'] ) : '';

	$is_login   = ( 'wp-login.php' === $self || 'wp-login.php' === $script );
	$is_secret  = ( $request_uri === '/' . $slug );

	// 1) Acceso por el slug secreto → cargar wp-login.php.
	if ( $is_secret && ! $is_login ) {
		global $pagenow;
		$pagenow = 'wp-login.php';
		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	// 2) Acceso directo a wp-login.php SIN venir del slug → 404.
	if ( $is_login ) {
		$referer_ok = false;
		if ( ! empty( $_REQUEST['st_secret'] ) && st_login_slug() === sanitize_title( wp_unslash( $_REQUEST['st_secret'] ) ) ) { // phpcs:ignore
			$referer_ok = true;
		}
		// Permitir POST del formulario (login) y logout/postpass con acción válida.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
		$allowed_actions = array( 'logout', 'postpass', 'rp', 'resetpass', 'lostpassword', 'retrievepassword' );
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) || in_array( $action, $allowed_actions, true ) || $referer_ok ) {
			return;
		}
		st_send_404();
	}
}, 1 );

/* -------------------------------------------------------------------------
 *  Bloquear /wp-admin/ para no logueados (excepto admin-ajax).
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() ) {
		st_send_404();
	}
} );

/* -------------------------------------------------------------------------
 *  Reescribir las URLs de login para que apunten al slug secreto.
 * ---------------------------------------------------------------------- */
add_filter( 'site_url', 'st_fix_login_url', 10, 2 );
add_filter( 'network_site_url', 'st_fix_login_url', 10, 2 );
function st_fix_login_url( $url, $path ) {
	if ( is_string( $path ) && strpos( $path, 'wp-login.php' ) !== false ) {
		$url = str_replace( 'wp-login.php', st_login_slug(), $url );
	}
	return $url;
}

add_filter( 'login_url', function ( $login_url ) {
	return home_url( '/' . st_login_slug() . '/' );
}, 10, 1 );

add_filter( 'logout_url', function ( $logout_url ) {
	return $logout_url; // logout conserva su nonce; se permite arriba.
} );

// Redirigir el enlace "Perdiste tu contraseña" etc. correctamente.
add_filter( 'lostpassword_url', function () {
	return home_url( '/' . st_login_slug() . '/?action=lostpassword' );
} );
