<?php

/**
 * @package WooCommerce Related Categories
 */
/*
Plugin Name: WooCommerce Related Categories
Plugin URI: https://github.com/sagaio/woocommerce-related-categories
Description: Define related categories and optionally display them under each product.
Version: 1.0.0
Author: SAGAIO
Author URI: http://www.sagaio.com
License: GPL-2.0
*/

defined( 'ABSPATH' ) or die( 'Aborted.' );
define('WP_DEBUG', true);


class WooCommerce_Related_Categories {

    static function init() {
        add_action( 'load-edit-tags.php', array( __CLASS__, 'handler' ) );
        add_action( 'admin_notices', array( __CLASS__, 'notice' ) );

        load_plugin_textdomain( 'woocommerce-related-categories', '', basename( dirname( __FILE__ ) ) . '/lang' );
    }

    private static function get_actions( $taxonomy ) {

        $actions = [];

        if ( is_taxonomy_hierarchical( $taxonomy ) ) {
            $actions = array_merge( array(
                'set_related'        => __( 'Set related', 'woocommerce-related-categories' ),
            ), $actions);
        }

        return $actions;
    }

    static function handler() {
        $defaults = array(
            'taxonomy' => 'post_tag',
            'delete_tags' => false,
            'action' => false,
        );

        $data = shortcode_atts( $defaults, $_REQUEST );

        $tax = get_taxonomy( $data['taxonomy'] );
        if ( !$tax )
            return;

        if ( !current_user_can( $tax->cap->manage_terms ) )
            return;

        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'script' ) );
        add_action( 'admin_footer', array( __CLASS__, 'inputs' ) );

        $action = false;
        foreach ( array( 'action' ) as $key ) {
            if ( $data[ $key ] && '-1' != $data[ $key ] ) {
                $action = $data[ $key ];
            }
        }

        if ( !$action )
            return;

        self::delegate_handling( $action, $data['taxonomy'], $data['delete_tags'] );
    }

    protected static function delegate_handling( $action, $taxonomy, $term_ids ) {
        if ( empty( $term_ids ) )
            return;

        foreach ( array_keys( self::get_actions( $taxonomy ) ) as $key ) {
            if ( 'bulk_' . $key == $action ) {
                check_admin_referer( 'bulk-tags' );
                $r = call_user_func( array( __CLASS__, 'handle_' . $key ), $term_ids, $taxonomy );
                break;
            }
        }

        if ( !isset( $r ) )
            return;

        $referer = wp_get_referer();
        if ( $referer && false !== strpos( $referer, 'edit-tags.php' ) ) {
            $location = $referer;
        } else {
            $location = add_query_arg( 'taxonomy', $taxonomy, 'edit-tags.php' );
        }

        if ( isset( $_REQUEST['post_type'] ) && 'post' != $_REQUEST['post_type'] ) {
            $location = add_query_arg( 'post_type', $_REQUEST['post_type'], $location );
        }

        wp_redirect( add_query_arg( 'message', $r ? 'sagaio-wrc-updated' : 'sagaio-wrc-error', $location ) );
        die;
    }

    static function notice() {
        if ( !isset( $_GET['message'] ) )
            return;

        switch ( $_GET['message'] ) {
        case  'sagaio-wrc-updated':
            echo '<div id="message" class="updated"><p>' . __( 'Terms updated.', 'woocommerce-related-categories' ) . '</p></div>';
            break;

        case 'sagaio-wrc-error':
            echo '<div id="message" class="error"><p>' . __( 'Terms not updated.', 'woocommerce-related-categories' ) . '</p></div>';
            break;
        }
    }

    static function handle_set_related( $term_ids, $taxonomy ) {

        // Get the "source"
        $source_term = $_REQUEST['source_term'];

        // And make sure that it actually exist
        if ( !taxonomy_exists( $source_term ) )
            return false;

        foreach ( $term_ids as $term_id ) {

            // Don't relate the ID to itself, just continue looping
            if ( $term_id == $source_term )
                continue;

            // Create an array of all the terms that shall be related
            $terms_related[] = array($term_id->term_taxonomy_id);

        }

        $query = wp_update_term( $source_term, $taxonomy, array( 'related_terms' => $terms_related ) );

        if ( is_wp_error( $ret ) )
            return false;

        // Clear the cache
        clean_term_cache( $terms_related, $taxonomy );

        return true;
    }

    static function script() {
        global $taxonomy;

        $dev_mode = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';

        wp_enqueue_script( 'woocommerce-related-categories', plugins_url( "js/script$dev_mode.js", __FILE__ ), array( 'jquery' ), '1.1' );

        wp_localize_script( 'woocommerce-related-categories', 'sagaioWRC', self::get_actions( $taxonomy ) );
    }

    static function inputs() {
        global $taxonomy;

        foreach ( array_keys( self::get_actions( $taxonomy ) ) as $key ) {
            echo "<div id='sagaio-wrc-input-$key' style='display:none'>\n";
            call_user_func( array( __CLASS__, 'input_' . $key ), $taxonomy );
            echo "</div>\n";
        }
    }

    static function input_set_related( $taxonomy ) {

        wp_dropdown_categories( array(
          'hide_empty'        => 0,
          'hierarchical'      => true,
          'name'              => 'source_term',
          'class'             => 'sagaio-wrc-select',
          'taxonomy'          => $taxonomy,
          'hide_if_empty'     => false,
          'orderby'           => 'name',
          'show_option_none' => __( 'None', 'woocommerce-related-categories' )
      ));


    }
}

WooCommerce_Related_Categories::init();
