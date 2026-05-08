<?php
declare( strict_types=1 );

namespace NuageLab\AutoDomainChanger\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregate counts returned from a Replacer run.
 */
final class Result {

	public int $tables_processed = 0;
	public int $rows_updated     = 0;

	/** @var string[] */
	public array $skipped = array();
}
