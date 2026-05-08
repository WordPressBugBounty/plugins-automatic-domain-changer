<?php
declare( strict_types=1 );

namespace NuageLab\AutoDomainChanger\Domain\Backup;

defined( 'ABSPATH' ) || exit;

interface Backup {

	/**
	 * Stream the backup to PHP output (does NOT call exit).
	 */
	public function stream( \wpdb $db ): void;

	public function filename( string $base ): string;

	public function content_type(): string;
}
