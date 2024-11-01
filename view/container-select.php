<?php
/**
 * Template for selection of media container
 *
 * @package WP Azure Offload
 */

?>
<div class="wrap-container"style="padding-top: 10px;" >
	<div class="container-save" style="<?php echo $this->get_setting( 'container' ) ? 'display:none' : 'display:block'; ?>">
		<div class="error inline container-error " style="display: none;">
			<p>
				<span class="notice-dismiss pos"> </span>
				<span class="title"></span>
				<span class="message"></span>
			</p>
		</div>
		
		<div class="container-manual">
		<h2><?php esc_html_e( 'What container would you like to use ?','azure-storage-and-cdn' ); ?></h2>
		<form method="post" class="manual-save-container-form">
			<div class="container-div">
			<input type="text"  name="container_name" class="azure-container-name" placeholder="<?php esc_html_e( 'Existing container name', 'azure-storage-and-cdn' ); ?>" value="<?php echo esc_attr( $this->get_container_name() ); ?>">
			<button id="container-manual-save" type="submit" class="container-action-save azure-button button button-primary" data-working="<?php esc_html_e( 'Saving...', 'azure-storage-and-cdn' ); ?>"><?php esc_html_e( 'SAVE CONTAINER', 'azure-storage-and-cdn' ); ?></button>
			</div>
			<p class="container-actions actions manual">
				<span><a href="#"  class="container-browse"><?php esc_html_e( 'BROWSE EXISTING CONTAINERS', 'azure-storage-and-cdn' ); ?></a></span>&nbsp;
				<span> |&nbsp; </span>
				<span><a href="#" class="container-create"><?php esc_html_e( 'CREATE NEW CONTAINER', 'azure-storage-and-cdn' ); ?></a></span>
			</p>			
		</form>
		</div>
		
		<div class="container-select" style="display:none">
			<h3><?php esc_html_e( 'Select container', 'azure-storage-and-cdn' ); ?></h3>
			<ul class="container-list" data-working="<?php esc_html_e( 'Loading...', 'azure-storage-and-cdn' ); ?>"></ul>
			<p class="container-actions ">
				<span> <a class="container-action-cancel"> <?php esc_html_e( 'CANCEL', 'azure-storage-and-cdn' ); ?> </a> </span>
				<span> &nbsp; | &nbsp; </span>
				<span><a href="#" class="container-refresh"><?php esc_html_e( 'REFRESH', 'azure-storage-and-cdn' ); ?></a></span>
			</p>		
		</div>
		
		<div class="container-action-create" style="display:none" >
		<h3> <?php esc_html_e( 'Create new Container', 'azure-storage-and-cdn' ); ?> </h3>	
		<p><?php esc_html_e( 'Container Naming Rules:', 'azure-storage-and-cdn' ); ?></p>
		<ol>
			<li>Container names must start with a letter or number, and can contain only letters, numbers, and the dash (-) character.</li>
			<li>Every dash (-) character must be immediately preceded and followed by a letter or number; consecutive dashes are not permitted in container names.</li>
			<li>All letters in a container name must be lowercase.</li>
			<li>Container names must be from 3 through 63 characters long</li>
		</ol>
		<form method="post" class="azure-create-container-form">
			<div class="container-div">
			<input type="text" class="azure-container-name" name="container_name" placeholder="<?php esc_html_e( 'Container name', 'azure-storage-and-cdn' ); ?>">
			<button id="bucket-manual-save" type="submit" class="azure-container-create azure-button button button-primary" ><?php esc_html_e( 'CREATE NEW CONTAINER', 'azure-storage-and-cdn' ); ?></button>
			</div>
			<span> <a class="container-action-cancel"><?php esc_html_e( 'CANCEL', 'azure-storage-and-cdn' ); ?> </a> </span>
		</form>
		</div>
		
	</div>
</div>
