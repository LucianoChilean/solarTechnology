<?php
/**
 * Plugin Name: SolarTech · 61 Reembolsos
 * Description: El cliente solicita un reembolso desde "Mi cuenta → Pedidos" (el pedido pasa a "Reembolso solicitado" y se avisa al admin). El admin lo aprueba con el botón de reembolso nativo, que llama a la API de Flow.
 * Version: 1.0.0
 * Author: SolarTechonolgy
 *
 * @package SolarTechPayments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', 'st_refunds_init', 12 );
function st_refunds_init() {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	/* ---- Registrar estado "Reembolso solicitado" ------------------------- */
	add_action( 'init', function () {
		register_post_status( 'wc-refund-req', array(
			'label'                     => 'Reembolso solicitado',
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Reembolso solicitado <span class="count">(%s)</span>', 'Reembolso solicitado <span class="count">(%s)</span>' ),
		) );
	} );

	add_filter( 'wc_order_statuses', function ( $statuses ) {
		$new = array();
		foreach ( $statuses as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				$new['wc-refund-req'] = 'Reembolso solicitado';
			}
		}
		if ( ! isset( $new['wc-refund-req'] ) ) {
			$new['wc-refund-req'] = 'Reembolso solicitado';
		}
		return $new;
	} );

	/* ---- Botón "Solicitar reembolso" en Mi cuenta → Pedidos -------------- */
	add_filter( 'woocommerce_my_account_my_orders_actions', function ( $actions, $order ) {
		if ( st_refund_is_requestable( $order ) ) {
			$actions['st_refund'] = array(
				'url'  => wp_nonce_url(
					add_query_arg( array( 'st_refund_order' => $order->get_id() ), wc_get_account_endpoint_url( 'orders' ) ),
					'st_refund_' . $order->get_id()
				),
				'name' => 'Solicitar reembolso',
			);
		}
		return $actions;
	}, 10, 2 );

	// También en la vista de detalle del pedido.
	add_action( 'woocommerce_order_details_after_order_table', function ( $order ) {
		if ( st_refund_is_requestable( $order ) ) {
			$url = wp_nonce_url(
				add_query_arg( array( 'st_refund_order' => $order->get_id() ), wc_get_account_endpoint_url( 'orders' ) ),
				'st_refund_' . $order->get_id()
			);
			echo '<p><a class="button" href="' . esc_url( $url ) . '">Solicitar reembolso de este pedido</a></p>';
		} elseif ( $order->has_status( 'refund-req' ) ) {
			echo '<p><strong>Tu solicitud de reembolso está en revisión.</strong></p>';
		}
	} );

	/* ---- Procesar la solicitud del cliente ------------------------------- */
	add_action( 'template_redirect', function () {
		if ( empty( $_GET['st_refund_order'] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$order_id = absint( $_GET['st_refund_order'] ); // phpcs:ignore
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'st_refund_' . $order_id ) ) {
			wc_add_notice( 'Enlace de reembolso inválido o expirado.', 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
			exit;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_customer_id() !== get_current_user_id() ) {
			wc_add_notice( 'No tienes permiso sobre este pedido.', 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
			exit;
		}
		if ( ! st_refund_is_requestable( $order ) ) {
			wc_add_notice( 'Este pedido no admite solicitud de reembolso.', 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
			exit;
		}

		$order->update_status( 'refund-req', 'El cliente solicitó un reembolso.' );
		$order->update_meta_data( '_st_refund_requested', time() );
		$order->save();

		// Avisar al admin.
		$admin = get_option( 'admin_email' );
		wp_mail(
			$admin,
			'[SolarTech] Reembolso solicitado — Pedido #' . $order->get_order_number(),
			sprintf(
				"El cliente %s (%s) solicitó el reembolso del pedido #%s por %s.\n\nRevísalo en: %s",
				$order->get_formatted_billing_full_name(),
				$order->get_billing_email(),
				$order->get_order_number(),
				html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ) ),
				admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' )
			)
		);

		wc_add_notice( 'Hemos recibido tu solicitud de reembolso. Un asesor la revisará a la brevedad.' );
		wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
		exit;
	} );

	/* ---- Aviso en el admin del pedido ------------------------------------ */
	add_action( 'woocommerce_admin_order_data_after_order_details', function ( $order ) {
		if ( $order->has_status( 'refund-req' ) ) {
			echo '<div class="notice notice-warning inline" style="margin:10px 0;padding:10px"><strong>Reembolso solicitado por el cliente.</strong> Usa el botón «Reembolso» (más abajo) para procesarlo vía Flow.</div>';
		}
	} );
}

/**
 * ¿El pedido admite solicitud de reembolso?
 */
function st_refund_is_requestable( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}
	if ( ! $order->is_paid() && ! $order->has_status( array( 'completed', 'processing' ) ) ) {
		return false;
	}
	if ( $order->has_status( array( 'refunded', 'refund-req', 'cancelled', 'failed' ) ) ) {
		return false;
	}
	// Solo pagos con Flow (o ajusta según necesites).
	return in_array( $order->get_payment_method(), array( 'st_flow', '' ), true ) || true;
}
