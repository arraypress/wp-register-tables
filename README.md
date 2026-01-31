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
        'title'            => 'Orders',          // Auto-generated from plural if empty
        'add_new'          => 'Add New Order',   // Auto-generated from singular if empty
        'search'           => 'Search Orders',   // Auto-generated from plural if empty
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
    
    // Columns
    'columns'        => [],
    'sortable'       => [],
    'primary_column' => '',          // Auto-detected from columns
    'hidden_columns' => [],
    'column_widths'  => [],
    
    // Actions
    'row_actions'        => [],      // Array config or callable
    'bulk_actions'       => [],
    'auto_delete_action' => true,    // Auto-add delete if callback exists
    
    // Filtering
    'views'           => [],
    'filters'         => [],
    'status_styles'   => [],
    'base_query_args' => [],         // Always-applied query args
    
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

### Column Widths

```php
'column_widths' => [
    'name'   => '30%',
    'status' => '100px',
],
```

### Sortable Columns

```php
// Simple - column name as orderby
'sortable' => [ 'name', 'created_at', 'total' ],

// Advanced - custom orderby field
'sortable' => [
    'name'       => [ 'name', false ],        // [orderby, desc_first]
    'created_at' => [ 'date_created', true ],
],
```

## Auto-Formatting

Columns are automatically formatted based on naming patterns:

| Pattern | Formatting |
|---------|------------|
| `email`, `*_email` | Mailto link |
| `*_at`, `date*`, `created`, `updated`, `modified`, `registered`, `last_sync` | Human time diff |
| `*_total`, `*_price`, `*_amount`, `*_spent` | Currency |
| `status`, `*_status` | Status badge |
| `*_count`, `count*`, `*limit` | Number (∞ for -1) |
| `*_url`, `url*` | Link (images as thumbnails) |
| `is_*`, `test`, `active`, `enabled` | Boolean icon |

### Status Badge Colors

Default mappings:

- **Success** (green): `active`, `completed`, `paid`, `published`, `approved`, `confirmed`, `delivered`
- **Warning** (yellow): `pending`, `processing`, `draft`, `on-hold`, `partially_refunded`, `unpaid`, `expired`, `scheduled`
- **Error** (red): `failed`, `cancelled`, `canceled`, `refunded`, `rejected`, `declined`, `blocked`, `revoked`, `suspended`, `terminated`
- **Info** (blue): `new`, `updated`
- **Default** (gray): `inactive`, `disabled`, `paused`, `archived`, `hidden`, `trashed`

### Custom Status Styles

```php
'status_styles' => [
    'vip'     => 'success',
    'flagged' => 'error',
    'review'  => 'warning',
],
```

## Row Actions

### Array Configuration

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
        'class'   => 'archive-link',
    ],
    
    // Conditional
    'restore' => [
        'label'     => __( 'Restore', 'myplugin' ),
        'url'       => fn( $item ) => admin_url( '...' ),
        'condition' => fn( $item ) => $item->get_status() === 'archived',
    ],
    
    // Capability check
    'manage' => [
        'label'      => __( 'Manage', 'myplugin' ),
        'url'        => '#',
        'capability' => 'manage_options',
    ],
    
    // Full custom
    'custom' => [
        'callback' => function( $item ) {
            return sprintf( '<a href="%s">Custom</a>', esc_url( '...' ) );
        },
    ],
],
```

### Callable Shorthand

For full control, pass a callable instead of an array:

```php
'row_actions' => function( $item, $item_id ) {
    $actions = [];
    
    if ( $item->can_edit() ) {
        $actions['edit'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( get_edit_link( $item_id ) ),
            __( 'Edit', 'myplugin' )
        );
    }
    
    if ( current_user_can( 'delete_items' ) ) {
        $actions['delete'] = sprintf(
            '<a href="%s" class="delete-link">%s</a>',
            esc_url( get_delete_link( $item_id ) ),
            __( 'Delete', 'myplugin' )
        );
    }
    
    return $actions;
},
```

### Auto Delete Action

When you provide a `delete` callback, the library automatically adds a delete row action with:

- Proper nonce verification
- Confirmation dialog
- Capability check (if `capabilities.delete` is set)
- Success/error admin notices

To disable this behavior:

```php
'auto_delete_action' => false,
```

## Bulk Actions

```php
'bulk_actions' => [
    // Simple
    'delete' => __( 'Delete', 'myplugin' ),
    
    // With callback
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
    
    // With capability
    'export' => [
        'label'      => __( 'Export', 'myplugin' ),
        'capability' => 'export',
        'callback'   => fn( $ids ) => export_items( $ids ),
    ],
],
```

Callback return values:

- **Array**: Passed as query args for admin notices (e.g., `['deleted' => 5]`)
- **Integer**: Used as `updated` count
- **Boolean**: Converts to count of items if true, 0 if false

## Views (Status Tabs)

```php
'views' => [
    'active'    => __( 'Active', 'myplugin' ),
    'pending'   => __( 'Pending', 'myplugin' ),
    'completed' => __( 'Completed', 'myplugin' ),
],
```

Views automatically show counts from your `get_counts` callback. Views with zero counts are hidden. The "All" view is added automatically.

## Filters

```php
'filters' => [
    // Static options
    'country' => [
        'label'   => __( 'All Countries', 'myplugin' ),
        'options' => [
            'us' => __( 'United States', 'myplugin' ),
            'uk' => __( 'United Kingdom', 'myplugin' ),
        ],
    ],
    
    // Dynamic options
    'category' => [
        'label'            => __( 'All Categories', 'myplugin' ),
        'options_callback' => fn() => get_category_options(),
    ],
    
    // Custom filter application
    'date_range' => [
        'label'   => __( 'All Dates', 'myplugin' ),
        'options' => [
            'today'      => __( 'Today', 'myplugin' ),
            'this_week'  => __( 'This Week', 'myplugin' ),
            'this_month' => __( 'This Month', 'myplugin' ),
        ],
        'apply_callback' => function( &$args, $value ) {
            switch ( $value ) {
                case 'today':
                    $args['date_query'] = [ 'after' => 'today' ];
                    break;
                case 'this_week':
                    $args['date_query'] = [ 'after' => '1 week ago' ];
                    break;
            }
        },
    ],
],
```

## Base Query Args

Default query arguments that always apply:

```php
'base_query_args' => [
    'status__not_in' => [ 'trash', 'deleted' ],
    'type'           => 'subscription',
],
```

## Flyout Integration

Integrates with [wp-register-flyouts](https://github.com/arraypress/wp-register-flyouts):

```php
// Register flyout
register_flyout( 'orders_edit', [
    'title'       => 'Edit Order',
    'admin_pages' => [ 'toplevel_page_my-orders' ],
    'fields'      => [
        'customer' => [ 'type' => 'text', 'label' => 'Customer' ],
        'status'   => [
            'type'    => 'select',
            'label'   => 'Status',
            'options' => [ 'pending' => 'Pending', 'completed' => 'Completed' ],
        ],
    ],
    'load' => fn( $id ) => get_order( $id ),
    'save' => fn( $id, $data ) => update_order( $id, $data ),
] );

// Register table with flyout
register_admin_table( 'my_orders', [
    'flyout' => 'orders_edit',
    
    'row_actions' => [
        'edit' => [
            'label'  => __( 'Edit', 'myplugin' ),
            'flyout' => true,
        ],
    ],
    // ...
] );
```

### Separate Add Flyout

```php
register_admin_table( 'my_orders', [
    'flyout'     => 'orders_edit',
    'add_flyout' => 'orders_add',
    // ...
] );
```

### Custom Add Button

```php
register_admin_table( 'my_orders', [
    'add_button_callback' => function() {
        return sprintf(
            '<a href="%s" class="page-title-action">%s</a>',
            admin_url( 'admin.php?page=add-order' ),
            __( 'Add New Order', 'myplugin' )
        );
    },
    // ...
] );
```

## Callbacks

Multiple callback formats are supported:

```php
'callbacks' => [
    // Function name
    'get_items' => 'get_orders',
    
    // Namespaced function
    'get_items' => '\\MyPlugin\\get_orders',
    
    // Arrow function
    'get_items' => fn( $args ) => get_orders( $args ),
    
    // Static class method
    'get_items' => [ OrderModel::class, 'get_all' ],
    
    // Object method
    'get_items' => [ $repository, 'findAll' ],
    
    // Closure
    'get_items' => function( $args ) {
        return get_orders( $args );
    },
],
```

### get_items Callback

Receives query arguments, returns array of items:

```php
'get_items' => function( $args ) {
    // $args contains:
    // - number: Items per page
    // - offset: Pagination offset
    // - orderby: Sort column
    // - order: ASC or DESC
    // - search: Search term (if any)
    // - status: Current status filter (if any)
    // - [filter_key]: Active filter values
    // - [base_query_args]: Merged base args
    
    return my_query_function( $args );
},
```

### get_counts Callback

Returns array of status counts:

```php
'get_counts' => function() {
    return [
        'total'     => 150,
        'active'    => 100,
        'pending'   => 30,
        'completed' => 20,
    ];
},
```

### delete Callback

Receives item ID, returns boolean:

```php
'delete' => function( $id ) {
    return delete_item( $id );
},
```

## Help Tabs

```php
'help' => [
    'overview' => [
        'title'   => __( 'Overview', 'myplugin' ),
        'content' => '<p>This screen shows all orders.</p>',
    ],
    'filtering' => [
        'title'    => __( 'Filtering', 'myplugin' ),
        'callback' => fn() => '<p>Dynamic help content.</p>',
    ],
    'sidebar' => '<p><strong>Need help?</strong></p><p>Contact support.</p>',
],
```

## Admin Notices

The library automatically shows admin notices after actions:

- **Deleted**: Shows count of deleted items or error message
- **Updated**: Shows count of updated items
- **Error**: Shows error message from `error` query arg

### Custom Notices

```php
add_filter( 'arraypress_table_admin_notices', function( $notices, $id, $config ) {
    if ( isset( $_GET['exported'] ) ) {
        $notices[] = [
            'message'     => sprintf( '%d items exported.', absint( $_GET['exported'] ) ),
            'type'        => 'success',
            'dismissible' => true,
        ];
    }
    return $notices;
}, 10, 3 );
```

## Hooks

### Filters

```php
// Filter columns
add_filter( 'arraypress_table_columns', fn( $columns, $id, $config ) => $columns, 10, 3 );

// Filter hidden columns
add_filter( 'arraypress_table_hidden_columns', fn( $hidden, $id, $config ) => $hidden, 10, 3 );

// Filter sortable columns
add_filter( 'arraypress_table_sortable_columns', fn( $sortable, $id, $config ) => $sortable, 10, 3 );

// Filter query args before data fetch
add_filter( 'arraypress_table_query_args', fn( $args, $id, $config ) => $args, 10, 3 );
add_filter( 'arraypress_table_query_args_{table_id}', fn( $args, $config ) => $args, 10, 2 );

// Filter row actions
add_filter( 'arraypress_table_row_actions', fn( $actions, $item, $id ) => $actions, 10, 3 );
add_filter( 'arraypress_table_row_actions_{table_id}', fn( $actions, $item ) => $actions, 10, 2 );

// Filter bulk actions
add_filter( 'arraypress_table_bulk_actions', fn( $actions, $id ) => $actions, 10, 2 );

// Filter views
add_filter( 'arraypress_table_views', fn( $views, $id, $status ) => $views, 10, 3 );

// Filter admin notices
add_filter( 'arraypress_table_admin_notices', fn( $notices, $id, $config ) => $notices, 10, 3 );
add_filter( 'arraypress_table_admin_notices_{table_id}', fn( $notices, $config ) => $notices, 10, 2 );
```

### Actions

```php
// Before/after table renders
add_action( 'arraypress_before_render_table', fn( $id, $config ) => null, 10, 2 );
add_action( 'arraypress_before_render_table_{table_id}', fn( $config ) => null );
add_action( 'arraypress_after_render_table', fn( $id, $config ) => null, 10, 2 );
add_action( 'arraypress_after_render_table_{table_id}', fn( $config ) => null );

// Item deleted
add_action( 'arraypress_table_item_deleted', fn( $item_id, $result, $id, $config ) => null, 10, 4 );
add_action( 'arraypress_table_item_deleted_{table_id}', fn( $item_id, $result, $config ) => null, 10, 3 );

// Bulk action processed
add_action( 'arraypress_table_bulk_action', fn( $items, $action, $id ) => null, 10, 3 );
add_action( 'arraypress_table_bulk_action_{table_id}', fn( $items, $action ) => null, 10, 2 );
add_action( 'arraypress_table_bulk_action_{table_id}_{action}', fn( $items ) => null );

// Custom single action
add_action( 'arraypress_table_single_action_{table_id}', fn( $action, $item_id, $config ) => null, 10, 3 );
```

## Complete Example

```php
// Register flyout
register_flyout( 'customers_edit', [
    'title'       => 'Edit Customer',
    'admin_pages' => [ 'toplevel_page_my-customers' ],
    'fields'      => [
        'name'   => [ 'type' => 'text', 'label' => 'Name' ],
        'email'  => [ 'type' => 'email', 'label' => 'Email' ],
        'status' => [
            'type'    => 'select',
            'label'   => 'Status',
            'options' => [ 'active' => 'Active', 'inactive' => 'Inactive' ],
        ],
    ],
    'load' => fn( $id ) => get_customer( $id ),
    'save' => fn( $id, $data ) => update_customer( $id, $data ),
] );

// Register table
register_admin_table( 'my_customers', [
    'labels' => [
        'singular'  => __( 'customer', 'myplugin' ),
        'plural'    => __( 'customers', 'myplugin' ),
        'title'     => __( 'Customers', 'myplugin' ),
        'not_found' => __( 'No customers yet. Add your first customer!', 'myplugin' ),
    ],
    
    'callbacks' => [
        'get_items'  => '\\MyPlugin\\get_customers',
        'get_counts' => '\\MyPlugin\\get_customer_counts',
        'delete'     => '\\MyPlugin\\delete_customer',
    ],
    
    'page'       => 'my-customers',
    'flyout'     => 'customers_edit',
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
        'total_spent' => [
            'label' => __( 'Total Spent', 'myplugin' ),
            'align' => 'right',
        ],
        'order_count' => __( 'Orders', 'myplugin' ),
        'status'      => __( 'Status', 'myplugin' ),
        'created_at'  => __( 'Joined', 'myplugin' ),
    ],
    
    'sortable'      => [ 'name', 'total_spent', 'order_count', 'created_at' ],
    'column_widths' => [ 'status' => '100px' ],
    
    'row_actions' => [
        'edit' => [
            'label'  => __( 'Edit', 'myplugin' ),
            'flyout' => true,
        ],
        'orders' => [
            'label' => __( 'View Orders', 'myplugin' ),
            'url'   => fn( $item ) => admin_url( 'admin.php?page=my-orders&customer=' . $item->get_id() ),
        ],
    ],
    
    'bulk_actions' => [
        'delete' => [
            'label'      => __( 'Delete', 'myplugin' ),
            'capability' => 'delete_users',
            'callback'   => function( $ids ) {
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
    
    'capabilities' => [
        'view'   => 'list_users',
        'delete' => 'delete_users',
    ],
] );

// Register menu
add_action( 'admin_menu', function() {
    add_menu_page(
        __( 'Customers', 'myplugin' ),
        __( 'Customers', 'myplugin' ),
        'list_users',
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

## License

GPL-2.0-or-later

## Credits

Created by [David Sherlock](https://davidsherlock.com) at [ArrayPress](https://arraypress.com).