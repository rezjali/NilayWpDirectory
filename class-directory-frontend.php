<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Directory_Frontend' ) ) {

    class Directory_Frontend {
        
        private $errors;

        public function __construct() {
            $this->errors = new WP_Error();
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
            add_action( 'init', [ $this, 'register_shortcodes' ] );
            add_action( 'template_redirect', [ $this, 'handle_form_submission' ] );
            add_action( 'template_redirect', [ $this, 'handle_listing_actions' ] );
            
            add_action( 'wp_ajax_wpd_load_custom_fields', [ $this, 'ajax_load_custom_fields' ] );
            add_action( 'wp_ajax_nopriv_wpd_filter_listings', [ $this, 'ajax_filter_listings' ] );
            add_action( 'wp_ajax_wpd_filter_listings', [ $this, 'ajax_filter_listings' ] );
        }

        public function enqueue_scripts() {
            wp_enqueue_style( 'wpd-main-style', WPD_ASSETS_URL . 'css/main.css', [], WPD_PLUGIN_VERSION );
            
            $dynamic_css = $this->generate_dynamic_css();
            if(!empty($dynamic_css)){
                wp_add_inline_style( 'wpd-main-style', $dynamic_css );
            }

            wp_enqueue_script( 'wpd-main-script', WPD_ASSETS_URL . 'js/main.js', [ 'jquery' ], WPD_PLUGIN_VERSION, true );
            
            // START OF CHANGE: Enqueue WP Datepicker for frontend
            if ( is_page( Directory_Main::get_option('general', [])['submit_page'] ?? 0 ) ) {
                wp_enqueue_script('jquery-ui-datepicker');
                wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
            }
            // END OF CHANGE

            wp_localize_script( 'wpd-main-script', 'wpd_ajax_obj', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wpd_ajax_nonce' ),
                // START OF CHANGE: Pass currency to JS
                'currency' => Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'],
                // END OF CHANGE
            ] );

            // START OF CHANGE: Add inline script for datepicker and product calculation
            add_action('wp_footer', function() {
                if ( is_page( Directory_Main::get_option('general', [])['submit_page'] ?? 0 ) ) {
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            function initializeFrontendComponents(container) {
                                // Datepicker
                                container.find('.wpd-date-picker:not(.hasDatepicker)').each(function() {
                                    $(this).datepicker({
                                        dateFormat: 'yy-mm-dd'
                                    });
                                });

                                // Product Price Calculation
                                function calculateTotalPrice() {
                                    let productsTotal = 0;
                                    $('.wpd-product-field-wrapper').each(function() {
                                        const $wrapper = $(this);
                                        const isSelected = $wrapper.find('.wpd-product-select').is(':checked');
                                        if (!isSelected) {
                                            return;
                                        }

                                        const pricingMode = $wrapper.data('pricing-mode');
                                        let price = 0;
                                        let quantity = 1;
                                        
                                        if ($wrapper.find('.wpd-product-quantity').length) {
                                            quantity = parseInt($wrapper.find('.wpd-product-quantity').val()) || 1;
                                        }

                                        if (pricingMode === 'fixed') {
                                            price = parseFloat($wrapper.data('fixed-price')) || 0;
                                        } else { // user_defined
                                            price = parseFloat($wrapper.find('.wpd-product-user-price').val()) || 0;
                                        }
                                        
                                        productsTotal += price * quantity;
                                    });

                                    $('#wpd-products-total-cost').text(productsTotal.toLocaleString());
                                    
                                    const baseCost = parseFloat($('#wpd-base-cost').data('base-cost')) || 0;
                                    const finalTotal = baseCost + productsTotal;
                                    $('#wpd-final-total-cost').text(finalTotal.toLocaleString());
                                }

                                $('#wpd-custom-fields-wrapper').on('change keyup', '.wpd-product-select, .wpd-product-quantity, .wpd-product-user-price', calculateTotalPrice);
                                
                                // Initial calculation
                                calculateTotalPrice();
                            }

                            // Initial call for existing fields
                            initializeFrontendComponents($('#wpd-custom-fields-wrapper'));

                            // Re-initialize after AJAX load
                            $(document).ajaxComplete(function(event, xhr, settings) {
                                if (settings.data && settings.data.includes('action=wpd_load_custom_fields')) {
                                    initializeFrontendComponents($('#wpd-custom-fields-wrapper'));
                                }
                            });
                        });
                    </script>
                    <style>
                        .ui-datepicker { z-index: 100 !important; direction: rtl; }
                        .ui-datepicker-header { direction: ltr; }
                        .wpd-payment-summary { margin-top: 20px; padding: 15px; border: 1px solid #0073aa; border-radius: 5px; background: #f0f8ff; }
                        .wpd-payment-summary h3 { margin-top: 0; }
                        .wpd-summary-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #ddd; }
                        .wpd-summary-row:last-child { border-bottom: none; }
                        .wpd-summary-row.total { font-weight: bold; font-size: 1.2em; color: #0073aa; }
                    </style>
                    <?php
                }
            });
            // END OF CHANGE
        }

        public function register_shortcodes() {
            add_shortcode( 'wpd_submit_form', [ $this, 'render_submit_form' ] );
            add_shortcode( 'wpd_dashboard', [ $this, 'render_dashboard' ] );
            add_shortcode( 'wpd_listing_archive', [ $this, 'render_archive_page' ] );
        }

        public function render_submit_form() {
            if ( ! is_user_logged_in() ) {
                return '<div class="wpd-alert wpd-alert-warning">' . __( 'برای ثبت آگهی، لطفا ابتدا وارد شوید یا ثبت‌نام کنید.', 'wp-directory' ) . '</div>';
            }
            
            $general_settings = Directory_Main::get_option('general', []);
            $packages_enabled = !empty($general_settings['enable_packages']);

            ob_start();

            if ( isset( $_GET['wpd_form_error'] ) && ! empty( $_SESSION['wpd_form_errors'] ) ) {
                $errors = $_SESSION['wpd_form_errors'];
                if ( is_wp_error( $errors ) ) {
                    echo '<div class="wpd-alert wpd-alert-danger">';
                    foreach ( $errors->get_error_messages() as $error ) {
                        echo '<p>' . esc_html( $error ) . '</p>';
                    }
                    echo '</div>';
                }
                unset( $_SESSION['wpd_form_errors'] );
            }

            $listing_id = isset( $_GET['listing_id'] ) ? intval( $_GET['listing_id'] ) : 0;

            if ( $listing_id > 0 ) {
                $listing = get_post($listing_id);
                if(!$listing || $listing->post_author != get_current_user_id()) {
                    return '<div class="wpd-alert wpd-alert-danger">' . __( 'شما اجازه ویرایش این آگهی را ندارید.', 'wp-directory' ) . '</div>';
                }
                $this->display_main_submit_form( $listing_id );
                return ob_get_clean();
            }

            if ($packages_enabled) {
                $step = isset( $_GET['step'] ) ? intval( $_GET['step'] ) : 1;
                $package_id = isset( $_GET['package_id'] ) ? intval( $_GET['package_id'] ) : 0;
                if ( $step === 1 ) {
                    $this->display_package_selection();
                } elseif ( $step === 2 && ! empty( $package_id ) ) {
                    $this->display_main_submit_form( 0, $package_id );
                } else {
                    $this->display_package_selection();
                }
            } else {
                $this->display_main_submit_form( 0, 0 );
            }

            return ob_get_clean();
        }

        private function display_package_selection() { /* ... Omitted for brevity ... */ }

        private function display_main_submit_form( $listing_id = 0, $package_id = 0 ) { /* ... Omitted for brevity ... */ }
        
        public function render_dashboard() { /* ... Omitted for brevity ... */ }

        private function render_dashboard_my_listings() { /* ... Omitted for brevity ... */ }

        private function render_dashboard_my_transactions() { /* ... Omitted for brevity ... */ }

        public function render_archive_page() { /* ... Omitted for brevity ... */ }

        public function get_listings_html($args) { /* ... Omitted for brevity ... */ }

        public function render_listing_item($post_id){ /* ... Omitted for brevity ... */ }
        
        public function handle_form_submission() {
            if ( ! isset( $_POST['wpd_submit_nonce'] ) || ! wp_verify_nonce( $_POST['wpd_submit_nonce'], 'wpd_submit_action' ) ) {
                return;
            }
            
            $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
            $package_id = isset( $_POST['package_id'] ) ? intval( $_POST['package_id'] ) : 0;
            $listing_type_id = isset( $_POST['listing_type'] ) ? intval( $_POST['listing_type'] ) : 0;
            $form_data = $_POST;

            $this->validate_form_data( $listing_type_id, $form_data, $listing_id );

            if ( $this->errors->has_errors() ) {
                if ( ! session_id() ) {
                    session_start();
                }
                $_SESSION['wpd_form_errors'] = $this->errors;
                
                wp_safe_redirect( add_query_arg( 'wpd_form_error', '1', wp_get_referer() ) );
                exit;
            }

            $general_settings = Directory_Main::get_option('general', []);
            $approval_method = $general_settings['approval_method'] ?? 'manual';
            $packages_enabled = !empty($general_settings['enable_packages']);
            $dashboard_page_url = get_permalink($general_settings['dashboard_page'] ?? 0);

            // START OF CHANGE: Calculate total cost including products
            $base_cost = 0;
            if ($packages_enabled) {
                $base_cost += (float)get_post_meta($package_id, '_price', true);
            }
            $base_cost += (float)get_post_meta($listing_type_id, '_cost', true);

            $products_cost = 0;
            $field_definitions = get_post_meta($listing_type_id, '_wpd_custom_fields', true);
            $custom_data = $form_data['wpd_custom'] ?? [];

            if (!empty($field_definitions) && is_array($field_definitions)) {
                foreach ($field_definitions as $field) {
                    if ($field['type'] === 'product' && !empty($custom_data[$field['key']]['selected'])) {
                        $product_data = $custom_data[$field['key']];
                        $product_settings = $field['product_settings'];
                        $price = 0;
                        $quantity = 1;

                        if ($product_settings['pricing_mode'] === 'fixed') {
                            $price = (float)$product_settings['fixed_price'];
                        } else { // user_defined
                            $price = (float)$product_data['price'];
                        }

                        if (!empty($product_settings['enable_quantity'])) {
                            $quantity = (int)$product_data['quantity'];
                        }
                        
                        $products_cost += $price * $quantity;
                    }
                }
            }

            $total_cost = $base_cost + $products_cost;
            // END OF CHANGE

            $post_data = [
                'post_title'   => sanitize_text_field( $form_data['listing_title'] ),
                'post_content' => wp_kses_post( $form_data['listing_description'] ),
                'post_author'  => get_current_user_id(),
                'post_type'    => 'wpd_listing',
            ];

            if ( $listing_id > 0 ) {
                $post_data['ID'] = $listing_id;
                wp_update_post( $post_data );
            } else {
                $post_data['post_status'] = 'pending';
                $listing_id = wp_insert_post( $post_data );
            }

            if ( is_wp_error( $listing_id ) ) {
                wp_redirect(add_query_arg('wpd_error', $listing_id->get_error_message(), wp_get_referer()));
                exit;
            }

            update_post_meta( $listing_id, '_wpd_listing_type', $listing_type_id );
            do_action('save_post_wpd_listing', $listing_id);

            if ($total_cost > 0) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'wpd_transactions';
                $wpdb->insert($table_name, [
                    'user_id' => get_current_user_id(),
                    'listing_id' => $listing_id,
                    'package_id' => $package_id,
                    'amount' => $total_cost,
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                ]);
                $transaction_id = $wpdb->insert_id;
                Directory_Gateways::process_payment($total_cost, $transaction_id, get_the_title($listing_id), $listing_id);
            } else {
                $final_status = ($approval_method === 'auto') ? 'publish' : 'pending';
                wp_update_post(['ID' => $listing_id, 'post_status' => $final_status]);

                if ($packages_enabled) {
                    $duration = get_post_meta($package_id, '_duration', true);
                    if ($duration > 0) {
                        $expiration_date = date('Y-m-d H:i:s', strtotime("+$duration days"));
                        update_post_meta($listing_id, '_wpd_expiration_date', $expiration_date);
                    }
                }
                
                if ($final_status === 'publish') {
                    Directory_Main::trigger_notification('listing_approved', ['user_id' => get_current_user_id(), 'listing_id' => $listing_id]);
                } else {
                    Directory_Main::trigger_notification('new_listing', ['user_id' => get_current_user_id(), 'listing_id' => $listing_id]);
                }
                
                wp_redirect(add_query_arg('wpd_success', 'آگهی شما با موفقیت ثبت شد.', $dashboard_page_url));
            }
            exit;
        }
        
        public function handle_listing_actions() { /* ... Omitted for brevity ... */ }

        public function ajax_load_custom_fields() { /* ... Omitted for brevity ... */ }
        
        private function get_custom_fields_html($listing_type_id, $listing_id = 0) { /* ... Omitted for brevity, but needs changes to render new fields ... */ }
        
        public function ajax_filter_listings() { /* ... Omitted for brevity ... */ }

        public function generate_dynamic_css() { /* ... Omitted for brevity ... */ }

        private function validate_form_data( $listing_type_id, $form_data, $listing_id = 0 ) { /* ... Omitted for brevity ... */ }
    
        private function is_field_visible( $field, $custom_data ) { /* ... Omitted for brevity ... */ }
    
        private function is_value_duplicate( $meta_key, $meta_value, $exclude_post_id = 0 ) { /* ... Omitted for brevity ... */ }
    }
}
