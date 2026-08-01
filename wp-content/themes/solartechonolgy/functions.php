<?php
/**
 * SolarTechonolgy — funciones del theme.
 *
 * @package SolarTechonolgy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ST_THEME_VERSION', '1.1.2' );

/* -------------------------------------------------------------------------
 *  Soporte del theme
 * ---------------------------------------------------------------------- */
function st_setup() {
	load_theme_textdomain( 'solartechonolgy', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array( 'height' => 60, 'width' => 200, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'responsive-embeds' );

	// WooCommerce.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus( array(
		'primary' => __( 'Menú principal', 'solartechonolgy' ),
	) );
}
add_action( 'after_setup_theme', 'st_setup' );

/* -------------------------------------------------------------------------
 *  Estilos y scripts
 * ---------------------------------------------------------------------- */
function st_assets() {
	// Google Fonts: Sora (titulares) + Manrope (texto), igual que el ejemplo.
	wp_enqueue_style(
		'st-fonts',
		'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@500;600;700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'st-main', get_theme_file_uri( 'assets/css/main.css' ), array( 'st-fonts' ), ST_THEME_VERSION );
	wp_style_add_data( 'st-main', 'rtl', 'replace' );

	wp_enqueue_script( 'st-main', get_theme_file_uri( 'assets/js/main.js' ), array(), ST_THEME_VERSION, true );

	// Datos para el front (WhatsApp, catálogo, carrito si la tienda está activa).
	$data = array(
		'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
		'catalogUrl'  => st_catalog_url(),
		'catalogMode' => st_catalog_mode(),
		'whatsapp'    => st_whatsapp_number(),
		'nonce'       => wp_create_nonce( 'st_nonce' ),
	);
	if ( ! st_catalog_mode() ) {
		$data['cartUrl']  = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/carrito/' );
		$data['checkout'] = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/finalizar-compra/' );
	}
	wp_localize_script( 'st-main', 'ST', $data );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'st_assets' );

/* -------------------------------------------------------------------------
 *  Modo catálogo (tienda desactivada)
 * ---------------------------------------------------------------------- */
/**
 * ¿El sitio funciona solo como catálogo (sin carrito ni pagos)?
 *
 * Para volver a activar la tienda:  wp option update st_catalog_mode no
 */
function st_catalog_mode() {
	return (bool) apply_filters( 'st_catalog_mode', 'no' !== get_option( 'st_catalog_mode', 'yes' ) );
}

/** ¿Se muestran precios en el catálogo?  wp option update st_catalog_prices no */
function st_catalog_show_prices() {
	return (bool) apply_filters( 'st_catalog_show_prices', 'no' !== get_option( 'st_catalog_prices', 'yes' ) );
}

/** URL del catálogo (página "tienda" de WooCommerce, renderizada como catálogo). */
function st_catalog_url() {
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$url = wc_get_page_permalink( 'shop' );
		if ( $url ) {
			return $url;
		}
	}
	return home_url( '/catalogo/' );
}

/** Enlace de WhatsApp para cotizar un producto concreto. */
function st_product_quote_link( $product ) {
	$name = is_object( $product ) ? $product->get_name() : (string) $product;
	$url  = is_object( $product ) ? get_permalink( $product->get_id() ) : '';
	$msg  = sprintf( 'Hola Solartechonolgy, quiero cotizar: %s', $name );
	if ( $url ) {
		$msg .= ' — ' . $url;
	}
	return st_wa_link( $msg );
}

/* -------------------------------------------------------------------------
 *  Helpers de negocio (opciones guardadas por seed/setup.sh)
 * ---------------------------------------------------------------------- */
function st_whatsapp_number() {
	// st_config() lo resuelve desde wp-config.php, el entorno o la opción (mu-plugin 01).
	$n = function_exists( 'st_config' )
		? st_config( 'WHATSAPP_NUMBER', '56974089594' )
		: get_option( 'st_whatsapp_number', '56974089594' );
	return preg_replace( '/[^0-9]/', '', (string) $n );
}

function st_contact_email() {
	return function_exists( 'st_config' )
		? st_config( 'CONTACT_EMAIL', 'Ventas.isisolareingenieria@gmail.com' )
		: get_option( 'st_contact_email', 'Ventas.isisolareingenieria@gmail.com' );
}

function st_wa_link( $text = '' ) {
	$base = 'https://wa.me/' . st_whatsapp_number();
	return $text ? $base . '?text=' . rawurlencode( $text ) : $base;
}

/**
 * Formatea CLP como el ejemplo: $3.300.000
 */
function st_clp( $n ) {
	return '$' . number_format( (float) $n, 0, ',', '.' );
}

/* -------------------------------------------------------------------------
 *  Banner principal (slider de 3 diapositivas)
 * ---------------------------------------------------------------------- */
/**
 * Diapositivas del banner. Cada una: imagen, badge, título, texto y botón.
 * Se editan en Apariencia → Personalizar → Banner principal.
 *
 * @return array<int,array<string,string>>
 */
function st_hero_slides() {
	$defaults = array(
		array(
			'badge'     => get_option( 'st_hero_badge', 'Oferta del Mes' ),
			'title'     => 'Soluciones solares para su',
			'highlight' => 'Hogar o Empresa',
			'text'      => 'Energía limpia, ahorro real y respaldo total. Kits llave en mano con instalación incluida y certificación SEC en todo Chile.',
			'cta_label' => 'Ver catálogo',
			'cta_url'   => '',
		),
		array(
			'badge'     => 'Instalación certificada',
			'title'     => 'Paneles e inversores de',
			'highlight' => 'marcas premium',
			'text'      => 'Diseñamos tu proyecto fotovoltaico a medida: cálculo de consumo, ingeniería, montaje y puesta en marcha con certificación SEC TE4.',
			'cta_label' => 'Ver catálogo',
			'cta_url'   => '',
		),
		array(
			'badge'     => 'Empresas e industria',
			'title'     => 'Autoconsumo solar para tu',
			'highlight' => 'negocio',
			'text'      => 'Sistemas on-grid e híbridos de 5 a 100 kW que reducen hasta un 90% tu cuenta de luz. Visita técnica gratuita en todo Chile.',
			'cta_label' => 'Cotizar mi proyecto',
			'cta_url'   => '',
		),
	);

	$slides = array();
	foreach ( $defaults as $i => $slide ) {
		$n     = $i + 1;
		$image = get_theme_mod( "st_slide_{$n}_image", '' );

		foreach ( array( 'badge', 'title', 'highlight', 'text', 'cta_label', 'cta_url' ) as $key ) {
			$value = get_theme_mod( "st_slide_{$n}_{$key}", '' );
			if ( '' !== $value && null !== $value ) {
				$slide[ $key ] = $value;
			}
		}

		$slide['image'] = $image;
		if ( '' === $slide['cta_url'] ) {
			$slide['cta_url'] = st_catalog_url();
		}
		$slides[] = $slide;
	}

	return apply_filters( 'st_hero_slides', $slides );
}

/* -------------------------------------------------------------------------
 *  WooCommerce — ajustes visuales
 * ---------------------------------------------------------------------- */
// Refrescar el contador del carrito vía fragments (para el header).
function st_cart_fragments( $fragments ) {
	$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	ob_start();
	?>
	<span class="st-cart-count" data-st-cart-count><?php echo esc_html( $count ); ?></span>
	<?php
	$fragments['[data-st-cart-count]'] = ob_get_clean();
	return $fragments;
}
if ( ! st_catalog_mode() ) {
	add_filter( 'woocommerce_add_to_cart_fragments', 'st_cart_fragments' );
}

// Precios "+ IVA": mostramos neto y dejamos que WooCommerce calcule el IVA (19%).
// El IVA se configura como tasa en seed/setup.sh.

// Columnas de la grilla de tienda.
add_filter( 'loop_shop_columns', function () { return 3; } );
add_filter( 'loop_shop_per_page', function () { return 24; } );

// Quitar el sidebar por defecto de WooCommerce.
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/* -------------------------------------------------------------------------
 *  Limpieza / seguridad ligera de cabeceras (complementa mu-plugins)
 * ---------------------------------------------------------------------- */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/* -------------------------------------------------------------------------
 *  Widgets
 * ---------------------------------------------------------------------- */
function st_widgets() {
	register_sidebar( array(
		'name'          => __( 'Pie de página', 'solartechonolgy' ),
		'id'            => 'footer-1',
		'before_widget' => '<div class="st-foot-widget">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4>',
		'after_title'   => '</h4>',
	) );
}
add_action( 'widgets_init', 'st_widgets' );

/* -------------------------------------------------------------------------
 *  Módulos
 * ---------------------------------------------------------------------- */
require_once get_theme_file_path( 'inc/customizer.php' );

if ( st_catalog_mode() ) {
	require_once get_theme_file_path( 'inc/catalog-mode.php' );
}
