<?php
declare( strict_types=1 );

namespace NuageLab\AutoDomainChanger\Domain\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the backup form submission to the right Backup implementation.
 *
 * Hooked from Plugin::register_hooks() — admin_init runs after pluggable.php
 * is loaded, so wp_verify_nonce() is safe here.
 */
final class BackupController {

	public const ACTION_PREFIX = 'backup-database';

	public function handle_request(): void {
		if ( ! isset( $_POST['action'], $_POST['nonce'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
		$nonce  = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return;
		}

		[ $action_name ] = explode( '+', $action, 2 ) + array( '' );
		if ( $action_name !== self::ACTION_PREFIX ) {
			return;
		}

		if ( ! current_user_can( 'export' ) ) {
			return;
		}

		$type   = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'sql';
		$backup = $type === 'php' ? new PhpBackup() : new SqlBackup();

		$this->stream( $backup );
	}

	private function stream( Backup $backup ): void {
		global $wpdb;

		set_time_limit( 0 );
		nocache_headers();

		$base = preg_replace(
			'/[^a-zA-Z0-9_\-]/',
			'_',
			(string) preg_replace( ',^https?://,i', '', (string) get_bloginfo( 'url' ) )
		);
		$file = $backup->filename( $base . '-' . gmdate( 'Ymd-His' ) );

		header( 'Content-Type: ' . $backup->content_type() );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );

		$backup->stream( $wpdb );
		exit;
	}
}
