<?php
/**
 * Personalizador: banner principal (slider de 3 diapositivas).
 *
 * Apariencia → Personalizar → Banner principal.
 *
 * @package SolarTechonolgy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra la sección y los controles de las 3 diapositivas.
 *
 * @param WP_Customize_Manager $wp_customize Gestor del personalizador.
 */
function st_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'st_hero', array(
		'title'       => __( 'Banner principal', 'solartechonolgy' ),
		'priority'    => 25,
		'description' => __( 'Tres diapositivas con imagen y texto propios. Se pasan solas y también con las flechas o los puntos.', 'solartechonolgy' ),
	) );

	$defaults = st_hero_slides();

	for ( $n = 1; $n <= 3; $n++ ) {
		$slide  = $defaults[ $n - 1 ];
		$fields = array(
			'badge'     => array( __( 'Etiqueta (badge)', 'solartechonolgy' ), 'text' ),
			'title'     => array( __( 'Título', 'solartechonolgy' ), 'text' ),
			'highlight' => array( __( 'Título destacado (en naranjo)', 'solartechonolgy' ), 'text' ),
			'text'      => array( __( 'Texto', 'solartechonolgy' ), 'textarea' ),
			'cta_label' => array( __( 'Texto del botón', 'solartechonolgy' ), 'text' ),
			'cta_url'   => array( __( 'Enlace del botón', 'solartechonolgy' ), 'url' ),
		);

		// Imagen de fondo.
		$wp_customize->add_setting( "st_slide_{$n}_image", array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, "st_slide_{$n}_image", array(
			'label'       => sprintf( __( 'Diapositiva %d — imagen', 'solartechonolgy' ), $n ),
			'description' => __( 'Recomendado: 1600×900 px o mayor.', 'solartechonolgy' ),
			'section'     => 'st_hero',
		) ) );

		foreach ( $fields as $key => $field ) {
			list( $label, $type ) = $field;

			$wp_customize->add_setting( "st_slide_{$n}_{$key}", array(
				'default'           => '',
				'sanitize_callback' => 'url' === $type ? 'esc_url_raw' : 'sanitize_text_field',
				'transport'         => 'refresh',
			) );
			$wp_customize->add_control( "st_slide_{$n}_{$key}", array(
				'label'       => sprintf( __( 'Diapositiva %1$d — %2$s', 'solartechonolgy' ), $n, $label ),
				'section'     => 'st_hero',
				'type'        => 'textarea' === $type ? 'textarea' : ( 'url' === $type ? 'url' : 'text' ),
				'input_attrs' => array( 'placeholder' => isset( $slide[ $key ] ) ? $slide[ $key ] : '' ),
			) );
		}
	}

	// Velocidad del pase automático.
	$wp_customize->add_setting( 'st_slider_autoplay', array(
		'default'           => 6,
		'sanitize_callback' => 'absint',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'st_slider_autoplay', array(
		'label'       => __( 'Segundos por diapositiva (0 = sin pase automático)', 'solartechonolgy' ),
		'section'     => 'st_hero',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 0, 'max' => 30, 'step' => 1 ),
	) );
}
add_action( 'customize_register', 'st_customize_register' );
