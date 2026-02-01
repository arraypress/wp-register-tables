# WordPress Admin Tables for BerlinDB

A declarative system for registering WordPress admin tables with BerlinDB integration. Eliminates hundreds of lines of boilerplate code for list tables.

## Installation

```bash
composer require arraypress/wp-register-tables
```

## Quick Start

```php
// Register your table
register_admin_table( 'my_orders', [
    'labels' => [
        'singular' => __( 'order', 'myplugin' ),
        'plural'   => __( 'orders', 'myplugin' ),
        'title'    => __( 'Orders', 'myplugin' ),
    ],
    
    'callbacks' => [
        'get_items'  => '\\MyPlugin\\get_orders',
        'get_counts' => '\\MyPlugin\\get_order_counts',
        'delete'     => '\\MyPlugin\\delete_order',
    ],
    
    'page'    => 'my-orders',
    'columns' => [
        'order_number' => __( 'Order', 'myplugin' ),
        'customer'     => __( 'Customer', 'myplugin' ),
        'total'        => __( 'Total', 'myplugin' ),
        'status'       => __( 'Status', 'myplugin' ),
        'created_at'   => __( 'Date', 'myplugin' ),
    ],
    
    'sortable' => [ 'order_number', 'total', 'created_at' ],
] );

// Register WordPress admin menu
add_action( 'admin_menu', function() {
    add_menu_page(
        'Orders',
        'Orders',
        'manage_options',
        'my-orders',
        create_page_callback( 'my_orders' )
    );
} );
```

## Configuration Reference

```php
register_admin_table( 'table_id', [
    // Labels
    'labels' => [
        'singular'         => 'order',
        'plural'           => 'orders',
        'title'            => 'Orders',
        'add_new'          => 'Add New Order',
        'search'           => 'Search Orders',
        'not_found'        => 'No orders yet.',
        'not_found_search' => 'No orders found for your search.',
    ],
    
    // Data callbacks
    'callbacks' => [
        'get_items'  => callable,  // Required: Returns array of items
        'get_counts' => callable,  // Required: Returns status counts
        'delete'     => callable,  // Optional: Enables auto delete action
        'update'     => callable,  // Optional: Update handler
    ],
    
    // Page & Display
    'page'           => 'my-orders',
    'per_page'       => 30,
    'searchable'     => true,
    'show_count'     => false,
    
    // Header Options (Modern EDD-style header)
    'logo'           => '',       // URL to logo image
    'header_title'   => '',       // Override title in header
    'show_title'     => true,     // Show/hide title
    
    // Columns
    'columns'        => [],
    'sortable'       => [],
    'primary_column' => '',
    'hidden_columns' => [],
    'column_widths'  => [],
    
    // Actions
    'row_actions'        => [],
    'bulk_actions'       => [],
    'auto_delete_action' => true,
    
    // Filtering
    'views'           => [],
    'filters'         => [],
    'status_styles'   => [],
    'base_query_args' => [],
    
    // Flyout Integration
    'flyout'              => '',
    'add_flyout'          => '',
    'add_button_callback' => null,
    
    // Permissions
    'capabilities' => [
        'view'   => '',
        'edit'   => '',
        'delete' => '',
        'bulk'   => '',
    ],
    
    // Help Tabs
    'help' => [],
] );
```

## Modern Header

The library includes a modern EDD-style header with logo support:

```php
register_admin_table( 'my_orders', [
    'logo'         => plugin_dir_url( __FILE__ ) . 'assets/logo.png',
    'header_title' => 'Order Management',
    'show_title'   => true,
    'show_count'   => true,
    // ...
] );
```

## Columns

### Simple Format

```php
'columns' => [
    'name'   => __( 'Name', 'myplugin' ),
    'email'  => __( 'Email', 'myplugin' ),
    'status' => __( 'Status', 'myplugin' ),
],
```

### Advanced Format

```php
'columns' => [
    'customer' => [
        'label'    => __( 'Customer', 'myplugin' ),
        'primary'  => true,
        'align'    => 'left',  // left, center, right
        'callback' => function( $item ) {
            $avatar = get_avatar( $item->get_email(), 32 );
            return $avatar . ' ' . esc_html( $item->get_display_name() );
        },
    ],
    'total' => [
        'label' => __( 'Total', 'myplugin' ),
        'align' => 'right',
    ],
],
```

### Auto-Formatting

Columns are automatically formatted based on naming patterns:

| Pattern | Formatting |
|---------|------------|
| `email`, `*_email` | Mailto link |
| `*_at`, `date*`, `created`, `updated`, `modified` | Human time diff |
| `*_total`, `*_price`, `*_amount`, `*_spent` | Currency |
| `status`, `*_status` | Status badge |
| `*_count`, `count*`, `*limit` | Number (∞ for -1) |
| `*_url`, `url*` | Link (images as thumbnails) |
| `is_*`, `test`, `active`, `enabled` | Boolean icon |

## Row Actions

### Standard Configuration

```php
'row_actions' => [
    // Flyout integration
    'edit' => [
        'label'  => __( 'Edit', 'myplugin' ),
        'flyout' => true,
    ],
    
    // URL-based
    'view' => [
        'label' => __( 'View', 'myplugin' ),
        'url'   => fn( $item ) => get_permalink( $item->get_id() ),
    ],
    
    // With confirmation
    'archive' => [
        'label'   => __( 'Archive', 'myplugin' ),
        'url'     => fn( $item ) => admin_url( '...' ),
        'confirm' => __( 'Archive this item?', 'myplugin' ),
    ],
],
```

### Auto-Handled Actions (NEW)

Define a `handler` callback and the action will be automatically processed:

```php
'row_actions' => [
    'toggle_status' => [
        'label'   => fn( $item ) => $item->get_status() === 'active' 
            ? __( 'Deactivate', 'myplugin' ) 
            : __( 'Activate', 'myplugin' ),
        'confirm' => fn( $item ) => $item->get_status() === 'active'
            ? __( 'Deactivate this customer?', 'myplugin' )
            : __( 'Activate this customer?', 'myplugin' ),
        'handler' => function( $item_id, $config ) {
            $customer = get_customer( $item_id );
            if ( $customer ) {
                $new_status = $customer->get_status() === 'active' ? 'inactive' : 'active';
                update_customer( $item_id, [ 'status' => $new_status ] );
            }
            return true;
        },
        // Optional: custom nonce action (default: {action_key}_{singular}_{item_id})
        'nonce_action' => 'toggle_customer_{id}',
    ],
],
```

When you define a `handler`, the library automatically:
- Generates the action link with proper nonce
- Processes the action on form submission
- Verifies the nonce
- Checks capabilities (if defined)
- Calls your handler
- Redirects to a clean URL with success/error message

No need to manually hook into `arraypress_table_single_action_{id}` anymore!

### Auto Delete Action

When you provide a `delete` callback, the library automatically adds a delete row action. To disable:

```php
'auto_delete_action' => false,
```

## Bulk Actions

```php
'bulk_actions' => [
    'delete' => [
        'label'    => __( 'Delete', 'myplugin' ),
        'callback' => function( $ids ) {
            $deleted = 0;
            foreach ( $ids as $id ) {
                if ( delete_item( $id ) ) {
                    $deleted++;
                }
            }
            return [ 'deleted' => $deleted ];
        },
    ],
    'activate' => [
        'label'      => __( 'Set Active', 'myplugin' ),
        'capability' => 'manage_options',
        'callback'   => function( $ids ) {
            $updated = 0;
            foreach ( $ids as $id ) {
                if ( update_item( $id, [ 'status' => 'active' ] ) ) {
                    $updated++;
                }
            }
            return [ 'updated' => $updated ];
        },
    ],
],
```

## Views (Status Tabs)

```php
'views' => [
    'active'    => __( 'Active', 'myplugin' ),
    'pending'   => __( 'Pending', 'myplugin' ),
    'completed' => __( 'Completed', 'myplugin' ),
],
```

## Filters

```php
'filters' => [
    'country' => [
        'label'            => __( 'All Countries', 'myplugin' ),
        'options_callback' => fn() => get_country_options(),
    ],
    'date_range' => [
        'label'   => __( 'All Dates', 'myplugin' ),
        'options' => [
            'today'      => __( 'Today', 'myplugin' ),
            'this_week'  => __( 'This Week', 'myplugin' ),
        ],
        'apply_callback' => function( &$args, $value ) {
            if ( $value === 'today' ) {
                $args['date_query'] = [ 'after' => 'today' ];
            }
        },
    ],
],
```

## Search Results Banner

When users search, a banner displays showing what they searched for with a "Clear search" link. This is automatic - no configuration needed.

## Clean URLs

The library now maintains clean URLs throughout:
- Filter submissions redirect to clean URLs (no `_wpnonce`, `_wp_http_referer`, `action` in URL)
- Single actions redirect to clean URLs after processing
- Bulk actions redirect to clean URLs after processing

This fixes issues where bulk actions would fail after filtering due to stale URL parameters.

## Flyout Integration

Integrates with [wp-register-flyouts](https://github.com/arraypress/wp-register-flyouts):

```php
register_admin_table( 'my_orders', [
    'flyout'     => 'orders_edit',
    'add_flyout' => 'orders_add',
    
    'row_actions' => [
        'edit' => [
            'label'  => __( 'Edit', 'myplugin' ),
            'flyout' => true,
        ],
    ],
] );
```

## Hooks

### Filters

```php
// Filter columns
add_filter( 'arraypress_table_columns', fn( $columns, $id, $config ) => $columns, 10, 3 );

// Filter query args
add_filter( 'arraypress_table_query_args', fn( $args, $id, $config ) => $args, 10, 3 );
add_filter( 'arraypress_table_query_args_{table_id}', fn( $args, $config ) => $args, 10, 2 );

// Filter row actions
add_filter( 'arraypress_table_row_actions', fn( $actions, $item, $id ) => $actions, 10, 3 );
add_filter( 'arraypress_table_row_actions_{table_id}', fn( $actions, $item ) => $actions, 10, 2 );

// Filter admin notices
add_filter( 'arraypress_table_admin_notices', fn( $notices, $id, $config ) => $notices, 10, 3 );
```

### Actions

```php
// Before/after table renders
add_action( 'arraypress_before_render_table', fn( $id, $config ) => null, 10, 2 );
add_action( 'arraypress_after_render_table', fn( $id, $config ) => null, 10, 2 );

// Item deleted
add_action( 'arraypress_table_item_deleted', fn( $item_id, $result, $id, $config ) => null, 10, 4 );

// Bulk action processed
add_action( 'arraypress_table_bulk_action', fn( $items, $action, $id ) => null, 10, 3 );

// Custom single action (only needed if NOT using handler in config)
add_action( 'arraypress_table_single_action_{table_id}', fn( $action, $item_id, $config ) => null, 10, 3 );
```

## Complete Example

```php
register_admin_table( 'my_customers', [
    'labels' => [
        'singular'  => __( 'customer', 'myplugin' ),
        'plural'    => __( 'customers', 'myplugin' ),
        'title'     => __( 'Customers', 'myplugin' ),
    ],
    
    'callbacks' => [
        'get_items'  => '\\MyPlugin\\get_customers',
        'get_counts' => '\\MyPlugin\\get_customer_counts',
        'delete'     => '\\MyPlugin\\delete_customer',
        'update'     => '\\MyPlugin\\update_customer',
    ],
    
    'page'       => 'my-customers',
    'logo'       => plugin_dir_url( __FILE__ ) . 'logo.png',
    'flyout'     => 'customers_edit',
    'add_flyout' => 'customers_add',
    'per_page'   => 25,
    'show_count' => true,
    
    'columns' => [
        'name' => [
            'label'    => __( 'Customer', 'myplugin' ),
            'primary'  => true,
            'callback' => function( $item ) {
                return get_avatar( $item->get_email(), 32 ) . 
                       '<strong>' . esc_html( $item->get_name() ) . '</strong>';
            },
        ],
        'email'       => __( 'Email', 'myplugin' ),
        'total_spent' => [ 'label' => __( 'Total Spent', 'myplugin' ), 'align' => 'right' ],
        'status'      => __( 'Status', 'myplugin' ),
        'date_created' => __( 'Joined', 'myplugin' ),
    ],
    
    'sortable'      => [ 'name', 'total_spent', 'date_created' ],
    'column_widths' => [ 'status' => '100px' ],
    
    'row_actions' => [
        'edit' => [
            'label'  => __( 'Edit', 'myplugin' ),
            'flyout' => true,
        ],
        'toggle_status' => [
            'label'   => fn( $item ) => $item->get_status() === 'active' ? 'Deactivate' : 'Activate',
            'confirm' => fn( $item ) => $item->get_status() === 'active' 
                ? 'Deactivate this customer?' 
                : 'Activate this customer?',
            'handler' => function( $item_id ) {
                $customer = get_customer( $item_id );
                $new_status = $customer->get_status() === 'active' ? 'inactive' : 'active';
                return update_customer( $item_id, [ 'status' => $new_status ] );
            },
        ],
    ],
    
    'bulk_actions' => [
        'delete' => [
            'label'    => __( 'Delete', 'myplugin' ),
            'callback' => function( $ids ) {
                $deleted = 0;
                foreach ( $ids as $id ) {
                    if ( delete_customer( $id ) ) {
                        $deleted++;
                    }
                }
                return [ 'deleted' => $deleted ];
            },
        ],
    ],
    
    'views' => [
        'active'   => __( 'Active', 'myplugin' ),
        'inactive' => __( 'Inactive', 'myplugin' ),
    ],
    
    'filters' => [
        'country' => [
            'label'            => __( 'All Countries', 'myplugin' ),
            'options_callback' => '\\MyPlugin\\get_country_options',
        ],
    ],
    
    'status_styles' => [
        'active'   => 'success',
        'inactive' => 'default',
    ],
] );

// Register menu
add_action( 'admin_menu', function() {
    add_menu_page(
        __( 'Customers', 'myplugin' ),
        __( 'Customers', 'myplugin' ),
        'manage_options',
        'my-customers',
        create_page_callback( 'my_customers' ),
        'dashicons-groups',
        30
    );
} );
```

## Requirements

- PHP 7.4+
- WordPress 5.0+
- BerlinDB-based custom tables
- arraypress/wp-composer-assets ^2.0

## License

GPL-2.0-or-later

## Credits

Created by [David Sherlock](https://davidsherlock.com) at [ArrayPress](https://arraypress.com).