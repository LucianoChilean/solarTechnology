<?php
/**
 * Plugin Name: SolarTech · 60 Pasarela Flow.cl
 * Description: Gateway propio de WooCommerce que integra la API de Flow (create / getStatus / refund). Medios: tarjetas y transferencia. Sin plugin externo.
 * Version: 1.0.0
 * Author: SolarTechonolgy
 *
 * @package SolarTechPayments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 *  Cliente HTTP de la API de Flow (firma HMAC-SHA256).
 * ---------------------------------------------------------------------- */
class ST_Flow_Client {

	protected $api_key;
	protected $secret;
	protected $sandbox;

	public function __construct( $api_key, $secret, $sandbox = true ) {
		$this->api_key = $api_key;
		$this->secret  = $secret;
		$this->sandbox = (bool) $sandbox;
	}

	public function base_url() {
		return $this->sandbox ? 'https://sandbox.flow.cl/api' : 'https://www.flow.cl/api';
	}

	/**
	 * Firma: concatena claves+valores ordenados alfabéticamente y aplica HMAC-SHA256.
	 */
	protected function sign( array $params ) {
		ksort( $params );
		$to_sign = '';
		foreach ( $params as $k => $v ) {
			$to_sign .= $k . $v;
		}
		return hash_hmac( 'sha256', $to_sign, $this->secret );
	}

	public function post( $service, array $params ) {
		$params['apiKey'] = $this->api_key;
		$params['s']      = $this->sign( $params );
		$resp = wp_remote_post( $this->base_url() . $service, array(
			'timeout' => 20,
			'body'    => $params,
		) );
		return $this->parse( $resp );
	}

	public function get( $service, array $params ) {
		$params['apiKey'] = $this->api_key;
		$params['s']      = $this->sign( $params );
		$resp = wp_remote_get( add_query_arg( $params, $this->base_url() . $service ), array( 'timeout' => 20 ) );
		return $this->parse( $resp );
	}

	protected function parse( $resp ) {
		if ( is_wp_error( $resp ) ) {
			return array( 'error' => $resp->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $body ) ? $body : array( 'error' => 'Respuesta inválida de Flow', 'raw' => wp_remote_retrieve_body( $resp ) );
	}
}

/* -------------------------------------------------------------------------
 *  Registro del gateway en WooCommerce.
 * ---------------------------------------------------------------------- */
add_action( 'plugins_loaded', 'st_flow_init_gateway', 11 );
function st_flow_init_gateway() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class ST_Flow_Gateway extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'st_flow';
			$this->method_title       = 'Flow.cl (SolarTech)';
			$this->method_description = 'Pago con tarjetas de crédito/débito y transferencia mediante Flow.cl.';
			$this->has_fields         = false;
			$this->supports           = array( 'products', 'refunds' );
			$this->icon               = '';

			$this->init_form_fields();
			$this->init_settings();

			$this->title       = $this->get_option( 'title', 'Tarjetas y transferencia (Flow)' );
			$this->description = $this->get_option( 'description', 'Serás redirigido a Flow para completar el pago de forma segura.' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			// Webhook / retorno de Flow.
			add_action( 'woocommerce_api_st_flow', array( $this, 'handle_callback' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'     => array(
					'title'   => 'Activar',
					'type'    => 'checkbox',
					'label'   => 'Habilitar pago con Flow.cl',
					'default' => 'yes',
				),
				'title'       => array(
					'title'   => 'Título',
					'type'    => 'text',
					'default' => 'Tarjetas y transferencia (Flow)',
				),
				'description' => array(
					'title'   => 'Descripción',
					'type'    => 'textarea',
					'default' => 'Serás redirigido a Flow para completar el pago de forma segura.',
				),
				'sandbox'     => array(
					'title'   => 'Modo sandbox',
					'type'    => 'checkbox',
					'label'   => 'Usar entorno de pruebas (sandbox.flow.cl)',
					'default' => ( getenv( 'FLOW_SANDBOX' ) === 'no' ) ? 'no' : 'yes',
				),
				'api_key'     => array(
					'title'       => 'Flow API Key',
					'type'        => 'text',
					'default'     => (string) getenv( 'FLOW_API_KEY' ),
					'description' => 'Se toma de .env (FLOW_API_KEY) si está vacío aquí.',
				),
				'secret_key'  => array(
					'title'   => 'Flow Secret Key',
					'type'    => 'password',
					'default' => (string) getenv( 'FLOW_SECRET_KEY' ),
				),
			);
		}

		protected function creds() {
			$api    = $this->get_option( 'api_key' ) ?: getenv( 'FLOW_API_KEY' );
			$secret = $this->get_option( 'secret_key' ) ?: getenv( 'FLOW_SECRET_KEY' );
			$sbox   = 'yes' === $this->get_option( 'sandbox', 'yes' );
			return array( $api, $secret, $sbox );
		}

		protected function client() {
			list( $api, $secret, $sbox ) = $this->creds();
			return new ST_Flow_Client( $api, $secret, $sbox );
		}

		public function is_available() {
			list( $api, $secret ) = $this->creds();
			return 'yes' === $this->enabled && $api && $secret;
		}

		/**
		 * Crear el pago en Flow y redirigir.
		 */
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			list( $api, $secret ) = $this->creds();

			if ( ! $api || ! $secret ) {
				wc_add_notice( 'El pago con Flow no está configurado. Falta FLOW_API_KEY / FLOW_SECRET_KEY en .env.', 'error' );
				return array( 'result' => 'failure' );
			}

			$client = $this->client();
			$params = array(
				'commerceOrder' => (string) $order->get_order_number(),
				'subject'       => 'Pedido #' . $order->get_order_number() . ' — SolarTechonolgy',
				'currency'      => $order->get_currency(),
				'amount'        => (int) round( $order->get_total() ), // CLP sin decimales.
				'email'         => $order->get_billing_email(),
				'urlConfirmation' => WC()->api_request_url( 'st_flow' ),
				'urlReturn'     => $this->get_return_url( $order ),
			);

			$res = $client->post( '/payment/create', $params );

			if ( empty( $res['url'] ) || empty( $res['token'] ) ) {
				$msg = $res['error'] ?? ( $res['message'] ?? 'Error desconocido al crear el pago en Flow.' );
				$order->add_order_note( 'Flow: fallo al crear pago — ' . wp_json_encode( $res ) );
				wc_add_notice( 'No se pudo iniciar el pago con Flow: ' . esc_html( $msg ), 'error' );
				return array( 'result' => 'failure' );
			}

			$order->update_meta_data( '_st_flow_token', $res['token'] );
			$order->update_status( 'pending', 'Redirigido a Flow para el pago.' );
			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => $res['url'] . '?token=' . rawurlencode( $res['token'] ),
			);
		}

		/**
		 * Confirmación de Flow (webhook) y retorno del usuario.
		 */
		public function handle_callback() {
			$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : ( isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '' ); // phpcs:ignore
			if ( ! $token ) {
				status_header( 400 );
				echo 'missing token';
				exit;
			}

			$client = $this->client();
			$status = $client->get( '/payment/getStatus', array( 'token' => $token ) );

			$order_number = $status['commerceOrder'] ?? '';
			$order        = $order_number ? wc_get_order( $order_number ) : false;
			if ( ! $order ) {
				status_header( 404 );
				echo 'order not found';
				exit;
			}

			// status: 1 pendiente, 2 pagado, 3 rechazado, 4 anulado.
			$flow_status = (int) ( $status['status'] ?? 0 );
			switch ( $flow_status ) {
				case 2:
					if ( ! $order->is_paid() ) {
						$order->payment_complete( $status['flowOrder'] ?? $token );
						$order->add_order_note( 'Flow: pago confirmado (flowOrder ' . ( $status['flowOrder'] ?? '—' ) . ').' );
					}
					break;
				case 3:
					$order->update_status( 'failed', 'Flow: pago rechazado.' );
					break;
				case 4:
					$order->update_status( 'cancelled', 'Flow: pago anulado.' );
					break;
				default:
					$order->update_status( 'on-hold', 'Flow: pago pendiente de confirmación.' );
			}
			$order->save();

			status_header( 200 );
			echo 'OK';
			exit;
		}

		/**
		 * Reembolso nativo de WooCommerce → API de Flow.
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order = wc_get_order( $order_id );
			list( $api, $secret ) = $this->creds();
			if ( ! $api || ! $secret ) {
				return new WP_Error( 'st_flow', 'Credenciales de Flow no configuradas.' );
			}
			$client = $this->client();
			$res    = $client->post( '/refund/create', array(
				'refundCommerceOrder' => (string) $order->get_order_number() . '-' . time(),
				'receiverEmail'       => $order->get_billing_email(),
				'amount'              => (int) round( $amount ),
				'urlCallBack'         => WC()->api_request_url( 'st_flow_refund' ),
				'commerceTrxId'       => (string) $order->get_transaction_id(),
			) );

			if ( ! empty( $res['token'] ) || ! empty( $res['flowRefundOrder'] ) ) {
				$order->add_order_note( sprintf( 'Flow: reembolso solicitado por %s. %s', wc_price( $amount ), $reason ) );
				return true;
			}
			return new WP_Error( 'st_flow', 'Flow rechazó el reembolso: ' . ( $res['message'] ?? $res['error'] ?? 'error' ) );
		}
	}

	// Registrar el gateway.
	add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
		$methods[] = 'ST_Flow_Gateway';
		return $methods;
	} );
}
