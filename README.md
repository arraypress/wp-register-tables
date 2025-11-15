# WordPress Admin Tables for BerlinDB

A declarative system for registering WordPress admin tables with BerlinDB integration. Eliminates hundreds of lines of boilerplate code for list tables.

## Installation
```bash
composer require arraypress/wp-register-tables
```

## Basic Usage
```php
// Register your table
register_admin_table( 'my_orders', [
    'labels' => [
        'singular' => __( 'order', 'myplugin' ),
        'plural'   => __( 'orders', 'myplugin' ),
        'title'    => __( 'Orders', 'myplugin' ),
        'add_new'  => __( 'Add New Order', 'myplugin' )
    ],
    
    'callbacks' => [
        'get_items'  => '\\MyPlugin\\get_orders',
        'get_counts' => '\\MyPlugin\\get_order_counts',
        'delete'     => '\\MyPlugin\\delete_order',
        'update'     => '\\MyPlugin\\update_order'
    ],
    
    'page'    => 'my-orders',
    'columns' => [
        'order_number' => __( 'Order', 'myplugin' ),
        'customer'     => __( 'Customer', 'myplugin' ),
        'total'        => __( 'Total', 'myplugin' ),
        'status'       => __( 'Status', 'myplugin' ),
        'created_at'   => __( 'Date', 'myplugin' )
    ],
    
    'sortable' => ['order_number', 'total', 'created_at']
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

## Features

- **Auto-formatting** - Dates, currency, statuses, emails automatically formatted based on column names
- **Flexible callbacks** - Use function names, closures, or class methods
- **Row actions** - Edit, delete, view with flyout integration support
- **Bulk actions** - Process multiple items with custom callbacks
- **Status views** - Automatic status filters from your counts callback
- **Custom filters** - Add dropdown filters with callbacks
- **Clean labels** - Explicit singular/plural labels, no magic pluralization

## Column Auto-Formatting

The library automatically formats columns based on naming patterns:

- `*_at`, `created`, `updated`, `registered` → Human-readable dates
- `*_total`, `*_price`, `*_amount`, `*_spent` → Currency formatting
- `email`, `*_email` → Mailto links
- `status`, `*_status` → Status badges
- `*_count` → Formatted numbers

## Advanced Configuration
```php
register_admin_table( 'my_customers', [
    'labels' => [
        'singular'    => __( 'customer', 'myplugin' ),
        'plural'      => __( 'customers', 'myplugin' ),
        'title'       => __( 'Customers', 'myplugin' ),
        'add_new'     => __( 'Add Customer', 'myplugin' ),
        'not_found'   => __( 'No customers yet.', 'myplugin' ),
        'search'      => __( 'Search Customers', 'myplugin' )
    ],
    
    'callbacks' => [
        // String function name
        'get_items'  => '\\MyPlugin\\get_customers',
        
        // Anonymous function
        'get_counts' => fn() => get_customer_counts(),
        
        // Class method
        'delete' => [ CustomerRepository::class, 'delete' ],
        
        // Inline callback
        'update' => function( $id, $data ) {
            return update_customer( $id, $data );
        }
    ],
    
    'flyout' => 'customer-flyout',
    
    // Custom column rendering
    'columns' => [
        'name' => [
            'label'    => __( 'Name', 'myplugin' ),
            'primary'  => true,  // Primary column with row actions
            'callback' => function( $item ) {
                $avatar = get_avatar( $item->get_email(), 32 );
                return $avatar . ' ' . esc_html( $item->get_display_name() );
            }
        ],
        'email'  => __( 'Email', 'myplugin' ),     // Auto-formatted
        'status' => __( 'Status', 'myplugin' ),    // Auto-badge
        'total_spent' => __( 'Total', 'myplugin' ) // Auto-currency
    ],
    
    // Row actions
    'row_actions' => [
        'edit' => [
            'label'  => __( 'Edit', 'myplugin' ),
            'flyout' => true  // Uses table's flyout
        ],
        'delete' => [
            'label'     => __( 'Delete', 'myplugin' ),
            'condition' => fn( $item ) => $item->get_order_count() === 0,
            'confirm'   => __( 'Delete this customer?', 'myplugin' )
        ]
    ],
    
    // Bulk actions with callbacks
    'bulk_actions' => [
        'delete' => [
            'label'    => __( 'Delete', 'myplugin' ),
            'callback' => function( $ids ) {
                foreach ( $ids as $id ) {
                    delete_customer( $id );
                }
                return ['deleted' => count( $ids )];
            }
        ],
        'export' => [
            'label'    => __( 'Export', 'myplugin' ),
            'callback' => fn( $ids ) => export_customers( $ids )
        ]
    ],
    
    // Status views
    'views' => [
        'all'      => __( 'All', 'myplugin' ),
        'active'   => __( 'Active', 'myplugin' ),
        'inactive' => __( 'Inactive', 'myplugin' )
    ],
    
    // Custom filters
    'filters' => [
        'country' => [
            'label'   => __( 'All Countries', 'myplugin' ),
            'options' => fn() => get_country_options()
        ]
    ]
] );
```

## Callback Types

The library supports multiple callback formats:
```php
'callbacks' => [
    // Function name (string)
    'get_items' => 'get_orders',
    
    // Namespaced function
    'get_items' => '\\MyPlugin\\get_orders',
    
    // Anonymous function
    'get_items' => fn( $args ) => get_orders( $args ),
    
    // Class static method
    'get_items' => [ OrderModel::class, 'get_all' ],
    
    // Object method
    'get_items' => [ $repository, 'findAll' ],
    
    // Closure with logic
    'get_items' => function( $args ) {
        // Custom logic here
        return get_orders( $args );
    }
]
```

## Requirements

- PHP 7.4+
- WordPress 5.0+
- BerlinDB-based custom tables

## License

GPL-2.0-or-later

## Credits

Created by [David Sherlock](https://davidsherlock.com) at [ArrayPress](https://arraypress.com).