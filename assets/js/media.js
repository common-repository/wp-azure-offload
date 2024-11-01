/**
 * Js file for media library actions
 *
 * @package     WP Azure Offload
 */

(function( $ ) {
	$( document ).ready( function() {
		$( ' .delete-from-azure' ).click(function() {
			$( this ).prop( 'disabled', true );
			var post_id = $( this ).data( 'post' );
			$( this ).append( '<div class="loader"></div>' );
			deleteFromAzure( this, post_id );
		});

		$( "<div style='float:left;width: 98%;display: none;' class='status'></div>" ).insertAfter( $( ' .top' ) );

		$( '<div id="myModal" class="modal"> <div class="sk-chasing-dots">' +
			'<div class="sk-child sk-dot1"></div>' +
			'<div class="sk-child sk-dot2"></div>' +
		'</div></div>' ).insertBefore( $( ' .wp-list-table' ) );

		$( '.copy-to-azure' ).click(function(){
			$( this ).prop( "disabled", true );
			var post_id = $( this ).data( 'post' );
			$( this ).append( "<div class='loader'></div>" );
			copyToAzure( this, post_id );
		});

		// For bulk actions.
		$form = $( '.wrap form' );
		is_media_single = ! $( '.wp-list-table' ).length;
		if ( ! is_media_single ) {
			$( '.tablenav select[name^=action]' ).each(function() {
				for (label in AZURESettings.labels) {
					$( 'option:last', this ).before( $( '<option>' ).attr( 'value', label ).text( decodeURIComponent( AZURESettings.labels[label].replace( /\+/g, '%20' ) ) ) );
				}
			});
		}

		$( '#post' ).submit( form_submit );
		$( '.tablenav .button.action' ).click( form_submit );

	});

	function copyToAzure(element,id){
		data = {
			action: 'copy-to-azure-from-library',
			post_id: id
		};

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'JSON',
			data: data,
			error: function(jqXHR,textStatus,errorThrown){
				$( '.loader' ).hide();
				var error = "<span>" + errorThrown + "</span>";
				$( '.status' ).html( error );
				$( '.status' ).css( {"color":"red","border-left-color": "red"} );
				$( '.notice-dismiss' ).click(function(){
					$( '.status' ).hide();
				});
				$( element ).prop( 'disabled', false );
			},
			success: function(data,textStatus,jqXHR){
				$( '.loader' ).hide();
				if ( 'undefined' !== typeof data[ 'success' ] ) {
					if ( data.error != null ) {
						var status = "<span>" + data.error + "</span>";
						$( '.status' ).css( {"border-left": "4px solid red"} );
						$( element ).prop( 'disabled', false );
					} else {
						var status = "<span>" + data.message + "</span>";
						$( '.status' ).css( {"border-left": "4px solid #46b450"} );
						$( element ).hide();
						$( element ).prop( 'disabled', false );
						if ( $( element ).next().length ) {
							$( element ).next().show();
						} else {
							$( element ).prev().show();
						}
					}
					$( '.status' ).html( "<div class='notice-dismiss pos' style='margin-left: 10px;'></div>" + status );
					$( '.status' ).css( {display: 'block'} );
					$( '.notice-dismiss' ).click(function(){
						$( '.status' ).hide();
					});
				}
			},
		});
	}

	function deleteFromAzure(element,id){

		data = {
			action: 'delete-from-azure',
			post_id: id
		};

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'JSON',
			data: data,
			error: function(jqXHR, textStatus, errorThrown){
				$( '.loader' ).hide();
				var error = "<span>" + errorThrown + "</span>";
				$( '.status' ).html( error );
				$( '.status' ).css( {"color":"red","border-left-color": "red"} );
				$( '.notice-dismiss' ).click(function(){
					$( '.status' ).hide();
				});
				$( element ).prop( 'disabled', false );
			},
			success: function(data, textStatus,jqXHR){
				$( '.loader' ).hide();
				if ( 'undefined' !== typeof data[ 'success' ] ) {

					if ( data.error != null ) {
						var status = "<span>" + data.error + "</span>";
						$( '.status' ).css( {"border-left": "4px solid red"} );
						$( element ).prop( 'disabled', false );
					} else {
						var status = "<span>" + data.message + "</span>";
						$( '.status' ).css( {"border-left": "4px solid #46b450"} );
						$( element ).hide();
						$( element ).prop( 'disabled', false );
						if ( $( element ).next().length ) {
							$( element ).next().show();
						} else {
							$( element ).prev().show();
						}
					}
					$( '.status' ).html( "<div class='notice-dismiss pos' style='margin-left: 10px;'></div>" + status );
					$( '.status' ).css( {display: 'block'} );
					$( '.notice-dismiss' ).click(function(){
						$( '.status' ).hide();
					});
				}
			}
		});
	}

	function form_submit(){
		var type = $( this ).siblings( 'select' ).length ? $( this ).siblings( 'select' ).val() : 'copytoazure';

		if ( ! is_media_single && (type != 'copytoazure' && type != 'deletefromazure') ) {
			return;
		}

		$( "#myModal" ).show();
		var post_id = new Array();
		$( '#the-list input:checked' ).each(function(){
			post_id.push( this.value );
		});

		var posts = post_id.join( ";" );
		data = {
			action: 'bulk-action',
			method: type,
			post_ids: posts
		};

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'JSON',
			data: data,
			error: function(jqXHR,textStatus, errorThrown){
				$( "#myModal" ).hide();
				window.location.reload();
			},
			success: function(data,textStatus, jqXHR){
				$( "#myModal" ).hide();
				window.location.reload();
			}
		});

		return false;
	}

})( jQuery);
