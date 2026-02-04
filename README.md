# WordPress Admin Tables for BerlinDB

A declarative system for registering WordPress admin tables with BerlinDB integration. Eliminates hundreds of lines of
boilerplate code for list tables.

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
        get_table_renderer( 'my_orders' )
    );
} );
```

## Configuration Reference

```php
register_admin_table( 'table_id', [
    // Labels
    'labels' => [
        'singular'         => 'order',          // Used in nonces, notices, no-items text
        'plural'           => 'orders',          // Used in bulk nonces, search, views
        'title'            => 'Orders',          // Page/header title (auto-generated from plural)
        'add_new'          => 'Add New Order',   // Add button text (auto-generated from singular)
        'search'           => 'Search Orders',   // Search box label (auto-generated from plural)
        'not_found'        => 'No orders yet.',  // Empty state message
        'not_found_search' => 'No orders found for your search.',
    ],
    
    // Data callbacks
    'callbacks' => [
        'get_items'       => callable,  // Required: Returns array of items
        'get_counts'      => callable,  // Required: Returns status counts array
        'delete'          => callable,  // Optional: Enables auto delete row action
        'update'          => callable,  // Optional: Update handler
        'search_callback' => callable,  // Optional: Custom search term resolution
    ],
    
    // Page & Display
    'page'       => 'my-orders',   // Admin page slug (required, must match menu registration)
    'per_page'   => 30,            // Default items per page
    'searchable' => true,          // Show search box
    'show_count' => false,         // Show total count in header title
    
    // Header
    'logo'         => '',          // URL to logo image for EDD-style header
    'header_title' => '',          // Override title in header (falls back to labels title)
    
    // Columns
    'columns'        => [],        // Column definitions (see Columns section)
    'sortable'       => [],        // Sortable column keys
    'primary_column' => '',        // Column for row actions (auto-detected)
    'hidden_columns' => [],        // Columns hidden by default in Screen Options
    
    // Actions
    'row_actions' => [],           // Row action definitions (see Row Actions section)
    'bulk_actions' => [],          // Bulk action definitions (see Bulk Actions section)
    
    // Filtering
    'views'          => [],        // Status view tabs (see Views section)
    'filters'        => [],        // Dropdown filters (see Filters section)
    'status_styles'  => [],        // Status => badge type mappings for auto-formatting
    
    // Flyout Integration
    'flyouts' => [
        'edit' => '',              // Flyout ID for edit actions
        'view' => '',              // Flyout ID for view actions
    ],
    'add_button' => '',            // Add button: flyout ID, URL string, or callable
    
    // Permissions
    'capability'  => '',           // Single capability applied to all actions
    'capabilities' => [            // Per-action overrides (takes precedence)
        'view'   => '',
        'edit'   => '',
        'delete' => '',
        'bulk'   => '',
    ],
    
    // Help Tabs
    'help' => [],                  // Help tab definitions (see Help Tabs section)
    
    // Styling
    'body_class' => '',            // Additional CSS class added to admin body
] );
```

## Modern Header

The library includes a modern EDD-style header with logo support. The header renders outside the WordPress `.wrap` div
for proper full-width styling.

```php
register_admin_table( 'my_orders', [
    'logo'         => plugin_dir_url( __FILE__ ) . 'assets/logo.png',
    'header_title' => 'Order Management',
    'show_count'   => true,
    // ...
] );
```

When `show_count` is enabled, the total item count displays next to the title.

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
        'primary'  => true,            // Row actions appear on this column
        'align'    => 'left',          // left, center, right
        'width'    => '200px',         // CSS width
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

### Structured Format

For more complex column layouts, use the structured format with `before`, `title`, `after`, and `link`:

```php
'columns' => [
    'customer' => [
        'label'   => __( 'Customer', 'myplugin' ),
        'primary' => true,
        'before'  => function( $item ) {
            return get_avatar( $item->get_email(), 32 );
        },
        'title'   => function( $item ) {
            return $item->get_display_name();
        },
        'after'   => function( $item ) {
            return '<br><small>' . esc_html( $item->get_email() ) . '</small>';
        },
        'link'    => 'edit_flyout',  // or 'view_flyout', callable, or URL string
    ],
],
```

The `link` option controls how the title is linked:

| Value           | Behavior                                               |
|-----------------|--------------------------------------------------------|
| `'edit_flyout'` | Opens the edit flyout (requires `flyouts.edit` config) |
| `'view_flyout'` | Opens the view flyout (requires `flyouts.view` config) |
| `callable`      | Called with `$item`, should return a URL               |
| `string`        | Used directly as URL                                   |

### Auto-Formatting

Columns are automatically formatted based on naming patterns. The library detects column types by matching against exact
names, prefixes, suffixes, and substrings:

| Type         | Matching Patterns                                                           | Formatting                                   |
|--------------|-----------------------------------------------------------------------------|----------------------------------------------|
| `email`      | Contains `email`                                                            | Mailto link                                  |
| `phone`      | `phone`, `mobile`, `cell`, `fax`, contains `phone`                          | Clickable tel: link                          |
| `country`    | `country`, `country_code`, suffix `_country`                                | Flag + country name                          |
| `date`       | `created`, `updated`, `modified`, contains `_at` or `date`                  | Human time diff                              |
| `price`      | Contains `price`, `total`, `amount`, `_spent`, `cost`, `revenue`, `balance` | Formatted currency                           |
| `rate`       | `rate`, `discount`, `commission`, suffix `_rate`                            | Rate format                                  |
| `percentage` | Contains `percent`, suffix `_pct`                                           | Percentage format                            |
| `status`     | `status`, contains `_status`                                                | Status badge                                 |
| `count`      | `count`, `limit`, `quantity`, contains `_count`                             | Number (∞ for -1)                            |
| `items`      | `items`, `order_items`, suffix `_items`                                     | Summary with "and X others"                  |
| `user`       | `user`, `author`, `customer`, suffix `_user`                                | Avatar + linked name                         |
| `taxonomy`   | `terms`, `tags`, `categories`, suffix `_terms`                              | Linked term badges                           |
| `image`      | `image`, `avatar`, `thumbnail`, contains `_image`                           | Thumbnail (supports attachment IDs and URLs) |
| `color`      | `color`, `colour`, suffix `_color`                                          | Color swatch + code                          |
| `url`        | `url`, `website`, `link`                                                    | Linked hostname                              |
| `boolean`    | `active`, `enabled`, `verified`, prefix `is_`, `has_`, `can_`               | Yes/No icon                                  |
| `code`       | `code`, `sku`, `uuid`, `hash`, suffix `_code`, `_id`, `_key`                | Monospace code                               |
| `duration`   | `elapsed`, `runtime`, contains `duration`, suffix `_seconds`                | Human duration                               |
| `file_size`  | `size`, `bytes`, contains `filesize`, suffix `_size`                        | Human file size                              |

### Column Auto-Format Configuration

Some auto-formatted types accept additional configuration via the column config array:

```php
'columns' => [
    'status' => [
        'label'  => __( 'Status', 'myplugin' ),
        'styles' => [                         // Custom status => badge type mappings
            'active'   => 'success',
            'inactive' => 'default',
            'pending'  => 'warning',
        ],
    ],
    'avatar' => [
        'label' => __( 'Avatar', 'myplugin' ),
        'size'  => [ 64, 64 ],               // Image size as [width, height] or size name
    ],
    'author' => [
        'label'  => __( 'Author', 'myplugin' ),
        'avatar' => 24,                       // Avatar size in pixels for user type
    ],
    'line_items' => [
        'label'    => __( 'Products', 'myplugin' ),
        'singular' => 'product',              // Singular label for items type
        'plural'   => 'products',             // Plural label for items type
    ],
    'tags' => [
        'label'    => __( 'Tags', 'myplugin' ),
        'taxonomy' => 'post_tag',             // Taxonomy slug for linked term admin pages
    ],
    'attachment_size' => [
        'label'    => __( 'Size', 'myplugin' ),
        'decimals' => 2,                      // Decimal places for file_size type
    ],
],
```

## Row Actions

Row actions appear on hover below the primary column.

### URL-Based Actions

```php
'row_actions' => [
    'view' => [
        'label' => __( 'View', 'myplugin' ),
        'url'   => fn( $item ) => get_permalink( $item->get_id() ),
    ],
    'archive' => [
        'label'   => __( 'Archive', 'myplugin' ),
        'url'     => fn( $item ) => admin_url( '...' ),
        'confirm' => __( 'Archive this item?', 'myplugin' ),
        'class'   => 'archive-link',
    ],
],
```

### Flyout Actions

```php
'row_actions' => [
    'edit' => [
        'label'  => __( 'Edit', 'myplugin' ),
        'flyout' => true,  // Opens the flyout defined in flyouts.edit
    ],
],
```

### Handler-Based Actions

Define a `handler` callback and the action is automatically processed with nonce verification, capability checks, and
clean redirects:

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
        // Optional: custom success/error notices
        'notice' => [
            'success' => __( 'Customer status updated.', 'myplugin' ),
            'error'   => __( 'Failed to update status.', 'myplugin' ),
        ],
    ],
],
```

Handler return values control the redirect:

| Return  | Behavior                                                      |
|---------|---------------------------------------------------------------|
| `true`  | Redirects with `updated=1`                                    |
| `false` | Redirects with `error=action_failed`                          |
| `array` | Array keys become URL parameters (e.g., `['activated' => 1]`) |

### Callback Actions

For full control over the action HTML:

```php
'row_actions' => [
    'custom' => [
        'callback' => function( $item ) {
            return sprintf( '<a href="%s">%s</a>', esc_url( '...' ), 'Custom' );
        },
    ],
],
```

### Conditional Actions

Actions can be conditionally shown based on the item:

```php
'row_actions' => [
    'refund' => [
        'label'     => __( 'Refund', 'myplugin' ),
        'condition' => fn( $item ) => $item->get_status() === 'completed',
        'handler'   => function( $item_id ) { /* ... */ },
    ],
],
```

### Action Capabilities

Individual row actions can require specific capabilities:

```php
'row_actions' => [
    'delete_permanently' => [
        'label'      => __( 'Delete Permanently', 'myplugin' ),
        'capability' => 'delete_others_posts',
        'handler'    => function( $item_id ) { /* ... */ },
    ],
],
```

### Auto Delete Action

When you provide a `delete` callback in `callbacks`, the library automatically adds a delete row action with nonce
verification and confirmation dialog. To disable:

```php
'callbacks' => [
    'delete' => '\\MyPlugin\\delete_order',
],
// The delete row action is added automatically.
// To prevent it, simply omit the delete callback.
```

### Row Actions as Callable

For complete control, pass a callable instead of an array:

```php
'row_actions' => function( $item, $item_id ) {
    $actions = [];
    $actions['edit'] = sprintf( '<a href="%s">Edit</a>', esc_url( '...' ) );
    return $actions;
},
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
        'notice' => [
            'success' => __( '%d customers activated.', 'myplugin' ),
            'error'   => __( 'Failed to activate customers.', 'myplugin' ),
        ],
    ],
],
```

Callback return values control the redirect:

| Return  | Behavior                                        |
|---------|-------------------------------------------------|
| `array` | Keys become URL parameters                      |
| `int`   | Redirects with `updated={value}`                |
| `bool`  | Redirects with `updated={count}` or `updated=0` |

### Bulk Action Notices

The `notice` config supports both array and callable formats:

```php
// Array format (with %d placeholder for count)
'notice' => [
    'success' => __( '%d customers activated.', 'myplugin' ),
    'error'   => __( 'Failed to activate customers.', 'myplugin' ),
],

// Callable format (receives $_GET for full control)
'notice' => function( $params ) {
    $count = absint( $params['updated'] ?? 0 );
    return [
        'type'    => 'success',
        'message' => sprintf( '%d items processed.', $count ),
    ];
},
```

## Views (Status Tabs)

Views display as clickable tabs above the table. Counts are automatically fetched from the `get_counts` callback.

### Simple Format

Keys are auto-labeled by replacing underscores/hyphens with spaces and capitalizing:

```php
'views' => [ 'active', 'pending', 'not_active' ],
// Renders as: All | Active | Pending | Not Active
```

### Explicit Format

```php
'views' => [
    'active'    => __( 'Active', 'myplugin' ),
    'pending'   => __( 'Awaiting Review', 'myplugin' ),
    'completed' => __( 'Completed', 'myplugin' ),
],
```

### Mixed Format

```php
'views' => [
    'active',                                      // Auto-labeled "Active"
    'pending' => __( 'Awaiting Review', 'myplugin' ),  // Custom label
    'inactive',                                    // Auto-labeled "Inactive"
],
```

Views with zero items are automatically hidden. The "All" tab is always shown with the total count.

## Filters

Dropdown filters appear above the table with a "Filter" button. A "Clear" button appears when any filter is active.

```php
'filters' => [
    'country' => [
        'label'            => __( 'All Countries', 'myplugin' ),
        'options_callback' => fn() => get_country_options(),
    ],
    'type' => [
        'label'   => __( 'All Types', 'myplugin' ),
        'options' => [
            'physical' => __( 'Physical', 'myplugin' ),
            'digital'  => __( 'Digital', 'myplugin' ),
        ],
    ],
    'date_range' => [
        'label'   => __( 'All Dates', 'myplugin' ),
        'options' => [
            'today'      => __( 'Today', 'myplugin' ),
            'this_week'  => __( 'This Week', 'myplugin' ),
            'this_month' => __( 'This Month', 'myplugin' ),
        ],
        'apply_callback' => function( &$args, $value ) {
            if ( $value === 'today' ) {
                $args['date_query'] = [ 'after' => 'today' ];
            }
        },
    ],
],
```

Without an `apply_callback`, the filter value is passed directly as a query argument using the filter key (e.g.,
`$args['country'] = 'us'`).

## Search

### Default Search

When `searchable` is enabled (default), a search box appears above the table. The search term is passed as
`$args['search']` to the `get_items` callback.

### Custom Search Callback

For tables where the search term needs to be resolved against related data (e.g., searching orders by customer email
when the email lives in a separate customers table):

```php
'callbacks' => [
    'get_items'       => '\\MyPlugin\\get_orders',
    'get_counts'      => '\\MyPlugin\\get_order_counts',
    'search_callback' => function( string $search ) {
        // Look up customer by email
        $customer = get_customer_by_email( $search );
        if ( $customer ) {
            return [ 'customer_id' => $customer->get_id() ];
        }
        
        // Search customers by name, return matching IDs
        $customers = get_customers( [ 'search' => $search, 'fields' => 'ids' ] );
        if ( ! empty( $customers ) ) {
            return [ 'customer_id__in' => $customers ];
        }
        
        // Return empty array to fall back to default search behavior
        return [];
    },
],
```

The callback receives the search string and returns an array of query args to merge into the query. When the callback
returns a non-empty array, the raw search term is not passed to `get_items`. When it returns an empty array, the default
`$args['search']` behavior is used as a fallback.

### Search Results Banner

When a search is active, a banner displays showing the search term with a "Clear search" link. This is automatic and
requires no configuration.

## Status Styles

Map status values to badge types for automatic status column formatting:

```php
'status_styles' => [
    'active'    => 'success',
    'pending'   => 'warning',
    'inactive'  => 'default',
    'cancelled' => 'danger',
],
```

These styles are passed to the auto-formatter when rendering status columns.

## Capabilities

### Single Capability

Apply one capability to all actions:

```php
'capability' => 'manage_options',
```

### Per-Action Capabilities

Override capabilities for specific actions. The single `capability` value is used as the default for any action not
explicitly defined:

```php
'capability'   => 'edit_posts',        // Default for all actions
'capabilities' => [
    'view'   => 'edit_posts',          // View the table
    'edit'   => 'edit_posts',          // Edit row actions
    'delete' => 'delete_others_posts', // Delete row action
    'bulk'   => 'manage_options',      // Bulk action dropdown
],
```

## Add Button

The add button appears in the header area. Three formats are supported:

```php
// Flyout ID — opens a flyout panel
'add_button' => 'customers_add',

// URL — renders as a link button
'add_button' => admin_url( 'admin.php?page=add-customer' ),

// Callable — full control over output
'add_button' => function() {
    return '<a href="#" class="page-title-action">Add New</a>';
},
```

The button text comes from `labels.add_new`. If `add_new` is empty, no button is rendered.

## Flyout Integration

Integrates with [wp-register-flyouts](https://github.com/arraypress/wp-register-flyouts) for inline editing panels:

```php
register_admin_table( 'my_orders', [
    'flyouts' => [
        'edit' => 'orders_edit',    // Flyout ID for editing
        'view' => 'orders_view',    // Flyout ID for viewing
    ],
    'add_button' => 'orders_add',   // Flyout ID for adding
    
    'row_actions' => [
        'edit' => [
            'label'  => __( 'Edit', 'myplugin' ),
            'flyout' => true,  // Uses flyouts.edit
        ],
    ],
    
    // Structured columns can also link to flyouts
    'columns' => [
        'name' => [
            'label' => __( 'Name', 'myplugin' ),
            'title' => fn( $item ) => $item->get_name(),
            'link'  => 'edit_flyout',  // Uses flyouts.edit
        ],
    ],
] );
```

## Help Tabs

Add help tabs to the Screen Options area:

```php
'help' => [
    'overview' => [
        'title'   => __( 'Overview', 'myplugin' ),
        'content' => '<p>This screen shows all customers.</p>',
    ],
    'filters' => [
        'title'    => __( 'Filtering', 'myplugin' ),
        'callback' => function() {
            return '<p>Use the dropdowns to filter by country or status.</p>';
        },
    ],
    'sidebar' => '<p><strong>For more info:</strong></p><p><a href="#">Documentation</a></p>',
],
```

The special `sidebar` key sets the help sidebar content. All other keys create individual help tabs.

## Screen Options

The library automatically registers a "Number of items per page" screen option. Users can also show/hide columns via
Screen Options. Both settings persist per-user.

## Clean URLs

The library maintains clean URLs throughout:

- Filter submissions redirect to clean URLs (no `_wpnonce`, `_wp_http_referer`, `action` in URL)
- Single actions redirect to clean URLs after processing
- Bulk actions redirect to clean URLs after processing

## Body Classes

Admin table pages automatically receive CSS body classes for targeted styling:

- `admin-table` — added to all table pages
- `admin-table-{id}` — table-specific class (e.g., `admin-table-my_customers`)
- Custom class from the `body_class` config option

## Hooks

### Filters

```php
// Modify column definitions
add_filter( 'arraypress_table_columns', fn( $columns, $id, $config ) => $columns, 10, 3 );

// Modify hidden columns
add_filter( 'arraypress_table_hidden_columns', fn( $hidden, $id, $config ) => $hidden, 10, 3 );

// Modify sortable columns
add_filter( 'arraypress_table_sortable_columns', fn( $sortable, $id, $config ) => $sortable, 10, 3 );

// Modify query args before fetching items
add_filter( 'arraypress_table_query_args', fn( $args, $id, $config ) => $args, 10, 3 );
add_filter( 'arraypress_table_query_args_{table_id}', fn( $args, $config ) => $args, 10, 2 );

// Modify row actions
add_filter( 'arraypress_table_row_actions', fn( $actions, $item, $id ) => $actions, 10, 3 );
add_filter( 'arraypress_table_row_actions_{table_id}', fn( $actions, $item ) => $actions, 10, 2 );

// Modify bulk actions
add_filter( 'arraypress_table_bulk_actions', fn( $actions, $id ) => $actions, 10, 2 );

// Modify status views
add_filter( 'arraypress_table_views', fn( $views, $id, $status ) => $views, 10, 3 );

// Custom admin notices
add_filter( 'arraypress_table_admin_notices', fn( $notices, $id, $config ) => $notices, 10, 3 );
add_filter( 'arraypress_table_admin_notices_{table_id}', fn( $notices, $config ) => $notices, 10, 2 );
```

### Actions

```php
// Before/after table renders
add_action( 'arraypress_before_render_table', fn( $id, $config ) => null, 10, 2 );
add_action( 'arraypress_before_render_table_{table_id}', fn( $config ) => null, 10, 1 );
add_action( 'arraypress_after_render_table', fn( $id, $config ) => null, 10, 2 );
add_action( 'arraypress_after_render_table_{table_id}', fn( $config ) => null, 10, 1 );

// Item deleted
add_action( 'arraypress_table_item_deleted', fn( $item_id, $result, $id, $config ) => null, 10, 4 );
add_action( 'arraypress_table_item_deleted_{table_id}', fn( $item_id, $result, $config ) => null, 10, 3 );

// Bulk action processed
add_action( 'arraypress_table_bulk_action', fn( $items, $action, $id ) => null, 10, 3 );
add_action( 'arraypress_table_bulk_action_{table_id}', fn( $items, $action ) => null, 10, 2 );
add_action( 'arraypress_table_bulk_action_{table_id}_{action}', fn( $items ) => null, 10, 1 );

// Custom single action (only needed if NOT using handler in row_actions config)
add_action( 'arraypress_table_single_action_{table_id}', fn( $action, $item_id, $config ) => null, 10, 3 );
```

## Complete Example

```php
register_admin_table( 'my_customers', [
    'labels' => [
        'singular' => __( 'customer', 'myplugin' ),
        'plural'   => __( 'customers', 'myplugin' ),
        'title'    => __( 'Customers', 'myplugin' ),
    ],
    
    'callbacks' => [
        'get_items'       => '\\MyPlugin\\get_customers',
        'get_counts'      => '\\MyPlugin\\get_customer_counts',
        'delete'          => '\\MyPlugin\\delete_customer',
        'update'          => '\\MyPlugin\\update_customer',
        'search_callback' => function( string $search ) {
            // Search across related orders table too
            $order_customer_ids = get_order_customer_ids_by_search( $search );
            if ( ! empty( $order_customer_ids ) ) {
                return [ 'id__in' => $order_customer_ids ];
            }
            return [];
        },
    ],
    
    'page'       => 'my-customers',
    'logo'       => plugin_dir_url( __FILE__ ) . 'logo.png',
    'per_page'   => 25,
    'show_count' => true,
    'body_class' => 'customers-page',
    
    'flyouts' => [
        'edit' => 'customers_edit',
    ],
    'add_button' => 'customers_add',
    
    'columns' => [
        'name' => [
            'label'   => __( 'Customer', 'myplugin' ),
            'primary' => true,
            'before'  => function( $item ) {
                return get_avatar( $item->get_email(), 32 );
            },
            'title'   => function( $item ) {
                return $item->get_name();
            },
            'link'    => 'edit_flyout',
        ],
        'email'        => __( 'Email', 'myplugin' ),
        'total_spent'  => [
            'label' => __( 'Total Spent', 'myplugin' ),
            'align' => 'right',
        ],
        'status'       => [
            'label'  => __( 'Status', 'myplugin' ),
            'width'  => '100px',
        ],
        'country'      => __( 'Country', 'myplugin' ),
        'date_created' => __( 'Joined', 'myplugin' ),
    ],
    
    'sortable' => [ 'name', 'total_spent', 'date_created' ],
    
    'row_actions' => [
        'edit' => [
            'label'  => __( 'Edit', 'myplugin' ),
            'flyout' => true,
        ],
        'toggle_status' => [
            'label'   => fn( $item ) => $item->get_status() === 'active' 
                ? __( 'Deactivate', 'myplugin' ) 
                : __( 'Activate', 'myplugin' ),
            'confirm' => fn( $item ) => $item->get_status() === 'active' 
                ? __( 'Deactivate this customer?', 'myplugin' ) 
                : __( 'Activate this customer?', 'myplugin' ),
            'handler' => function( $item_id ) {
                $customer   = get_customer( $item_id );
                $new_status = $customer->get_status() === 'active' ? 'inactive' : 'active';
                return update_customer( $item_id, [ 'status' => $new_status ] );
            },
            'notice' => function( $params ) {
                $action = isset( $params['activated'] ) ? 'activated' : 'deactivated';
                return [
                    'type'    => 'success',
                    'message' => sprintf( 'Customer %s successfully.', $action ),
                ];
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
        'activate' => [
            'label'      => __( 'Set Active', 'myplugin' ),
            'capability' => 'manage_options',
            'callback'   => function( $ids ) {
                $updated = 0;
                foreach ( $ids as $id ) {
                    if ( update_customer( $id, [ 'status' => 'active' ] ) ) {
                        $updated++;
                    }
                }
                return [ 'updated' => $updated ];
            },
            'notice' => [
                'success' => __( '%d customers activated.', 'myplugin' ),
                'error'   => __( 'Failed to activate customers.', 'myplugin' ),
            ],
        ],
    ],
    
    'views' => [ 'active', 'inactive', 'pending' ],
    
    'filters' => [
        'country' => [
            'label'            => __( 'All Countries', 'myplugin' ),
            'options_callback' => '\\MyPlugin\\get_country_options',
        ],
    ],
    
    'status_styles' => [
        'active'   => 'success',
        'inactive' => 'default',
        'pending'  => 'warning',
    ],
    
    'capabilities' => [
        'delete' => 'manage_options',
    ],
    
    'help' => [
        'overview' => [
            'title'   => __( 'Overview', 'myplugin' ),
            'content' => '<p>Manage your customers from this screen.</p>',
        ],
    ],
] );

// Register menu
add_action( 'admin_menu', function() {
    add_menu_page(
        __( 'Customers', 'myplugin' ),
        __( 'Customers', 'myplugin' ),
        'manage_options',
        'my-customers',
        get_table_renderer( 'my_customers' ),
        'dashicons-groups',
        30
    );
} );
```

## Requirements

- PHP 7.4+
- WordPress 5.0+
- BerlinDB-based custom tables
- arraypress/wp-composer-assets

## Dependencies

The following ArrayPress libraries are used for column auto-formatting:

- [wp-date-utils](https://github.com/arraypress/wp-date-utils) — Date and duration formatting
- [wp-countries](https://github.com/arraypress/wp-countries) — Country flag and name rendering
- [wp-currencies](https://github.com/arraypress/wp-currencies) — Currency formatting
- [wp-status-badge](https://github.com/arraypress/wp-status-badge) — Status badge rendering
- [wp-rate-format](https://github.com/arraypress/wp-rate-format) — Rate and percentage formatting

## License

GPL-2.0-or-later

## Credits

Created by [David Sherlock](https://davidsherlock.com) at [ArrayPress](https://arraypress.com).