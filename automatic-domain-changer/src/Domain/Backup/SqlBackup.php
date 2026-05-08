<?php
declare( strict_types=1 );

namespace NuageLab\AutoDomainChanger\Domain\Backup;

defined( 'ABSPATH' ) || exit;

final class SqlBackup implements Backup {

	public function content_type(): string {
		return 'application/sql; charset=UTF-8';
	}

	public function filename( string $base ): string {
		return $base . '.sql';
	}

	public function stream( \wpdb $db ): void {
		$prefix      = $db->prefix;
		$prefix_like = $db->esc_like( $prefix ) . '%';
		$tables      = $db->get_col( $db->prepare( 'SHOW TABLES LIKE %s', $prefix_like ) );

		if ( ! is_array( $tables ) ) {
			return;
		}

		foreach ( $tables as $table ) {
			if ( strpos( $table, $prefix ) !== 0 ) {
				continue;
			}

			$ident  = str_replace( '`', '', $table );
			$create = $db->get_results( 'SHOW CREATE TABLE `' . $ident . '`' );
			foreach ( (array) $create as $row ) {
				$sql = $row->{'Create Table'} ?? '';
				echo $sql . ';' . PHP_EOL;
			}

			$offset = 0;
			do {
				$rows = $db->get_results(
					$db->prepare(
						'SELECT * FROM `' . $ident . '` LIMIT %d, %d',
						$offset,
						50
					)
				);
				if ( ! is_array( $rows ) ) {
					break;
				}

				foreach ( $rows as $row ) {
					echo $this->format_insert( $table, get_object_vars( $row ) ) . PHP_EOL;
				}
				$offset += count( $rows );
			} while ( count( $rows ) > 0 );

			echo PHP_EOL . PHP_EOL;
		}
	}

	/**
	 * @param array<string,scalar|null> $row
	 */
	private function format_insert( string $table, array $row ): string {
		global $wpdb;

		$columns = array();
		$values  = array();
		foreach ( $row as $column => $value ) {
			$columns[] = '`' . str_replace( '`', '', $column ) . '`';
			if ( $value === null ) {
				$values[] = 'NULL';
			} else {
				$values[] = "'" . esc_sql( (string) $value ) . "'";
			}
		}

		return sprintf(
			'INSERT INTO `%1$s` (%2$s) VALUES (%3$s);',
			str_replace( '`', '', $table ),
			implode( ', ', $columns ),
			implode( ', ', $values )
		);
	}
}
