<?php
namespace WpWhatsAppEvolution;

// Validação de número WhatsApp no checkout
class Checkout_Validator {
	public static function init() {
		add_action( 'woocommerce_after_checkout_validation', [ __CLASS__, 'validate_phone' ], 10, 2 );
	}

	public static function validate_phone( $data, $errors ) {
		$phone = isset( $data['billing_phone'] ) ? $data['billing_phone'] : '';
		if ( ! $phone ) return;

		// Remove caracteres não numéricos
		$phone = preg_replace( '/[^0-9]/', '', $phone );

		$api_url = get_option( 'wpwevo_api_url', '' );
		$api_key = get_option( 'wpwevo_api_key', '' );
		$instance = get_option( 'wpwevo_instance', '' );

		if ( ! $api_url || ! $api_key || ! $instance ) return;

		// Primeiro verifica se a instância está ativa
		if ( ! wpwevo_check_instance() ) {
			$errors->add( 'billing_phone', __( 'Sistema WhatsApp indisponível no momento. Tente novamente em alguns minutos.', 'wp-whatsapp-evolution' ) );
			return;
		}

		$request_url = trailingslashit( $api_url ) . 'chat/whatsappNumbers/' . urlencode( $instance );
		$response = wp_remote_post( $request_url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'api-key' => $api_key,
			],
			'body' => wp_json_encode([
				'numbers' => [ $phone ],
			]),
			'timeout' => 20,
		]);

		if ( is_wp_error( $response ) ) {
			$errors->add( 'billing_phone', __( 'Não foi possível validar o número de WhatsApp. Tente novamente.', 'wp-whatsapp-evolution' ) );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Verifica se o número existe no WhatsApp
		if ( ! is_array( $body ) || empty( $body[0]['exists'] ) ) {
			$errors->add( 'billing_phone', __( 'O número informado não é um WhatsApp válido.', 'wp-whatsapp-evolution' ) );
		}
	}
} 