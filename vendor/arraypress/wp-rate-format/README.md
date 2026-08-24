# WordPress Rate Format

A WordPress library for formatting, rendering, sanitizing, and validating rates and percentages. Handles values that can be either a percentage or a flat currency amount, with automatic type resolution from data objects.

## Installation
```bash
composer require arraypress/wp-rate-format
```

## Features

- **Type Resolution** — Automatically detects whether a rate is a percentage or flat amount from object properties
- **Percentage Formatting** — Format and render percentage values with i18n support
- **Rate Formatting** — Smart formatting that dispatches to percentage or currency based on type
- **HTML Rendering** — Ready-to-use HTML output for admin tables and displays
- **Sanitization** — Clamp and sanitize percentage and rate values for safe storage
- **Validation** — Validate rates and percentages with configurable bounds

## Usage

### Percentage Formatting
```php
use ArrayPress\RateFormat\Rate;

// Plain string: "15%"
Rate::format_percentage( 15 );

// With decimals: "15.50%"
Rate::format_percentage( 15.5, 2 );

// HTML: <span class="percentage">15%</span>
Rate::render_percentage( 15 );
```

### Rate Formatting (Auto-Detection)

The library resolves the rate type from your data object by checking for `{column}_type` or `type` properties:
```php
// Object with rate_type = 'percentage'
$item = (object) [
    'discount'      => 15,
    'discount_type' => 'percentage',
];

// Returns: "15%"
Rate::format( $item->discount, $item, 'discount' );

// Object with rate_type = 'flat' and currency
$item = (object) [
    'discount'      => 1500,
    'discount_type' => 'flat',
    'currency'      => 'USD',
];

// Returns: "$15.00"
Rate::format( $item->discount, $item, 'discount' );
```

### HTML Rendering
```php
// Renders as percentage or currency HTML based on type
Rate::render( $item->rate, $item, 'rate' );

// Returns null for invalid values (pair with your own empty state)
Rate::render( $value, $item ) ?? '<span>—</span>';
```

### Type Resolution
```php
// Resolve type from an object
$type = Rate::resolve_type( $item, 'discount' ); // 'percentage', 'flat', etc.

// Check type categories
Rate::is_percentage_type( 'percent' );  // true
Rate::is_percentage_type( '%' );        // true
Rate::is_flat_type( 'fixed' );          // true
Rate::is_flat_type( 'amount' );         // true

// Determine the effective format
Rate::determine_format( 15, $item, 'discount' ); // 'percentage' or 'currency'
```

### Sanitization
```php
// Clamp percentage between 0-100, round to 2 decimals
Rate::sanitize_percentage( 150 );        // 100.0
Rate::sanitize_percentage( -5 );         // 0.0
Rate::sanitize_percentage( 15.555, 0, 100, 1 ); // 15.6

// Custom bounds
Rate::sanitize_percentage( 50, 10, 90 ); // 50.0

// Sanitize rate based on resolved type
Rate::sanitize_rate( $value, $item, 'discount' );
```

### Validation
```php
// Validate percentage
Rate::is_valid_percentage( 50 );         // true
Rate::is_valid_percentage( 150 );        // false
Rate::is_valid_percentage( 'abc' );      // false

// Custom bounds
Rate::is_valid_percentage( 50, 10, 90 ); // true

// Validate rate based on resolved type
Rate::is_valid_rate( $value, $item, 'discount' );
```

### Integration with Admin Tables
```php
use ArrayPress\RateFormat\Rate;

// In your column formatter
'rate'       => Rate::render( $value, $item, $column_name ) ?? self::render_empty(),
'percentage' => Rate::render_percentage( $value ) ?? self::render_empty(),
```

## Type Resolution Order

When resolving the rate type from an item object, the library checks in this order:

1. `$item->get_{column}_type()` method (e.g., `get_discount_type()`)
2. `$item->{column}_type` property (e.g., `discount_type`)
3. `$item->get_type()` method
4. `$item->type` property
5. Falls back to guessing: values 0–100 are treated as percentages, others as currency

## Supported Type Identifiers

| Category   | Identifiers                    |
|------------|--------------------------------|
| Percentage | `percent`, `percentage`, `%`   |
| Flat       | `flat`, `fixed`, `amount`      |

## Requirements

- PHP 7.4 or later
- WordPress 6.2 or later
- [arraypress/wp-currencies](https://github.com/arraypress/wp-currencies)

## License

GPL-2.0-or-later