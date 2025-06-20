<?php
/**
 * Plugin Name:       Custom Tables
 * Plugin URI:        https://github.com/arraypress/wo-custom-tables
 * Description:       A powerful WordPress admin tables library for easy creation of custom admin list tables with advanced filtering.
 * Author:            ArrayPress Limited
 * Author URI:        https://arraypress.com
 * License:           GNU General Public License v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-custom-tables
 * Domain Path:       /assets/languages
 * Requires PHP:      7.4
 * Requires at least: 6.7.2
 * Version:           1.0.0
 */
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$base_path = plugin_dir_path(__FILE__) . 'src/';

// Table Traits
require_once $base_path . '/Traits/Table/Action.php';
require_once $base_path . '/Traits/Table/Column.php';
require_once $base_path . '/Traits/Table/Notice.php';
require_once $base_path . '/Traits/Table/Register.php';
require_once $base_path . '/Traits/Table/Render.php';
require_once $base_path . '/Traits/Table/URL.php';
require_once $base_path . '/Traits/Table/AJAX.php';
require_once $base_path . '/Traits/Table/Help.php';
require_once $base_path . '/Traits/Table/ScreenOptions.php';
require_once $base_path . '/Traits/Table/Utils.php';

// Traits
require_once $base_path . '/Traits/ListTable/Assets.php';
require_once $base_path . '/Traits/ListTable/Base.php';
require_once $base_path . '/Traits/ListTable/BulkActions.php';
require_once $base_path . '/Traits/ListTable/ColumnFields.php';
require_once $base_path . '/Traits/ListTable/Columns.php';
require_once $base_path . '/Traits/ListTable/Data.php';
require_once $base_path . '/Traits/ListTable/Filters.php';
require_once $base_path . '/Traits/ListTable/FilterFields.php';
require_once $base_path . '/Traits/ListTable/FilterUI.php';
require_once $base_path . '/Traits/ListTable/Overrides.php';
require_once $base_path . '/Traits/ListTable/Views.php';

// Core files
require_once $base_path . 'Tables.php';
require_once $base_path . 'ListTable.php';
require_once $base_path . 'AssetsManager.php';
require_once $base_path . 'Utils.php';
require_once $base_path . 'Format.php';

// Utilities
require_once $base_path . '/Utilities/Functions.php';