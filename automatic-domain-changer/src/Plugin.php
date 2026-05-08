<?php
declare( strict_types=1 );

namespace NuageLab\AutoDomainChanger;

use NuageLab\AutoDomainChanger\Admin\DomainChangeNotice;
use NuageLab\AutoDomainChanger\Admin\SettingsPage;
use NuageLab\AutoDomainChanger\Domain\Backup\BackupController;

defined( 'ABSPATH' ) || exit;

/**
 * Top-level plugin object. Wires hooks; owns nothing else.
 */
final class Plugin {

	private const VERSION = '3.0.1';

	private static ?self $instance = null;

	private string $plugin_file;

	private function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	public static function boot( string $plugin_file ): void {
		if ( self::$instance !== null ) {
			return;
		}
		self::$instance = new self( $plugin_file );
		self::$instance->register_hooks();
	}

	public function version(): string {
		return self::VERSION;
	}

	private function register_hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_option( 'auto_domain_change-https', false );
		add_option( 'auto_domain_change-www', true );

		if ( ! is_admin() ) {
			return;
		}

		$plugin_url = plugin_dir_url( $this->plugin_file );

		$settings = new SettingsPage( $plugin_url, self::VERSION );
		add_action( 'admin_menu', array( $settings, 'register_menu' ) );

		$notice = new DomainChangeNotice();
		add_action( 'admin_init', array( $notice, 'check' ) );

		$backup = new BackupController();
		add_action( 'admin_init', array( $backup, 'handle_request' ) );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'auto-domain-change',
			false,
			dirname( plugin_basename( $this->plugin_file ) ) . '/languages/'
		);
	}
}
