<?php
/**
 * Plugin configuration template
 *
 * @package WP Azure Offload
 */

?>
	<div class="sub-header" style="width: 175px;"> 
		<h3 class="sub-header-title"> <?php esc_html_e( 'ACCESS CREDENTIALS', 'azure-storage-services' ); ?></h3>   
	</div>
</div>
<div class="azure-settings">
	<div id="dialog-confirm" class="modal">
		<div class="modal-content">
			<div class="confirm-header">
				<div class="notice-dismiss pos"></div>
				<h4 id="info" style="margin:0"> Remove keys ? </h4>
			</div>
			<div class="confirm-content">
				<p>The access keys will be permanently deleted and cannot be recovered. Are you sure?</p>
				<hr/>
				<input type="button" style="margin-left:10px;" class="right button confirm-remove" value="YES" / >
				<input type="button" style="margin-left:10px;" class="right button grey reject" value="NO" / >
			</div>
		</div>
	</div>

	<form name="config-setting" method="post" id="access_credentials" >
		<?php $action = filter_input( INPUT_POST, 'action' ); ?>
		<?php
		if ( ( isset( $action ) && 'save' === $action ) ) {
			header( 'Location: ' . network_admin_url( 'admin.php?page=azure-storage-services' ) );
		}
		?>

		<input type="hidden" name="action" value="save" />
		<?php wp_nonce_field( 'azure-save-settings' ); ?>

		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'CHOOSE DEFAULT END POINT PROTOCOL', 'azure-storage-services' ); ?></th>
				<td> : </td>
				<td>
					<input type="radio" name="access_end_prorocol" value="http" <?php echo ($this->get_access_end_prorocol() === 'http') ? 'checked' : ''; ?> size="50" autocomplete="off" /> http &nbsp;&nbsp;
					<input type="radio" name="access_end_prorocol" value="https" <?php echo ( ! $this->get_access_end_prorocol() || $this->get_access_end_prorocol() === 'https') ? 'checked' : ''; ?> size="50" autocomplete="off" /> https
				</td>
			</tr>
			<tr valign="top">
				<th  scope="row"><?php esc_html_e( 'ACCOUNT NAME:', 'azure-storage-services' ); ?></th>
				<td> : </td>
				<td>
					<input type="text"  id= "access_account_name" name="access_account_name" value="<?php echo esc_attr( $this->get_access_account_name() ); ?>" size="50" autocomplete="off" />
				</td>
			</tr>
			<tr valign="top">
				<th  scope="row"><?php esc_html_e( 'ACCOUNT KEY:', 'azure-storage-services' ); ?></th>
				<td> : </td>
				<td>
					<input type="text" id="access_account_key" name="access_account_key" value="<?php echo esc_attr( $this->get_access_account_key() ); ?>" size="50" autocomplete="off" />
				</td>
			</tr>
			<tr valign="top">
				<td></td>
				<td> </td>
				<td>
					<button type="submit" class="button azure-button button-primary"><?php esc_html_e( 'SAVE CHANGES', 'azure-storage-services' ); ?></button>
					<?php if ( $this->get_access_account_name() || $this->get_access_account_key() ) : ?>
						&nbsp;
						<input type="button" name="remove-keys"  class="button grey azure-button remove-keys" value="REMOVE KEYS" / >
					<?php endif; ?>
				</th>
			</tr>
		</table>

	</form>
</div>
