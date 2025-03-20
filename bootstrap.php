<?php
/**
 * Plugin Name:       TablePress Pro
 * Plugin URI:        https://arraypress.com/tablepress-pro
 * Description:       A powerful WordPress admin tables library for easy creation of custom admin list tables with advanced filtering.
 * Author:            ArrayPress Limited
 * Author URI:        https://arraypress.com
 * License:           GNU General Public License v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tablepress-pro
 * Domain Path:       /assets/languages
 * Requires PHP:      7.4
 * Requires at least: 6.1
 * Version:           1.0.0
 */
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$base_path = plugin_dir_path(__FILE__) . 'src/';

// Traits
require_once $base_path . '/Traits/Assets.php';
require_once $base_path . '/Traits/Base.php';
require_once $base_path . '/Traits/BulkActions.php';
require_once $base_path . '/Traits/Columns.php';
require_once $base_path . '/Traits/Data.php';
require_once $base_path . '/Traits/Filters.php';
require_once $base_path . '/Traits/UI.php';
require_once $base_path . '/Traits/Overrides.php';
require_once $base_path . '/Traits/Utils.php';
require_once $base_path . '/Traits/Views.php';

// Core files
require_once $base_path . 'Tables.php';
require_once $base_path . 'ListTable.php';
require_once $base_path . 'TableNotices.php';
require_once $base_path . 'AssetsManager.php';

// Utilities
require_once $base_path . '/Utilities/Functions.php';