<?php
declare( strict_types=1 );

namespace NuageLab\AutoDomainChanger\Admin;

use NuageLab\AutoDomainChanger\Domain\Options;
use NuageLab\AutoDomainChanger\Domain\Replacer;

defined( 'ABSPATH' ) || exit;

/**
 * Tools → Change Domain admin page.
 *
 * Owns:
 *   - menu registration
 *   - asset enqueue (CSS/JS only on this screen)
 *   - form rendering (delegated to a template)
 *   - form submission → Replacer
 */
final class SettingsPage {

	public const SLUG          = 'auto-domain-change.php';
	public const ACTION_PREFIX = 'change-domain';
	public const CAPABILITY    = 'update_core';

	private string $plugin_url;
	private string $version;

	public function __construct( string $plugin_url, string $version ) {
		$this->plugin_url = untrailingslashit( $plugin_url );
		$this->version    = $version;
	}

	public function register_menu(): void {
		$hook = add_management_page(
			__( 'Change Domain', 'auto-domain-change' ),
			__( 'Change Domain', 'auto-domain-change' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);

		if ( $hook ) {
			add_action( 'admin_print_styles-' . $hook, array( $this, 'enqueue_assets' ) );
		}
	}

	public function enqueue_assets(): void {
		wp_enqueue_style(
			'auto-domain-change-admin',
			$this->plugin_url . '/assets/css/admin.css',
			array(),
			$this->version
		);
		wp_enqueue_script(
			'auto-domain-change-admin',
			$this->plugin_url . '/assets/js/admin.js',
			array(),
			$this->version,
			true
		);
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'auto-domain-change' ) );
		}

		$state = $this->maybe_handle_submission();
		if ( $state['handled'] ) {
			return;
		}

		$view = array(
			'error_terms'    => $state['error_terms'],
			'force_protocol' => $this->detect_force_protocol(),
			'current_host'   => $this->current_host(),
			'old_domain'     => (string) get_option( 'auto_domain_change-domain', '' ),
			'options'        => Options::from_settings(),
			'change_action'  => self::ACTION_PREFIX . '+' . wp_generate_password( 12, false ),
			'backup_action'  => 'backup-database+' . wp_generate_password( 12, false ),
		);

		require dirname( __DIR__, 2 ) . '/templates/settings-page.php';
	}

	/**
	 * @return array{handled:bool,error_terms:bool}
	 */
	private function maybe_handle_submission(): array {
		$result = array(
			'handled'     => false,
			'error_terms' => false,
		);

		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		$nonce  = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( $action === '' || ! wp_verify_nonce( $nonce, $action ) ) {
			return $result;
		}

		// Verified + capability-gated: persist toggle options.
		update_option( 'auto_domain_change-https', ! empty( $_POST['https-domain'] ) );
		update_option( 'auto_domain_change-www', ! empty( $_POST['www-domain'] ) );

		[ $action_name ] = explode( '+', $action, 2 ) + array( '' );
		if ( $action_name !== self::ACTION_PREFIX ) {
			return $result;
		}

		if ( empty( $_POST['accept-terms'] ) ) {
			$result['error_terms'] = true;
			return $result;
		}

		$old = isset( $_POST['old-domain'] ) ? $this->sanitize_domain( wp_unslash( $_POST['old-domain'] ) ) : '';
		$new = isset( $_POST['new-domain'] ) ? $this->sanitize_domain( wp_unslash( $_POST['new-domain'] ) ) : '';
		if ( $old === '' || $new === '' ) {
			wp_die( esc_html__( 'Invalid domain provided.', 'auto-domain-change' ) );
		}

		$force_protocol = isset( $_POST['force-protocol'] ) ? sanitize_key( wp_unslash( $_POST['force-protocol'] ) ) : '';
		if ( ! in_array( $force_protocol, array( 'http', 'https', '' ), true ) ) {
			$force_protocol = '';
		}

		$this->run_replacement( $old, $new, $force_protocol !== '' ? $force_protocol : null );
		$result['handled'] = true;
		return $result;
	}

	private function run_replacement( string $old, string $new, ?string $force_protocol ): void {
		global $wpdb;

		set_time_limit( 0 );

		echo '<div class="wrap adc-progress">';
		echo '<h1>' . esc_html__( 'Changing domain', 'auto-domain-change' ) . '</h1>';
		echo '<pre class="adc-log">';
		printf( "%s\n", esc_html( sprintf( __( 'Old domain: %s', 'auto-domain-change' ), $old ) ) );
		printf( "%s\n", esc_html( sprintf( __( 'New domain: %s', 'auto-domain-change' ), $new ) ) );
		echo "----\n";

		$replacer = new Replacer(
			$wpdb,
			Options::from_settings(),
			static function ( string $message ): void {
				echo esc_html( $message ) . "\n";
				flush();
			}
		);

		$result = $replacer->run( $old, $new, $force_protocol );

		echo "----\n";
		printf(
			esc_html(
				/* translators: 1: number of tables touched, 2: rows updated, 3: tables skipped. */
				__( 'Done. Tables processed: %1$d, rows updated: %2$d, tables skipped: %3$d.', 'auto-domain-change' )
			),
			$result->tables_processed,
			$result->rows_updated,
			count( $result->skipped )
		);
		echo '</pre>';

		update_option( 'auto_domain_change-domain', $new );

		printf(
			'<p><a class="button button-primary" href="%s">%s</a></p>',
			esc_url( admin_url( 'tools.php?page=' . self::SLUG ) ),
			esc_html__( 'Back', 'auto-domain-change' )
		);
		echo '</div>';
	}

	private function sanitize_domain( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value === '' ) {
			return '';
		}
		$value = (string) preg_replace( '~^https?://~i', '', $value );
		$value = (string) preg_replace( '~[/?#].*$~', '', $value );
		if ( ! preg_match( '/^[A-Za-z0-9.\-:]+$/', $value ) ) {
			return '';
		}
		return strtolower( $value );
	}

	private function detect_force_protocol(): string {
		if ( array_key_exists( 'force-protocol', $_POST ) ) {
			$candidate = sanitize_key( wp_unslash( $_POST['force-protocol'] ) );
			if ( in_array( $candidate, array( 'http', 'https', '' ), true ) ) {
				return $candidate;
			}
			return 'http';
		}
		if ( ! empty( $_SERVER['HTTPS'] ) ) {
			return 'https';
		}
		if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] )
			&& sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) === 'https'
		) {
			return 'https';
		}
		return 'http';
	}

	private function current_host(): string {
		return isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
			: '';
	}
}
