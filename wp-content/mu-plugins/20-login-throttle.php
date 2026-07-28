<?php
/**
 * Plugin Name: SolarTech · 20 Límite de intentos de login
 * Description: Bloquea temporalmente la IP tras varios intentos fallidos de acceso.
 * Version: 1.0.0
 * Author: SolarTechonolgy
 *
 * @package SolarTechSecurity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ST_LOGIN_MAX_ATTEMPTS' ) ) {
	define( 'ST_LOGIN_MAX_ATTEMPTS', 5 );
}
if ( ! defined( 'ST_LOGIN_LOCK_MINUTES' ) ) {
	define( 'ST_LOGIN_LOCK_MINUTES', 15 );
}

/**
 * IP del visitante (respetando proxies conocidos con cautela).
 */
function st_client_ip() {
	$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	// Solo confiamos en cabeceras de proxy si el remoto es privado (entorno Docker).
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
		foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$parts = explode( ',', $_SERVER[ $h ] );
				$cand  = trim( $parts[0] );
				if ( filter_var( $cand, FILTER_VALIDATE_IP ) ) {
					return $cand;
				}
			}
		}
	}
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

function st_throttle_key( $ip ) {
	return 'st_lockout_' . md5( $ip );
}

/* -------------------------------------------------------------------------
 *  Antes de autenticar: si la IP está bloqueada, cortar.
 * ---------------------------------------------------------------------- */
add_filter( 'authenticate', function ( $user ) {
	$ip   = st_client_ip();
	$data = get_transient( st_throttle_key( $ip ) );
	if ( is_array( $data ) && ! empty( $data['locked_until'] ) && time() < $data['locked_until'] ) {
		$mins = ceil( ( $data['locked_until'] - time() ) / 60 );
		return new WP_Error(
			'st_locked',
			sprintf(
				/* translators: %d minutes */
				__( 'Demasiados intentos. Vuelve a intentar en %d minutos.', 'solartechonolgy' ),
				$mins
			)
		);
	}
	return $user;
}, 30 );

/* -------------------------------------------------------------------------
 *  Login fallido: contar.
 * ---------------------------------------------------------------------- */
add_action( 'wp_login_failed', function () {
	$ip   = st_client_ip();
	$key  = st_throttle_key( $ip );
	$data = get_transient( $key );
	if ( ! is_array( $data ) ) {
		$data = array( 'count' => 0, 'locked_until' => 0 );
	}
	$data['count']++;
	if ( $data['count'] >= ST_LOGIN_MAX_ATTEMPTS ) {
		$data['locked_until'] = time() + ST_LOGIN_LOCK_MINUTES * 60;
		$data['count']        = 0;
		/**
		 * Permite a otros módulos reaccionar (p. ej. el monitor añade la IP a la lista negra).
		 */
		do_action( 'st_login_lockout', $ip );
	}
	set_transient( $key, $data, ST_LOGIN_LOCK_MINUTES * 60 );
} );

/* -------------------------------------------------------------------------
 *  Login correcto: limpiar contador.
 * ---------------------------------------------------------------------- */
add_action( 'wp_login', function () {
	delete_transient( st_throttle_key( st_client_ip() ) );
} );
