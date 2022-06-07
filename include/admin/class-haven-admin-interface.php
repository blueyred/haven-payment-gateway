<?php
/*
 * Copyright (c) 2018, Ryo Currency Project, Monero Integrations
 * Admin interface for Haven gateway
 * Authors: mosu-forge, bluey.red
 */

defined( 'ABSPATH' ) || exit;

require_once('class-haven-admin-payments-list.php');

if (class_exists('Haven_Admin_Interface', false)) {
    return new Haven_Admin_Interface();
}

class Haven_Admin_Interface {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'meta_boxes'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_head', array( $this, 'admin_menu_update'));
    }

    /**
     * Add meta boxes.
     */
    public function meta_boxes() {
        add_meta_box(
            'haven_admin_order_details',
            __('Haven Protocol Gateway','haven_gateway'),
            array($this, 'meta_box_order_details'),
            'shop_order',
            'normal',
            'high'
        );
    }

    /**
     * Meta box for order page
     */
    public function meta_box_order_details($order) {
        Haven_Gateway::admin_order_page($order);
    }

    /**
     * Add menu items.
     */
    public function admin_menu() {
        add_menu_page(
            __('Haven Protocol', 'haven_gateway'),
            __('Haven Protocol', 'haven_gateway'),
            'manage_woocommerce',
            'haven_gateway',
            array($this, 'orders_page'),
            HAVEN_GATEWAY_PLUGIN_URL.'/assets/images/haven-icon-admin.png',
            56 // Position on menu, woocommerce has 55.5, products has 55.6
        );

        add_submenu_page(
            'haven_gateway',
            __('Payments', 'haven_gateway'),
            __('Payments', 'haven_gateway'),
            'manage_woocommerce',
            'haven_gateway_payments',
            array($this, 'payments_page')
        );


        $settings_page = add_submenu_page(
            'haven_gateway',
            __('Settings', 'haven_gateway'),
            __('Settings', 'haven_gateway'),
            'manage_options',
            'haven_gateway_settings',
            array($this, 'settings_page')
        );
        add_action('load-'.$settings_page, array($this, 'settings_page_init'));

        if( defined('HAVEN_GATEWAY_DEBUG') && HAVEN_GATEWAY_DEBUG ){

            $debug_page = add_submenu_page(
                'haven_gateway',
                __('Debug', 'haven_gateway'),
                __('Debug', 'haven_gateway'),
                'manage_options',
                'haven_gateway_debug',
                array($this, 'debug_page_init')
            );

        }
    }

    /**
     * Remove duplicate sub-menu item
     */
    public function admin_menu_update() {
        global $submenu;
        if (isset($submenu['haven_gateway'])) {
            unset($submenu['haven_gateway'][0]);
        }
    }

    /**
     * Haven payments page
     */
    public function payments_page() {
        $payments_list = new Haven_Admin_Payments_List();
        $payments_list->prepare_items();
        $payments_list->display();
    }

    /**
     * Monero settings page
     */
    public function settings_page() {
        WC_Admin_Settings::output();
    }

    public function settings_page_init() {
        global $current_tab, $current_section;

        $current_section = 'haven_gateway';
        $current_tab = 'checkout';

        // Include settings pages.
        WC_Admin_Settings::get_settings_pages();

        // Save settings if data has been posted.
        if (apply_filters("woocommerce_save_settings_{$current_tab}_{$current_section}", !empty($_POST))) {
            WC_Admin_Settings::save();
        }

        // Add any posted messages.
        if (!empty($_GET['wc_error'])) {
            WC_Admin_Settings::add_error(wp_kses_post(wp_unslash($_GET['wc_error'])));
        }

        if (!empty($_GET['wc_message'])) {
            WC_Admin_Settings::add_message(wp_kses_post(wp_unslash($_GET['wc_message'])));
        }

        do_action('woocommerce_settings_page_init');
    }


    /**
     * A few simple checks that the plugin is working
     */
    public function debug_page_init(){
        global $current_tab, $current_section, $wpdb;

        $current_section = 'haven_gateway';
        $current_tab = 'debug';

        echo '<h1>Haven Payment Debug Page</h1>';
        echo '<h2>Database setup</h2>';
        echo '<pre>';
        foreach( array('haven_gateway_quotes', 'haven_gateway_quotes_txids', 'haven_gateway_live_rates') as $haven_table_name):
            $wp_table_name = $wpdb->prefix . $haven_table_name;
            $table_check = $wpdb->get_var("show tables like '$wp_table_name'");
            echo '<p>Checking database table &quot;' . $wp_table_name . '&quot; ';
            if( $table_check == $wp_table_name):
                echo '<span style="color:green">Found in database</span>';
            else:
                echo '<span style="color:red">Error, not found in database. Try deactivating and reactivating plugin, if that still doesn\'t help try removing and re-installing plugin</span>';
            endif;
            echo  '</p>';

        endforeach;
        echo '</pre>';
        


        //check cron settings
        $cron_jobs = get_option( 'cron' );
        echo '<h2>Scheduled Jobs</h2>';
        echo '<p>Note: the timings on the page show the cached page load data, the job can run and update these values during page load, these are the settings at the start of page load. When they get executed these time values update here on the next page load.</p>';

        foreach( $cron_jobs as $cron_timestamp => $cron_job_array){
            if( is_array($cron_job_array)){
                foreach( $cron_job_array as $cron_job_slug => $cron_job_data_array){
                    if( stripos($cron_job_slug, 'haven_' ) !== false  ){
                        echo '<h4>Job Slug: '.$cron_job_slug.'</h4>';
                        echo '<p>Ran at: ' . date('c', $cron_timestamp) . ' (' . $cron_timestamp . ')</p>';
                        //echo '<pre>';
                        //print_r($cron_job_data_array);
                        //echo '</pre>';
                    }
                }
            }

        }

        

        $next_haven_update_event = wp_next_scheduled('haven_update_event');
        if( $next_haven_update_event !== false ){
            echo '<h4>Next haven update</h4>';
            echo '<p>Net update due at: ' . date('c', $next_haven_update_event) . ' (' . $next_haven_update_event . ')</p>';
            $now_time = time();
            echo '<p>Server time now: ' . date('c', $now_time) . ' (' . $now_time . ') </p>';
        }else{
            echo '<h4>Error: No haven update scheduled. Try deactivating and reactivating the plugin</h4>';
        }


        $height = get_transient( 'haven_gateway_network_height' );
        echo '<p>Current cached gateway height: <b>' . $height . '</b></p>';

        is_haven_debug();

    }

}

return new Haven_Admin_Interface();
