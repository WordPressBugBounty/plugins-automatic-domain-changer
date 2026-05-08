<?php
/**
 * Template: Tools → Change Domain
 *
 * @var array{
 *     error_terms:bool,
 *     force_protocol:string,
 *     current_host:string,
 *     old_domain:string,
 *     options:\NuageLab\AutoDomainChanger\Domain\Options,
 *     change_action:string,
 *     backup_action:string,
 * } $view
 */

defined( 'ABSPATH' ) || exit;

$protocols = array(
	'https' => __( 'https://', 'auto-domain-change' ),
	'http'  => __( 'http://', 'auto-domain-change' ),
	''      => __( '(same)', 'auto-domain-change' ),
);
?>
<div class="wrap adc-wrap">
	<h1><?php esc_html_e( 'Change Domain', 'auto-domain-change' ); ?></h1>

	<form method="post" class="adc-form">
		<?php wp_nonce_field( $view['change_action'], 'nonce' ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $view['change_action'] ); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="old-domain"><?php esc_html_e( 'Change domain from', 'auto-domain-change' ); ?></label>
					</th>
					<td>
						<span class="adc-protocol-prefix">http://</span>
						<input type="text" name="old-domain" id="old-domain"
							class="regular-text adc-domain-input"
							value="<?php echo esc_attr( $view['old_domain'] ); ?>" required />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="new-domain"><?php esc_html_e( 'Change domain to', 'auto-domain-change' ); ?></label>
					</th>
					<td>
						<select name="force-protocol" id="force-protocol" class="adc-select">
							<?php foreach ( $protocols as $protocol => $label ) : ?>
								<option value="<?php echo esc_attr( $protocol ); ?>" <?php selected( $view['force_protocol'], $protocol ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="text" name="new-domain" id="new-domain"
							class="regular-text adc-domain-input"
							value="<?php echo esc_attr( $view['current_host'] ); ?>" required />
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'auto-domain-change' ); ?></th>
					<td>
						<fieldset>
							<label for="https-domain">
								<input type="checkbox" name="https-domain" id="https-domain" value="1"
									<?php checked( $view['options']->include_https ); ?> />
								<?php
								echo wp_kses(
									__( 'Also change secure <code>https</code> links', 'auto-domain-change' ),
									array( 'code' => array() )
								);
								?>
							</label>
							<br>
							<label for="www-domain">
								<input type="checkbox" name="www-domain" id="www-domain" value="1"
									<?php checked( $view['options']->include_www ); ?> />
								<?php
								echo wp_kses(
									__( 'Change both <code>www.old-domain.com</code> and <code>old-domain.com</code> links', 'auto-domain-change' ),
									array( 'code' => array() )
								);
								?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Confirm', 'auto-domain-change' ); ?></th>
					<td>
						<label for="accept-terms" class="<?php echo $view['error_terms'] ? 'adc-error' : ''; ?>">
							<input type="checkbox" name="accept-terms" id="accept-terms" value="1" required />
							<?php esc_html_e( 'I have backed up my database, checked the backup\'s integrity, know how to restore it, and accept responsibility for any data loss or corruption.', 'auto-domain-change' ); ?>
						</label>

						<p class="adc-backup-actions">
							<button type="button" class="button adc-backup-button" data-type="sql">
								<?php esc_html_e( 'Backup database as SQL', 'auto-domain-change' ); ?>
							</button>
							<button type="button" class="button adc-backup-button" data-type="php">
								<?php esc_html_e( 'Backup database as PHP', 'auto-domain-change' ); ?>
							</button>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Change domain', 'auto-domain-change' ); ?>
			</button>
		</p>
	</form>

	<form method="post" id="adc-backup-db" class="adc-hidden">
		<?php wp_nonce_field( $view['backup_action'], 'nonce' ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $view['backup_action'] ); ?>" />
		<input type="hidden" name="type" value="sql" />
	</form>
</div>
