<?php
/*
Plugin Name: WC Product Mailpoet Subscribe
Plugin URI: http://sabbirahmed.me/
Description: This is a simple woocommerce and mailpoet base plugin for auto subscribing users by his/her buying products
Version: 0.1
Author: Sabbir Ahmed
Author URI: http://sabbirahmed.com/
License: GPL2
*/

/**
 * Copyright (c) 2014 Sabbir Ahmed (email: sabbir.081070@gmail.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

// don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * WC_Product_Mailpoet class
 *
 * @class WC_Product_Mailpoet The class that holds the entire WC_Product_Mailpoet plugin
 */
class WC_Product_Mailpoet {

    /**
     * Constructor for the WC_Product_Mailpoet class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     *
     * @uses register_activation_hook()
     * @uses register_deactivation_hook()
     * @uses is_admin()
     * @uses add_action()
     */
    public function __construct() {

        if ( !class_exists( 'WYSIJA' ) ) {
            return;
        }

        // Localize our plugin
        add_action( 'init', array( $this, 'localization_setup' ) );

        //add_action( 'admin_init', array( $this, 'load_metabox_in_product_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'load_metabox_in_product_post_type' ) );

        // Loads frontend scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Trigger when post is saved
        add_action( 'save_post', array( $this, 'product_subscribe_list_save' ) );

        // Trigger hwen woocommerce product status update
        add_action( 'woocommerce_order_status_changed', array( $this, 'save_user_in_subscriber_list' ), 10, 3 );
    }

    /**
     * Initializes the WC_Product_Mailpoet() class
     *
     * Checks for an existing WC_Product_Mailpoet() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new WC_Product_Mailpoet();
        }

        return $instance;
    }

    /**
     * Initialize plugin for localization
     *
     * @uses load_plugin_textdomain()
     */
    public function localization_setup() {
        load_plugin_textdomain( 'wc_product_mailpoet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Enqueue admin scripts
     *
     * Allows plugin assets to be loaded.
     *
     * @uses wp_enqueue_script()
     * @uses wp_localize_script()
     * @uses wp_enqueue_style
     */
    public function enqueue_scripts() {

        /**
         * All styles goes here
         */
        wp_enqueue_style( 'wc_product_mailpoet-styles', plugins_url( 'css/style.css', __FILE__ ), false, date( 'Ymd' ) );
    }

    /**
     *  Load Metabox in product post type
     */
    public function load_metabox_in_product_post_type() {
        add_meta_box( 'product_mailpoet', __( 'MailPoet Subscriber List ', 'wc_product_mailpoet' ), array( $this, 'product_subscribe_call' ), 'product', 'side', 'high' );
    }

    /**
     * Dsiplay Subscriber list
     *
     * Callback function of add_meta_box in function load_metabox_in_product_post_type()
     *
     * @param ofject  $post
     */
    public function product_subscribe_call( $post ) {

        $modelList = WYSIJA::get( 'list', 'model' );
        $wysijaLists = $modelList->get( array( 'name', 'list_id' ), array( 'is_enabled' => 1 ) );

        $selected = get_post_meta( $post->ID, '_product_subscriber_list', true );
        $selected = is_array( $selected ) ? $selected : array();
        ?>
        <div id="product_mailpoet_list">
            <?php if ( $wysijaLists ): ?>
                <?php wp_nonce_field( 'product_mailpoet_list_nonce', 'product_mailpoet_noncename' ); ?>
                <?php foreach ( $wysijaLists as $list ) : ?>
                        <?php printf( '<label class="%s"><input type="checkbox" name="%s[]" value="%d" %s> %s </label>', 'mailpoet_list', 'product_subscriber_list', $list['list_id'], ( in_array( $list['list_id'], $selected ) ) ? 'checked="checked"' : '' , $list['name'] ); ?>
                <?php endforeach; ?>

            <?php else: ?>
            <div class="description">
                <?php _e( 'No list found', 'wc_product_mailpoet' ) ?>
            </div>
            <?php endif; ?>
         </div>
        <?php
    }

    /**
     * Porduct Subscriber list save in post meta correspond to product
     *
     * @param integer $post_id
     * @return null
     */
    public function product_subscribe_list_save( $post_id ) {

        // verify if this is an auto save routine.
        // If it is our form has not been submitted, so we dont want to do anything
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // verify this came from the our screen and with proper authorization,
        // because save_post can be triggered at other times
        // if our nonce isn't there, or we can't verify it, bail
        if ( !isset( $_POST['product_mailpoet_noncename'] ) || !wp_verify_nonce( $_POST['product_mailpoet_noncename'], 'product_mailpoet_list_nonce' ) ) {
            return;
        }

        // Check permissions
        if ( ( isset ( $_POST['post_type'] ) ) && ( 'page' == $_POST['post_type'] )  ) {
            if ( ! current_user_can( 'edit_page', $post_id ) ) {
                return;
            }
        } else {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }
        }

        // OK, we're authenticated: we need to find and save the data
        if ( isset ( $_POST['product_subscriber_list'] ) ) {
            update_post_meta( $post_id, '_product_subscriber_list', $_POST['product_subscriber_list'] );
        }
    }

    /**
     * Save User in subscriber list
     *
     * Save user subscriber list when woocommerce order
     * is placed. It only add user in subscriber list when
     * order is completed.
     *
     * @param [type]  $order_id   [description]
     * @param [type]  $old_status [description]
     * @param [type]  $new_status [description]
     * @return [type]             [description]
     */
    public function save_user_in_subscriber_list( $order_id, $old_status, $new_status ) {

        $final_array = array();
        $list_ids = array();

        if ( $new_status == 'completed' ) {
            $order = new WC_Order( $order_id );
            $items = $order->get_items();

            foreach ( $items as $item ) {

                $id = get_post_meta( $item['product_id'], '_product_subscriber_list', true );

                if ( $id == '' && empty( $id ) ) {
                    continue;
                }

                $list_id[] = $id;
            }

            foreach ( $list_id as $val ) {
                foreach ( $val as $val2 ) {
                    $ids[] = $val2;
                }
            }

            $list_ids = array_unique( $ids );

            if ( !count( $list_ids ) ) {
                return;
            }

            $this->save_list_ids_in_subscriber_list ( $order_id, $list_ids );
        }
    }

    /**
     * Load Mailpoet core file and save user subscriber list
     *
     * @param integer $order_id
     * @param array   $list_ids
     * @return null
     */
    public function save_list_ids_in_subscriber_list( $order_id, $list_ids ) {

        $user_id = get_post_meta( $order_id, '_customer_user', true );

        if ( $user_id == 0 ) {

            $user_email = get_post_meta( $order_id, '_billing_email', true );
            $user_firstname = get_post_meta( $order_id, '_billing_first_name', true );
            $userData = array( 'email' => $user_email, 'firstname' => $user_firstname );

        } else {

            $user = get_user_by( 'id', $user_id );

            if ( $user->first_name && 'false' !== $user->first_name ) {
                $userData = array( 'email' => $user->user_email, 'firstname' => $user->first_name );
            } else {
                $userData = array( 'email' => $user->user_email );
            }

        }

        $data = array(
            'user'      => $userData,
            'user_list' => array( 'list_ids' => $list_ids )
        );

        // Add subscriber to MailPoet.
        $userHelper = WYSIJA::get( 'user', 'helper' );
        $userHelper->addSubscriber( $data );
    }

} // WC_Product_Mailpoet

add_action( 'plugins_loaded', 'initialte_wc_product_mailpoet' );

function initialte_wc_product_mailpoet() {
    $wc_product_mailpoet = WC_Product_Mailpoet::init();
}
