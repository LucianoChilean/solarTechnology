<?php
/**
 * Modo catálogo: el sitio muestra los productos como catálogo, sin carrito,
 * sin checkout, sin cuentas de cliente y sin pasarelas de pago.
 *
 * Este archivo solo se carga cuando st_catalog_mode() es true.
 * Para volver a abrir la tienda:  wp option update st_catalog_mode no
 *
 * @package SolarTechonolgy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 *  Nada se puede comprar
 * ---------------------------------------------------------------------- */
add_filter( 'woocommerce_is_purchasable', '__return_false' );
add_filter( 'woocommerce_add_to_cart_validation', '__return_false' );
add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array' );

// Ocultar precios si así se configuró (wp option update st_catalog_prices no).
if ( ! st_catalog_show_prices() ) {
	add_filter( 'woocommerce_get_price_html', '__return_empty_string' );
}

/* -------------------------------------------------------------------------
 *  Quitar los botones de compra de las plantillas de WooCommerce
 * ---------------------------------------------------------------------- */
function st_catalog_remove_cart_templates() {
	// Grilla de productos.
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
	// Ficha de producto.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	// Botón "Cotizar por WhatsApp" en su lugar.
	add_action( 'woocommerce_single_product_summary', 'st_catalog_quote_button', 30 );
}
add_action( 'init', 'st_catalog_remove_cart_templates', 20 );

/** Botón de cotización en la ficha del producto. */
function st_catalog_quote_button() {
	global $product;
	if ( ! $product ) {
		return;
	}
	printf(
		'<p class="st-quote-actions"><a class="st-btn st-btn--orange" href="%1$s" target="_blank" rel="noopener">%2$s</a> <a class="st-btn st-btn--ghost-dark" href="%3$s">%4$s</a></p>',
		esc_url( st_product_quote_link( $product ) ),
		esc_html__( 'Cotizar por WhatsApp', 'solartechonolgy' ),
		esc_url( st_catalog_url() ),
		esc_html__( 'Volver al catálogo', 'solartechonolgy' )
	);
}

/* -------------------------------------------------------------------------
 *  Páginas de tienda fuera de servicio → al catálogo
 * ---------------------------------------------------------------------- */
function st_catalog_block_store_pages() {
	if ( is_admin() || ! function_exists( 'is_cart' ) ) {
		return;
	}

	// Carrito, checkout y "mi cuenta" redirigen al catálogo.
	if ( is_cart() || is_checkout() || is_account_page() ) {
		wp_safe_redirect( st_catalog_url(), 302 );
		exit;
	}

	// Un ?add-to-cart=ID directo tampoco debe hacer nada.
	if ( isset( $_GET['add-to-cart'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( remove_query_arg( array( 'add-to-cart', 'quantity' ) ), 302 );
		exit;
	}

	// Enlaces antiguos a /tienda/ cuando la página pasó a llamarse /catalogo/.
	if ( is_404() ) {
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH ), '/' );
		if ( in_array( $path, array( 'tienda', 'shop', 'carrito', 'finalizar-compra', 'mi-cuenta' ), true ) ) {
			wp_safe_redirect( st_catalog_url(), 301 );
			exit;
		}
	}
}
add_action( 'template_redirect', 'st_catalog_block_store_pages', 1 );

/* -------------------------------------------------------------------------
 *  Scripts y avisos de tienda que ya no hacen falta
 * ---------------------------------------------------------------------- */
function st_catalog_dequeue_cart_assets() {
	wp_dequeue_script( 'wc-cart-fragments' );
	wp_dequeue_script( 'wc-add-to-cart' );
	wp_dequeue_script( 'woocommerce' );
}
add_action( 'wp_enqueue_scripts', 'st_catalog_dequeue_cart_assets', 20 );

// Sin "Ver carrito" ni contadores en menús o widgets de WooCommerce.
add_filter( 'woocommerce_widget_cart_is_hidden', '__return_true' );
add_filter( 'woocommerce_return_to_shop_text', function () {
	return __( 'Ver catálogo', 'solartechonolgy' );
} );

// Los productos ya no muestran "Leer más"/"Añadir al carrito" en el loop nativo.
add_filter( 'woocommerce_loop_add_to_cart_link', '__return_empty_string' );

/* -------------------------------------------------------------------------
 *  Textos: "Tienda" → "Catálogo"
 * ---------------------------------------------------------------------- */
function st_catalog_shop_title( $title ) {
	return ( function_exists( 'is_shop' ) && is_shop() ) ? __( 'Catálogo', 'solartechonolgy' ) : $title;
}
add_filter( 'woocommerce_page_title', 'st_catalog_shop_title' );
