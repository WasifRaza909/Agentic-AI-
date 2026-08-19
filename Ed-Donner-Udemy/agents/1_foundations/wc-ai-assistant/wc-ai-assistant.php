<?php
/**
 * Plugin Name: WC AI Assistant
 * Description: A floating AI chat bubble that answers customer questions about your WooCommerce store.
 * Version:     1.4.1
 * Author:      Wasif
 * License:     GPL-2.0-or-later
 * Requires PHP: 8.1
 * Text Domain: wc-ai-assistant
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCAI_VERSION', '1.4.1' );
define( 'WCAI_FILE', __FILE__ );
define( 'WCAI_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCAI_URL', plugin_dir_url( __FILE__ ) );

require_once WCAI_DIR . 'includes/class-wcai-icons.php';
require_once WCAI_DIR . 'includes/class-wcai-settings.php';
require_once WCAI_DIR . 'includes/class-wcai-leads.php';
require_once WCAI_DIR . 'includes/class-wcai-gemini-client.php';
require_once WCAI_DIR . 'includes/class-wcai-context.php';
require_once WCAI_DIR . 'includes/class-wcai-tools.php';
require_once WCAI_DIR . 'includes/class-wcai-rest.php';
require_once WCAI_DIR . 'includes/class-wcai-widget.php';

register_activation_hook( __FILE__, [ WCAI_Leads::class, 'install_table' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p><strong>WC AI Assistant</strong> requires WooCommerce to be active.</p></div>';
				}
			);
			return;
		}

		WCAI_Settings::init();
		WCAI_Leads::init();
		WCAI_REST::init();
		WCAI_Widget::init();
	}
);

/**
 * Invalidate the cached store context whenever the catalogue changes,
 * so the assistant never quotes a stale price.
 */
add_action( 'woocommerce_update_product', [ WCAI_Context::class, 'flush_cache' ] );
add_action( 'woocommerce_new_product', [ WCAI_Context::class, 'flush_cache' ] );
add_action( 'woocommerce_delete_product', [ WCAI_Context::class, 'flush_cache' ] );

// The prompt embeds the policies text and the assistant's name, so saving
// settings has to drop the cache too — otherwise an edit takes up to 15
// minutes to show up and looks like it didn't save.
add_action( 'update_option_' . WCAI_Settings::OPTION, [ WCAI_Context::class, 'flush_cache' ] );
