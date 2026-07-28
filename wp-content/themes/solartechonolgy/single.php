<?php
/**
 * Entrada individual.
 *
 * @package SolarTechonolgy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'st-page st-page--narrow' ); ?>>
		<header class="st-section-head st-section-head--left">
			<h1><?php the_title(); ?></h1>
			<div class="st-post__meta"><?php echo esc_html( get_the_date() ); ?></div>
		</header>
		<div class="st-content"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
