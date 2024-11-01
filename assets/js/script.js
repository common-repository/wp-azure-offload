/**
 * Js file for containers ane media settings
 *
 * @package     WP Azure Offload
 */

(function( $ ) {

	$( document ).ready( function() {

		$(function() {
			$( "form[name='media-settings']" ).validate({
				rules: {
					'cdn-endpoint': "required",
				},
				messages: {
					'cdn-endpoint': "Please enter your CDN Endpoint",
				},
				submitHandler: function(form) {
					form.submit();
				}
			});
		});

		$( '.copy-All' ).click(function(){
			startMediaCron();
		});

		$( '.remove-keys' ).click( function() {
			$( "#dialog-confirm" ).show();
		} );

		$( '.confirm-remove' ).click( function() {
			$( "#dialog-confirm" ).hide();
			$( 'input[name=access_end_prorocol],input[name=access_account_name],input[name=access_account_key]' ).val( '' );
			var form  = $( "form[name='config-setting']" );
			form.submit();
		} );

		$( '.reject' ).click( function() {
			$( "#dialog-confirm" ).hide();
		} );

		$( '.notice-dismiss' ).click(function(){
			$( '.azure-updated' ).hide();
			$( '.container-error' ).hide();
			$( "#dialog-confirm" ).hide();
		});

		if ( $( '#serve-from-cdn' ).is( ':checked' ) ) {
			$( '.cdn-endpoint' ).show();
		}

		$( '#serve-from-cdn' ).click(function(){
			if ( $( this ).is( ':checked' ) ) {
				$( '.cdn-endpoint' ).show();
			} else {
				$( '.cdn-endpoint' ).hide();
			}
		});

		$( '.container-create' ).click(function(){
			$( '.container-manual' ).hide();
			$( '.container-error' ).hide();
			$( '.container-action-create' ).css( "display", "block" );
		});

		$( '.container-change' ).click(function(){
			$( '.container-save' ).show();
			$( '.azure-main-settings' ).hide();
		});

		$( 'body' ).on( 'click', '.container-browse', function( e ) {
			e.preventDefault();
			$( '.container-manual' ).hide();
			$( '.container-error' ).hide();
			$( '.container-select' ).css( "display", "block" );
			loadContainerList();
		});

		$( '.container-action-cancel' ).click(function(){
			$( '.container-manual' ).show();
			$( '.container-error' ).hide();
			$( '.container-select' ).hide();
			$( '.container-action-create' ).css( "display", "none" );
		});

		$( '.container-refresh' ).click(function(){
			$( '.container-error' ).hide();
		});

		$( 'body' ).on( 'click', '.container-action-save', function( e ) {
			e.preventDefault();
			saveContainer();
		} );

		$( 'body' ).on( 'click', '.azure-container-create', function( e ) {
			e.preventDefault();
			createContainer();
		} );

		$( 'body' ).on( 'click', '.container-refresh', function( e ) {
			e.preventDefault();
			loadContainerList();
		} );

		checkMediaCron();
	} );

	// save container to the database.
	function saveContainer() {
		var $containerForm = $( '.manual-save-container-form' );
		var $containerInput = $containerForm.find( '.azure-container-name' );
		var $containerButton = $containerForm.find( 'button[type=submit]' );
		var containerName = $containerInput.val();
		var originalContainerText = $containerButton.first().text();

		$( '.container-error' ).hide();
		$containerButton.text( $containerButton.attr( 'data-working' ) );
		$containerButton.prop( 'disabled', true );

		var data = {
			action: 'manual-save-container',
			container_name: containerName,
			_wpnonce: 'container',
		};

		var that = this;

		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			dataType: 'JSON',
			data: data,
			error: function( jqXHR, textStatus, errorThrown ) {
				$containerButton.text( originalContainerText );
				displayError( azure.strings.save_container_error, data[ 'error' ], 'container-save' );
			},
			success: function( data, textStatus, jqXHR ) {
				$containerButton.text( originalContainerText );
				$containerButton.prop( 'disabled', false );
				if ( 'undefined' !== typeof data[ 'success' ] ) {
					// moves to the main settings.
					location.reload();
					$( '.container-save' ).hide();
					$( '.azure-main-settings' ).show();
					$( '.azure-active-container' ).html( data[ 'container' ] );
				} else {
					displayError( azure.strings.save_container_error, data[ 'error' ], 'container-save' );
				}
			}
		} );
	}

	// create new container.
	function createContainer() {
		var $containerForm = $( '.azure-create-container-form' );
		var $containerInput = $containerForm.find( '.azure-container-name' );
		var $containerButton = $containerForm.find( 'button[type=submit]' );
		var containerName = $containerInput.val();
		var originalContainerText = $containerButton.first().text();

		$( '.container-error' ).hide();
		$containerButton.text( 'creating...' );
		$containerButton.prop( 'disabled', true );

		var data = {
			action: 'azure-container-create',
			container_name: containerName,
			_wpnonce: 'container',
		};

		var that = this;

		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			dataType: 'JSON',
			data: data,
			error: function( jqXHR, textStatus, errorThrown ) {
				$containerButton.text( originalContainerText );
				displayError( azure.strings.create_container_error, data[ 'error' ], 'container-save' );
			},
			success: function( data, textStatus, jqXHR ) {
				$containerButton.text( originalContainerText );
				$containerButton.prop( 'disabled', false );
				if ( 'undefined' !== typeof data[ 'success' ] ) {
					// moves to the main settings.
					location.reload();
					$( '.container-save' ).hide();
					$( '.azure-main-settings' ).show();
					$( '.azure-active-container' ).html( data[ 'container' ] );
				} else {
					displayError( azure.strings.create_container_error, data[ 'error' ], 'container-save' );
				}
			}
		} );
	}

	// loadContainerList.
	function loadContainerList(){
		var $container_list = $( '.container-list' );
		$container_list.html( "<li class='loading'>" + $container_list.attr( 'data-working' ) + "</li>" );

		var data = {
			action:'get-container-list',
		};

		$.ajax({
			url: ajaxurl,
			type: 'GET',
			dataType: 'JSON',
			data: data,
			error: function(jqXHR, textStatus, errorThrown){
				$container_list.html( '' );
				displayError( azure.strings.get_container_error, data['error'], 'container-save' );
			},
			success: function(data, textStatus,jqXHR){
				$container_list.html( '' );
				if ( 'undefined' !== typeof data[ 'success' ] ) {
					$( data[ 'containers' ] ).each( function( idx, container ) {
						$container_list.append( '<li><a id = "' + container + '" href="#" data-bucket="' + container + '"><span class="container"><span class="dashicons dashicons-portfolio"></span> ' + container + '</span><span class="spinner"></span></span></a></li>' );
					} );
					clickToSelect();

				} else {
					displayError( azure.strings.get_container_error, data[ 'error' ], 'container-save' );
				}
			}

		});
	}

	function clickToSelect(){
		$( '.container-list li a' ).click(function(){
			var containerName = this.id;
			$( '#' + containerName ).find( 'span.spinner' ).css( "visibility", "visible" );
			data = {
				action: 'manual-save-container',
				container_name: containerName,
				_wpnonce: 'container',
			}
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'JSON',
				data: data,
				error: function( jqXHR, textStatus, errorThrown ) {
					displayError( azure.strings.save_container_error, data[ 'error' ], 'container-save' );
				},
				success: function( data, textStatus, jqXHR ) {
					if ( 'undefined' !== typeof data[ 'success' ] ) {
						$( '#' + containerName ).find( 'span.spinner' ).css( "visibility","hidden" );
						// moves to the main settings.
						location.reload();
						$( '.container-save' ).hide();
						$( '.azure-main-settings' ).show();
						$( '.azure-active-container' ).html( data[ 'container' ] );
					} else {
						displayError( azure.strings.save_container_error, data[ 'error' ], 'container-save' );
					}
				}
			});
		});
	}

	function displayError( title, error, context ) {
		var $activeView = $( '.wrap-container' ).children( ':visible' );
		var $containerError = $activeView.find( '.container-error' );
		context = ( 'undefined' === typeof context ) ? null : context;

		if ( context && ! $activeView.hasClass( context ) ) {
			return;
		}

		$containerError.find( 'span.title' ).html( title + ' &mdash;' );
		$containerError.find( 'span.message' ).html( error );
		$containerError.show();
	}

	// if container existed then moves to the main setting page.
	function containerExist(){
		var data = {
			action:'container-exist',
		};

		$.ajax({
			url: ajaxurl,
			type: 'GET',
			dataType: 'JSON',
			data: data,
			error: function(jqXHR, textStatus, errorThrown){
				displayError( azure.strings.get_container_error, data['error'], 'container-save' );
			},
			success: function(data, textStatus,jqXHR){
				if ( 'undefined' !== typeof data[ 'success' ] ) {
					$( '.container-save' ).hide();
					$( '.azure-main-settings' ).show();
				} else {
					$( '.container-save' ).show();
					$( '.azure-main-settings' ).hide();
				}
			}
		});
	}

	/************************ Progress bar ***************************/

	function startMediaCron(){
		var data = {
			action: 'media-cron',
		};

		$.ajax({
			url: ajaxurl,
			type: 'GET',
			dataType: 'JSON',
			data: data,
			error: function(jqXHR, textStatus, errorThrown){},
			success: function(data, textStatus,jqXHR){
				if ( false === data.enable_plugin ){
						alert( data.notice );
				} else {
					if ( data.data > 0 ) {
						$( ".copy-all-media" ).attr( "disabled", true );
						$( ".circle").show();
						var progressBarOptions = {
							startAngle: -1.55,
							size: 40,
							animation: false,
							value: 0,
							fill: {
								color: '#006799'
							}
						}
						$('.circle').circleProgress(progressBarOptions);
						processStart();
					} else {
						alert( "Each file has already been uploaded!" );
					}
				}
			},
		});
	}

	function checkMediaCron(){
		var data = {
			action: 'check-media-cron',
		};
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'JSON',
			data: data,
			error: function( jqXHR, textStatus, errorThrown){},
			success: function( data, textStatus, jqXHR){
				if ( data.data.media_cron_running ) {
					$( ".copy-all-media" ).attr( "disabled", true );
					$( ".circle" ).show();
					var total_count = data.data.total_count;
					uploadedCount( total_count );
				}
			},
		});
	}

	function processStart(){
		var data = {
			action: 'process-start',
		};

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'JSON',
			data: data,
			error: function(jqXHR, textStatus, errorThrown){},
			success: function( data, textStatus, jqXHR) {
				var total_count = data.data;
				uploadedCount( total_count );
			},
		});
	}

	function uploadedCount(total_count){
		var initial_count = total_count;
		var progressBar = setInterval(function(){
				returnCount( function (d){
					var remaining_count = initial_count - d;
					var bar_width = Math.ceil( ( ( initial_count - d ) / initial_count ) * 100 );
					$( '.circle-status' ).html( ( bar_width ) + '%' );
					
					var progressBarOptions = {
						startAngle: -1.55,
						value: ( bar_width / 100 ),
						animation: false,
						size: 40,
						fill: {
							color: '#008000'
						}
					}
					$('.circle').circleProgress(progressBarOptions);

					if ( d === 0 ) {
						removCronDetails( progressBar );
					}

				});
		},1000);
	}

	function returnCount(callback){
		var data = {
			action: 'process-start',
		};

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'JSON',
			data: data,
			error: function( jqXHR, textStatus, errorThrown){},
			success: function( data, textStatus, jqXHR){
				callback( data.data );
			},
		});
	}

	function removCronDetails( progressBar ) {
		clearInterval( progressBar );
		var data = {
			action: 'remove-cron-details',
		};

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'JSON',
			data: data,
			error: function( jqXHR, textStatus, errorThrown){},
			success: function( data, textStatus, jqXHR){
				$( ".copy-all-media" ).attr( "disabled", false );
				$( ".circle").hide();
			},
		});
	}

})( jQuery );

