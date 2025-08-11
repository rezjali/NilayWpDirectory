<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Directory_Frontend' ) ) {

    class Directory_Frontend {
        
        // START OF CHANGE: Add a property to store errors
        private $errors;
        // END OF CHANGE

        public function __construct() {
            // START OF CHANGE: Initialize errors array
            $this->errors = new WP_Error();
            // END OF CHANGE
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
            
            // START OF CHANGE: No longer needed as date fields are removed from frontend logic for now
            // wp_enqueue_style( 'persian-datepicker-style', 'https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css' );
            // wp_enqueue_script( 'persian-date', 'https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js', [], null, true );
            // wp_enqueue_script( 'persian-datepicker-script', 'https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js', [ 'jquery', 'persian-date' ], null, true );
            // END OF CHANGE

            wp_localize_script( 'wpd-main-script', 'wpd_ajax_obj', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wpd_ajax_nonce' ),
            ] );
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

            // START OF CHANGE: Display validation errors
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
            // END OF CHANGE

            $listing_id = isset( $_GET['listing_id'] ) ? intval( $_GET['listing_id'] ) : 0;

            // حالت ویرایش آگهی
            if ( $listing_id > 0 ) {
                $listing = get_post($listing_id);
                if(!$listing || $listing->post_author != get_current_user_id()) {
                    return '<div class="wpd-alert wpd-alert-danger">' . __( 'شما اجازه ویرایش این آگهی را ندارید.', 'wp-directory' ) . '</div>';
                }
                $this->display_main_submit_form( $listing_id );
                return ob_get_clean();
            }

            // حالت ثبت آگهی جدید
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
                // اگر سیستم بسته‌ها غیرفعال بود، مستقیم فرم را نمایش بده
                $this->display_main_submit_form( 0, 0 );
            }

            return ob_get_clean();
        }

        private function display_package_selection() { /* ... کد قبلی بدون تغییر ... */ }

        private function display_main_submit_form( $listing_id = 0, $package_id = 0 ) { /* ... کد قبلی بدون تغییر ... */ }
        
        public function render_dashboard() {
            ob_start();
            if (isset($_GET['wpd_error'])) {
                echo '<div class="wpd-alert wpd-alert-danger">' . esc_html(urldecode($_GET['wpd_error'])) . '</div>';
            }
            if (isset($_GET['wpd_success'])) {
                echo '<div class="wpd-alert wpd-alert-success">' . esc_html(urldecode($_GET['wpd_success'])) . '</div>';
            }
            // بقیه کد داشبورد بدون تغییر
            ?>
            <div class="wpd-container wpd-dashboard-container">
                <ul class="wpd-dashboard-nav">
                    <li><a href="#my-listings"><?php echo Directory_Main::get_term('my_listings'); ?></a></li>
                    <li><a href="#my-profile"><?php echo Directory_Main::get_term('my_profile'); ?></a></li>
                    <li><a href="#my-transactions"><?php echo Directory_Main::get_term('my_transactions'); ?></a></li>
                </ul>
                <div class="wpd-dashboard-content">
                    <div id="my-listings" class="wpd-tab-content">
                        <?php $this->render_dashboard_my_listings(); ?>
                    </div>
                    <div id="my-profile" class="wpd-tab-content">
                        <?php echo do_shortcode('[wpuf_profile type="profile" id="1"]'); // Example shortcode for profile editing ?>
                    </div>
                    <div id="my-transactions" class="wpd-tab-content">
                        <?php $this->render_dashboard_my_transactions(); ?>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_dashboard_my_listings() {
            $user_id = get_current_user_id();
            $listings = get_posts(['post_type' => 'wpd_listing', 'author' => $user_id, 'posts_per_page' => -1, 'post_status' => ['publish', 'pending', 'draft', 'expired']]);
            $submit_page_url = get_permalink( Directory_Main::get_option( 'general', [] )['submit_page'] ?? 0 );
            ?>
            <h3><?php echo Directory_Main::get_term('my_listings'); ?></h3>
            <table class="wpd-table">
                <thead>
                    <tr>
                        <th><?php echo Directory_Main::get_term('listing_title'); ?></th>
                        <th><?php echo Directory_Main::get_term('status'); ?></th>
                        <th><?php echo Directory_Main::get_term('expires_on'); ?></th>
                        <th><?php _e('عملیات', 'wp-directory'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($listings)): foreach($listings as $listing): 
                        $expiration_date = get_post_meta($listing->ID, '_wpd_expiration_date', true);
                    ?>
                    <tr>
                        <td><a href="<?php echo get_permalink($listing->ID); ?>"><?php echo esc_html($listing->post_title); ?></a></td>
                        <td><?php echo Directory_Main::get_term($listing->post_status, true); ?></td>
                        <td><?php echo !empty($expiration_date) ? date_i18n('Y/m/d', strtotime($expiration_date)) : '---'; ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('listing_id', $listing->ID, $submit_page_url)); ?>"><?php _e('ویرایش', 'wp-directory'); ?></a> | 
                            <a href="<?php echo wp_nonce_url(add_query_arg(['wpd_action' => 'delete_listing', 'listing_id' => $listing->ID]), 'wpd_delete_listing_nonce', '_wpnonce'); ?>" onclick="return confirm('آیا از حذف این آگهی مطمئن هستید؟');"><?php _e('حذف', 'wp-directory'); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="4"><?php _e('شما هنوز هیچ آگهی ثبت نکرده‌اید.', 'wp-directory'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php
        }

        private function render_dashboard_my_transactions() { /* ... کد قبلی بدون تغییر ... */ }

        public function render_archive_page() { /* ... کد قبلی بدون تغییر ... */ }

        public function get_listings_html($args) { /* ... کد قبلی بدون تغییر ... */ }

        public function render_listing_item($post_id){ /* ... کد قبلی بدون تغییر ... */ }
        
        public function handle_form_submission() {
            if ( ! isset( $_POST['wpd_submit_nonce'] ) || ! wp_verify_nonce( $_POST['wpd_submit_nonce'], 'wpd_submit_action' ) ) {
                return;
            }

            // START OF CHANGE: Major rewrite of submission handling to include validation
            
            $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
            $package_id = isset( $_POST['package_id'] ) ? intval( $_POST['package_id'] ) : 0;
            $listing_type_id = isset( $_POST['listing_type'] ) ? intval( $_POST['listing_type'] ) : 0;
            $form_data = $_POST;

            // 1. Validate the form data
            $this->validate_form_data( $listing_type_id, $form_data, $listing_id );

            // 2. Check for errors
            if ( $this->errors->has_errors() ) {
                // Store errors in session to display after redirect
                if ( ! session_id() ) {
                    session_start();
                }
                $_SESSION['wpd_form_errors'] = $this->errors;
                
                // Redirect back to the form with an error flag
                wp_safe_redirect( add_query_arg( 'wpd_form_error', '1', wp_get_referer() ) );
                exit;
            }

            // 3. If validation passes, proceed with saving
            $general_settings = Directory_Main::get_option('general', []);
            $approval_method = $general_settings['approval_method'] ?? 'manual';
            $packages_enabled = !empty($general_settings['enable_packages']);
            $dashboard_page_url = get_permalink($general_settings['dashboard_page'] ?? 0);

            // محاسبه هزینه نهایی
            $total_cost = 0;
            $package_cost = $packages_enabled ? (float)get_post_meta($package_id, '_price', true) : 0;
            $type_cost = (float)get_post_meta($listing_type_id, '_cost', true);
            $total_cost = $package_cost + $type_cost;

            // ایجاد یا بروزرسانی پست آگهی
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
                $post_data['post_status'] = 'pending'; // همیشه با وضعیت pending ایجاد کن
                $listing_id = wp_insert_post( $post_data );
            }

            if ( is_wp_error( $listing_id ) ) {
                wp_redirect(add_query_arg('wpd_error', $listing_id->get_error_message(), wp_get_referer()));
                exit;
            }

            // ذخیره متادیتا
            update_post_meta( $listing_id, '_wpd_listing_type', $listing_type_id );
            do_action('save_post_wpd_listing', $listing_id);

            // تصمیم‌گیری برای پرداخت یا ثبت نهایی
            if ($total_cost > 0) {
                // ایجاد تراکنش و هدایت به درگاه
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
                // ثبت آگهی رایگان
                $final_status = ($approval_method === 'auto') ? 'publish' : 'pending';
                wp_update_post(['ID' => $listing_id, 'post_status' => $final_status]);

                // تنظیم تاریخ انقضا برای بسته رایگان
                if ($packages_enabled) {
                    $duration = get_post_meta($package_id, '_duration', true);
                    if ($duration > 0) {
                        $expiration_date = date('Y-m-d H:i:s', strtotime("+$duration days"));
                        update_post_meta($listing_id, '_wpd_expiration_date', $expiration_date);
                    }
                }
                
                // ارسال اعلان
                if ($final_status === 'publish') {
                    Directory_Main::trigger_notification('listing_approved', ['user_id' => get_current_user_id(), 'listing_id' => $listing_id]);
                } else {
                    Directory_Main::trigger_notification('new_listing', ['user_id' => get_current_user_id(), 'listing_id' => $listing_id]);
                }
                
                wp_redirect(add_query_arg('wpd_success', 'آگهی شما با موفقیت ثبت شد.', $dashboard_page_url));
            }
            exit;
            // END OF CHANGE
        }
        
        public function handle_listing_actions() { /* ... کد قبلی بدون تغییر ... */ }

        public function ajax_load_custom_fields() { /* ... کد قبلی بدون تغییر ... */ }
        
        private function get_custom_fields_html($listing_type_id, $listing_id = 0) { /* ... کد قبلی بدون تغییر ... */ }
        
        public function ajax_filter_listings() { /* ... کد قبلی بدون تغییر ... */ }

        public function generate_dynamic_css() {
            $appearance_settings = Directory_Main::get_option('appearance', []);
            if (empty($appearance_settings)) {
                return '';
            }

            $css = ':root {' . PHP_EOL;
            
            // Colors
            $colors = [
                'primary_color' => '--wpd-primary-color',
                'text_color' => '--wpd-text-color',
                'background_color' => '--wpd-bg-color',
            ];
            foreach ($colors as $key => $var) {
                if (!empty($appearance_settings[$key])) {
                    $css .= esc_attr($var) . ': ' . esc_attr($appearance_settings[$key]) . ';' . PHP_EOL;
                }
            }
            $css .= '}' . PHP_EOL;

            // Typography
            $main_font = $appearance_settings['main_font'] ?? 'vazir';
            if ($main_font !== 'custom') {
                $font_family = 'Vazirmatn, sans-serif'; // Default
                if ($main_font === 'iransans') $font_family = 'IRANSans, sans-serif';
                if ($main_font === 'dana') $font_family = 'Dana, sans-serif';
                $css .= '.wpd-container, .wpd-container button, .wpd-container input, .wpd-container select, .wpd-container textarea { font-family: ' . $font_family . '; }' . PHP_EOL;
            }

            $typo_elements = [
                'title' => '.wpd-item-content h3, .wpd-dashboard-content h3',
                'body' => '.wpd-container, .wpd-item-excerpt, .wpd-form-group label',
                'button' => '.wpd-button, button[type="submit"]',
            ];

            foreach ($typo_elements as $key => $selector) {
                $font_size = $appearance_settings["font_size_{$key}"] ?? '';
                $font_weight = $appearance_settings["font_weight_{$key}"] ?? '';
                if (!empty($font_size) || !empty($font_weight)) {
                    $css .= $selector . ' {';
                    if (!empty($font_size)) {
                        $css .= ' font-size: ' . esc_attr($font_size) . ';';
                    }
                    if (!empty($font_weight)) {
                        $css .= ' font-weight: ' . esc_attr($font_weight) . ';';
                    }
                    $css .= '}' . PHP_EOL;
                }
            }

            return $css;
        }

        // START OF CHANGE: New validation functions
        private function validate_form_data( $listing_type_id, $form_data, $listing_id = 0 ) {
            if ( empty( $listing_type_id ) ) {
                $this->errors->add( 'no_listing_type', 'لطفا نوع آگهی را انتخاب کنید.' );
                return;
            }
    
            $fields = get_post_meta( $listing_type_id, '_wpd_custom_fields', true );
            if ( ! is_array( $fields ) ) {
                return;
            }
    
            $custom_data = $form_data['wpd_custom'] ?? [];
    
            foreach ( $fields as $field ) {
                // Skip validation for structural fields
                if ( in_array( $field['type'], ['section_title', 'html_content'] ) ) {
                    continue;
                }

                // 1. Check if the field should be visible based on conditional logic
                if ( ! $this->is_field_visible( $field, $custom_data ) ) {
                    continue; // Skip validation for hidden fields
                }
    
                $value = $custom_data[ $field['key'] ] ?? null;
    
                // 2. Check for required fields
                if ( ! empty( $field['required'] ) && empty( $value ) ) {
                    $this->errors->add( 'field_required', sprintf( 'فیلد "%s" الزامی است.', $field['label'] ) );
                    continue; // No need for further validation if it's empty
                }
    
                // 3. Check for unique fields
                if ( ! empty( $field['unique'] ) && ! empty( $value ) ) {
                    if ( $this->is_value_duplicate( $field['key'], $value, $listing_id ) ) {
                        $this->errors->add( 'field_unique', sprintf( 'مقدار وارد شده برای "%s" قبلاً استفاده شده است.', $field['label'] ) );
                    }
                }
            }
        }
    
        private function is_field_visible( $field, $custom_data ) {
            if ( empty( $field['conditional_logic']['enabled'] ) ) {
                return true; // Always visible if no logic is set
            }
    
            $logic = $field['conditional_logic'];
            $target_value = $custom_data[ $logic['target_field'] ] ?? null;
    
            $condition_met = false;
            switch ( $logic['operator'] ) {
                case 'is':
                    $condition_met = ( $target_value == $logic['value'] );
                    break;
                case 'is_not':
                    $condition_met = ( $target_value != $logic['value'] );
                    break;
                case 'is_empty':
                    $condition_met = empty( $target_value );
                    break;
                case 'is_not_empty':
                    $condition_met = ! empty( $target_value );
                    break;
            }
    
            return ( $logic['action'] === 'show' ) ? $condition_met : ! $condition_met;
        }
    
        private function is_value_duplicate( $meta_key, $meta_value, $exclude_post_id = 0 ) {
            global $wpdb;
            $meta_key_prefixed = '_wpd_' . sanitize_key( $meta_key );
    
            $query = $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
                $meta_key_prefixed,
                $meta_value
            );
    
            $results = $wpdb->get_col( $query );
    
            if ( empty( $results ) ) {
                return false;
            }
    
            // If we are editing a post, we should exclude it from the check
            if ( $exclude_post_id > 0 ) {
                $filtered_results = array_filter( $results, function ( $post_id ) use ( $exclude_post_id ) {
                    return $post_id != $exclude_post_id;
                } );
                return ! empty( $filtered_results );
            }
    
            return true;
        }
        // END OF CHANGE
    }
}
