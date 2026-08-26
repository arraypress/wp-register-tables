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

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
