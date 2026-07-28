<?php
/**
 * Catálogo de productos (sin carrito): grilla + filtros por categoría.
 *
 * Se usa en la portada y en la página de catálogo.
 *
 * Args admitidos:
 *   id      string  ID del <section>.
 *   eyebrow string  Texto pequeño sobre el título.
 *   title   string  Título de la sección ('' = sin cabecera).
 *   text    string  Bajada.
 *   limit   int     Nº de productos (por defecto 60).
 *   filters bool    Mostrar los chips de categoría (por defecto true).
 *   more    bool    Mostrar el botón "Ver todo el catálogo".
 *   term    WP_Term Restringir a una categoría de producto.
 *
 * @package SolarTechonolgy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'id'      => 'catalogo',
		'eyebrow' => __( 'Catálogo', 'solartechonolgy' ),
		'title'   => __( 'Nuestros productos', 'solartechonolgy' ),
		'text'    => '',
		'limit'   => 60,
		'filters' => true,
		'more'    => false,
		'term'    => null,
	)
);

$show_prices = st_catalog_show_prices();

$query_args = array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => (int) $args['limit'],
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'no_found_rows'  => true,
);

if ( $args['term'] instanceof WP_Term ) {
	$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		array(
			'taxonomy' => $args['term']->taxonomy,
			'field'    => 'term_id',
			'terms'    => $args['term']->term_id,
		),
	);
	$args['filters'] = false;
}

$catalog = new WP_Query( $query_args );
?>

<section id="<?php echo esc_attr( $args['id'] ); ?>" class="st-products">
	<?php if ( $args['title'] ) : ?>
		<div class="st-section-head">
			<?php if ( $args['eyebrow'] ) : ?>
				<span class="st-eyebrow"><?php echo esc_html( $args['eyebrow'] ); ?></span>
			<?php endif; ?>
			<h2><?php echo esc_html( $args['title'] ); ?></h2>
			<?php if ( $args['text'] ) : ?>
				<p><?php echo esc_html( $args['text'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php
	if ( $args['filters'] ) :
		$cat_terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
		if ( ! is_wp_error( $cat_terms ) && $cat_terms ) :
			?>
			<div class="st-filters" data-st-filters>
				<button type="button" class="st-chip-btn is-active" data-cat="all"><?php esc_html_e( 'Todos', 'solartechonolgy' ); ?></button>
				<?php foreach ( $cat_terms as $t ) : ?>
					<button type="button" class="st-chip-btn" data-cat="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php
		endif;
	endif;
	?>

	<div class="st-grid" data-st-grid>
		<?php
		if ( $catalog->have_posts() ) :
			while ( $catalog->have_posts() ) :
				$catalog->the_post();
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
				if ( ! $product ) {
					continue;
				}
				$terms     = wp_get_post_terms( get_the_ID(), 'product_cat', array( 'fields' => 'all' ) );
				$slugs     = is_wp_error( $terms ) ? array() : wp_list_pluck( $terms, 'slug' );
				$cat_label = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0]->name : '';
				$excerpt   = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
				?>
				<article class="st-card" data-cat="<?php echo esc_attr( implode( ' ', $slugs ) ); ?>">
					<a class="st-card__img" href="<?php the_permalink(); ?>">
						<?php if ( $cat_label ) : ?>
							<span class="st-card__cat"><?php echo esc_html( $cat_label ); ?></span>
						<?php endif; ?>
						<?php
						if ( has_post_thumbnail() ) {
							the_post_thumbnail( 'medium' );
						} else {
							echo '<span class="st-card__ph">// ' . esc_html( $product->get_sku() ? $product->get_sku() : sanitize_title( get_the_title() ) ) . '.png</span>';
						}
						?>
					</a>
					<div class="st-card__body">
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $excerpt ), 18 ) ); ?></p>
						<div class="st-card__spacer"></div>
						<?php if ( $show_prices && '' !== $product->get_price() ) : ?>
							<div class="st-card__price">
								<span><?php echo esc_html( st_clp( $product->get_price() ) ); ?></span>
								<small><?php esc_html_e( '+ IVA', 'solartechonolgy' ); ?></small>
							</div>
						<?php endif; ?>
						<div class="st-card__actions">
							<a class="st-btn st-btn--orange" href="<?php echo esc_url( st_product_quote_link( $product ) ); ?>" target="_blank" rel="noopener">
								<?php esc_html_e( 'Cotizar', 'solartechonolgy' ); ?>
							</a>
							<a class="st-btn st-btn--ghost-dark" href="<?php the_permalink(); ?>">
								<?php esc_html_e( 'Ver detalle', 'solartechonolgy' ); ?>
							</a>
						</div>
					</div>
				</article>
				<?php
			endwhile;
			wp_reset_postdata();
		else :
			?>
			<p class="st-empty"><?php esc_html_e( 'Aún no hay productos en el catálogo.', 'solartechonolgy' ); ?></p>
			<?php
		endif;
		?>
	</div>

	<?php if ( $args['more'] && $catalog->post_count >= (int) $args['limit'] ) : ?>
		<div class="st-products__more">
			<a class="st-btn st-btn--ghost-dark" href="<?php echo esc_url( st_catalog_url() ); ?>">
				<?php esc_html_e( 'Ver todo el catálogo', 'solartechonolgy' ); ?>
			</a>
		</div>
	<?php endif; ?>
</section>
