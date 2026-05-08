<?php
declare( strict_types=1 );

namespace NuageLab\AutoDomainChanger\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Detects a domain change between requests and surfaces a one-line admin notice
 * pointing at the settings page (with a nonced dismiss link).
 */
final class DomainChangeNotice {

	public const DISMISS_NONCE = 'auto_domain_change_dismiss';
	public const PLUGIN_FILE   = 'auto-domain-change.php';

	public function check(): void {
		$current_host = $this->current_host();
		if ( $current_host === '' ) {
			return;
		}

		$old_domain = (string) get_option( 'auto_domain_change-domain', '' );
		if ( $old_domain === '' ) {
			update_option( 'auto_domain_change-domain', $current_host );
			return;
		}

		if ( $old_domain === $current_host || isset( $_POST['new-domain'] ) ) {
			return;
		}

		if ( $this->dismiss_requested() ) {
			update_option( 'auto_domain_change-dismiss', $current_host );
			return;
		}

		if ( strtolower( (string) get_option( 'auto_domain_change-dismiss', '' ) ) === strtolower( $current_host ) ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'update_core' ) ) {
			return;
		}

		$tool_url    = admin_url( 'tools.php?page=' . self::PLUGIN_FILE );
		$dismiss_url = wp_nonce_url(
			add_query_arg( 'dismiss-domain-change', '1' ),
			self::DISMISS_NONCE
		);

		echo '<div class="notice notice-warning"><p>';
		echo wp_kses(
			sprintf(
				/* translators: 1: URL to the domain-change tool page, 2: URL that dismisses this notice. */
				__( 'The domain name of your WordPress blog appears to have changed! <a href="%1$s">Click here to update your config</a> or <a href="%2$s">dismiss</a>.', 'auto-domain-change' ),
				esc_url( $tool_url ),
				esc_url( $dismiss_url )
			),
			array( 'a' => array( 'href' => array() ) )
		);
		echo '</p></div>';
	}

	private function dismiss_requested(): bool {
		if ( empty( $_GET['dismiss-domain-change'] ) ) {
			return false;
		}
		if ( ! current_user_can( 'update_core' ) ) {
			return false;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		return (bool) wp_verify_nonce( $nonce, self::DISMISS_NONCE );
	}

	private function current_host(): string {
		if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
	}
}
