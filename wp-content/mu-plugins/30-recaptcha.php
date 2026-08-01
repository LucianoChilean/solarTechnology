<?php
/**
 * Plugin Name: SolarTech · 30 reCAPTCHA v3
 * Description: reCAPTCHA v3 en login, checkout y contacto. Si faltan las claves, la validación queda desactivada (no bloquea).
 * Version: 1.0.0
 * Author: SolarTechonolgy
 *
 * @package SolarTechSecurity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function st_recaptcha_site_key() {
	return function_exists( 'st_config' )
		? st_config( 'RECAPTCHA_SITE_KEY' )
		: get_option( 'st_recaptcha_site_key', '' );
}
function st_recaptcha_secret_key() {
	return function_exists( 'st_config' )
		? st_config( 'RECAPTCHA_SECRET_KEY' )
		: get_option( 'st_recaptcha_secret_key', '' );
}
function st_recaptcha_enabled() {
	return st_recaptcha_site_key() && st_recaptcha_secret_key();
}

/**
 * Verifica el token contra la API de Google. Devuelve true si pasa o si está desactivado.
 */
function st_recaptcha_verify( $action = 'submit', $min_score = 0.5 ) {
	if ( ! st_recaptcha_enabled() ) {
		return true; // Sin claves reales, no bloqueamos.
	}
	$token = isset( $_POST['st_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['st_recaptcha_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $token ) {
		return false;
	}
	$resp = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
		'timeout' => 8,
		'body'    => array(
			'secret'   => st_recaptcha_secret_key(),
			'response' => $token,
			'remoteip' => function_exists( 'st_client_ip' ) ? st_client_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' ),
		),
	) );
	if ( is_wp_error( $resp ) ) {
		return true; // No penalizar al usuario por un fallo de red hacia Google.
	}
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( empty( $body['success'] ) ) {
		return false;
	}
	if ( isset( $body['score'] ) && $body['score'] < $min_score ) {
		return false;
	}
	if ( isset( $body['action'] ) && $action && $body['action'] !== $action ) {
		return false;
	}
	return true;
}

/* -------------------------------------------------------------------------
 *  Cargar el script de reCAPTCHA e inyectar el token en formularios.
 * ---------------------------------------------------------------------- */
add_action( 'login_enqueue_scripts', 'st_recaptcha_scripts' );
add_action( 'wp_enqueue_scripts', function () {
	if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_page() ) ) {
		st_recaptcha_scripts();
	}
} );

function st_recaptcha_scripts() {
	if ( ! st_recaptcha_enabled() ) {
		return;
	}
	$key = st_recaptcha_site_key();
	wp_enqueue_script( 'st-recaptcha-api', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $key ), array(), null, true );
	$inline = "document.addEventListener('submit',function(e){var f=e.target;if(!f||f.__stRc)return;});" .
		"function stRcInject(action){if(!window.grecaptcha)return;grecaptcha.ready(function(){grecaptcha.execute('" . esc_js( $key ) . "',{action:action}).then(function(t){document.querySelectorAll('form').forEach(function(f){var i=f.querySelector('input[name=st_recaptcha_token]');if(!i){i=document.createElement('input');i.type='hidden';i.name='st_recaptcha_token';f.appendChild(i);}i.value=t;});});});}" .
		"stRcInject('submit');setInterval(function(){stRcInject('submit');},90000);";
	wp_add_inline_script( 'st-recaptcha-api', $inline );
}

/* -------------------------------------------------------------------------
 *  Login: validar antes de autenticar.
 * ---------------------------------------------------------------------- */
add_filter( 'authenticate', function ( $user ) {
	if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' && ! empty( $_POST['log'] ) ) { // phpcs:ignore
		if ( ! st_recaptcha_verify( 'submit', 0.3 ) ) {
			return new WP_Error( 'st_recaptcha', __( 'Verificación de seguridad fallida. Recarga e inténtalo de nuevo.', 'solartechonolgy' ) );
		}
	}
	return $user;
}, 25 );

/* -------------------------------------------------------------------------
 *  Checkout de WooCommerce.
 * ---------------------------------------------------------------------- */
add_action( 'woocommerce_checkout_process', function () {
	if ( ! st_recaptcha_verify( 'submit', 0.4 ) ) {
		wc_add_notice( __( 'No pudimos verificar que eres humano. Recarga la página e inténtalo otra vez.', 'solartechonolgy' ), 'error' );
	}
} );

/* -------------------------------------------------------------------------
 *  Formulario de contacto (shortcode [st_contacto]).
 * ---------------------------------------------------------------------- */
add_action( 'admin_notices', function () {
	if ( current_user_can( 'manage_options' ) && ! st_recaptcha_enabled() ) {
		echo '<div class="notice notice-info is-dismissible"><p><strong>SolarTech:</strong> reCAPTCHA está <em>desactivado</em> (faltan claves en .env). Añade RECAPTCHA_SITE_KEY y RECAPTCHA_SECRET_KEY para activarlo.</p></div>';
	}
} );
