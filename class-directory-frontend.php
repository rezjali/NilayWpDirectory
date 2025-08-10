<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Directory_Frontend' ) ) {

    class Directory_Frontend {

        public function __construct() {
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
            add_action( 'init', [ $this, 'register_shortcodes' ] );
            add_action( 'template_redirect', [ $this, 'handle_form_submission' ] );
            add_action( 'template_redirect', [ $this, 'handle_listing_actions' ] );
            
            // ثبت پاسخگوهای ایجکس
            add_action( 'wp_ajax_wpd_load_custom_fields', [ $this, 'ajax_load_custom_fields' ] );
            add_action( 'wp_ajax_nopriv_wpd_filter_listings', [ $this, 'ajax_filter_listings' ] );
            add_action( 'wp_ajax_wpd_filter_listings', [ $this, 'ajax_filter_listings' ] );
        }

        public function enqueue_scripts() {
            wp_enqueue_style( 'wpd-main-style', WPD_ASSETS_URL . 'css/main.css', [], WPD_PLUGIN_VERSION );
            $dynamic_css = $this->generate_dynamic_css();
            wp_add_inline_style( 'wpd-main-style', $dynamic_css );
            wp_enqueue_script( 'wpd-main-script', WPD_ASSETS_URL . 'js/main.js', [ 'jquery' ], WPD_PLUGIN_VERSION, true );
            wp_enqueue_style( 'persian-datepicker-style', 'https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css' );
            wp_enqueue_script( 'persian-date', 'https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js', [], null, true );
            wp_enqueue_script( 'persian-datepicker-script', 'https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js', [ 'jquery', 'persian-date' ], null, true );
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
            if ( ! is_user_logged_in() ) return '<div class="wpd-alert wpd-alert-warning">' . __( 'برای ثبت آگهی، لطفا ابتدا وارد شوید یا ثبت‌نام کنید.', 'wp-directory' ) . '</div>';
            
            ob_start();
            $listing_id = isset( $_GET['listing_id'] ) ? intval( $_GET['listing_id'] ) : 0;

            if ( $listing_id > 0 ) {
                $listing = get_post($listing_id);
                if(!$listing || $listing->post_author != get_current_user_id()) {
                    return '<div class="wpd-alert wpd-alert-danger">' . __( 'شما اجازه ویرایش این آگهی را ندارید.', 'wp-directory' ) . '</div>';
                }
                $this->display_main_submit_form( $listing_id );
            } else {
                $step = isset( $_GET['step'] ) ? intval( $_GET['step'] ) : 1;
                $package_id = isset( $_GET['package_id'] ) ? intval( $_GET['package_id'] ) : 0;
                if ( $step === 1 ) $this->display_package_selection();
                elseif ( $step === 2 && ! empty( $package_id ) ) $this->display_main_submit_form( 0, $package_id );
                else $this->display_package_selection();
            }
            return ob_get_clean();
        }

        private function display_package_selection() { /* ... کد قبلی بدون تغییر ... */ }

        private function display_main_submit_form( $listing_id = 0, $package_id = 0 ) { /* ... کد قبلی بدون تغییر ... */ }
        
        public function render_dashboard() { /* ... کد قبلی بدون تغییر ... */ }

        private function render_dashboard_my_listings() {
            $user_id = get_current_user_id();
            $listings = get_posts(['post_type' => 'wpd_listing', 'author' => $user_id, 'posts_per_page' => -1, 'post_status' => ['publish', 'pending', 'draft']]);
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
                        <td><?php echo esc_html($listing->post_status); ?></td>
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

        public function render_archive_page() {
            ob_start();
            ?>
            <div class="wpd-archive-container">
                <aside class="wpd-archive-sidebar">
                    <form id="wpd-filter-form">
                        <h3><?php echo Directory_Main::get_term('filter'); ?></h3>
                        
                        <div class="wpd-filter-group">
                            <label for="filter-keyword"><?php _e('کلمه کلیدی', 'wp-directory'); ?></label>
                            <input type="text" id="filter-keyword" name="filter_keyword" placeholder="<?php _e('عنوان یا توضیحات...', 'wp-directory'); ?>">
                        </div>

                        <div class="wpd-filter-group">
                            <label for="filter-category"><?php echo Directory_Main::get_term('listing_category'); ?></label>
                            <?php wp_dropdown_categories(['taxonomy' => 'wpd_listing_category', 'name' => 'filter_category', 'hierarchical' => true, 'hide_empty' => false, 'show_option_none' => '— ' . __('همه دسته‌بندی‌ها', 'wp-directory') . ' —']); ?>
                        </div>

                        <div class="wpd-filter-group">
                            <label for="filter-location"><?php echo Directory_Main::get_term('listing_location'); ?></label>
                            <?php wp_dropdown_categories(['taxonomy' => 'wpd_listing_location', 'name' => 'filter_location', 'hierarchical' => true, 'hide_empty' => false, 'show_option_none' => '— ' . __('همه مناطق', 'wp-directory') . ' —']); ?>
                        </div>

                        <button type="submit" class="wpd-button"><?php echo Directory_Main::get_term('search'); ?></button>
                    </form>
                </aside>
                <main class="wpd-archive-main">
                    <div id="wpd-listings-result-container">
                        <?php echo $this->get_listings_html([]); ?>
                    </div>
                </main>
            </div>
            <?php
            return ob_get_clean();
        }

        public function get_listings_html($args) {
            $defaults = [
                'post_type' => 'wpd_listing',
                'post_status' => 'publish',
                'posts_per_page' => 10,
            ];
            $query_args = wp_parse_args($args, $defaults);
            $query = new WP_Query($query_args);

            ob_start();
            if($query->have_posts()){
                echo '<div class="wpd-listings-container">';
                while($query->have_posts()){
                    $query->the_post();
                    $this->render_listing_item(get_the_ID());
                }
                echo '</div>';
                // Pagination
                the_posts_pagination(['mid_size' => 2]);
                wp_reset_postdata();
            } else {
                echo '<p>' . __('هیچ آگهی با این مشخصات یافت نشد.', 'wp-directory') . '</p>';
            }
            return ob_get_clean();
        }

        public function render_listing_item($post_id){ /* ... کد قبلی بدون تغییر ... */ }
        
        public function handle_form_submission() { /* ... کد قبلی بدون تغییر ... */ }
        
        public function handle_listing_actions() { /* ... کد قبلی بدون تغییر ... */ }

        public function ajax_load_custom_fields() { /* ... کد قبلی بدون تغییر ... */ }
        
        private function get_custom_fields_html($listing_type_id, $listing_id = 0) { /* ... کد قبلی بدون تغییر ... */ }
        
        public function ajax_filter_listings() {
            check_ajax_referer('wpd_ajax_nonce', 'nonce');
            
            $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
            $args = [
                'paged' => $paged,
            ];

            if(!empty($_POST['filter_keyword'])) {
                $args['s'] = sanitize_text_field($_POST['filter_keyword']);
            }

            $tax_query = [];
            if(!empty($_POST['filter_category'])) {
                $tax_query[] = [
                    'taxonomy' => 'wpd_listing_category',
                    'field' => 'term_id',
                    'terms' => intval($_POST['filter_category']),
                ];
            }
            if(!empty($_POST['filter_location'])) {
                $tax_query[] = [
                    'taxonomy' => 'wpd_listing_location',
                    'field' => 'term_id',
                    'terms' => intval($_POST['filter_location']),
                ];
            }
            if(!empty($tax_query)) {
                $args['tax_query'] = $tax_query;
            }
            
            wp_send_json_success(['html' => $this->get_listings_html($args)]);
        }

        public function generate_dynamic_css() { /* ... کد قبلی بدون تغییر ... */ }
    }
}
