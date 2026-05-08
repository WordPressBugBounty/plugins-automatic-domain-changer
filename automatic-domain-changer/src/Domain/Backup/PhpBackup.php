<?php
declare( strict_types=1 );

namespace NuageLab\AutoDomainChanger\Domain\Backup;

defined( 'ABSPATH' ) || exit;

final class PhpBackup implements Backup {

	public function content_type(): string {
		return 'application/x-php; charset=UTF-8';
	}

	public function filename( string $base ): string {
		return $base . '.php';
	}

	public function stream( \wpdb $db ): void {
		echo '<?php' . PHP_EOL;
		echo '// Place this file at the root of your WordPress installation and execute it via CLI or browser.' . PHP_EOL;
		echo 'require __DIR__ . "/wp-config.php";' . PHP_EOL;
		echo 'global $wpdb;' . PHP_EOL . PHP_EOL;

		$prefix      = $db->prefix;
		$prefix_like = $db->esc_like( $prefix ) . '%';
		$tables      = $db->get_col( $db->prepare( 'SHOW TABLES LIKE %s', $prefix_like ) );

		if ( ! is_array( $tables ) ) {
			return;
		}

		$drop_template = 'if (isset($_GET["drop"])) { $wpdb->query("DROP TABLE IF EXISTS `%T%`;"); if ($wpdb->last_error) die("Query failed"); }' . PHP_EOL;

		foreach ( $tables as $table ) {
			if ( strpos( $table, $prefix ) !== 0 ) {
				continue;
			}

			$ident  = str_replace( '`', '', $table );
			$create = $db->get_results( 'SHOW CREATE TABLE `' . $ident . '`' );
			foreach ( (array) $create as $row ) {
				$sql = $row->{'Create Table'} ?? '';
				echo str_replace( '%T%', $ident, $drop_template );
				echo '$wpdb->query(' . var_export( $sql, true ) . '); if ($wpdb->last_error) die("Query failed");' . PHP_EOL;
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
					$insert = $this->format_insert( $table, get_object_vars( $row ) );
					echo '$wpdb->query(' . var_export( $insert, true ) . '); if ($wpdb->last_error) die("Query failed");' . PHP_EOL;
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
