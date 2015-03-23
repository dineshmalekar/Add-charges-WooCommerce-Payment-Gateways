<?php
/*
  Plugin Name: Add charges wooCommerce Payment Gateways
  Plugin URI: http://www.dineshmalekar.com
  Description: You can add payment charges to gateway.It works on coupon too.Gateway fee (which will be calculate on the discounted price(when coupon implement))
  Version: 1.0
  Author: Dinesh Malekar
  Author URI: http://www.dineshmalekar.com
 */

/**
 * Copyright (c) `date "+%Y"` Dinesh Malkear. All rights reserved.
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
 * **********************************************************************
 */
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

class WC_PaymentGateway_Add_Charges {

    public function __construct() {
        $this->current_gateway_title = '';
        $this->current_gateway_extra_charges = '';
        add_action('admin_head', array($this, 'add_form_fields'));
        add_action('woocommerce_calculate_totals', array($this, 'calculate_totals'), 10, 1);
        wp_enqueue_script('wc-add-extra-charges', $this->plugin_url() . '/assets/app.js', array('wc-checkout'), false, true);
    }

    function add_form_fields() {
        global $woocommerce;
        // Get current tab/section
        $current_tab = ( empty($_GET['tab']) ) ? '' : sanitize_text_field(urldecode($_GET['tab']));
        $current_section = ( empty($_REQUEST['section']) ) ? '' : sanitize_text_field(urldecode($_REQUEST['section']));
        if ($current_tab == 'checkout' && $current_section != '') {
            //$gateways = $woocommerce->payment_gateways->payment_gateways();
            $gateways1= $woocommerce->payment_gateways;
            $gateways=$gateways1->payment_gateways;
            foreach ($gateways as $gateway) {
                if (strtolower(get_class($gateway)) == $current_section) {
                    $current_gateway = $gateway->id;
                    $extra_charges_id = 'woocommerce_' . $current_gateway . '_extra_charges';
                    $extra_charges_type = $extra_charges_id . '_type';
                    if (isset($_REQUEST['save'])) {
                        update_option($extra_charges_id, $_REQUEST[$extra_charges_id]);
                        update_option($extra_charges_type, $_REQUEST[$extra_charges_type]);
                    }
                    $extra_charges = get_option($extra_charges_id);
                    $extra_charges_type_value = get_option($extra_charges_type);
                }
            }
            ?>
            <script>
                jQuery(document).ready(function($) {
                    $data = '<h4>Add Extra Charges</h4><table class="form-table">';
                    $data += '<tr valign="top">';
                    $data += '<th scope="row" class="titledesc">Extra Charges</th>';
                    $data += '<td class="forminp">';
                    $data += '<fieldset>';
                    $data += '<input style="" name="<?php echo $extra_charges_id ?>" id="<?php echo $extra_charges_id ?>" type="text" value="<?php echo $extra_charges ?>"/>';
                    $data += '<br /></fieldset></td></tr>';
                    $data += '<tr valign="top">';
                    $data += '<th scope="row" class="titledesc">Extra Charges Type</th>';
                    $data += '<td class="forminp">';
                    $data += '<fieldset>';
                    $data += '<select name="<?php echo $extra_charges_type ?>">';
            //                    $data += '<option <?php if ($extra_charges_type_value == "add") echo "selected=selected" ?> value="add">Total Add</option>';
                    $data += '<option <?php if ($extra_charges_type_value == "percentage") echo "selected=selected" ?> value="percentage">Total % Add</option>';
                    $data += '<br /></fieldset></td></tr></table>';
                    $('.form-table:last').after($data);

                });
            </script>
            <?php
        }
    }

    public function calculate_totals($totals) {
        global $woocommerce;
        
         $gateways = $woocommerce->payment_gateways;
         $available_gateways=$gateways->payment_gateways;
         
        $current_gateway = '';
        if (!empty($available_gateways)) {
              $chosen_payment_method = WC()->session->get('chosen_payment_method');
              if (isset($chosen_payment_method)) {
                $current_gateway = $chosen_payment_method;
            } 
        }
       
        if ($current_gateway != '') {
           
            $extra_charges_id = 'woocommerce_' . $current_gateway . '_extra_charges';
            $extra_charges_type = $extra_charges_id . '_type';
           
            $extra_charges = (float) get_option($extra_charges_id);

            $extra_charges_type_value = get_option($extra_charges_type);
            
                     

            if(!empty($totals->discount_total)){
                $charge = (($totals->cart_contents_total-$totals->discount_total) * $extra_charges) / 100;
            }else{
                $charge = ($totals->cart_contents_total * $extra_charges) / 100;
            }
            
           
            $this->current_gateway_extra_charges_amount = number_format((float) $charge, 2, '.', '');
            
            if ($extra_charges) {
               if ($extra_charges_type_value == "percentage") {
                    $total_amount = $totals->cart_contents_total + $charge;
                    
                } else {
                    $total_amount = $totals->cart_contents_total + $charge;
                }
                $totals->cart_contents_total = $total_amount;
                $this->current_gateway_title = $current_gateway->title;
                $this->current_gateway_extra_charges = $extra_charges;

                $this->current_gateway_extra_charges_type_value = $extra_charges_type_value;
                add_action('woocommerce_review_order_before_order_total', array($this, 'add_payment_gateway_extra_charges_row'));
            }
        }
        return $totals;
    }

    function add_payment_gateway_extra_charges_row() {
       
        ?>
        <tr class="cart-subtotal payment-extra-charge">
            <th><?php echo $this->current_gateway_extra_charges . '% Credit Card Fee' ?></th>
            <td></td>
            <td><span class="amount">$<?php echo $this->current_gateway_extra_charges_amount ?></span></td>
        </tr>
        <?php
    }

    /**
     * Get the plugin url.
     *
     * @access public
     * @return string
     */
    public function plugin_url() {
        if ($this->plugin_url)
            return $this->plugin_url;
        return $this->plugin_url = untrailingslashit(plugins_url('/', __FILE__));
    }

    /**
     * Get the plugin path.
     *
     * @access public
     * @return string
     */
    public function plugin_path() {
        if ($this->plugin_path)
            return $this->plugin_path;

        return $this->plugin_path = untrailingslashit(plugin_dir_path(__FILE__));
    }

}

new Wc_PaymentGateway_Add_Charges();