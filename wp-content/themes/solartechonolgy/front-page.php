<?php
/**
 * Portada — banner deslizante (3 diapositivas) + catálogo.
 *
 * @package SolarTechonolgy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$slides   = st_hero_slides();
$total    = count( $slides );
$autoplay = (int) get_theme_mod( 'st_slider_autoplay', 6 );
$stats    = array(
	array( '+500', 'Instalaciones' ),
	array( '5 años', 'Garantía' ),
	array( 'Todo Chile', 'Cobertura' ),
);
?>

<!-- HERO / BANNER DESLIZANTE -->
<section class="st-hero st-slider" data-st-slider data-autoplay="<?php echo esc_attr( max( 0, $autoplay ) * 1000 ); ?>"
	aria-roledescription="carrusel" aria-label="<?php esc_attr_e( 'Banner principal', 'solartechonolgy' ); ?>">

	<!-- Capa de imágenes (se desliza en sincronía con los textos) -->
	<div class="st-slider__bglayer" aria-hidden="true">
		<div class="st-slider__track" data-st-track>
			<?php foreach ( $slides as $i => $slide ) : ?>
				<div class="st-slide__bg st-slide__bg--<?php echo esc_attr( $i + 1 ); ?>"
					<?php if ( ! empty( $slide['image'] ) ) : ?>style="background-image:url('<?php echo esc_url( $slide['image'] ); ?>')"<?php endif; ?>></div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Efectos fijos del hero -->
	<div class="st-hero__fx" aria-hidden="true">
		<div class="st-hero__veil"></div>
		<div class="st-hero__grid"></div>
		<div class="st-hero__scan"></div>
		<div class="st-hero__glow st-hero__glow--o"></div>
		<div class="st-hero__glow st-hero__glow--b"></div>
	</div>

	<!-- Textos -->
	<div class="st-slider__viewport">
		<div class="st-slider__track" data-st-track>
			<?php foreach ( $slides as $i => $slide ) : ?>
				<article class="st-slide" role="group" aria-roledescription="<?php esc_attr_e( 'diapositiva', 'solartechonolgy' ); ?>"
					aria-label="<?php echo esc_attr( sprintf( __( '%1$d de %2$d', 'solartechonolgy' ), $i + 1, $total ) ); ?>"
					<?php echo 0 === $i ? '' : 'aria-hidden="true"'; ?>>
					<div class="st-slide__inner">
						<div class="st-hero__copy">
							<?php if ( ! empty( $slide['badge'] ) ) : ?>
								<span class="st-badge"><?php echo esc_html( $slide['badge'] ); ?></span>
							<?php endif; ?>

							<?php
							// Solo la primera diapositiva lleva el h1 de la página.
							$tag       = 0 === $i ? 'h1' : 'h2';
							$highlight = ! empty( $slide['highlight'] ) ? ' <span>' . esc_html( $slide['highlight'] ) . '</span>' : '';
							printf(
								'<%1$s class="st-slide__title">%2$s%3$s</%1$s>',
								esc_attr( $tag ),
								esc_html( $slide['title'] ),
								$highlight // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
							);
							?>

							<p><?php echo esc_html( $slide['text'] ); ?></p>

							<div class="st-hero__cta">
								<a class="st-btn st-btn--orange" href="<?php echo esc_url( $slide['cta_url'] ); ?>" <?php echo 0 === $i ? '' : 'tabindex="-1"'; ?>>
									<?php echo esc_html( $slide['cta_label'] ); ?>
								</a>
								<a class="st-btn st-btn--ghost" href="<?php echo esc_url( st_wa_link( 'Hola Solartechonolgy, quiero cotizar gratis.' ) ); ?>"
									target="_blank" rel="noopener" <?php echo 0 === $i ? '' : 'tabindex="-1"'; ?>>
									<?php esc_html_e( 'Cotizar gratis', 'solartechonolgy' ); ?>
								</a>
							</div>

							<div class="st-hero__stats">
								<?php foreach ( $stats as $stat ) : ?>
									<div><strong><?php echo esc_html( $stat[0] ); ?></strong><small><?php echo esc_html( $stat[1] ); ?></small></div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Controles -->
	<button type="button" class="st-slider__arrow st-slider__arrow--prev" data-st-prev
		aria-label="<?php esc_attr_e( 'Diapositiva anterior', 'solartechonolgy' ); ?>">&#8249;</button>
	<button type="button" class="st-slider__arrow st-slider__arrow--next" data-st-next
		aria-label="<?php esc_attr_e( 'Diapositiva siguiente', 'solartechonolgy' ); ?>">&#8250;</button>

	<div class="st-slider__dots" data-st-dots role="tablist" aria-label="<?php esc_attr_e( 'Diapositivas', 'solartechonolgy' ); ?>">
		<?php foreach ( $slides as $i => $slide ) : ?>
			<button type="button" class="st-slider__dot<?php echo 0 === $i ? ' is-active' : ''; ?>" role="tab"
				data-st-dot="<?php echo esc_attr( $i ); ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
				aria-label="<?php echo esc_attr( sprintf( __( 'Ir a la diapositiva %d', 'solartechonolgy' ), $i + 1 ) ); ?>"></button>
		<?php endforeach; ?>
	</div>
</section>

<!-- BENEFITS -->
<section id="beneficios" class="st-benefits">
	<div class="st-benefits__inner">
		<?php
		$benefits = array(
			array( 'Visita Técnica Gratis', 'Sin costo ni compromiso' ),
			array( 'Instalación en Todo Chile', 'Cobertura nacional' ),
			array( '5 Años de Garantía', 'En la instalación' ),
			array( 'Empresa Chilena', 'Calidad Premium' ),
		);
		foreach ( $benefits as $b ) :
			?>
			<div class="st-benefit">
				<span class="st-benefit__ico">✓</span>
				<div><strong><?php echo esc_html( $b[0] ); ?></strong><small><?php echo esc_html( $b[1] ); ?></small></div>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<!-- CATÁLOGO -->
<?php
get_template_part( 'template-parts/catalog', null, array(
	'id'      => 'productos',
	'eyebrow' => __( 'Catálogo', 'solartechonolgy' ),
	'title'   => __( 'Kits y componentes solares', 'solartechonolgy' ),
	'text'    => __( 'Todo lo que necesitas para tu proyecto fotovoltaico, con respaldo técnico y garantía. Cotiza sin compromiso.', 'solartechonolgy' ),
	'limit'   => 12,
	'more'    => true,
) );
?>

<!-- PROCESS -->
<section id="proceso" class="st-process">
	<div class="st-process__inner">
		<div class="st-section-head">
			<span class="st-eyebrow">Proceso</span>
			<h2>Cómo funciona</h2>
		</div>
		<div class="st-steps">
			<?php
			$steps = array(
				array( '1', 'Cotiza', 'Elige tu kit o componentes del catálogo y escríbenos por WhatsApp.' ),
				array( '2', 'Visita técnica', 'Coordinamos una visita gratuita para evaluar tu instalación.' ),
				array( '3', 'Instalación', 'Instalamos con certificación SEC TE4 en todo Chile.' ),
				array( '4', 'Genera energía', 'Comienza a ahorrar y monitorea tu producción solar.' ),
			);
			foreach ( $steps as $s ) :
				?>
				<div class="st-step">
					<div class="st-step__n"><?php echo esc_html( $s[0] ); ?></div>
					<h3><?php echo esc_html( $s[1] ); ?></h3>
					<p><?php echo esc_html( $s[2] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- TESTIMONIALS -->
<section class="st-testimonials">
	<div class="st-section-head">
		<span class="st-eyebrow">Clientes</span>
		<h2>Lo que dicen de nosotros</h2>
	</div>
	<div class="st-testimonials__grid">
		<?php
		$testimonials = array(
			array( 'Instalaron el kit de 6kW en mi casa y mi cuenta de luz bajó casi a cero. Equipo muy profesional.', 'Carlos Muñoz', 'La Serena', 'C' ),
			array( 'La visita técnica fue rápida y la instalación impecable. Todo certificado y a tiempo.', 'Patricia Rojas', 'Santiago', 'P' ),
			array( 'Excelente respaldo post venta. Recomiendo Solartechonolgy para empresas, instalaron 10kW sin problemas.', 'Andrés Vidal', 'Concepción', 'A' ),
		);
		foreach ( $testimonials as $t ) :
			?>
			<div class="st-testimonial">
				<div class="st-testimonial__stars">★★★★★</div>
				<p><?php echo esc_html( $t[0] ); ?></p>
				<div class="st-testimonial__who">
					<span class="st-testimonial__ini"><?php echo esc_html( $t[3] ); ?></span>
					<div><strong><?php echo esc_html( $t[1] ); ?></strong><small><?php echo esc_html( $t[2] ); ?></small></div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php
get_footer();
