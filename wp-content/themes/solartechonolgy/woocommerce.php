<?php
/**
 * Envoltorio para todas las páginas de WooCommerce.
 *
 * En modo catálogo, la página de tienda y las categorías de producto se
 * renderizan con la grilla del theme (sin carrito); la ficha de producto sigue
 * usando las plantillas de WooCommerce.
 *
 * @package SolarTechonolgy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$is_catalog_archive = function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );

if ( st_catalog_mode() && $is_catalog_archive ) {
	$term = is_product_taxonomy() ? get_queried_object() : null;

	get_template_part( 'template-parts/catalog', null, array(
		'id'      => 'catalogo',
		'eyebrow' => __( 'Catálogo', 'solartechonolgy' ),
		'title'   => ( $term instanceof WP_Term ) ? $term->name : __( 'Kits y componentes solares', 'solartechonolgy' ),
		'text'    => ( $term instanceof WP_Term )
			? wp_strip_all_tags( term_description( $term ) )
			: __( 'Revisa todos nuestros productos y cotiza sin compromiso por WhatsApp.', 'solartechonolgy' ),
		'limit'   => 100,
		'term'    => $term instanceof WP_Term ? $term : null,
	) );
} else {
	?>
	<div class="st-page st-woo">
		<?php woocommerce_content(); ?>
	</div>
	<?php
}

get_footer();
