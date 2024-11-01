<?php
/**
 * Media settings template
 *
 * @package WP Azure Offload
 */

	$container = $this->get_setting( 'container' );
	if ( $container ) {
?>
	<div class="media-container"><h2 style="display: inline;margin-right: 100px;"><?php esc_html_e( 'Container', 'azure-storage-and-cdn' ); ?>
		</h2><span class="azure-active-container"><b><?php echo esc_attr( $container ); ?></b></span>
		<span> <a class="button button-primary azure-button container-change"> CHANGE </a> </span>
	</div>
	<?php } ?>
	<div class="sub-header" style="width: 135px;"> 
		<h3 class="sub-header-title"> <?php esc_html_e( 'MEDIA SETTINGS', 'azure-storage-and-cdn' ); ?></h3>
	</div>
</div>	

<?php $this->display_view( 'container-select' ); ?>
		
<div class="azure-main-settings" style="display : <?php echo $container ? 'block' : 'none'; ?>">
		<form method="post" name="media-settings">
		<?php $action = filter_input( INPUT_POST, 'action' ); ?>
		<?php if ( ( isset( $action ) && null !== $action ) ) { ?>
				<div class="azure-updated updated">
					<p><strong>Settings saved.</strong> 
					<span class="notice-dismiss pos"> </span></p>
				</div>
			<?php } ?>
			<input type="hidden" name="action" value="save" />
			<input id="container" type="hidden" class="no-compare" name="container" value="<?php echo esc_attr( $container ); ?>" />
			<table class="form-table">
				<tr class="">
					<td colspan="2">
						<h3 class="h3-bg"><?php esc_html_e( 'Copy Existing Files to Azure Storage', 'azure-storage-and-cdn' ); ?> 
						<input type="button"  class=" button azure-button button-primary copy-All copy-all-media" value="Click Now">
						</h3>
						<div class="circle">
							<strong class="circle-status"></strong>
						</div>
					</td>
				</tr>
				<tr class="">
					<td>
						  <input type="checkbox" name="copy-to-azure"  <?php echo $this->get_copy_to_azure_setting() ? 'checked' : ''; ?>>
					</td>
					<td>						
						<h3><?php esc_html_e( 'COPY FILES TO AZURE STORAGE', 'azure-storage-and-cdn' ); ?></h3>
						<p>
							<?php esc_html_e( 'When a file is uploaded to the Media Library, copy it to Azure Storage.', 'azure-storage-and-cdn' ); ?>							
						</p>

					</td>
				</tr>
				<tr>
					<td>
						  <input type="checkbox" id="serve-from-cdn" name="serve-from-cdn"  <?php echo $this->get_serve_from_azure_setting() ? 'checked' : ''; ?>>
					</td>
					<td>						
						<h3><?php esc_html_e( 'SERVE FILES USING CDN URL', 'azure-storage-and-cdn' ); ?></h3>
						<p>
							<?php esc_attr_e( 'For media library that have been copied to Azure Storage rewrite URL so that they can be served from CDN instead of your server', 'azure-storage-and-cdn' ); ?>
						</p>
						<div class="cdn-endpoint" style="display:none">
						<h4> CDN ENDPOINT</h4><input type="text" name="cdn-endpoint" value="<?php echo esc_attr( $this->get_cdn_endpoint() ); ?>"> &nbsp; (For eg: "abc.azureedge.net")
						</div>
					</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<h3><?php esc_html_e( 'SET MAX AGE FOR CACHE-CONTROL', 'azure-storage-and-cdn' ); ?></h3>
						<input type="text" name="max-age" value="<?php echo $this->get_max_age() ? $this->get_max_age() : 2592000; ?>" /> &nbsp; 
						<?php echo $this->get_max_age() ? '' : '(By default it is 2592000 seconds .)'; ?>
					</td>
				</tr>
				<tr>
					<td>
						<input type="checkbox" name="find-and-replace" <?php echo $this->get_find_and_replace() ? 'checked' : '' ; ?>>
					</td>
					<td>
						<h3><?php esc_html_e( 'FIND & REPLACE', 'azure-storage-and-cdn' ); ?></h3>
						<p><?php esc_html_e( 'While copying already existed image/s to Azure Storage, Find its attached posts or pages( posts/pages in which images has been used) and rewrites CDN url.', 'azure-storage-and-cdn' ); ?></p>
					</td>
				</tr>
				<tr>
					<td>
						<input type="checkbox" name="remove-local-files" <?php echo $this->is_remove_local_files() ? 'checked' : ''; ?>>
					</td>
					<td>
						<h3><?php esc_html_e( 'REMOVE FILES FROM LOCAL SERVER', 'azure-storage-and-cdn' ); ?></h3>
						<p><?php esc_html_e( 'Remove files from the local server while uploading new media in media library.', 'azure-storage-and-cdn' ); ?></p>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button azure-button button-primary" ><?php esc_html_e( 'SAVE CHANGES', 'azure-storage-and-cdn' ); ?></button>
			</p>
		</form>
	</div>

