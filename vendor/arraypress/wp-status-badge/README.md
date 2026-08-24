# WordPress Status Badge

A lightweight WordPress library for rendering styled status badges with automatic type detection. Works in both admin and frontend contexts with sensible defaults for common status values.

## Features

* 🎨 **Zero Configuration**: Common statuses like `active`, `pending`, `failed` just work
* 🏷️ **Five Badge Types**: Success, warning, danger, info, and default
* 🔧 **Custom Mappings**: Override or extend defaults with your own status-to-type mappings
* 🌐 **Admin & Frontend**: Works everywhere with a standalone CSS file
* 📦 **Composer Assets**: Automatic stylesheet management via `wp-composer-assets`
* 🏗️ **Instance-Based**: No global state, no static registries, no side effects
* 🎯 **Auto Labels**: Status strings automatically converted to readable labels

## Requirements

* PHP 7.4 or later
* WordPress 5.0 or later

## Installation
```bash
composer require arraypress/wp-status-badge
```

## Basic Usage

### Rendering Badges
```php
use ArrayPress\StatusBadge\StatusBadge;

// All defaults — covers most common statuses
$badge = new StatusBadge();

echo $badge->render( 'active' );    // Green badge: "Active"
echo $badge->render( 'pending' );   // Amber badge: "Pending"
echo $badge->render( 'failed' );    // Red badge: "Failed"
echo $badge->render( 'new' );       // Blue badge: "New"
echo $badge->render( 'inactive' );  // Grey badge: "Inactive"
```

### Custom Mappings
```php
// Merge custom mappings over defaults
$badge = new StatusBadge( [
    'churned'  => 'danger',
    'trialing' => 'warning',
    'vip'      => 'success',
] );

echo $badge->render( 'churned' );   // Red badge: "Churned"
echo $badge->render( 'vip' );       // Green badge: "Vip"
echo $badge->render( 'active' );    // Still works — defaults preserved
```

### One-Off Overrides
```php
$badge = new StatusBadge();

// Override type without changing the instance
echo $badge->render( 'custom_status', 'info' );

// Override both type and label
echo $badge->render( 'in_progress', 'warning', 'In Progress' );
```

### Early Asset Enqueueing
```php
// CSS enqueues automatically on first render(), but you can enqueue early
$badge = new StatusBadge();
$badge->enqueue();
```

## Badge Types

| Type        | Colour | Example Statuses                                          |
|-------------|--------|-----------------------------------------------------------|
| `success`   | Green  | active, approved, completed, paid, published, verified    |
| `warning`   | Amber  | pending, draft, processing, scheduled, trial, on_hold     |
| `danger`    | Red    | failed, cancelled, expired, rejected, suspended, banned   |
| `info`      | Blue   | new, updated, importing, syncing, notice                  |
| `default`   | Grey   | inactive, disabled, archived, closed, paused, unknown     |

## Type Detection
```php
$badge = new StatusBadge();

// Get the badge type for any status
$type = $badge->get_type( 'active' );    // "success"
$type = $badge->get_type( 'pending' );   // "warning"
$type = $badge->get_type( 'unknown_status' ); // "default"

// Boolean checks
if ( $badge->is_success( 'active' ) ) {
    // Status maps to success type
}

if ( $badge->is_danger( 'expired' ) ) {
    // Status maps to danger type
}

// Check any type
if ( $badge->is_type( 'draft', 'warning' ) ) {
    // Status maps to warning type
}
```

### Available Check Methods
```php
$badge->is_success( $status );  // Green statuses
$badge->is_warning( $status );  // Amber statuses
$badge->is_danger( $status );   // Red statuses
$badge->is_info( $status );     // Blue statuses
```

## Labels
```php
// Labels auto-generate from status strings
StatusBadge::format_label( 'in_progress' );  // "In Progress"
StatusBadge::format_label( 'on-hold' );      // "On Hold"
StatusBadge::format_label( 'active' );       // "Active"

// Override label at render time
echo $badge->render( 'active', label: 'Currently Active' );
```

## Utility Methods
```php
$badge = new StatusBadge();

// Get the full status-to-type map
$map = $badge->get_map();

// Get the icon class for a badge type
$icon = $badge->get_icon( 'success' );  // "dashicons-yes-alt"
$icon = $badge->get_icon( 'danger' );   // "dashicons-dismiss"

// Get all valid badge types
$types = StatusBadge::get_types();  // ['success', 'warning', 'danger', 'info', 'default']
```

## Default Status Map

The library ships with a comprehensive default map. All mappings are case-insensitive.

<details>
<summary>View full default map</summary>

**Success:** active, approved, completed, confirmed, connected, delivered, enabled, live, open, paid, published, resolved, valid, verified, yes

**Warning:** awaiting, draft, expiring, on-hold, on_hold, partially_refunded, pending, processing, review, scheduled, trial, trialing, unpaid

**Danger:** banned, blocked, cancelled, canceled, declined, error, expired, failed, invalid, overdue, refunded, rejected, revoked, spam, suspended, terminated

**Info:** importing, info, new, notice, syncing, updated

**Default:** archived, closed, disabled, hidden, inactive, no, none, paused, trashed, unknown

</details>

## Common Use Cases

### Admin List Table Column
```php
$badge = new StatusBadge();

// In your column rendering
function column_status( $item ) {
    global $badge;
    return $badge->render( $item->get_status() );
}
```

### WooCommerce Order Statuses
```php
$badge = new StatusBadge( [
    'wc-on-hold'    => 'warning',
    'wc-processing' => 'warning',
    'wc-completed'  => 'success',
    'wc-refunded'   => 'danger',
] );

echo $badge->render( $order->get_status() );
```

### Dashboard Widget
```php
$badge = new StatusBadge();

foreach ( $items as $item ) {
    printf(
        '<tr><td>%s</td><td>%s</td></tr>',
        esc_html( $item->get_title() ),
        $badge->render( $item->get_status() )
    );
}
```

### Frontend Display
```php
// Works outside wp-admin — CSS is standalone
$badge = new StatusBadge();

echo '<div class="order-status">';
echo $badge->render( $order->status );
echo '</div>';
```

## API Reference

### Constructor

| Parameter | Type    | Default | Description                             |
|-----------|---------|---------|-----------------------------------------|
| `$custom` | `array` | `[]`    | Custom status-to-type mappings to merge |

### Rendering

| Method                                  | Description                        |
|-----------------------------------------|------------------------------------|
| `render($status, $type, $label)`        | Render badge HTML                  |
| `enqueue()`                             | Enqueue stylesheet without render  |

### Type Detection

| Method                    | Description                             |
|---------------------------|-----------------------------------------|
| `get_type($status)`       | Get badge type for a status             |
| `get_icon($type)`         | Get dashicon class for a badge type     |
| `is_type($status, $type)` | Check if status maps to a specific type |
| `is_success($status)`     | Check if status maps to success         |
| `is_warning($status)`     | Check if status maps to warning         |
| `is_danger($status)`      | Check if status maps to danger          |
| `is_info($status)`        | Check if status maps to info            |

### Utility

| Method                  | Description                                |
|-------------------------|--------------------------------------------|
| `get_map()`             | Get the full status-to-type map            |
| `get_types()`           | Get all valid badge types (static)         |
| `format_label($status)` | Convert status string to label (static)    |

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0-or-later License.

## Support

- [Documentation](https://github.com/arraypress/wp-status-badge)
- [Issue Tracker](https://github.com/arraypress/wp-status-badge/issues)