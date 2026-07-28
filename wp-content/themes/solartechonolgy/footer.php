<?php
/**
 * Pie del sitio + drawer del carrito (mini-cart de WooCommerce).
 *
 * @package SolarTechonolgy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main><!-- #content -->

<section id="contacto" class="st-contact">
	<div class="st-contact__inner">
		<div>
			<h2><?php esc_html_e( '¿Listo para ahorrar con energía solar?', 'solartechonolgy' ); ?></h2>
			<p><?php esc_html_e( 'Agenda tu visita técnica gratuita. Te asesoramos y cotizamos sin compromiso en todo Chile.', 'solartechonolgy' ); ?></p>
			<div class="st-contact__links">
				<a href="<?php echo esc_url( st_wa_link() ); ?>" target="_blank" rel="noopener">
					<span class="st-ico st-ico--wa">WA</span>
					<span><small>WhatsApp</small><strong>+56 9 7408 9594</strong></span>
				</a>
				<a href="mailto:<?php echo esc_attr( st_contact_email() ); ?>">
					<span class="st-ico st-ico--mail">@</span>
					<span><small>Email</small><strong><?php echo esc_html( st_contact_email() ); ?></strong></span>
				</a>
			</div>
		</div>
		<div class="st-includes">
			<h3><?php esc_html_e( 'Solartechonolgy incluye siempre', 'solartechonolgy' ); ?></h3>
			<?php
			$includes = array(
				'Certificación SEC TE4',
				'Inversor y paneles de marcas premium',
				'Instalación según condiciones climáticas',
				'Mantenimiento preventivo 1 año gratis',
				'Monitoreo de producción',
			);
			foreach ( $includes as $i ) :
				?>
				<div class="st-includes__row"><span>✓</span><span><?php echo esc_html( $i ); ?></span></div>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="st-contact__bar">
		<div class="st-contact__bar-inner">
			<span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Solartechonolgy · www.solartechonolgy.cl · Empresa Chilena</span>
			<span><?php esc_html_e( 'Instalación certificada SEC en todo Chile', 'solartechonolgy' ); ?></span>
		</div>
	</div>
</section>

<?php if ( ! st_catalog_mode() ) : ?>
<!-- Drawer del carrito -->
<div class="st-cart-backdrop" data-st-cart-backdrop></div>
<aside class="st-cart-drawer" data-st-cart-drawer aria-hidden="true">
	<div class="st-cart-drawer__head">
		<h3><?php esc_html_e( 'Tu carrito', 'solartechonolgy' ); ?></h3>
		<button type="button" class="st-cart-drawer__close" data-st-close-cart aria-label="<?php esc_attr_e( 'Cerrar', 'solartechonolgy' ); ?>">✕</button>
	</div>
	<div class="st-cart-drawer__body" data-st-cart-body>
		<?php
		if ( function_exists( 'woocommerce_mini_cart' ) ) {
			echo '<div class="widget_shopping_cart_content">';
			woocommerce_mini_cart();
			echo '</div>';
		}
		?>
	</div>
	<div class="st-cart-drawer__foot">
		<a class="st-btn st-btn--dark" href="<?php echo esc_url( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/finalizar-compra/' ) ); ?>"><?php esc_html_e( 'Ir a pagar', 'solartechonolgy' ); ?></a>
		<a class="st-btn st-btn--wa-outline" href="<?php echo esc_url( st_wa_link( 'Hola Solartechonolgy, quiero cotizar mi carrito.' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Cotizar por WhatsApp', 'solartechonolgy' ); ?></a>
	</div>
</aside>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
