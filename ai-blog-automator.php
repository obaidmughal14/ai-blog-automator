<?php
/**
 * Plugin Name: AI Blog Automator
 * Plugin URI:  https://yoursite.com
 * Description: Automates blog writing, SEO, images, internal linking & Google indexing via Gemini AI.
 * Version:     1.1.2
 * Author:      Devigon Tech
 * Author URI:  https://devigontech.com
 * Text Domain: ai-blog-automator
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

define( 'AIBA_VERSION', '1.1.2' );
define( 'AIBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIBA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIBA_PREFIX', 'aiba_' );

require_once AIBA_PLUGIN_DIR . 'includes/class-core.php';

register_activation_hook( __FILE__, array( 'AIBA_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AIBA_Core', 'deactivate' ) );

AIBA_Core::init();
