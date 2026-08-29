/**
 * Quick Edit and Bulk Edit, as core does them.
 *
 * Modelled on wp-admin/js/inline-edit-post.js, which is post-specific: it
 * reads `#inline_{id} .post_title`, submits `post[]`, and knows about
 * taxonomies and page parents. This is the same two editors against a table
 * whose rows are not posts.
 *
 * They do not work the same way, and the difference is core's, not an
 * inconsistency introduced here.
 *
 * Bulk Edit never saves from script. It reveals the row and lists what was
 * ticked; pressing Update submits the surrounding list form, selection
 * included, and the page reloads. That is why the bulk half has no ajax.
 *
 * Quick Edit does save from script. It reveals the row over a single row,
 * fills the inputs from the hidden `#inline_{id}` block, posts the fields,
 * and swaps in the row markup the server sends back. The response is the
 * rendered row rather than a success flag on purpose -- a status column, a
 * formatted amount and a row action set all change with the save, and a
 * script rebuilding them would be a second renderer drifting from the first.
 */
( function ( $, wp ) {
	'use strict';

	var settings = window.arrayPressInlineEdit || {};

	var InlineEdit = {

		/**
		 * The row being quick edited, or an empty string.
		 */
		what: '',

		/**
		 * Bind the bulk action selects, the row actions and the row buttons.
		 */
		init: function () {
			var self = this,
				$bulk = $( '#bulk-edit' ),
				$quick = $( '#inline-edit' );

			if ( ! $bulk.length && ! $quick.length ) {
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

			/*
			 * Bound on each row's own elements, never delegated from the
			 * container holding them.
			 *
			 * edit() clones #inline-edit out into the list table, and
			 * clone(true) copies handlers bound directly to the row -- it
			 * cannot copy a delegated handler, which stays behind on the
			 * ancestor the clone just left. Delegating from #inlineedit left
			 * both Update and Cancel dead in the open editor, on a row that
			 * looked entirely normal.
			 *
			 * The bulk row is moved rather than cloned, so its handlers travel
			 * with it either way; it is bound the same way for consistency.
			 */
			$( '.cancel', $bulk ).on( 'click', function () {
				return self.revert();
			} );

			$bulk.on( 'keyup', function ( event ) {
				if ( 27 === event.which ) {
					return self.revert();
				}
			} );

			// Removing one from the list unticks its row, so the form posts
			// what the list shows. Core does the same, and it is the only
			// thing keeping the two in step.
			$bulk.on( 'click', '.ntdelbutton', function () {
				var id = $( this ).attr( 'id' ).substr( 1 );

				$( 'table.widefat input[value="' + id + '"]' ).prop( 'checked', false );
				$( this ).parent().remove();

				if ( ! $( '#bulk-titles-list li' ).length ) {
					self.revert();
				}
			} );

			if ( ! $quick.length ) {
				return;
			}

			// This one is delegated on purpose: the row is replaced after
			// every save, and a handler bound to the old row would stop
			// firing. #the-list is never moved.
			$( '#the-list' ).on( 'click', '.editinline', function ( event ) {
				event.preventDefault();

				$( this ).attr( 'aria-expanded', 'true' );
				self.edit( this );
			} );

			$( '.save', $quick ).on( 'click', function () {
				return self.save();
			} );

			$( '.cancel', $quick ).on( 'click', function () {
				return self.revert();
			} );

			$( 'td', $quick ).on( 'keydown', function ( event ) {
				if ( 13 === event.which && ! $( event.target ).hasClass( 'cancel' ) ) {
					event.preventDefault();

					return self.save();
				}
			} );

			$quick.on( 'keyup', function ( event ) {
				if ( 27 === event.which ) {
					return self.revert();
				}
			} );
		},

		/**
		 * Open the quick editor over a row.
		 *
		 * @param {string|Element} id The row, or its id.
		 */
		edit: function ( id ) {
			var self = this,
				$editRow,
				$inlineData;

			this.revert();

			if ( 'object' === typeof id ) {
				id = this.getId( id );
			}

			this.what = id;

			$editRow = $( '#inline-edit' ).clone( true );

			$( 'td', $editRow ).attr(
				'colspan',
				$( 'th:visible, td:visible', '.widefat:first thead' ).length
			);

			// An empty row above it so the zebra striping survives, which is
			// what core does and why the row below is not suddenly the wrong
			// colour.
			$( '#item-' + id )
				.addClass( 'inline-edit-row' )
				.hide()
				.after( $editRow )
				.after( '<tr class="hidden"></tr>' );

			$editRow.attr( 'id', 'edit-' + id ).addClass( 'inline-editor' ).show();

			$inlineData = $( '#inline_' + id );

			$editRow.find( 'input[name="item_id"]' ).val( id );
			$editRow.find( 'input[name="row_title"]' ).val( this.readField( $inlineData, 'row_title' ) );

			// Every field takes its current value from the hidden block the
			// checkbox column printed. A field with no block entry is left at
			// its default rather than being blanked, so a control added by
			// the markup hook is not cleared by opening the editor.
			$editRow.find( '[name]' ).each( function () {
				var name = $( this ).attr( 'name' ),
					value;

				if ( 'item_id' === name || 'table_id' === name || 'row_title' === name ) {
					return;
				}

				value = self.readField( $inlineData, name );

				if ( null === value ) {
					return;
				}

				if ( 'checkbox' === this.type ) {
					$( this ).prop( 'checked', '1' === value || 'yes' === value );
					return;
				}

				$( this ).val( value );
			} );

			$editRow.find( ':input:visible' ).first().trigger( 'focus' );

			return false;
		},

		/**
		 * A value out of the hidden data block, or null when it is absent.
		 *
		 * @param {Object} $data The block.
		 * @param {string} name  The field.
		 *
		 * @return {?string} The value.
		 */
		readField: function ( $data, name ) {
			var $field = $data.find( '.' + name );

			return $field.length ? $field.text() : null;
		},

		/**
		 * Post the open quick editor and swap in the row it returns.
		 */
		save: function () {
			var self = this,
				id = this.what,
				$row = $( '#edit-' + id ),
				params;

			if ( ! id ) {
				return false;
			}

			$( 'table.widefat .spinner' ).addClass( 'is-active' );

			params = $( ':input', $row ).serialize() +
				'&action=arraypress_table_quick_edit' +
				'&item_id=' + encodeURIComponent( id );

			$.post( settings.ajaxUrl || window.ajaxurl, params )
				.done( function ( html ) {
					var $error = $row.find( '.error' );

					$( 'table.widefat .spinner' ).removeClass( 'is-active' );

					// A save that returns nothing did not save. Saying so is
					// the whole point -- the alternative is a row that
					// silently snaps back to what it was.
					if ( ! html || '-1' === $.trim( html ) ) {
						$error
							.html( settings.error || 'The change could not be saved.' )
							.parent()
							.removeClass( 'hidden' )
							.show();

						return;
					}

					// tr.hidden, not .hidden: the data block each row carries
					// is a div.hidden, and a bare class selector here removed
					// every one of them -- so the next Quick Edit opened with
					// empty fields, on rows that had never been touched.
					$( '#item-' + id ).siblings( 'tr.hidden' ).addBack().remove();
					$row.before( html ).remove();

					$( '#item-' + id )
						.hide()
						.fadeIn( 400, function () {
							$( this )
								.find( '.editinline' )
								.attr( 'aria-expanded', 'false' )
								.trigger( 'focus' );

							if ( wp && wp.a11y && wp.a11y.speak ) {
								wp.a11y.speak( settings.saved || 'Changes saved.' );
							}
						} );

					self.what = '';
				} )
				.fail( function () {
					$( 'table.widefat .spinner' ).removeClass( 'is-active' );

					$row.find( '.error' )
						.html( settings.error || 'The change could not be saved.' )
						.parent()
						.removeClass( 'hidden' )
						.show();
				} );

			return false;
		},

		/**
		 * The row id out of an element inside it.
		 *
		 * @param {Element} el Anything inside the row.
		 *
		 * @return {string} The id.
		 */
		getId: function ( el ) {
			var id = $( el ).closest( 'tr' ).attr( 'id' ) || '';

			return id.replace( 'item-', '' );
		},

		/**
		 * Move the bulk row into the table and list what was selected.
		 */
		setBulk: function () {
			var items = '',
				any = false;

			this.revert();

			$( '#bulk-edit td' ).attr(
				'colspan',
				$( 'th:visible, td:visible', '.widefat:first thead' ).length
			);

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
					title = $( '#inline_' + id + ' .row_title' ).text() || '';

				items +=
					'<li class="ntdelitem"><button type="button" id="_' +
					id +
					'" class="button-link ntdelbutton"><span class="screen-reader-text">' +
					( settings.removeFromBulk || 'Remove from Bulk Edit' ) +
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
		 * Close whichever editor is open and put the table back.
		 */
		revert: function () {
			var id = $( 'table.widefat tr.inline-editor' ).attr( 'id' );

			if ( ! id ) {
				return false;
			}

			$( 'table.widefat .spinner' ).removeClass( 'is-active' );

			if ( 'bulk-edit' === id ) {
				$( '#bulk-edit' ).removeClass( 'inline-editor' ).hide().siblings( 'tr.hidden' ).remove();
				$( '#bulk-titles' ).empty();
				$( '#inlineedit' ).append( $( '#bulk-edit' ) );

				return false;
			}

			$( '#' + id ).siblings( 'tr.hidden' ).addBack().remove();

			id = id.replace( 'edit-', '' );
			this.what = '';

			$( '#item-' + id )
				.show()
				.find( '.editinline' )
				.attr( 'aria-expanded', 'false' )
				.trigger( 'focus' );

			return false;
		}
	};

	$( document ).ready( function () {
		InlineEdit.init();
	} );

	window.arrayPressInlineEditor = InlineEdit;
} )( jQuery, window.wp );
