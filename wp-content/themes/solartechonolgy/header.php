<?php
/**
 * Cabecera del sitio: nav sticky + acceso a WhatsApp y carrito.
 *
 * @package SolarTechonolgy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Saltar al contenido', 'solartechonolgy' ); ?></a>

<header class="st-header" id="top">
	<div class="st-header__inner">
		<a class="st-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<span class="st-logo__dot"></span>
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<span class="st-logo__text">SOLAR<span>TECHONOLGY</span></span>
			<?php endif; ?>
		</a>

		<button
			class="st-nav-toggle"
			type="button"
			aria-controls="st-nav"
			aria-expanded="false"
			aria-label="<?php esc_attr_e( 'Abrir el menú', 'solartechonolgy' ); ?>"
			data-st-nav-toggle
		>
			<span class="st-nav-toggle__bars" aria-hidden="true"></span>
		</button>

		<nav id="st-nav" class="st-nav" aria-label="<?php esc_attr_e( 'Principal', 'solartechonolgy' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'st-nav__list',
					'depth'          => 1,
					'fallback_cb'    => false,
				) );
			} else {
				?>
				<ul class="st-nav__list">
					<li><a href="<?php echo esc_url( st_catalog_url() ); ?>"><?php esc_html_e( 'Catálogo', 'solartechonolgy' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#beneficios' ) ); ?>"><?php esc_html_e( 'Beneficios', 'solartechonolgy' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#proceso' ) ); ?>"><?php esc_html_e( 'Cómo funciona', 'solartechonolgy' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#contacto' ) ); ?>"><?php esc_html_e( 'Contacto', 'solartechonolgy' ); ?></a></li>
				</ul>
				<?php
			}
			?>
		</nav>

		<span class="st-header__spacer"></span>

		<a class="st-wa-pill" href="<?php echo esc_url( st_wa_link() ); ?>" target="_blank" rel="noopener">
			<span class="st-wa-pill__dot"></span>WhatsApp
		</a>

		<?php if ( st_catalog_mode() ) : ?>
			<a class="st-cart-btn" href="<?php echo esc_url( st_catalog_url() ); ?>">
				<?php esc_html_e( 'Ver catálogo', 'solartechonolgy' ); ?>
			</a>
		<?php else : ?>
			<a class="st-cart-btn" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/carrito/' ) ); ?>" data-st-open-cart>
				<?php esc_html_e( 'Carrito', 'solartechonolgy' ); ?>
				<span class="st-cart-count" data-st-cart-count><?php echo esc_html( ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>
			</a>
		<?php endif; ?>
	</div>
</header>

<main id="content" class="st-main">
