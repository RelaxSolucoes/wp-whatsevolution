<?php
namespace WpWhatsAppEvolution;

// Envio em massa de mensagens WhatsApp
class Mass_Send {
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_submenu' ] );
	}

	public static function add_submenu() {
		add_submenu_page(
			'wpwevo-settings',
            __( 'Envio em Massa', 'wp-whatsevolution' ),
            __( 'Envio em Massa', 'wp-whatsevolution' ),
			'manage_options',
			'wpwevo-mass-send',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page() {
		?>
		<div class="wrap">
            <h1><?php _e( 'Envio em Massa de WhatsApp', 'wp-whatsevolution' ); ?></h1>
            <p><?php _e( 'Selecione clientes, importe CSV ou filtre para enviar mensagens em massa. Agende e acompanhe o progresso.', 'wp-whatsevolution' ); ?></p>
			<!-- Interface simplificada, implementação completa recomendada para produção -->
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpwevo_mass_send', 'wpwevo_mass_send_nonce' ); ?>
				<p><input type="file" name="wpwevo_csv"></p>
				<p><textarea name="wpwevo_numbers" rows="3" placeholder="Números separados por vírgula"></textarea></p>
				<p><textarea name="wpwevo_message" rows="4" placeholder="Mensagem"></textarea></p>
                <?php submit_button( __( 'Enviar em Massa', 'wp-whatsevolution' ) ); ?>
			</form>
		</div>
		<?php
		// Lógica de envio em massa simplificada
		if ( isset( $_POST['wpwevo_mass_send_nonce'] ) && wp_verify_nonce( $_POST['wpwevo_mass_send_nonce'], 'wpwevo_mass_send' ) ) {
			$numbers = [];
			if ( ! empty( $_POST['wpwevo_numbers'] ) ) {
				$numbers = array_map( 'trim', explode( ',', sanitize_text_field( $_POST['wpwevo_numbers'] ) ) );
			}
			if ( ! empty( $_FILES['wpwevo_csv']['tmp_name'] ) ) {
				$csv = array_map( 'str_getcsv', file( $_FILES['wpwevo_csv']['tmp_name'] ) );
				foreach ( $csv as $row ) {
					if ( isset( $row[0] ) ) $numbers[] = trim( $row[0] );
				}
			}
			$message = isset( $_POST['wpwevo_message'] ) ? sanitize_textarea_field( $_POST['wpwevo_message'] ) : '';
			$count = 0;
			foreach ( $numbers as $number ) {
				if ( $number && $message ) {
					wpwevo_send_message( $number, $message );
					$count++;
				}
			}
            echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( '%d mensagens enviadas.', 'wp-whatsevolution' ), $count ) . '</p></div>';
		}
	}
} 