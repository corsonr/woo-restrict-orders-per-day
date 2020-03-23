<?php
/**
 * Plugin Name:       Restrict Orders per Day for WooCommerce
 * Plugin URI:        https://remicorson.com
 * Description:       Put the shop into catalogue mode once number of orders per day is reached.
 * Version:           0.2
 * Author:            Remi Corson, corsonr
 * Author URI:        https://remicorson.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-restrict-orders-per-day
 * Domain Path:       /languages
 */

/*
 * NOTE: this is basic coding for now, quickly done to fulfill a need for companies struggling due to COVID-19 bad stuff occuring ATM.
 * Feel free to submit improvements.
 * Edit the number of orders per day via the WooCommerce > Settings > General meu item.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Class.
 *
 * @version 0.1
 * @since   0.2
 */

if ( ! class_exists( 'ROPD_Restrict_Orders_Per_Day' ) ) {

	/**
	 * Main Class.
	 */
	class ROPD_Restrict_Orders_Per_Day {

		/**
		 * Constructor
		 */
		public function __construct() {

			add_action( 'plugins_loaded', array( $this, 'woo_restrict_orders_per_day_textdomain' ) );
			add_filter( 'woocommerce_general_settings', array( $this, 'woo_restrict_orders_per_day_settings' ) );
			add_action( 'init', array( $this, 'enable_catalog_mode' ) );
		}

		/**
		 * Text Domain
		 */
		public function woo_restrict_orders_per_day_textdomain() {
			load_plugin_textdomain( 'woo-restrict-orders-per-day', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Adds WooCommerce Settings
		 *
		 * @return $settings
		 */
		public function woo_restrict_orders_per_day_settings( $settings ) {

			$updated_settings = array();

			foreach ( $settings as $section ) {

				if ( isset( $section['id'] ) && 'general_options' == $section['id'] &&
				isset( $section['type'] ) && 'sectionend' == $section['type'] ) {

					$updated_settings[] = array(
						'name'     => __( 'Maximum orders per day', 'woo-restrict-orders-per-day' ),
						'desc_tip' => __( 'Define the number of orders your shop can handle.', 'woo-restrict-orders-per-day' ),
						'id'       => 'woocommerce_max_orders_per_day',
						'type'     => 'number',
						'css'      => 'min-width:300px;',
						'std'      => '100',  // WC < 2.0
						'default'  => '100',  // WC >= 2.0
						'desc'     => __( 'You must enter a number.', 'woo-restrict-orders-per-day' ),
					);

					$updated_settings[] = array(
						'name'     => __( 'Message displayed when limit is reached', 'woo-restrict-orders-per-day' ),
						'desc_tip' => __( 'The message your customers will see when the order limit is reached.', 'woo-restrict-orders-per-day' ),
						'id'       => 'woocommerce_max_orders_per_day_message',
						'type'     => 'textarea',
						'css'      => 'min-width:300px;',
						'std'      => __( 'We exceeded our capacity and we are sorry to say we can not take new orders for today!', 'woo-restrict-orders-per-day' ),  // WC < 2.0
						'default'  => __( 'We exceeded our capacity and we are sorry to say we can not take new orders for today!', 'woo-restrict-orders-per-day' ),  // WC >= 2.0
					);
				}

				$updated_settings[] = $section;
			}

			return $updated_settings;
		}

		/**
		 * Get the number of orders for a date or a dates range.
		 *
		 * @return $result
		 */
		public function get_daily_orders_count( $date = 'now' ) {
			if ( 'now' === $date ) {
				$date        = date( 'Y-m-d' );
				$date_string = "> '$date'";
			} else {
				$date        = date( 'Y-m-d', strtotime( $date ) );
				$date2       = date( 'Y-m-d', strtotime( $date ) + 86400 );
				$date_string = "BETWEEN '$date' AND '$date2'";
			}
			global $wpdb;
			$result = $wpdb->get_var( "
				SELECT DISTINCT count(p.ID) FROM {$wpdb->prefix}posts as p
				WHERE p.post_type = 'shop_order' AND p.post_date $date_string
				AND p.post_status IN ('wc-on-hold','wc-processing','wc-completed')
			" );

			return $result;
		}

		/**
		 * Removes all add to cart buttons & prices.
		 */
		public function enable_catalog_mode() {

			if ( is_admin() ) {
				return;
			}

			$orders_capacity = get_option( 'woocommerce_max_orders_per_day' );
			$default_orders_capacity_message = __( 'We exceeded our capacity and we are sorry to say we can not take new orders for today!', 'woo-restrict-orders-per-day' );
			$orders_capacity_message = get_option( 'woocommerce_max_orders_per_day_message', $default_orders_capacity_message );

			if ( empty( $orders_capacity_message ) ) {
				$orders_capacity_message = $default_orders_capacity_message;
			}

			if ( $this->get_daily_orders_count() >= $orders_capacity ) {
				remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
				remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( $orders_capacity_message, 'notice' );
				}
			}

		}

	}

}

new ROPD_Restrict_Orders_Per_Day();
