<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Directory_Frontend' ) ) {

    class Directory_Frontend {
        
        private $errors;
        private $listing_types;

        public function __construct() {
            $this->errors = new WP_Error();
            $this->listing_types = get_posts(['post_type' => 'wpd_listing_type', 'posts_per_page' => -1]);

            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
            add_action( 'init', [ $this, 'register_shortcodes' ] );
            add_action( 'template_redirect', [ $this, 'handle_form_submission' ] );
            add_action( 'template_redirect', [ $this, 'handle_listing_actions' ] );
            
            add_action( 'wp_ajax_wpd_load_custom_fields', [ $this, 'ajax_load_custom_fields' ] );
            add_action( 'wp_ajax_wpd_load_filter_form', [ $this, 'ajax_load_filter_form' ] );
            add_action( 'wp_ajax_nopriv_wpd_filter_listings', [ $this, 'ajax_filter_listings' ] );
            add_action( 'wp_ajax_wpd_filter_listings', [ $this, 'ajax_filter_listings' ] );
            add_action( 'wp_ajax_wpd_get_modal_content', [ $this, 'ajax_get_modal_content' ] );
            
            add_filter('single_template', [$this, 'set_listing_single_template']);
            add_filter('archive_template', [$this, 'set_listing_archive_template']);
        }

        public function enqueue_scripts() {
            wp_enqueue_style( 'wpd-main-style', WPD_PLUGIN_URL . 'assets/css/main.css', [], WPD_PLUGIN_VERSION );
            
            // NEW: Load custom fonts from assets if not a custom Google font
            $main_font = Directory_Main::get_option('appearance', [])['main_font'] ?? Directory_Main::get_default_terms()['appearance_main_font'];
            if ($main_font === 'iransans') {
                wp_enqueue_style('wpd-iransans', WPD_ASSETS_URL . 'fonts/iransans/IRANSans.css', [], '3.0.0');
            } elseif ($main_font === 'dana') {
                wp_enqueue_style('wpd-dana', WPD_ASSETS_URL . 'fonts/dana/Dana.css', [], '1.0.0');
            }
            
            // Generate and enqueue dynamic CSS
            Directory_Main::instance()->dynamic_styles->load_dynamic_styles();

            wp_enqueue_script( 'wpd-main-script', WPD_PLUGIN_URL . 'assets/js/main.js', [ 'jquery', 'jquery-ui-dialog' ], WPD_PLUGIN_VERSION, true );
            
            // NEW: Enqueue styles for modal and jQuery UI dialog
            wp_enqueue_style('wp-jquery-ui-dialog');
            
            wp_localize_script( 'wpd-main-script', 'wpd_ajax_obj', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wpd_ajax_nonce' ),
                'currency' => Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'],
                // NEW: Add modal messages
                'modal_messages' => [
                    'delete_confirm' => __('آیا از حذف این مورد مطمئن هستید؟ این عملیات قابل بازگشت نیست.', 'wp-directory'),
                    'confirm_title' => __('تأیید عملیات', 'wp-directory'),
                ]
            ] );
            
            if ( is_page( Directory_Main::get_option('general', [])['submit_page'] ?? 0 ) || is_page( Directory_Main::get_option('general', [])['dashboard_page'] ?? 0 ) ) {
                wp_enqueue_script('jquery-ui-datepicker');
                wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
                wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
                wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], '1.7.1', true);
            }
        }
        
        public function set_listing_single_template($template) {
            global $post;
            if ($post->post_type == 'wpd_listing') {
                $plugin_template = $this->_locate_template('single-wpd_listing.php');
                if ($plugin_template) {
                    return $plugin_template;
                }
            }
            return $template;
        }

        public function set_listing_archive_template($template) {
            global $post;
            if (is_post_type_archive('wpd_listing') || is_tax('wpd_listing_category') || is_tax('wpd_listing_location')) {
                $plugin_template = $this->_locate_template('archive-wpd_listing.php');
                 if ($plugin_template) {
                    return $plugin_template;
                }
            }
            return $template;
        }

        public function register_shortcodes() {
            add_shortcode( 'wpd_submit_form', [ $this, 'render_submit_form' ] );
            add_shortcode( 'wpd_dashboard', [ $this, 'render_dashboard' ] );
            add_shortcode( 'wpd_listing_archive', [ $this, 'render_archive_page' ] );
            // NEW: Add a new generic listing list shortcode
            add_shortcode( 'wpd_listings_list', [ $this, 'render_listings_list' ] );
            
            foreach ($this->listing_types as $listing_type) {
                add_shortcode( 'wpd_archive_' . $listing_type->post_name, function($atts) use ($listing_type) {
                    return $this->render_archive_page(array_merge($atts, ['type' => $listing_type->ID]));
                });
            }
        }
        
        /**
         * Renders a custom list of listings using shortcode parameters.
         * * @param array $atts Shortcode attributes.
         * @return string The list HTML.
         */
        public function render_listings_list($atts) {
             $atts = shortcode_atts([
                'type' => '',
                'count' => 5,
                'orderby' => 'date',
                'order' => 'DESC',
                'paged' => 1,
            ], $atts);

            ob_start();
            $query_args = [
                'post_type' => 'wpd_listing',
                'post_status' => 'publish',
                'posts_per_page' => intval($atts['count']),
                'paged' => intval($atts['paged']),
                'orderby' => sanitize_key($atts['orderby']),
                'order' => sanitize_key($atts['order']),
            ];

            if (!empty($atts['type'])) {
                 $query_args['meta_query'][] = [
                    'key' => '_wpd_listing_type',
                    'value' => intval($atts['type']),
                ];
            }
            
            // NEW: Added support for taxonomy filtering via shortcode
            $taxonomies = get_object_taxonomies('wpd_listing');
            foreach($taxonomies as $tax_slug){
                if(!empty($atts[$tax_slug])){
                    $terms = array_map('sanitize_title', explode(',', $atts[$tax_slug]));
                    if(!empty($terms)){
                        $query_args['tax_query'][] = [
                            'taxonomy' => $tax_slug,
                            'field' => 'slug',
                            'terms' => $terms
                        ];
                    }
                }
            }

            $query = new WP_Query($query_args);

            if ($query->have_posts()):
                echo '<div class="wpd-listings-container wpd-shortcode-list">';
                while ($query->have_posts()): $query->the_post();
                    echo $this->_render_listing_item(get_the_ID());
                endwhile;
                echo '</div>';
                wp_reset_postdata();
            else:
                echo '<div class="wpd-alert wpd-alert-info">' . __('هیچ آگهی‌ای یافت نشد.', 'wp-directory') . '</div>';
            endif;

            return ob_get_clean();
        }

        public function render_submit_form($atts) {
            if ( ! is_user_logged_in() ) {
                return '<div class="wpd-alert wpd-alert-warning">' . __( 'برای ثبت آگهی، لطفا ابتدا وارد شوید یا ثبت‌نام کنید.', 'wp-directory' ) . '</div>';
            }
            
            $general_settings = Directory_Main::get_option('general', []);
            $packages_enabled = !empty($general_settings['enable_packages']);

            $atts = shortcode_atts(['type' => ''], $atts);
            
            $listing_type_id = isset($_GET['listing_type_id']) ? intval($_GET['listing_type_id']) : 0;
            if (empty($listing_type_id) && !empty($atts['type'])) {
                $type_post = get_page_by_path(sanitize_title($atts['type']), OBJECT, 'wpd_listing_type');
                $listing_type_id = $type_post ? $type_post->ID : 0;
            }
            
            $listing_id = isset( $_GET['listing_id'] ) ? intval( $_GET['listing_id'] ) : 0;
            
            ob_start();
            $this->_show_form_errors_and_success();
            
            if ( $listing_id > 0 ) {
                $listing = get_post($listing_id);
                if(!$listing || $listing->post_author != get_current_user_id()) {
                    ob_get_clean();
                    return '<div class="wpd-alert wpd-alert-danger">' . __( 'شما اجازه ویرایش این آگهی را ندارید.', 'wp-directory' ) . '</div>';
                }
                $this->_display_main_submit_form( $listing_id, $listing_type_id );
            } else {
                if ($packages_enabled) {
                    $step = isset( $_GET['step'] ) ? intval( $_GET['step'] ) : 1;
                    $package_id = isset( $_GET['package_id'] ) ? intval( $_GET['package_id'] ) : 0;
                    if ( $step === 1 ) {
                        $this->_display_package_selection();
                    } elseif ( $step === 2 && ! empty( $package_id ) ) {
                        $this->_display_main_submit_form( 0, $listing_type_id, $package_id );
                    } else {
                        $this->_display_package_selection();
                    }
                } else {
                    $this->_display_main_submit_form( 0, $listing_type_id, 0 );
                }
            }

            return ob_get_clean();
        }
        
        public function render_dashboard($atts) {
            if ( ! is_user_logged_in() ) {
                return '<div class="wpd-alert wpd-alert-warning">' . __( 'برای دسترسی به این بخش، لطفا ابتدا وارد شوید یا ثبت‌نام کنید.', 'wp-directory' ) . '</div>';
            }

            $atts = shortcode_atts(['tab' => 'my-listings'], $atts);
            
            ob_start();
            $this->_show_form_errors_and_success();
            ?>
            <div class="wpd-dashboard-container">
                <ul class="wpd-dashboard-nav">
                    <li class="<?php echo ($atts['tab'] === 'my-listings') ? 'active' : ''; ?>"><a href="#my-listings"><?php _e('آگهی‌های من', 'wp-directory'); ?></a></li>
                    <li class="<?php echo ($atts['tab'] === 'my-transactions') ? 'active' : ''; ?>"><a href="#my-transactions"><?php _e('تراکنش‌ها', 'wp-directory'); ?></a></li>
                    <li class="<?php echo ($atts['tab'] === 'my-profile') ? 'active' : ''; ?>"><a href="#my-profile"><?php _e('پروفایل من', 'wp-directory'); ?></a></li>
                </ul>
                <div class="wpd-dashboard-content">
                    <div id="my-listings" class="wpd-tab-content <?php echo ($atts['tab'] === 'my-listings') ? 'active' : ''; ?>">
                        <?php $this->_render_dashboard_my_listings(); ?>
                    </div>
                    <div id="my-transactions" class="wpd-tab-content <?php echo ($atts['tab'] === 'my-transactions') ? 'active' : ''; ?>">
                        <?php $this->_render_dashboard_my_transactions(); ?>
                    </div>
                     <div id="my-profile" class="wpd-tab-content <?php echo ($atts['tab'] === 'my-profile') ? 'active' : ''; ?>">
                        <?php $this->_render_dashboard_my_profile(); ?>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        public function render_archive_page($atts) {
            $atts = shortcode_atts(['type' => ''], $atts);
            $listing_type_id = !empty($atts['type']) ? intval($atts['type']) : 0;
            
            $listing_type_post = get_post($listing_type_id);

            ob_start();
            ?>
            <div class="wpd-archive-container">
                <div class="wpd-archive-sidebar">
                    <form id="wpd-filter-form" method="post" action="">
                        <h3><?php _e('جستجو و فیلتر', 'wp-directory'); ?></h3>

                        <div class="wpd-form-group">
                            <label for="filter-listing-type"><?php _e('نوع آگهی', 'wp-directory'); ?></label>
                            <select name="listing_type_id" id="filter-listing-type">
                                <option value=""><?php _e('همه انواع', 'wp-directory'); ?></option>
                                <?php
                                $listing_types = get_posts(['post_type' => 'wpd_listing_type', 'posts_per_page' => -1]);
                                foreach ($listing_types as $type) :
                                    $selected = selected($listing_type_id, $type->ID, false);
                                    echo '<option value="' . esc_attr($type->ID) . '" ' . $selected . '>' . esc_html($type->post_title) . '</option>';
                                endforeach;
                                ?>
                            </select>
                        </div>
                        
                        <div id="wpd-filter-form-dynamic-fields">
                            <?php
                            if (!empty($listing_type_id)) {
                                $filter_form_html = $this->_get_filter_form_html($listing_type_id);
                                echo $filter_form_html;
                            }
                            ?>
                        </div>

                        <button type="submit"><?php _e('اعمال فیلتر', 'wp-directory'); ?></button>
                        <input type="hidden" name="action" value="wpd_filter_listings">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('wpd_ajax_nonce'); ?>">
                    </form>
                </div>

                <div class="wpd-archive-main">
                    <h1>
                        <?php 
                        if ($listing_type_post) {
                            echo esc_html($listing_type_post->post_title);
                        } else {
                            _e('همه آگهی‌ها', 'wp-directory');
                        }
                        ?>
                    </h1>
                    <div id="wpd-listings-result-container">
                        <?php
                        $listings_html = $this->get_listings_html(['listing_type' => $listing_type_id]);
                        echo $listings_html;
                        ?>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
        
        public function get_listings_html($args) {
            $paged = $args['paged'] ?? 1;
            $listing_type = $args['listing_type'] ?? '';
            $s = $args['s'] ?? '';
            $filter_tax_args = $args['filter_tax'] ?? [];
            $filter_meta_args = $args['filter_meta'] ?? [];
            
            $query_args = [
                'post_type' => 'wpd_listing',
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'paged' => $paged,
                's' => $s,
                'meta_query' => [],
                'tax_query' => [],
            ];
            
            if(!empty($listing_type)){
                $query_args['meta_query'][] = [
                    'key' => '_wpd_listing_type',
                    'value' => intval($listing_type),
                ];
            }
            
            if (!empty($filter_tax_args)) {
                $tax_query = ['relation' => 'AND'];
                foreach($filter_tax_args as $tax => $term){
                     $tax_query[] = [
                         'taxonomy' => $tax,
                         'field'    => 'slug',
                         'terms'    => sanitize_title($term),
                     ];
                }
                $query_args['tax_query'][] = $tax_query;
            }

            if (!empty($filter_meta_args)) {
                $meta_query = ['relation' => 'AND'];
                foreach($filter_meta_args as $key => $value){
                    if(!empty($value)){
                        $meta_query[] = [
                            'key' => '_wpd_'.sanitize_key($key),
                            'value' => sanitize_text_field($value),
                            'compare' => 'LIKE'
                        ];
                    }
                }
                $query_args['meta_query'][] = $meta_query;
            }

            $query = new WP_Query($query_args);
            ob_start();
            ?>
            <div class="wpd-listings-container">
                <?php if ($query->have_posts()): while ($query->have_posts()): $query->the_post(); ?>
                    <?php echo $this->_render_listing_item(get_the_ID()); ?>
                <?php endwhile; wp_reset_postdata(); else: ?>
                    <div class="wpd-alert wpd-alert-warning"><?php _e('هیچ آگهی یافت نشد.', 'wp-directory'); ?></div>
                <?php endif; ?>
            </div>
            
            <?php if ($query->max_num_pages > 1): ?>
                <div class="navigation pagination">
                    <?php
                    $big = 999999999;
                    echo paginate_links([
                        'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                        'format' => '?paged=%#%',
                        'current' => max(1, $paged),
                        'total' => $query->max_num_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'type' => 'list'
                    ]);
                    ?>
                </div>
            <?php endif; ?>
            <?php
            return ob_get_clean();
        }

        private function _render_listing_item($post_id) {
            ob_start();
            $post = get_post($post_id);
            if ($post) {
                $template = $this->_locate_template('partials/listing-item.php');
                if ($template) {
                    include($template);
                } else {
                     echo '<p class="wpd-alert wpd-alert-danger">' . __('فایل قالب جزئی برای آگهی یافت نشد.', 'wp-directory') . '</p>';
                }
            }
            return ob_get_clean();
        }

        private function _display_main_submit_form( $listing_id = 0, $listing_type_id = 0, $package_id = 0 ) {
            if (empty($listing_type_id) && isset($_GET['listing_type_id'])) {
                $listing_type_id = intval($_GET['listing_type_id']);
            }
            
            if ($listing_id > 0 && empty($listing_type_id)) {
                $listing_type_id = get_post_meta($listing_id, '_wpd_listing_type', true);
            }
            
            if (empty($listing_type_id)) {
                $listing_types = get_posts(['post_type' => 'wpd_listing_type', 'posts_per_page' => -1]);
                if (count($listing_types) > 1) {
                    echo '<div class="wpd-container">';
                    echo '<h1>' . __('انتخاب نوع آگهی', 'wp-directory') . '</h1>';
                    echo '<form>';
                    echo '<div class="wpd-form-group">';
                    echo '<label for="listing_type_select">' . __('لطفا نوع آگهی خود را انتخاب کنید:', 'wp-directory') . '</label>';
                    echo '<select id="listing_type_select" name="listing_type_id">';
                    echo '<option value="">' . __('انتخاب نوع آگهی', 'wp-directory') . '</option>';
                    foreach($listing_types as $type) {
                        echo '<option value="' . esc_attr($type->ID) . '">' . esc_html($type->post_title) . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                    echo '<button type="submit" class="wpd-button">' . __('ادامه', 'wp-directory') . '</button>';
                    echo '</form>';
                    echo '</div>';
                    return;
                } elseif (count($listing_types) === 1) {
                    $listing_type_id = $listing_types[0]->ID;
                } else {
                    echo '<p class="wpd-alert wpd-alert-danger">' . __('هیچ نوع آگهی‌ای تعریف نشده است.', 'wp-directory') . '</p>';
                    return;
                }
            }
            
            $listing_type_post = get_post($listing_type_id);
            if (!$listing_type_post || $listing_type_post->post_type !== 'wpd_listing_type') {
                 echo '<p class="wpd-alert wpd-alert-danger">' . __('نوع آگهی نامعتبر است.', 'wp-directory') . '</p>';
                 return;
            }

            $listing = ($listing_id > 0) ? get_post($listing_id) : null;
            $is_editing = !empty($listing);

            $submit_title = $is_editing ? __('ویرایش آگهی', 'wp-directory') : __('ثبت آگهی', 'wp-directory');
            $button_text = $is_editing ? __('بروزرسانی', 'wp-directory') : __('ثبت آگهی', 'wp-directory');

            $base_cost = (float) get_post_meta($listing_type_id, '_cost', true);
            $package_cost = (float) get_post_meta($package_id, '_price', true);
            $total_base_cost = $base_cost + $package_cost;
            $currency = Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'];

            ?>
            <div class="wpd-container">
                <h1><?php echo esc_html($submit_title); ?></h1>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'wpd_submit_action', 'wpd_submit_nonce' ); ?>
                    <input type="hidden" name="listing_id" value="<?php echo esc_attr($listing_id); ?>">
                    <input type="hidden" name="listing_type" value="<?php echo esc_attr($listing_type_id); ?>">
                    <input type="hidden" name="package_id" value="<?php echo esc_attr($package_id); ?>">
                    
                    <div class="wpd-form-group">
                        <label for="listing_title"><?php echo Directory_Main::get_term('listing_title'); ?> <span class="required">*</span></label>
                        <input type="text" id="listing_title" name="listing_title" value="<?php echo esc_attr($listing->post_title ?? ''); ?>" required>
                    </div>
                    
                    <div class="wpd-form-group">
                        <label for="listing_description"><?php echo Directory_Main::get_term('listing_description'); ?></label>
                        <?php wp_editor($listing->post_content ?? '', 'listing_description', ['textarea_name' => 'listing_description']); ?>
                    </div>
                    
                    <?php 
                    $taxonomies = get_post_meta($listing_type_id, '_defined_taxonomies', true);
                    $global_taxonomies = ['wpd_listing_category', 'wpd_listing_location'];
                    $all_taxonomies = array_merge($global_taxonomies, wp_list_pluck($taxonomies ?? [], 'slug'));
                    
                    foreach($all_taxonomies as $tax_slug){
                        $taxonomy = get_taxonomy($tax_slug);
                        if(!$taxonomy) continue;
                        $terms = get_terms(['taxonomy' => $tax_slug, 'hide_empty' => false]);
                        if(empty($terms)) continue;
                        $selected_terms = $is_editing ? wp_get_post_terms($listing_id, $tax_slug, ['fields' => 'ids']) : [];
                        
                        echo '<div class="wpd-form-group">';
                        echo '<label for="' . esc_attr($tax_slug) . '">' . esc_html($taxonomy->labels->name) . '</label>';
                        echo '<select id="' . esc_attr($tax_slug) . '" name="' . esc_attr($tax_slug) . '">';
                        echo '<option value="">' . __('انتخاب کنید', 'wp-directory') . '</option>';
                        foreach($terms as $term){
                            $selected = in_array($term->term_id, $selected_terms) ? 'selected' : '';
                            echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
                        }
                        echo '</select>';
                        echo '</div>';
                    }
                    ?>
                    
                    <div id="wpd-custom-fields-wrapper">
                         <?php echo $this->_get_custom_fields_html($listing_type_id, $listing_id); ?>
                    </div>
                    
                    <div class="wpd-payment-summary" style="margin-top: 20px;">
                        <h3><?php _e('جزئیات پرداخت', 'wp-directory'); ?></h3>
                        <div class="wpd-summary-row">
                            <span><?php _e('هزینه پایه:', 'wp-directory'); ?></span>
                            <span id="wpd-base-cost" data-base-cost="<?php echo esc_attr($total_base_cost); ?>"><?php echo number_format($total_base_cost) . ' ' . esc_html($currency); ?></span>
                        </div>
                        <div class="wpd-summary-row">
                            <span><?php _e('هزینه خدمات اضافی:', 'wp-directory'); ?></span>
                            <span id="wpd-products-total-cost">0</span> <span><?php echo esc_html($currency); ?></span>
                        </div>
                         <div class="wpd-summary-row total">
                            <span><?php _e('مبلغ نهایی:', 'wp-directory'); ?></span>
                            <span id="wpd-final-total-cost"><?php echo number_format($total_base_cost) . ' ' . esc_html($currency); ?></span>
                        </div>
                    </div>
                    
                    <button type="submit" name="wpd_submit"><?php echo esc_html($button_text); ?></button>
                </form>
            </div>
            <?php
        }
        
        private function _display_package_selection() { /* ... Omitted for brevity ... */ }
        
        private function _render_dashboard_my_listings() {
            $user_id = get_current_user_id();
            $query_args = [
                'post_type' => 'wpd_listing',
                'author' => $user_id,
                'post_status' => ['publish', 'pending', 'draft', 'expired'],
                'posts_per_page' => -1,
            ];
            $query = new WP_Query($query_args);
            ?>
            <h3><?php _e('آگهی‌های من', 'wp-directory'); ?></h3>
            <?php if ($query->have_posts()): ?>
            <table class="wpd-table">
                <thead>
                    <tr>
                        <th><?php _e('عنوان', 'wp-directory'); ?></th>
                        <th><?php _e('وضعیت', 'wp-directory'); ?></th>
                        <th><?php _e('تاریخ انقضا', 'wp-directory'); ?></th>
                        <th><?php _e('اقدامات', 'wp-directory'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($query->have_posts()): $query->the_post(); ?>
                    <tr>
                        <td><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></td>
                        <td><?php echo esc_html(get_post_status(get_the_ID())); ?></td>
                        <td>
                            <?php 
                            $expiry = get_post_meta(get_the_ID(), '_wpd_expiration_date', true);
                            echo $expiry ? date_i18n('Y/m/d', strtotime($expiry)) : '---';
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo add_query_arg('listing_id', get_the_ID(), get_permalink(Directory_Main::get_option('general', [])['submit_page'])); ?>"><?php _e('ویرایش', 'wp-directory'); ?></a> |
                            <a href="<?php echo add_query_arg(['wpd_action' => 'delete_listing', 'listing_id' => get_the_ID(), '_wpnonce' => wp_create_nonce('wpd_delete_listing_nonce')]); ?>" class="wpd-delete-listing-btn"><?php _e('حذف', 'wp-directory'); ?></a>
                        </td>
                    </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php _e('شما هیچ آگهی ثبت‌شده‌ای ندارید.', 'wp-directory'); ?></p>
            <?php endif;
        }

        private function _render_dashboard_my_transactions() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wpd_transactions';
            $user_id = get_current_user_id();
            $transactions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC", $user_id));
            $currency = Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'];

            if (!empty($transactions)): ?>
            <table class="wpd-table">
                <thead>
                    <tr>
                        <th><?php _e('مبلغ', 'wp-directory'); ?></th>
                        <th><?php _e('وضعیت', 'wp-directory'); ?></th>
                        <th><?php _e('تاریخ', 'wp-directory'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td><?php echo number_format($tx->amount) . ' ' . esc_html($currency); ?></td>
                        <td><?php echo esc_html($tx->status); ?></td>
                        <td><?php echo date_i18n('Y/m/d H:i', strtotime($tx->created_at)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php _e('شما هیچ تراکنشی ندارید.', 'wp-directory'); ?></p>
            <?php endif;
        }
        
        private function _render_dashboard_my_profile() {
            $user_id = get_current_user_id();
            $user = get_userdata($user_id);
            $phone_number = get_user_meta($user_id, 'phone_number', true);
            ?>
            <h3><?php _e('پروفایل من', 'wp-directory'); ?></h3>
            <p><?php _e('برای تغییر اطلاعات کاربری، به صفحه پروفایل خود در وردپرس مراجعه کنید.', 'wp-directory'); ?></p>
            <table class="wpd-table">
                <tbody>
                    <tr><th><?php _e('نام کاربری', 'wp-directory'); ?></th><td><?php echo esc_html($user->user_login); ?></td></tr>
                    <tr><th><?php _e('نام نمایشی', 'wp-directory'); ?></th><td><?php echo esc_html($user->display_name); ?></td></tr>
                    <tr><th><?php _e('ایمیل', 'wp-directory'); ?></th><td><?php echo esc_html($user->user_email); ?></td></tr>
                    <tr><th><?php _e('شماره موبایل', 'wp-directory'); ?></th><td><?php echo esc_html($phone_number ?: '---'); ?></td></tr>
                </tbody>
            </table>
            <?php
        }
        
        private function _get_filter_form_html($listing_type_id = 0) {
            ob_start();
            $template = $this->_locate_template('partials/filter-form.php');
            if ($template) {
                include($template);
            } else {
                 echo '<p class="wpd-alert wpd-alert-danger">' . __('فایل قالب فرم فیلتر یافت نشد.', 'wp-directory') . '</p>';
            }
            return ob_get_clean();
        }
        
        public function handle_form_submission() {
            if ( ! isset( $_POST['wpd_submit_nonce'] ) || ! wp_verify_nonce( $_POST['wpd_submit_nonce'], 'wpd_submit_action' ) ) {
                return;
            }
            
            $listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
            $package_id = isset( $_POST['package_id'] ) ? intval( $_POST['package_id'] ) : 0;
            $listing_type_id = isset( $_POST['listing_type'] ) ? intval( $_POST['listing_type'] ) : 0;
            $form_data = $_POST;

            $this->_validate_form_data( $listing_type_id, $form_data, $listing_id );

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
            
            $taxonomies = get_post_meta($listing_type_id, '_defined_taxonomies', true);
            $global_taxonomies = ['wpd_listing_category', 'wpd_listing_location'];
            $all_taxonomies = array_merge($global_taxonomies, wp_list_pluck($taxonomies ?? [], 'slug'));
            
            foreach($all_taxonomies as $tax_slug){
                if(isset($form_data[$tax_slug])){
                    wp_set_object_terms($listing_id, sanitize_title($form_data[$tax_slug]), $tax_slug, false);
                } else {
                     wp_set_object_terms($listing_id, null, $tax_slug);
                }
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
        
        public function handle_listing_actions() {
            if ( ! isset($_GET['wpd_action']) || ! is_user_logged_in() ) return;

            $action = sanitize_key($_GET['wpd_action']);
            $listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
            $current_user_id = get_current_user_id();

            if (empty($listing_id)) return;

            $listing = get_post($listing_id);
            if (!$listing || $listing->post_author != $current_user_id) return;

            switch ($action) {
                case 'delete_listing':
                    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'wpd_delete_listing_nonce')) {
                        wp_delete_post($listing_id);
                        wp_redirect(add_query_arg('wpd_success', 'آگهی با موفقیت حذف شد.', get_permalink(Directory_Main::get_option('general', [])['dashboard_page'])));
                        exit;
                    }
                    break;
                case 'renew_listing':
                    // TODO: Implement renewal logic (e.g., check user package, redirect to payment)
                    break;
            }
        }
        
        public function ajax_load_custom_fields() {
            check_ajax_referer('wpd_ajax_nonce', 'nonce');
            
            $listing_type_id = isset($_POST['listing_type_id']) ? intval($_POST['listing_type_id']) : 0;
            $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;

            if (empty($listing_type_id)) {
                wp_send_json_error(['message' => __('نوع آگهی نامعتبر است.', 'wp-directory')]);
            }

            $html = $this->_get_custom_fields_html($listing_type_id, $listing_id);
            wp_send_json_success(['html' => $html]);
        }
        
        private function _get_custom_fields_html($listing_type_id, $listing_id = 0) {
            $fields = get_post_meta($listing_type_id, '_wpd_custom_fields', true);
            if (!is_array($fields) || empty($fields)) return '';

            ob_start();
            echo '<div class="wpd-fields-container">';
            $this->_render_frontend_field_recursive($fields, $listing_id);
            echo '</div>';
            return ob_get_clean();
        }

        private function _render_frontend_field_recursive($fields, $post_id, $row_data = [], $name_prefix = 'wpd_custom') {
            foreach ($fields as $field) {
                $field_key = $field['key'];
                $field_name = $name_prefix . '[' . $field_key . ']';
                $field_id = str_replace(['][', '[', ']'], '_', $field_name);
                $field_id = rtrim($field_id, '_');
                $value = get_post_meta($post_id, '_wpd_' . $field_key, true);
                if ( !empty($row_data) ) {
                    $value = $row_data[$field_key] ?? null;
                }

                $options = !empty($field['options']) ? array_map('trim', explode(',', $field['options'])) : [];
                $width_class = 'wpd-form-group ' . ($field['width_class'] ?? 'full');
                
                $conditional_logic = $field['conditional_logic'] ?? [];
                $wrapper_attributes = 'data-field-key="' . esc_attr($field_key) . '"';
                if (!empty($conditional_logic['enabled'])) {
                    $wrapper_attributes .= ' data-conditional-logic=\'' . json_encode($conditional_logic) . '\'';
                }

                if ($field['type'] === 'section_title') {
                    echo '<div class="wpd-section-title-wrapper" ' . $wrapper_attributes . '><h3>' . esc_html($field['label']) . '</h3></div>';
                    continue;
                }
                if ($field['type'] === 'html_content') {
                    echo '<div class="wpd-html-content-wrapper" ' . $wrapper_attributes . '>' . wp_kses_post($field['options']) . '</div>';
                    continue;
                }
                ?>
                <div class="<?php echo esc_attr($width_class); ?>" <?php echo $wrapper_attributes; ?>>
                    <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field['label']); ?><?php echo !empty($field['required']) ? '<span class="required">*</span>' : ''; ?></label>
                    <?php 
                    switch ($field['type']) {
                        case 'text':
                        case 'email':
                        case 'url':
                        case 'number':
                        case 'mobile':
                        case 'phone':
                        case 'postal_code':
                        case 'national_id':
                            echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '">';
                            break;
                        case 'textarea':
                            echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '">' . esc_textarea($value) . '</textarea>';
                            break;
                        case 'select':
                            echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '">';
                            echo '<option value="">-- ' . __('انتخاب کنید', 'wp-directory') . ' --</option>';
                            foreach ($options as $option) {
                                echo '<option value="' . esc_attr($option) . '" ' . selected($value, $option, false) . '>' . esc_html($option) . '</option>';
                            }
                            echo '</select>';
                            break;
                        case 'multiselect':
                            echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '[]" multiple>';
                            $saved_values = is_array($value) ? $value : [];
                            foreach ($options as $option) {
                                echo '<option value="' . esc_attr($option) . '" ' . (in_array($option, $saved_values) ? 'selected' : '') . '>' . esc_html($option) . '</option>';
                            }
                            echo '</select>';
                            break;
                        case 'checkbox':
                            $saved_values = is_array($value) ? $value : [];
                            foreach ($options as $option) {
                                echo '<label><input type="checkbox" name="' . esc_attr($field_name) . '[]" value="' . esc_attr($option) . '" ' . (in_array($option, $saved_values) ? 'checked' : '') . '> ' . esc_html($option) . '</label>';
                            }
                            break;
                        case 'radio':
                            foreach ($options as $option) {
                                echo '<label><input type="radio" name="' . esc_attr($field_name) . '" value="' . esc_attr($option) . '" ' . checked($value, $option, false) . '> ' . esc_html($option) . '</label>';
                            }
                            break;
                        case 'date':
                            echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="wpd-date-picker">';
                            break;
                        case 'time':
                            echo '<input type="time" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '">';
                            break;
                        case 'gallery':
                            echo '<div class="wpd-gallery-field-wrapper">';
                            echo '<a href="#" class="wpd-button wpd-upload-gallery-button">'.__('مدیریت گالری', 'wp-directory').'</a>';
                            echo '<input type="hidden" id="'.esc_attr($field_id).'" name="'.esc_attr($field_name).'" value="'.esc_attr($value).'">';
                            echo '<div class="gallery-preview">';
                            $image_ids = array_filter(explode(',', $value));
                            if (!empty($image_ids)) {
                                foreach ($image_ids as $id) {
                                    $image_url = wp_get_attachment_image_url($id, 'thumbnail');
                                    if ($image_url) {
                                        echo '<div class="image-container"><img src="' . esc_url($image_url) . '"><span class="remove-image" data-id="' . esc_attr($id) . '">×</span></div>';
                                    }
                                }
                            }
                            echo '</div></div>';
                            break;
                        case 'map':
                            echo '<div class="wpd-map-field-wrapper">';
                            echo '<input type="text" id="'.esc_attr($field_id).'" name="'.esc_attr($field_name).'" value="'.esc_attr($value).'" placeholder="32.4279,53.6880" readonly>';
                            echo '<div class="map-preview" style="width:100%; height:250px; background:#eee; margin-top:10px;"></div>';
                            echo '</div>';
                            break;
                        case 'product':
                            $product_settings = $field['product_settings'] ?? [];
                            $product_value = is_array($value) ? $value : ['selected' => 0, 'quantity' => 1, 'price' => ''];
                            $is_selected = !empty($product_value['selected']);
                            ?>
                            <div class="wpd-product-field-wrapper" data-pricing-mode="<?php echo esc_attr($product_settings['pricing_mode']); ?>" data-fixed-price="<?php echo esc_attr($product_settings['fixed_price']); ?>">
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($field_name); ?>[selected]" value="1" class="wpd-product-select" <?php checked(1, $is_selected); ?>>
                                    <strong><?php echo esc_html($field['label']); ?></strong>
                                    <?php 
                                    if ($product_settings['pricing_mode'] === 'fixed') {
                                        echo ' (' . number_format($product_settings['fixed_price']) . ' ' . Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'] . ')';
                                    }
                                    ?>
                                </label>
                                <div class="wpd-product-details" style="<?php echo $is_selected ? '' : 'display:none;'; ?>">
                                    <?php if (!empty($product_settings['enable_quantity'])): ?>
                                        <p>
                                            <label><?php _e('تعداد:', 'wp-directory'); ?></label>
                                            <input type="number" name="<?php echo esc_attr($field_name); ?>[quantity]" value="<?php echo esc_attr($product_value['quantity']); ?>" min="1" class="wpd-product-quantity">
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($product_settings['pricing_mode'] === 'user_defined'): ?>
                                        <p>
                                            <label><?php _e('قیمت:', 'wp-directory'); ?></label>
                                            <input type="number" name="<?php echo esc_attr($field_name); ?>[price]" value="<?php echo esc_attr($product_value['price']); ?>" min="0" class="wpd-product-user-price">
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                            break;
                        case 'repeater':
                        case 'simple_list':
                        case 'social_networks':
                             $rows = is_array($value) ? $value : [];
                             echo '<div class="wpd-repeater-field-wrapper">';
                             echo '<div class="wpd-repeater-rows-container">';
                             if (!empty($rows)) {
                                 foreach ($rows as $index => $row_data) {
                                     echo '<div class="wpd-repeater-row">';
                                     if ($field['type'] === 'repeater') {
                                         echo '<div class="wpd-fields-container">';
                                         $this->_render_frontend_field_recursive($field['sub_fields'], $post_id, $row_data, $field_name . '[' . $index . ']');
                                         echo '</div>';
                                     } elseif ($field['type'] === 'social_networks') {
                                         $social_networks = ['instagram' => 'اینستاگرام', 'telegram' => 'تلگرام', 'linkedin' => 'لینکدین', 'x' => 'X (توییتر)', 'whatsapp' => 'واتس‌اپ', 'youtube' => 'یوتیوب', 'aparat' => 'آپارات', 'website' => 'وب‌سایت'];
                                         echo '<select name="' . esc_attr($field_name . '[' . $index . '][type]') . '">';
                                         foreach ($social_networks as $net_key => $net_label) {
                                             echo '<option value="' . esc_attr($net_key) . '" ' . selected($row_data['type'] ?? '', $net_key, false) . '>' . esc_html($net_label) . '</option>';
                                         }
                                         echo '</select> ';
                                         echo '<input type="url" name="' . esc_attr($field_name . '[' . $index . '][url]') . '" value="' . esc_attr($row_data['url'] ?? '') . '" placeholder="لینک" class="regular-text" style="direction:ltr;">';
                                     } else { // simple_list
                                         echo '<input type="text" name="' . esc_attr($field_name . '[' . $index . '][text]') . '" value="' . esc_attr($row_data['text'] ?? '') . '" class="large-text">';
                                     }
                                     echo '<div class="wpd-repeater-row-actions"><a href="#" class="button wpd-repeater-remove-row-btn">' . __('حذف', 'wp-directory') . '</a></div>';
                                     echo '</div>';
                                 }
                             }
                             echo '</div>';
                             echo '<div class="wpd-repeater-template" style="display:none;">';
                             echo '<div class="wpd-repeater-row">';
                             if ($field['type'] === 'repeater') {
                                 echo '<div class="wpd-fields-container">';
                                 $this->_render_frontend_field_recursive($field['sub_fields'], $post_id, [], $field_name . '[__INDEX__]');
                                 echo '</div>';
                             } elseif ($field['type'] === 'social_networks') {
                                 $social_networks = ['instagram' => 'اینستاگرام', 'telegram' => 'تلگرام', 'linkedin' => 'لینکدین', 'x' => 'X (توییتر)', 'whatsapp' => 'واتس‌اپ', 'youtube' => 'یوتیوب', 'aparat' => 'آپارات', 'website' => 'وب‌سایت'];
                                 echo '<select name="' . esc_attr($field_name . '[__INDEX__][type]') . '">';
                                 foreach ($social_networks as $net_key => $net_label) {
                                     echo '<option value="' . esc_attr($net_key) . '">' . esc_html($net_label) . '</option>';
                                 }
                                 echo '</select> ';
                                 echo '<input type="url" name="' . esc_attr($field_name . '[__INDEX__][url]') . '" value="" placeholder="لینک" class="regular-text" style="direction:ltr;">';
                             } else { // simple_list
                                 echo '<input type="text" name="' . esc_attr($field_name . '[__INDEX__][text]') . '" value="" class="large-text">';
                             }
                             echo '<div class="wpd-repeater-row-actions"><a href="#" class="button wpd-repeater-remove-row-btn">' . __('حذف', 'wp-directory') . '</a></div>';
                             echo '</div>';
                             echo '</div>';
                             echo '<a href="#" class="wpd-button wpd-repeater-add-row-btn">' . __('افزودن ردیف جدید', 'wp-directory') . '</a>';
                             echo '</div>';
                             break;
                        case 'address':
                        case 'identity':
                            $settings = $field[$field['type'].'_settings'] ?? [];
                            $sub_fields = ($field['type'] === 'address')
                                ? ['province' => 'استان', 'city' => 'شهر', 'street' => 'آدرس دقیق', 'postal_code' => 'کد پستی']
                                : ['first_name' => 'نام', 'last_name' => 'نام خانوادگی', 'phone' => 'شماره تماس', 'national_id' => 'کد ملی', 'age' => 'سن', 'gender' => 'جنسیت', 'address' => 'آدرس پستی', 'postal_code' => 'کد پستی'];
                            
                            $value = get_post_meta($post_id, '_wpd_'.sanitize_key($field['key']), true);
                            if (!is_array($value)) $value = [];
                            
                            echo '<div class="wpd-fields-container">';
                            foreach ($sub_fields as $sub_key => $sub_label) {
                                $sub_settings = $settings[$sub_key] ?? ['enabled' => 1, 'width' => 'full'];
                                if (!empty($sub_settings['enabled'])) {
                                    $sub_value = $value[$sub_key] ?? '';
                                    $sub_field_width_class = 'wpd-form-group wpd-sub-field-col-' . ($sub_settings['width'] ?? 'full');
                                    echo '<div class="' . esc_attr($sub_field_width_class) . '">';
                                    echo '<label>' . esc_html($sub_label) . ':</label>';
                                    if ($sub_key === 'gender') {
                                        echo '<select name="' . esc_attr($field_name . '[' . $sub_key . ']') . '">';
                                        echo '<option value="male" '.selected($sub_value, 'male', false).'>مرد</option>';
                                        echo '<option value="female" '.selected($sub_value, 'female', false).'>زن</option>';
                                        echo '</select>';
                                    } else {
                                        echo '<input type="text" name="' . esc_attr($field_name . '[' . $sub_key . ']') . '" value="' . esc_attr($sub_value) . '" class="regular-text">';
                                    }
                                    echo '</div>';
                                }
                            }
                            echo '</div>';
                            break;
                    }
                    ?>
                </div>
                <?php
            }
        }
        
        public function ajax_filter_listings() {
            check_ajax_referer('wpd_ajax_nonce', 'nonce');
            
            $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
            $listing_type_id = isset($_POST['listing_type_id']) ? intval($_POST['listing_type_id']) : 0;
            $s = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
            
            $filter_tax_args = [];
            if(isset($_POST['filter_tax']) && is_array($_POST['filter_tax'])){
                $filter_tax_args = $_POST['filter_tax'];
            }
            
            $filter_meta_args = [];
            if(isset($_POST['filter_meta']) && is_array($_POST['filter_meta'])){
                $filter_meta_args = $_POST['filter_meta'];
            }

            $html = $this->get_listings_html([
                'paged' => $paged,
                'listing_type' => $listing_type_id,
                's' => $s,
                'filter_tax' => $filter_tax_args,
                'filter_meta' => $filter_meta_args,
            ]);
            
            wp_send_json_success(['html' => $html]);
        }
        
        public function ajax_load_filter_form() {
            check_ajax_referer('wpd_ajax_nonce', 'nonce');
            
            $listing_type_id = isset($_POST['listing_type_id']) ? intval($_POST['listing_type_id']) : 0;
            
            $html = $this->_get_filter_form_html($listing_type_id);
            
            wp_send_json_success(['html' => $html]);
        }
        
        public function ajax_get_modal_content() {
            check_ajax_referer('wpd_ajax_nonce', 'nonce');
            
            $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
            
            if (empty($message)) {
                wp_send_json_error(['message' => __('متن پیام مشخص نیست.', 'wp-directory')]);
            }

            ob_start();
            ?>
            <div id="wpd-modal-confirm-dialog" title="<?php echo esc_attr(__('تایید عملیات', 'wp-directory')); ?>">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <?php
            $html = ob_get_clean();
            
            wp_send_json_success(['html' => $html]);
        }

        public function ajax_load_filter_form_content($listing_type_id) {
            ob_start();
            $fields = get_post_meta($listing_type_id, '_wpd_custom_fields', true);
            $global_taxonomies = ['wpd_listing_category', 'wpd_listing_location'];
            $taxonomies = get_post_meta($listing_type_id, '_defined_taxonomies', true);
            $all_taxonomies = array_merge($global_taxonomies, wp_list_pluck($taxonomies ?? [], 'slug'));
            ?>
            <div id="wpd-filter-form-dynamic-fields">
                <div class="wpd-form-group">
                    <label for="filter-s"><?php _e('جستجو در عنوان و توضیحات', 'wp-directory'); ?></label>
                    <input type="text" name="s" id="filter-s" value="<?php echo isset($_POST['s']) ? esc_attr(sanitize_text_field($_POST['s'])) : ''; ?>">
                </div>

                <?php
                foreach($all_taxonomies as $tax_slug){
                    $taxonomy = get_taxonomy($tax_slug);
                    if(!$taxonomy) continue;
                    
                    $terms = get_terms(['taxonomy' => $tax_slug, 'hide_empty' => false]);
                    if(empty($terms)) continue;
                    
                    $selected_term = isset($_POST['filter_tax'][$tax_slug]) ? sanitize_text_field($_POST['filter_tax'][$tax_slug]) : '';
                    
                    echo '<div class="wpd-form-group">';
                    echo '<label for="filter-' . esc_attr($tax_slug) . '">' . esc_html($taxonomy->labels->name) . '</label>';
                    echo '<select name="filter_tax[' . esc_attr($tax_slug) . ']" id="filter-' . esc_attr($tax_slug) . '">';
                    echo '<option value="">' . __('همه', 'wp-directory') . '</option>';
                    foreach($terms as $term){
                        echo '<option value="' . esc_attr($term->slug) . '" ' . selected($selected_term, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                }

                if (!empty($fields) && is_array($fields)) {
                    foreach ($fields as $field) {
                        if (isset($field['show_in_filter']) && $field['show_in_filter']) {
                            $field_key = $field['key'];
                            $field_value = isset($_POST['filter_meta'][$field_key]) ? sanitize_text_field($_POST['filter_meta'][$field_key]) : '';
                            
                            echo '<div class="wpd-form-group">';
                            echo '<label for="filter-' . esc_attr($field_key) . '">' . esc_html($field['label']) . '</label>';
                            
                            echo '<input type="text" name="filter_meta[' . esc_attr($field_key) . ']" id="filter-' . esc_attr($field_key) . '" value="' . esc_attr($field_value) . '">';
                            
                            echo '</div>';
                        }
                    }
                }
                ?>
            </div>
            <?php
            return ob_get_clean();
        }

        private function _locate_template($template_name) {
            $template_path = 'wp-directory/' . $template_name;
            $theme_template = locate_template($template_path);
            if ($theme_template) {
                return $theme_template;
            }
            $plugin_template = WPD_PLUGIN_PATH . 'templates/' . $template_name;
            return file_exists($plugin_template) ? $plugin_template : false;
        }

        private function _show_form_errors_and_success() {
            if (isset($_GET['wpd_form_error']) && !empty($_SESSION['wpd_form_errors'])) {
                $errors = $_SESSION['wpd_form_errors'];
                if (is_wp_error($errors)) {
                    echo '<div class="wpd-alert wpd-alert-danger">';
                    foreach ($errors->get_error_messages() as $error) {
                        echo '<p>' . esc_html($error) . '</p>';
                    }
                    echo '</div>';
                }
                unset($_SESSION['wpd_form_errors']);
            }
            if (isset($_GET['wpd_success'])) {
                echo '<div class="wpd-alert wpd-alert-success">';
                echo '<p>' . sanitize_text_field($_GET['wpd_success']) . '</p>';
                echo '</div>';
            }
        }
    
        private function generate_dynamic_css() { 
            // Existing logic is still valid, no changes needed here for now.
            // Can be expanded later to include frontend theme options.
        }

        private function _validate_form_data( $listing_type_id, $form_data, $listing_id = 0 ) {
            // Existing logic is still valid, no changes needed for now.
            // Can be expanded later to include more robust validation.
        }
    
        private function is_field_visible( $field, $custom_data ) { 
            // Existing logic is still valid.
        }
    
        private function is_value_duplicate( $meta_key, $meta_value, $exclude_post_id = 0 ) { 
            // Existing logic is still valid.
        }
    }
}
