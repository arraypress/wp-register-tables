# Register Tables

A WordPress list table for your own data — the screen, the menu, the search,
the sorting and the paging — from a description of the columns.

## What it does

`WP_List_Table` is a class you extend, and extending it means implementing
half a dozen methods before anything appears: the columns, the rows, the
sortable list, the bulk actions, the pagination, the search, plus the menu
page around it. It is a day's work for a table of orders, and the next table
is the same day again.

This takes the description instead. You supply a callback that returns rows
and one that counts them; everything from the menu to the "no items found"
message follows.

## Features

* Get a working list-table screen from a callback and a list of columns
* Search, sort and paginate without implementing any of the three
* Add status views along the top, with counts beside each
* Add filters, bulk actions and row actions to the same table
* Format a column as a price, a date, a status badge or a country, by naming the type
* Hang the screen off any menu — top level, a post type, Tools, Settings
* Add a header with tabs and actions, matching the newer core screens

## Installation

```bash
composer require arraypress/wp-register-tables
```

## Quick start

```php
register_admin_table( 'my_orders', [
	'page_title' => __( 'Orders', 'my-plugin' ),
	'menu_title' => __( 'Orders', 'my-plugin' ),
	'menu_slug'  => 'my-orders',
	'capability' => 'manage_options',
	'icon'       => 'dashicons-cart',

	'labels'     => [
		'singular' => __( 'order', 'my-plugin' ),
		'plural'   => __( 'orders', 'my-plugin' ),
	],

	'callbacks'  => [
		'get_items'  => '\MyPlugin\get_orders',
		'get_counts' => '\MyPlugin\get_order_counts',
	],

	'columns'    => [
		'id'      => [ 'label' => __( 'Order', 'my-plugin' ), 'sortable' => true ],
		'total'   => [ 'label' => __( 'Total', 'my-plugin' ), 'type' => 'price' ],
		'status'  => [ 'label' => __( 'Status', 'my-plugin' ), 'type' => 'status' ],
		'created' => [ 'label' => __( 'Date', 'my-plugin' ), 'type' => 'date' ],
	],
] );
```

`get_items` is handed the query — search, sort, page, filters, already
resolved — and returns rows. That is the only part that knows about your data.

## Hooks

Every hook is suffixed with the table's own id, so a filter written for one
plugin's orders table never reaches another's. `{table_id}` below is whatever
was passed to `register_admin_table()`.

### Filters

| Hook | Filters |
| --- | --- |
| `arraypress_table_columns_{table_id}` | The columns, after the config is read |
| `arraypress_table_hidden_columns_{table_id}` | Which columns start hidden |
| `arraypress_table_sortable_columns_{table_id}` | Which columns can be sorted |
| `arraypress_table_views_{table_id}` | The status links above the table |
| `arraypress_table_row_actions_{table_id}` | The actions under a row |
| `arraypress_table_bulk_actions_{table_id}` | The bulk actions dropdown |
| `arraypress_table_query_args_{table_id}` | The arguments before items are fetched |
| `arraypress_table_admin_notices_{table_id}` | The notices shown above the table |
| `arraypress_table_quick_edit_fields_{table_id}` | The fields in the Quick Edit row |
| `arraypress_table_bulk_edit_fields_{table_id}` | The fields in the Bulk Edit row |

### Actions

| Hook | Fires |
| --- | --- |
| `arraypress_before_render_table_{table_id}` | Before the table is drawn |
| `arraypress_after_render_table_{table_id}` | After the table is drawn |
| `arraypress_table_single_action_{table_id}` | A row action with no handler of its own |
| `arraypress_table_bulk_action_{table_id}` | A bulk action was applied |
| `arraypress_table_item_deleted_{table_id}` | An item was deleted |
| `arraypress_table_quick_edit_{table_id}` | One row was quick edited |
| `arraypress_table_bulk_edit_{table_id}` | An edit was applied to a selection |
| `arraypress_table_inline_edit_{table_id}` | Inside either inline editor, to print extra markup |

A bulk action also fires a second, narrower action naming the action itself,
which is usually the one worth hooking:

```php
add_action( 'arraypress_table_bulk_action_{table_id}_{action}', function ( array $ids ) {
	foreach ( $ids as $id ) {
		my_plugin_refund_order( (int) $id );
	}
} );
```

Both the table id and the action name are substituted, and the broader hook in
the table above still fires alongside it.

## Quick Edit and Bulk Edit

Core's two inline editors, on a table that is not a post type. Declaring
either one is enough -- a table with only `quick_edit` gets the same fields in
its bulk row, and the other way round.

```php
register_admin_table( 'my_products', [
	// ...
	'quick_edit'   => [
		'status' => [
			'label'   => __( 'Status', 'my-plugin' ),
			'options' => [
				'active' => __( 'Active', 'my-plugin' ),
				'draft'  => __( 'Draft', 'my-plugin' ),
			],
		],
	],
	'bulk_actions' => [ 'edit' => __( 'Bulk edit', 'my-plugin' ) ],
	'callbacks'    => [
		'get_item' => 'my_plugin_get_product',
	],
] );
```

Neither editor writes anything. They validate what was submitted -- a select
only accepts a value it actually offered -- and then fire an action, because
only you know whether a status change also has to reach somewhere else:

```php
add_action( 'arraypress_table_quick_edit_{table_id}', function ( int $id, array $values ) {
	my_plugin_update_product( $id, $values );
}, 10, 2 );

add_action( 'arraypress_table_bulk_edit_{table_id}', function ( array $ids, array $values ) {
	foreach ( $ids as $id ) {
		my_plugin_update_product( (int) $id, $values );
	}
}, 10, 2 );
```

Two things are worth knowing. `'edit'` in `bulk_actions` is what reveals the
bulk row -- without it there is a bulk editor nothing opens. And Quick Edit
redraws the saved row from `callbacks.get_item`; leave that out and the save
happens but the row does not change, which reads exactly like a save that
failed.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
