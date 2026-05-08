<?php
declare( strict_types=1 );

namespace NuageLab\AutoDomainChanger\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Walks a domain change across every WordPress-prefixed table.
 *
 * The replacer takes an Options object and a wpdb-like handle so it can be
 * exercised from WP-CLI or unit tests without depending on the admin UI.
 */
final class Replacer {

	/** @var \wpdb */
	private $db;

	/** @var Options */
	private $options;

	/** @var callable|null */
	private $progress;

	public function __construct( \wpdb $db, Options $options, ?callable $progress = null ) {
		$this->db       = $db;
		$this->options  = $options;
		$this->progress = $progress;
	}

	/**
	 * Run the replacement across every table sharing the WP prefix.
	 *
	 * @return Result Number of rows touched, tables skipped, etc.
	 */
	public function run( string $old, string $new, ?string $force_protocol = null ): Result {
		$result = new Result();

		// Fast path: nothing to do when both ends match (case-insensitively) and
		// the user did not request a protocol flip. Skipping prevents needless
		// reads + the unserialize/reserialize round-trip on every row.
		if ( $force_protocol === null && strtolower( $old ) === strtolower( $new ) ) {
			$this->progress( 'Old and new domains match — nothing to do.' );
			return $result;
		}

		$prefix      = $this->db->prefix;
		$prefix_like = $this->db->esc_like( $prefix ) . '%';
		$tables      = $this->db->get_col( $this->db->prepare( 'SHOW TABLES LIKE %s', $prefix_like ) );

		if ( ! is_array( $tables ) ) {
			return $result;
		}

		foreach ( $tables as $table ) {
			if ( strpos( $table, $prefix ) !== 0 ) {
				continue;
			}
			$this->process_table( $table, $old, $new, $force_protocol, $result );
		}

		return $result;
	}

	private function process_table( string $table, string $old, string $new, ?string $force_protocol, Result $result ): void {
		$id = $this->find_unique_column( $table );
		if ( $id === null ) {
			$result->skipped[] = $table;
			$this->progress( sprintf( 'Skipping table %s because no unique id', $table ) );
			return;
		}

		$this->progress( sprintf( 'Processing table %s', $table ) );
		$result->tables_processed++;

		$offset = 0;
		do {
			$rows = $this->db->get_results(
				$this->db->prepare(
					'SELECT * FROM `' . $this->safe_ident( $table ) . '` LIMIT %d, %d',
					$offset,
					50
				)
			);

			if ( ! is_array( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$row     = get_object_vars( $row );
				$updates = array();

				foreach ( $row as $column => $value ) {
					$original = $value;
					$value    = $this->process_value( $value, $old, $new, $force_protocol );
					if ( $original !== $value ) {
						$updates[ $column ] = $value;
					}
				}

				if ( ! empty( $updates ) && array_key_exists( $id, $row ) ) {
					$this->db->update( $table, $updates, array( $id => $row[ $id ] ) );
					$result->rows_updated++;
				}
			}

			$offset += count( $rows );
		} while ( count( $rows ) > 0 );
	}

	private function find_unique_column( string $table ): ?string {
		$indices = $this->db->get_results( 'SHOW INDEX FROM `' . $this->safe_ident( $table ) . '`' );
		if ( ! is_array( $indices ) ) {
			return null;
		}

		$candidate = null;
		foreach ( $indices as $row ) {
			$row = get_object_vars( $row );
			if ( ( $row['Key_name'] ?? '' ) === 'PRIMARY' ) {
				return (string) $row['Column_name'];
			}
			if ( (int) ( $row['Non_unique'] ?? 1 ) === 0 ) {
				$candidate = (string) $row['Column_name'];
			}
		}
		return $candidate;
	}

	/**
	 * Walk one column value, recursively handling serialized arrays/scalars and JSON.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function process_value( $value, string $old, string $new, ?string $force_protocol ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		// If the row holds a serialized payload that contains a PHP class instance
		// anywhere inside it, leave the entire row untouched. We cannot deserialize
		// it safely (allowed_classes => false would replace every instance with
		// __PHP_Incomplete_Class, which then fatals when other code mutates it),
		// and walking the bytes is out of scope here. Plain strings, arrays, and
		// nested arrays are handled normally below — only objects opt out.
		if ( self::looks_serialized( $value ) && self::serialized_contains_object( $value ) ) {
			return $value;
		}

		$serialized = false;
		$json       = false;

		// Disable class loading on unserialize to prevent PHP object injection.
		$unserialized = @unserialize( $value, array( 'allowed_classes' => false ) );
		if ( $unserialized !== false || $value === serialize( false ) ) {
			$value      = $unserialized;
			$serialized = true;
		} else {
			$decoded = json_decode( $value );
			if ( $decoded !== null && $decoded != $value && $decoded != json_decode( 'false' ) ) {
				$value = $decoded;
				$json  = true;
			}
		}

		if ( $serialized && is_string( $value ) ) {
			// Double-serialize: recurse.
			$value = $this->process_value( $value, $old, $new, $force_protocol );
		} else {
			$this->replace( $value, $old, $new, $force_protocol );
		}

		if ( $serialized ) {
			return serialize( $value );
		}
		if ( $json ) {
			return wp_json_encode( $value );
		}
		return $value;
	}

	/**
	 * Cheap heuristic — true if $value smells like a PHP-serialized payload.
	 *
	 * We don't need to be exhaustive here; anything that fails the next layer's
	 * unserialize() call is silently caught.
	 */
	private static function looks_serialized( string $value ): bool {
		return (bool) preg_match( '/^[abdiNOos]:/', $value );
	}

	/**
	 * Returns true if the serialized payload contains at least one object instance
	 * (token `O:N:"ClassName":...`). We scan the raw bytes rather than unserialize,
	 * because unserialize with allowed_classes=false would itself destroy class
	 * names — exactly what we are trying to avoid here.
	 */
	private static function serialized_contains_object( string $value ): bool {
		return (bool) preg_match( '/(?:^|[;{:])O:\d+:"/', $value );
	}

	/**
	 * Walk a (possibly nested) value and rewrite the domain in any string leaves.
	 *
	 * @param mixed $value
	 */
	private function replace( &$value, string $old, string $new, ?string $force_protocol ): void {
		$protocols = array( 'http' );
		if ( $this->options->include_https ) {
			$protocols[] = 'https';
		}

		$domains = array( $old => $new );
		if ( $this->options->include_www ) {
			$bare = preg_replace( '/^www\./i', '', $old );
			if ( strtolower( $bare ) !== strtolower( $old ) ) {
				$domains[ $bare ] = $new;
			}
			$with_www = 'www.' . $bare;
			if ( strtolower( $with_www ) !== strtolower( $old ) ) {
				$domains[ $with_www ] = $new;
			}
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( $value as &$inner ) {
				$this->replace( $inner, $old, $new, $force_protocol );
			}
			return;
		}

		if ( ! is_string( $value ) ) {
			return;
		}

		foreach ( $protocols as $protocol ) {
			$to_protocol = $force_protocol ?? $protocol;
			foreach ( $domains as $from => $to ) {
				$pattern = ',' . $protocol . '://' . preg_quote( $from, ',' ) . ',i';
				$value   = preg_replace( $pattern, $to_protocol . '://' . $to, $value );
			}
		}
	}

	private function safe_ident( string $ident ): string {
		// Defense in depth — table/column names should not contain backticks.
		return str_replace( '`', '', $ident );
	}

	private function progress( string $message ): void {
		if ( $this->progress !== null ) {
			( $this->progress )( $message );
		}
	}
}
