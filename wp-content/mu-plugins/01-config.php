<?php
/**
 * Plugin Name: SolarTech · 01 Configuración
 * Description: Punto único de configuración. Resuelve cada valor en orden: constante de wp-config.php → variable de entorno (Docker) → opción de la base de datos.
 * Version: 1.0.0
 * Author: SolarTechonolgy
 *
 * @package SolarTechSecurity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * En Docker la configuración llega por variables de entorno del compose. En un
 * hosting con cPanel no hay entorno que definir, así que el mismo valor se pone
 * como constante en `wp-config.php` con el prefijo `ST_`:
 *
 *     define( 'ST_LOGIN_SLUG', 'acceso-solar' );
 *
 * Si no hay ni constante ni entorno, se usa la opción que dejó `seed/setup.sh`
 * en la base de datos. Así el sitio funciona igual en los dos escenarios.
 */

/**
 * Claves conocidas y la opción de la BD que las respalda.
 *
 * @return array<string,string>
 */
function st_config_options() {
	return array(
		'LOGIN_SLUG'           => 'st_login_slug',
		'WHATSAPP_NUMBER'      => 'st_whatsapp_number',
		'CONTACT_EMAIL'        => 'st_contact_email',
		'RECAPTCHA_SITE_KEY'   => 'st_recaptcha_site_key',
		'RECAPTCHA_SECRET_KEY' => 'st_recaptcha_secret_key',
		'FLOW_API_KEY'         => 'st_flow_api_key',
		'FLOW_SECRET_KEY'      => 'st_flow_secret_key',
		'FLOW_SANDBOX'         => 'st_flow_sandbox',
	);
}

/**
 * Devuelve un valor de configuración.
 *
 * @param string $key     Clave sin prefijo, p. ej. 'LOGIN_SLUG'.
 * @param string $default Valor si no está definida en ninguna parte.
 * @return string
 */
function st_config( $key, $default = '' ) {

	// 1. Constante de wp-config.php — la vía de producción (cPanel).
	$constant = 'ST_' . $key;
	if ( defined( $constant ) ) {
		$value = constant( $constant );
		if ( '' !== $value && null !== $value ) {
			return (string) $value;
		}
	}

	// 2. Variable de entorno — la vía de Docker.
	$value = getenv( $key );
	if ( false !== $value && '' !== $value ) {
		return (string) $value;
	}

	// 3. Opción de la base de datos — la deja seed/setup.sh y viaja en el dump.
	$options = st_config_options();
	if ( isset( $options[ $key ] ) && function_exists( 'get_option' ) ) {
		$value = get_option( $options[ $key ], '' );
		if ( '' !== $value && false !== $value ) {
			return (string) $value;
		}
	}

	return $default;
}
