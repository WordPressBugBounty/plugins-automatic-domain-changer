<?php
declare( strict_types=1 );

namespace NuageLab\AutoDomainChanger\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Plain value object describing how aggressively the replacer should match.
 */
final class Options {

	public bool $include_https;
	public bool $include_www;

	public function __construct( bool $include_https = false, bool $include_www = true ) {
		$this->include_https = $include_https;
		$this->include_www   = $include_www;
	}

	public static function from_settings(): self {
		return new self(
			(bool) get_option( 'auto_domain_change-https', false ),
			(bool) get_option( 'auto_domain_change-www', true )
		);
	}
}
