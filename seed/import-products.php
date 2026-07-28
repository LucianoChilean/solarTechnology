<?php
/**
 * Importador de productos WooCommerce desde seed/products.csv.
 *
 * Uso (dentro del contenedor wpcli):
 *   wp eval-file /seed/import-products.php
 *
 * Es idempotente: si el SKU ya existe, actualiza; si no, crea.
 *
 * @package SolarTechSeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Este script debe ejecutarse con WP-CLI (wp eval-file).\n" );
	exit( 1 );
}

if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product_id_by_sku' ) ) {
	WP_CLI::error( 'WooCommerce no está activo. Actívalo antes de importar.' );
	return;
}

$csv = '/seed/products.csv';
if ( ! file_exists( $csv ) ) {
	$csv = __DIR__ . '/products.csv';
}
if ( ! file_exists( $csv ) ) {
	WP_CLI::error( "No se encontró products.csv en $csv" );
	return;
}

$fh = fopen( $csv, 'r' );
if ( ! $fh ) {
	WP_CLI::error( 'No se pudo abrir products.csv' );
	return;
}

$header  = fgetcsv( $fh );
$created = 0;
$updated = 0;

while ( ( $row = fgetcsv( $fh ) ) !== false ) {
	if ( count( $row ) < 5 || '' === trim( $row[0] ) ) {
		continue;
	}
	list( $sku, $name, $category, $price, $short ) = array_map( 'trim', $row );

	// Categoría (crear si no existe).
	$term = term_exists( $category, 'product_cat' );
	if ( ! $term ) {
		$term = wp_insert_term( $category, 'product_cat' );
	}
	$cat_id = is_array( $term ) ? (int) $term['term_id'] : 0;

	$existing_id = wc_get_product_id_by_sku( $sku );
	$product     = $existing_id ? wc_get_product( $existing_id ) : new WC_Product_Simple();

	$product->set_name( $name );
	$product->set_sku( $sku );
	$product->set_regular_price( (string) intval( $price ) ); // Precio NETO (el IVA lo añade Woo).
	$product->set_short_description( $short );
	$product->set_description( $short . ' Instalación y certificación SEC disponibles en todo Chile.' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_status( 'publish' );
	$product->set_manage_stock( false );
	$product->set_stock_status( 'instock' );
	$product->set_tax_status( 'taxable' );
	$product->set_tax_class( '' );
	if ( $cat_id ) {
		$product->set_category_ids( array( $cat_id ) );
	}

	$id = $product->save();

	if ( $existing_id ) {
		$updated++;
		WP_CLI::log( "  · actualizado [$sku] $name (#$id)" );
	} else {
		$created++;
		WP_CLI::log( "  + creado     [$sku] $name (#$id)" );
	}
}
fclose( $fh );

WP_CLI::success( "Importación completada: $created creados, $updated actualizados." );
