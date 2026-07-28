<?php
/**
 * Fallback genérico (blog / archivos).
 *
 * @package SolarTechonolgy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="st-page st-page--narrow">
	<?php if ( have_posts() ) : ?>
		<?php if ( ! is_front_page() && ( is_home() || is_archive() ) ) : ?>
			<header class="st-section-head st-section-head--left">
				<h1><?php echo esc_html( wp_get_document_title() ); ?></h1>
			</header>
		<?php endif; ?>

		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'st-post' ); ?>>
				<h2 class="st-post__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<div class="st-post__meta"><?php echo esc_html( get_the_date() ); ?></div>
				<div class="st-post__content"><?php the_excerpt(); ?></div>
			</article>
			<?php
		endwhile;

		the_posts_pagination( array( 'mid_size' => 2 ) );
	else :
		?>
		<p><?php esc_html_e( 'No se encontró contenido.', 'solartechonolgy' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
