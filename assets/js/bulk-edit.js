/**
 * Bulk edit, as core does it.
 *
 * Modelled on wp-admin/js/inline-edit-post.js, which is post-specific: it
 * reads `#inline_{id} .post_title`, submits `post[]`, and carries a lot of
 * Quick Edit that does not apply here. This is the bulk half of it, against
 * a table whose rows are not posts.
 *
 * The script only reveals the row and fills in the list of what was
 * selected. Pressing Update submits the list form -- selection included --
 * and the page reloads, which is core's flow and the reason there is no
 * saving code here at all.
 */
( function ( $ ) {
	'use strict';

	var BulkEdit = {

		/**
		 * Bind the bulk action selects and the row's own buttons.
		 */
		init: function () {
			var self = this;

			if ( ! $( '#bulk-edit' ).length ) {
				return;
			}

			// Both, because core has a set of bulk controls above the table
			// and another below it, and either can be the one used.
			$( '#doaction, #doaction2' ).on( 'click', function ( event ) {
				var action = $( this ).attr( 'id' ).substr( 2 );

				if ( 'edit' !== $( 'select[name="' + action + '"]' ).val() ) {
					return;
				}

				event.preventDefault();
				self.setBulk();
			} );

			$( '#bulk-edit' ).on( 'click', '.cancel', function () {
				self.revert();
			} );

			// Removing one from the list unticks its row, so the form posts
			// what the list shows. Core does the same, and it is the only
			// thing keeping the two in step.
			$( '#bulk-edit' ).on( 'click', '.ntdelbutton', function () {
				var id = $( this ).attr( 'id' ).substr( 1 );

				$( 'table.widefat input[value="' + id + '"]' ).prop( 'checked', false );
				$( this ).parent().remove();

				if ( ! $( '#bulk-titles-list li' ).length ) {
					self.revert();
				}
			} );
		},

		/**
		 * Move the row into the table and list what was selected.
		 */
		setBulk: function () {
			var items = '',
				any = false;

			this.revert();

			$( '#bulk-edit td' ).attr(
				'colspan',
				$( 'th:visible, td:visible', '.widefat:first thead' ).length
			);

			// An empty row above it so the zebra striping survives, which is
			// what core does and why the row below it is not suddenly the
			// wrong colour.
			$( 'table.widefat tbody' )
				.prepend( $( '#bulk-edit' ) )
				.prepend( '<tr class="hidden"></tr>' );

			$( '#bulk-edit' ).addClass( 'inline-editor' ).show();

			$( 'tbody .check-column input[type="checkbox"]' ).each( function () {
				if ( ! $( this ).prop( 'checked' ) ) {
					return;
				}

				any = true;

				var id = $( this ).val(),
					title = $( '#inline_' + id + ' .row-title' ).text() || '';

				items +=
					'<li class="ntdelitem"><button type="button" id="_' +
					id +
					'" class="button-link ntdelbutton"><span class="screen-reader-text">' +
					'Remove from Bulk Edit' +
					'</span></button><span class="ntdeltitle" aria-hidden="true">' +
					$( '<div>' ).text( title ).html() +
					'</span></li>';
			} );

			// Nothing ticked is nothing to edit.
			if ( ! any ) {
				return this.revert();
			}

			$( '#bulk-titles' ).html(
				'<ul id="bulk-titles-list" role="list">' + items + '</ul>'
			);

			$( '#bulk-edit' ).find( 'select' ).first().trigger( 'focus' );
		},

		/**
		 * Put the row back where it came from.
		 */
		revert: function () {
			var $row = $( 'table.widefat #bulk-edit' );

			if ( ! $row.length ) {
				return false;
			}

			$row.removeClass( 'inline-editor' ).hide();
			$row.siblings( '.hidden' ).remove();
			$( '#bulk-titles' ).empty();

			$( '#inlineedit' ).append( $row );

			return false;
		}
	};

	$( document ).ready( function () {
		BulkEdit.init();
	} );
} )( jQuery );
