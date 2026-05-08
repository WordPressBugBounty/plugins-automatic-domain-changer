<?php
/*
Plugin Name: Automatic Domain Changer
Plugin URI: http://www.nuagelab.com/wordpress-plugins/auto-domain-change
Description: Automatically changes the domain of a WordPress blog
Author: NuageLab <wordpress-plugins@nuagelab.com>
Version: 3.0.1
License: GPLv2 or later
Author URI: http://www.nuagelab.com/wordpress-plugins
Text Domain: auto-domain-change
Domain Path: /languages
Requires at least: 5.0
Requires PHP: 7.4
*/

defined( 'ABSPATH' ) || exit;

// Runtime PHP version guard. The `Requires PHP` header is honored by WP 5.1+,
// but on older WP we still need to bail out gracefully because unserialize()'s
// `allowed_classes` option (used to prevent object injection) is silently
// ignored on PHP < 7.0.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					esc_html__( 'Automatic Domain Changer requires PHP %1$s or higher. You are running PHP %2$s; the plugin has been disabled.', 'auto-domain-change' ),
					'7.4',
					esc_html( PHP_VERSION )
				)
			);
		}
	);
	return;
}

// Lightweight PSR-4 autoloader for the NuageLab\AutoDomainChanger\ namespace.
// Avoids a Composer runtime dependency for a plugin with no third-party deps.
spl_autoload_register(
	static function ( $class ) {
		$prefix   = 'NuageLab\\AutoDomainChanger\\';
		$base_dir = __DIR__ . '/src/';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);

NuageLab\AutoDomainChanger\Plugin::boot( __FILE__ );
