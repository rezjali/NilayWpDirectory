<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Directory_Post_Types' ) ) {

    class Directory_Post_Types {

        public function __construct() {
            add_action( 'init', [ $this, 'register_post_types' ] );
            add_action( 'init', [ $this, 'register_global_taxonomies' ] );
            add_action( 'init', [ $this, 'register_dynamic_taxonomies' ], 10 );
            add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
            add_action( 'admin_menu', [ $this, 'remove_default_meta_boxes' ] );
            
            add_action( 'save_post_wpd_listing', [ $this, 'save_listing_meta_data' ] );
            add_action( 'save_post_wpd_listing_type', [ $this, 'save_listing_type_meta_data' ] );
            add_action( 'save_post_wpd_upgrade', [ $this, 'save_upgrade_meta_data' ] );

            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
            add_action( 'admin_footer', [ $this, 'admin_scripts' ] );
            add_action( 'wp_ajax_wpd_load_admin_fields_and_taxonomies', [ $this, 'ajax_load_admin_fields_and_taxonomies' ] );

            add_filter( 'manage_wpd_listing_posts_columns', [ $this, 'add_listing_columns' ] );
            add_action( 'manage_wpd_listing_posts_custom_column', [ $this, 'render_listing_columns' ], 10, 2 );
            add_filter( 'manage_wpd_package_posts_columns', [ $this, 'add_package_columns' ] );
            add_action( 'manage_wpd_package_posts_custom_column', [ $this, 'render_package_columns' ], 10, 2 );
            add_filter( 'manage_wpd_upgrade_posts_columns', [ $this, 'add_upgrade_columns' ] );
            add_action( 'manage_wpd_upgrade_posts_custom_column', [ $this, 'render_upgrade_columns' ], 10, 2 );

            add_action( 'restrict_manage_posts', [ $this, 'add_listing_type_filter' ] );
            add_filter( 'parse_query', [ $this, 'filter_listings_by_type' ] );
            add_filter( 'bulk_actions-edit-wpd_listing', [ $this, 'add_listing_bulk_actions' ] );
            add_filter( 'handle_bulk_actions-edit-wpd_listing', [ $this, 'handle_listing_bulk_actions' ], 10, 3 );
            add_action( 'admin_init', [ $this, 'handle_field_builder_export' ] );
            add_action( 'admin_notices', [ $this, 'bulk_action_admin_notice' ] );
            
            add_action( 'after_switch_theme', 'flush_rewrite_rules' );
        }

        public function register_post_types() {
            $listing_labels = [
                'name'               => Directory_Main::get_term( 'listing_plural' ),
                'singular_name'      => Directory_Main::get_term( 'listing_singular' ),
                'menu_name'          => 'نیلای دایرکتوری',
                'name_admin_bar'     => Directory_Main::get_term( 'listing_singular' ),
                'add_new'            => __( 'افزودن جدید', 'wp-directory' ),
                'add_new_item'       => sprintf( __( 'افزودن %s جدید', 'wp-directory' ), Directory_Main::get_term( 'listing_singular' ) ),
                'new_item'           => sprintf( __( '%s جدید', 'wp-directory' ), Directory_Main::get_term( 'listing_singular' ) ),
                'edit_item'          => sprintf( __( 'ویرایش %s', 'wp-directory' ), Directory_Main::get_term( 'listing_singular' ) ),
                'view_item'          => sprintf( __( 'مشاهده %s', 'wp-directory' ), Directory_Main::get_term( 'listing_singular' ) ),
                'all_items'          => sprintf( __( 'همه %s', 'wp-directory' ), Directory_Main::get_term( 'listing_plural' ) ),
                'search_items'       => sprintf( __( 'جستجوی %s', 'wp-directory' ), Directory_Main::get_term( 'listing_plural' ) ),
                'not_found'          => sprintf( __( '%s یافت نشد.', 'wp-directory' ), Directory_Main::get_term( 'listing_plural' ) ),
                'not_found_in_trash' => sprintf( __( '%s در زباله‌دان یافت نشد.', 'wp-directory' ), Directory_Main::get_term( 'listing_plural' ) ),
            ];
            $listing_args = [
                'labels'             => $listing_labels,
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_in_menu'       => 'wpd-main-menu',
                'query_var'          => true,
                'rewrite'            => [ 'slug' => 'listing', 'with_front' => false ],
                'capability_type'    => 'post',
                'has_archive'        => true,
                'hierarchical'       => false,
                'menu_position'      => 5,
                'supports'           => [ 'title', 'author' ], 
                'menu_icon'          => 'dashicons-list-view',
            ];
            register_post_type( 'wpd_listing', $listing_args );
            
            flush_rewrite_rules();

            $type_labels = [
                'name'          => __( 'انواع آگهی', 'wp-directory' ),
                'singular_name' => __( 'نوع آگهی', 'wp-directory' ),
                'add_new_item'  => __( 'افزودن نوع آگهی جدید', 'wp-directory' ),
                'edit_item'     => __( 'ویرایش نوع آگهی', 'wp-directory' ),
            ];
            $type_args = [
                'labels'        => $type_labels,
                'public'        => false,
                'show_ui'       => true,
                'show_in_menu'  => 'wpd-main-menu',
                'capability_type' => 'post',
                'supports'      => [ 'title' ],
            ];
            register_post_type( 'wpd_listing_type', $type_args );

            $upgrade_labels = [
                'name'               => __( 'بسته‌های ارتقا', 'wp-directory' ),
                'singular_name'      => __( 'بسته ارتقا', 'wp-directory' ),
                'menu_name'          => __( 'بسته‌های ارتقا', 'wp-directory' ),
                'add_new_item'       => __( 'افزودن بسته ارتقا جدید', 'wp-directory' ),
                'edit_item'          => __( 'ویرایش بسته ارتقا', 'wp-directory' ),
                'all_items'          => __( 'همه بسته‌های ارتقا', 'wp-directory' ),
            ];
            $upgrade_args = [
                'labels'             => $upgrade_labels,
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => false,
                'capability_type'    => 'post',
                'supports'           => [ 'title' ],
            ];
            register_post_type( 'wpd_upgrade', $upgrade_args );
        }

        public function register_global_taxonomies() {
            $category_labels = [
                'name' => __( 'دسته‌بندی‌های آگهی', 'wp-directory' ),
                'singular_name' => __( 'دسته‌بندی', 'wp-directory' ),
                'menu_name' => __( 'دسته‌بندی‌ها', 'wp-directory' ),
            ];
            register_taxonomy('wpd_listing_category', ['wpd_listing'], [
                'labels' => $category_labels,
                'hierarchical' => true,
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => 'wpd-main-menu',
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => ['slug' => 'listing-category'],
            ]);

            $location_labels = [
                'name' => __( 'مناطق آگهی', 'wp-directory' ),
                'singular_name' => __( 'منطقه', 'wp-directory' ),
                'menu_name' => __( 'مناطق', 'wp-directory' ),
            ];
            register_taxonomy('wpd_listing_location', ['wpd_listing'], [
                'labels' => $location_labels,
                'hierarchical' => true,
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => 'wpd-main-menu',
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => ['slug' => 'listing-location'],
            ]);
        }

        public function register_dynamic_taxonomies() {
            $listing_types = get_posts(['post_type' => 'wpd_listing_type', 'numberposts' => -1, 'post_status' => 'publish']);
            if(empty($listing_types)) return;

            foreach($listing_types as $type_post) {
                $taxonomies = get_post_meta($type_post->ID, '_defined_taxonomies', true);
                if(empty($taxonomies) || !is_array($taxonomies)) continue;

                foreach($taxonomies as $tax) {
                    if(empty($tax['slug']) || empty($tax['name'])) continue;
                    
                    if(!taxonomy_exists($tax['slug'])) {
                        $is_hierarchical = (bool) ($tax['hierarchical'] ?? 1);
                        $args = [
                            'labels' => ['name' => $tax['name'], 'singular_name' => $tax['name']],
                            'hierarchical' => $is_hierarchical,
                            'public' => true,
                            'show_ui' => true,
                            'show_in_menu' => false,
                            'show_admin_column' => true,
                            'query_var' => true,
                            'rewrite' => ['slug' => $tax['slug']],
                        ];
                        register_taxonomy($tax['slug'], ['wpd_listing'], $args);
                    }
                }
            }
        }

        public function add_meta_boxes() {
            add_meta_box( 'wpd_listing_type_mb', __( 'اطلاعات اصلی آگهی', 'wp-directory' ), [ $this, 'render_listing_type_metabox' ], 'wpd_listing', 'normal', 'high' );
            
            add_meta_box( 'wpd_listing_type_settings_mb', __( 'تنظیمات اصلی نوع آگهی', 'wp-directory' ), [ $this, 'render_listing_type_settings_metabox' ], 'wpd_listing_type', 'normal', 'high' );
            add_meta_box( 'wpd_field_builder_mb', __( 'فیلد ساز', 'wp-directory' ), [ $this, 'render_field_builder_metabox' ], 'wpd_listing_type', 'normal', 'default' );
            add_meta_box( 'wpd_taxonomy_builder_mb', __( 'طبقه‌بندی ساز', 'wp-directory' ), [ $this, 'render_taxonomy_builder_metabox' ], 'wpd_listing_type', 'normal', 'default' );
            add_meta_box( 'wpd_notification_settings_mb', __( 'تنظیمات اعلان‌های این نوع', 'wp-directory' ), [ $this, 'render_notification_settings_metabox' ], 'wpd_listing_type', 'side', 'default' );

            add_meta_box( 'wpd_upgrade_details_mb', __( 'جزئیات بسته ارتقا', 'wp-directory' ), [ $this, 'render_upgrade_details_metabox' ], 'wpd_upgrade', 'normal', 'high' );
        }

        public function remove_default_meta_boxes() {
            remove_meta_box( 'postimagediv', 'wpd_listing', 'side' );
            remove_meta_box( 'commentstatusdiv', 'wpd_listing', 'normal' );
            remove_meta_box( 'commentsdiv', 'wpd_listing', 'normal' );
            remove_meta_box( 'slugdiv', 'wpd_listing', 'normal' );
            remove_meta_box( 'authordiv', 'wpd_listing', 'normal' );
            remove_meta_box( 'postexcerpt', 'wpd_listing', 'normal' );
        }

        public function render_listing_type_settings_metabox($post) {
            wp_nonce_field( 'wpd_save_listing_type_meta', 'wpd_listing_type_nonce' );
            $currency = Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'];
            $cost = get_post_meta($post->ID, '_cost', true);
            ?>
            <table class="form-table">
                <tr>
                    <th><label for="wpd_cost"><?php printf(__( 'هزینه ثبت (%s)', 'wp-directory' ), esc_html($currency)); ?></label></th>
                    <td>
                        <input type="number" id="wpd_cost" name="wpd_meta[_cost]" value="<?php echo esc_attr($cost); ?>" class="small-text">
                        <p class="description"><?php _e('این مبلغ به هزینه بسته عضویت اضافه می‌شود. برای ثبت رایگان، 0 یا خالی بگذارید.', 'wp-directory'); ?></p>
                    </td>
                </tr>
            </table>
            <?php
        }

        public function render_notification_settings_metabox($post) {
            $settings = get_post_meta($post->ID, '_notification_settings', true);
            $settings = is_array($settings) ? $settings : [];

            $events = Directory_Main::get_notification_events();
            ?>
            <p class="description"><?php _e('ارسال اعلان‌های پیش‌فرض برای این نوع آگهی را مدیریت کنید.', 'wp-directory'); ?></p>
            <?php foreach ($events as $id => $details): ?>
                <strong><?php echo esc_html($details['label']); ?></strong>
                <ul>
                    <li>
                        <label>
                            <input type="checkbox" name="wpd_notifications[<?php echo esc_attr($id); ?>][email]" value="1" <?php checked(1, $settings[$id]['email'] ?? 1); ?>>
                            <?php _e('ارسال ایمیل', 'wp-directory'); ?>
                        </label>
                    </li>
                    <li>
                        <label>
                            <input type="checkbox" name="wpd_notifications[<?php echo esc_attr($id); ?>][sms]" value="1" <?php checked(1, $settings[$id]['sms'] ?? 1); ?>>
                            <?php _e('ارسال پیامک', 'wp-directory'); ?>
                        </label>
                    </li>
                </ul>
            <?php endforeach;
        }
        
        public function render_taxonomy_builder_metabox($post) {
            wp_nonce_field( 'wpd_save_taxonomy_builder_meta', 'wpd_taxonomy_builder_nonce' );
            $taxonomies = get_post_meta( $post->ID, '_defined_taxonomies', true );
            if ( ! is_array( $taxonomies ) ) $taxonomies = [];
            ?>
            <p class="description"><?php _e('در این بخش می‌توانید طبقه‌بندی‌های اختصاصی برای این نوع آگهی تعریف کنید.', 'wp-directory'); ?></p>
            <div id="wpd-taxonomy-builder-wrapper">
                <div id="wpd-taxonomies-container">
                    <?php if ( ! empty( $taxonomies ) ) : foreach ( $taxonomies as $index => $tax ) : ?>
                    <div class="wpd-field-row">
                        <span class="dashicons dashicons-move handle"></span>
                        <div class="wpd-field-inputs">
                            <input type="text" name="wpd_taxonomies[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr( $tax['name'] ?? '' ); ?>" placeholder="<?php _e( 'نام طبقه‌بندی (فارسی)', 'wp-directory' ); ?>">
                            <input type="text" name="wpd_taxonomies[<?php echo esc_attr($index); ?>][slug]" value="<?php echo esc_attr( $tax['slug'] ?? '' ); ?>" placeholder="<?php _e( 'نامک (انگلیسی)', 'wp-directory' ); ?>">
                            <select name="wpd_taxonomies[<?php echo esc_attr($index); ?>][hierarchical]">
                                <option value="1" <?php selected( $tax['hierarchical'] ?? '1', '1' ); ?>><?php _e('سلسله مراتبی', 'wp-directory'); ?></option>
                                <option value="0" <?php selected( $tax['hierarchical'] ?? '1', '0' ); ?>><?php _e('غیر سلسله مراتبی (تگ)', 'wp-directory'); ?></option>
                            </select>
                        </div>
                        <a href="#" class="button wpd-remove-taxonomy" style="margin: 10px;"><?php _e( 'حذف', 'wp-directory' ); ?></a>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <a href="#" id="wpd-add-taxonomy" class="button button-primary"><?php _e( 'افزودن طبقه‌بندی جدید', 'wp-directory' ); ?></a>
            </div>
            <?php
        }

        public function render_field_builder_metabox( $post ) {
            wp_nonce_field( 'wpd_save_field_builder_meta', 'wpd_field_builder_nonce' );
            $fields = get_post_meta( $post->ID, '_wpd_custom_fields', true );
            if ( ! is_array( $fields ) ) $fields = [];
            
            ?>
            <div class="wpd-field-builder-actions" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                <a href="<?php echo esc_url(add_query_arg(['wpd_action' => 'export_fields', 'post_id' => $post->ID, '_wpnonce' => wp_create_nonce('wpd_export_fields_nonce')])); ?>" class="button"><?php _e('برون‌بری فیلدها', 'wp-directory'); ?></a>
                <label for="wpd_import_fields_file" class="button"><?php _e('درون‌ریزی فیلدها', 'wp-directory'); ?></label>
                <input type="file" id="wpd_import_fields_file" name="wpd_import_fields_file" accept=".json" style="display: none;">
                <span class="description" style="margin-right: 10px;"><?php _e('برای درون‌ریزی، یک فایل JSON انتخاب و سپس نوع آگهی را ذخیره کنید.', 'wp-directory'); ?></span>
            </div>
            
            <div id="wpd-field-builder-wrapper">
                <div id="wpd-fields-container" class="wpd-sortable-list">
                    <?php if ( ! empty( $fields ) ) : foreach ( $fields as $index => $field ) : ?>
                        <?php $this->render_field_builder_row($index, $field, $fields); ?>
                    <?php endforeach; endif; ?>
                </div>
                <a href="#" id="wpd-add-field" class="button button-primary"><?php _e( 'افزودن فیلد جدید', 'wp-directory' ); ?></a>
            </div>
            <?php
        }

        private function render_field_builder_row($index, $field_data = [], $all_fields = []) {
            $label = $field_data['label'] ?? '';
            $key = $field_data['key'] ?? '';
            $type = $field_data['type'] ?? 'text';
            $options = $field_data['options'] ?? '';
            $sub_fields = $field_data['sub_fields'] ?? [];
            $required = $field_data['required'] ?? 0;
            $unique = $field_data['unique'] ?? 0;
            $show_in_filter = $field_data['show_in_filter'] ?? 0;
            $show_in_frontend = $field_data['show_in_frontend'] ?? 1;
            $width_class = $field_data['width_class'] ?? 'full';
            $help_text = $field_data['help_text'] ?? '';
            $placeholder = $field_data['placeholder'] ?? '';
            $default_value = $field_data['default_value'] ?? '';
            $conditional_logic = wp_parse_args($field_data['conditional_logic'] ?? [], [
                'enabled'      => 0,
                'action'       => 'show',
                'target_field' => '',
                'operator'     => 'is',
                'value'        => '',
            ]);
            $address_settings = $field_data['address_settings'] ?? [];
            $identity_settings = $field_data['identity_settings'] ?? [];
            $product_settings = wp_parse_args($field_data['product_settings'] ?? [], [
                'pricing_mode' => 'fixed',
                'fixed_price' => '',
                'enable_quantity' => 0,
            ]);
            $file_settings = wp_parse_args($field_data['file_settings'] ?? [], [
                'allowed_formats' => '',
                'max_size' => '',
                'use_as_featured_image' => 0,
                'max_files' => 1
            ]);
            ?>
            <div class="wpd-field-row" data-index="<?php echo esc_attr($index); ?>" data-field-key="<?php echo esc_attr($key); ?>">
                <div class="wpd-field-header">
                    <span class="dashicons dashicons-move handle"></span>
                    <strong><?php echo esc_html($label) ?: __('فیلد جدید', 'wp-directory'); ?></strong> (<span class="wpd-field-type-display"><?php echo esc_html($type); ?></span>)
                    <a href="#" class="wpd-copy-field" title="<?php _e('کپی کردن فیلد', 'wp-directory'); ?>"><span class="dashicons dashicons-admin-page"></span></a>
                    <a href="#" class="wpd-toggle-field-details"><?php _e('جزئیات', 'wp-directory'); ?></a>
                    <a href="#" class="wpd-quick-remove-field" title="<?php _e('حذف سریع', 'wp-directory'); ?>"><span class="dashicons dashicons-no-alt" style="color: red;"></span></a>
                </div>
                <div class="wpd-field-details" style="display:none;">
                    <div class="wpd-field-inputs">
                        <input type="text" name="wpd_fields[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php _e( 'عنوان فیلد', 'wp-directory' ); ?>" class="field-label-input">
                        <input type="text" name="wpd_fields[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($key); ?>" placeholder="<?php _e( 'کلید متا (انگلیسی)', 'wp-directory' ); ?>" class="field-key-input">
                        <select class="wpd-field-type-selector" name="wpd_fields[<?php echo esc_attr($index); ?>][type]">
                            <optgroup label="<?php _e('فیلدهای پایه', 'wp-directory'); ?>">
                                <option value="text" <?php selected( $type, 'text' ); ?>><?php _e( 'متن', 'wp-directory' ); ?></option>
                                <option value="textarea" <?php selected( $type, 'textarea' ); ?>><?php _e( 'متن بلند', 'wp-directory' ); ?></option>
                                <option value="number" <?php selected( $type, 'number' ); ?>><?php _e( 'عدد', 'wp-directory' ); ?></option>
                                <option value="email" <?php selected( $type, 'email' ); ?>><?php _e( 'ایمیل', 'wp-directory' ); ?></option>
                                <option value="url" <?php selected( $type, 'url' ); ?>><?php _e( 'وب‌سایت', 'wp-directory' ); ?></option>
                                <option value="time" <?php selected( $type, 'time' ); ?>><?php _e( 'ساعت', 'wp-directory' ); ?></option>
                                <option value="date" <?php selected( $type, 'date' ); ?>><?php _e( 'تاریخ', 'wp-directory' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('فیلدهای اعتبارسنجی', 'wp-directory'); ?>">
                                <option value="mobile" <?php selected( $type, 'mobile' ); ?>><?php _e( 'شماره موبایل', 'wp-directory' ); ?></option>
                                <option value="phone" <?php selected( $type, 'phone' ); ?>><?php _e( 'شماره تلفن ثابت', 'wp-directory' ); ?></option>
                                <option value="postal_code" <?php selected( $type, 'postal_code' ); ?>><?php _e( 'کد پستی', 'wp-directory' ); ?></option>
                                <option value="national_id" <?php selected( $type, 'national_id' ); ?>><?php _e( 'کد ملی', 'wp-directory' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('فیلدهای انتخاب', 'wp-directory'); ?>">
                                <option value="select" <?php selected( $type, 'select' ); ?>><?php _e( 'لیست کشویی', 'wp-directory' ); ?></option>
                                <option value="multiselect" <?php selected( $type, 'multiselect' ); ?>><?php _e( 'چند انتخابی', 'wp-directory' ); ?></option>
                                <option value="checkbox" <?php selected( $type, 'checkbox' ); ?>><?php _e( 'چک‌باکس', 'wp-directory' ); ?></option>
                                <option value="radio" <?php selected( $type, 'radio' ); ?>><?php _e( 'دکمه رادیویی', 'wp-directory' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('فیلدهای ساختاری', 'wp-directory'); ?>">
                                <option value="section_title" <?php selected( $type, 'section_title' ); ?>><?php _e('عنوان بخش (Heading)', 'wp-directory'); ?></option>
                                <option value="html_content" <?php selected( $type, 'html_content' ); ?>><?php _e('محتوای HTML', 'wp-directory'); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('فیلدهای آپلود', 'wp-directory'); ?>">
                                <option value="image" <?php selected( $type, 'image' ); ?>><?php _e('تصویر', 'wp-directory'); ?></option>
                                <option value="file" <?php selected( $type, 'file' ); ?>><?php _e('فایل', 'wp-directory' ); ?></option>
                                <option value="gallery" <?php selected( $type, 'gallery' ); ?>><?php _e( 'گالری تصاویر', 'wp-directory' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('فیلدهای پیشرفته', 'wp-directory'); ?>">
                                <option value="map" <?php selected( $type, 'map' ); ?>><?php _e( 'نقشه', 'wp-directory' ); ?></option>
                                <option value="repeater" <?php selected( $type, 'repeater' ); ?>><?php _e( 'تکرار شونده', 'wp-directory' ); ?></option>
                                <option value="social_networks" <?php selected( $type, 'social_networks' ); ?>><?php _e( 'لیست شبکه‌های اجتماعی', 'wp-directory' ); ?></option>
                                <option value="simple_list" <?php selected( $type, 'simple_list' ); ?>><?php _e( 'فیلد لیستی', 'wp-directory' ); ?></option>
                                <option value="product" <?php selected( $type, 'product' ); ?>><?php _e( 'محصول/خدمت', 'wp-directory' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('فیلدهای ترکیبی', 'wp-directory'); ?>">
                                <option value="address" <?php selected( $type, 'address' ); ?>><?php _e( 'آدرس پستی', 'wp-directory' ); ?></option>
                                <option value="identity" <?php selected( $type, 'identity' ); ?>><?php _e( 'اطلاعات هویتی', 'wp-directory' ); ?></option>
                            </optgroup>
                        </select>
                        <textarea name="wpd_fields[<?php echo esc_attr($index); ?>][options]" class="wpd-field-options" placeholder="<?php _e( 'گزینه‌ها (جدا شده با کاما) یا محتوای HTML', 'wp-directory' ); ?>" style="<?php echo in_array($type, ['select', 'multiselect', 'checkbox', 'radio', 'html_content']) ? '' : 'display:none;'; ?>"><?php echo esc_textarea($options); ?></textarea>
                    </div>
                    
                    <div class="wpd-field-settings-panel">
                        <h4><?php _e('تنظیمات پایه', 'wp-directory'); ?></h4>
                        <table class="form-table">
                            <tbody>
                                <tr class="wpd-setting-row">
                                    <th><label for="wpd_fields_<?php echo esc_attr($index); ?>_placeholder"><?php _e('Placeholder', 'wp-directory'); ?></label></th>
                                    <td><input type="text" id="wpd_fields_<?php echo esc_attr($index); ?>_placeholder" name="wpd_fields[<?php echo esc_attr($index); ?>][placeholder]" value="<?php echo esc_attr($placeholder); ?>"></td>
                                </tr>
                                <tr class="wpd-setting-row">
                                    <th><label for="wpd_fields_<?php echo esc_attr($index); ?>_help_text"><?php _e('متن راهنما', 'wp-directory'); ?></label></th>
                                    <td><input type="text" id="wpd_fields_<?php echo esc_attr($index); ?>_help_text" name="wpd_fields[<?php echo esc_attr($index); ?>][help_text]" value="<?php echo esc_attr($help_text); ?>"></td>
                                </tr>
                                <tr class="wpd-setting-row">
                                    <th><label for="wpd_fields_<?php echo esc_attr($index); ?>_default_value"><?php _e('مقدار پیش‌فرض', 'wp-directory'); ?></label></th>
                                    <td>
                                        <input type="text" id="wpd_fields_<?php echo esc_attr($index); ?>_default_value" name="wpd_fields[<?php echo esc_attr($index); ?>][default_value]" value="<?php echo esc_attr($default_value); ?>">
                                        <p class="description"><?php _e('می‌توانید از متا کی‌ها یا شورت‌کدها استفاده کنید.', 'wp-directory'); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="wpd-field-rules">
                        <h4><?php _e('تنظیمات نمایش', 'wp-directory'); ?></h4>
                        <label>
                            <?php _e('عرض فیلد:', 'wp-directory'); ?>
                            <select name="wpd_fields[<?php echo esc_attr($index); ?>][width_class]">
                                <option value="full" <?php selected('full', $width_class); ?>><?php _e('عرض کامل', 'wp-directory'); ?></option>
                                <option value="half" <?php selected('half', $width_class); ?>><?php _e('یک دوم', 'wp-directory'); ?></option>
                                <option value="third" <?php selected('third', $width_class); ?>><?php _e('یک سوم', 'wp-directory'); ?></option>
                                <option value="quarter" <?php selected('quarter', $width_class); ?>><?php _e('یک چهارم', 'wp-directory'); ?></option>
                            </select>
                        </label>
                        <label style="margin-right: 15px;">
                            <input type="checkbox" name="wpd_fields[<?php echo esc_attr($index); ?>][show_in_frontend]" value="1" <?php checked(1, $show_in_frontend); ?>>
                            <?php _e('نمایش در فرانت‌اند', 'wp-directory'); ?>
                        </label>
                    </div>
                    
                    <div class="wpd-field-settings-panel wpd-file-settings" style="<?php echo in_array($type, ['image', 'file', 'gallery']) ? '' : 'display:none;'; ?>">
                        <h4><?php _e('تنظیمات آپلود', 'wp-directory'); ?></h4>
                        <div class="wpd-compound-sub-field-setting">
                            <label for="wpd_fields_<?php echo esc_attr($index); ?>_allowed_formats"><?php _e('فرمت‌های مجاز (جدا شده با کاما):', 'wp-directory'); ?></label>
                            <input type="text" id="wpd_fields_<?php echo esc_attr($index); ?>_allowed_formats" name="wpd_fields[<?php echo esc_attr($index); ?>][file_settings][allowed_formats]" value="<?php echo esc_attr($file_settings['allowed_formats']); ?>" placeholder="jpg,png,pdf">
                        </div>
                        <div class="wpd-compound-sub-field-setting">
                            <label for="wpd_fields_<?php echo esc_attr($index); ?>_max_size"><?php _e('حداکثر حجم (به کیلوبایت):', 'wp-directory'); ?></label>
                            <input type="number" id="wpd_fields_<?php echo esc_attr($index); ?>_max_size" name="wpd_fields[<?php echo esc_attr($index); ?>][file_settings][max_size]" value="<?php echo esc_attr($file_settings['max_size']); ?>">
                        </div>
                        <div class="wpd-file-specific-settings wpd-image-settings" style="<?php echo ($type === 'image') ? '' : 'display:none;'; ?>">
                            <div class="wpd-compound-sub-field-setting">
                                <label>
                                    <input type="checkbox" name="wpd_fields[<?php echo esc_attr($index); ?>][file_settings][use_as_featured_image]" value="1" <?php checked(1, $file_settings['use_as_featured_image']); ?>>
                                    <?php _e('استفاده به عنوان تصویر شاخص آگهی', 'wp-directory'); ?>
                                </label>
                            </div>
                        </div>
                         <div class="wpd-file-specific-settings wpd-gallery-settings" style="<?php echo ($type === 'gallery') ? '' : 'display:none;'; ?>">
                            <div class="wpd-compound-sub-field-setting">
                                <label for="wpd_fields_<?php echo esc_attr($index); ?>_max_files"><?php _e('حداکثر تعداد تصاویر:', 'wp-directory'); ?></label>
                                <input type="number" id="wpd_fields_<?php echo esc_attr($index); ?>_max_files" name="wpd_fields[<?php echo esc_attr($index); ?>][file_settings][max_files]" value="<?php echo esc_attr($file_settings['max_files']); ?>">
                            </div>
                         </div>
                    </div>
                    
                    <div class="wpd-field-settings-panel wpd-address-settings" style="<?php echo ($type === 'address') ? '' : 'display:none;'; ?>">
                        <h4><?php _e('تنظیمات فیلد آدرس', 'wp-directory'); ?></h4>
                        <?php
                        $address_sub_fields = ['province' => 'استان', 'city' => 'شهر', 'street' => 'آدرس دقیق', 'postal_code' => 'کد پستی'];
                        foreach ($address_sub_fields as $sub_key => $sub_label) {
                            $sub_settings = $address_settings[$sub_key] ?? ['enabled' => 1, 'width' => 'full'];
                            echo '<div class="wpd-compound-sub-field-setting">';
                            echo '<label><input type="checkbox" name="wpd_fields['.esc_attr($index).'][address_settings]['.$sub_key.'][enabled]" value="1" ' . checked(1, $sub_settings['enabled'], false) . '> '.esc_html($sub_label).'</label>';
                            echo '<select name="wpd_fields['.esc_attr($index).'][address_settings]['.$sub_key.'][width]">';
                            echo '<option value="full" '.selected('full', $sub_settings['width'], false).'>عرض کامل</option>';
                            echo '<option value="half" '.selected('half', $sub_settings['width'], false).'>یک دوم</option>';
                            echo '<option value="third" '.selected('third', $sub_settings['width'], false).'>یک سوم</option>';
                            echo '<option value="quarter" '.selected('quarter', $sub_settings['width'], false).'>یک چهارم</option>';
                            echo '</select>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <div class="wpd-field-settings-panel wpd-identity-settings" style="<?php echo ($type === 'identity') ? '' : 'display:none;'; ?>">
                        <h4><?php _e('تنظیمات فیلد اطلاعات هویتی', 'wp-directory'); ?></h4>
                        <?php
                        $identity_sub_fields = ['first_name' => 'نام', 'last_name' => 'نام خانوادگی', 'phone' => 'شماره تماس', 'national_id' => 'کد ملی', 'age' => 'سن', 'gender' => 'جنسیت', 'address' => 'آدرس پستی', 'postal_code' => 'کد پستی'];
                        foreach ($identity_sub_fields as $sub_key => $sub_label) {
                            $sub_settings = $identity_settings[$sub_key] ?? ['enabled' => 1, 'width' => 'full'];
                            echo '<div class="wpd-compound-sub-field-setting">';
                            echo '<label><input type="checkbox" name="wpd_fields['.esc_attr($index).'][identity_settings]['.$sub_key.'][enabled]" value="1" ' . checked(1, $sub_settings['enabled'], false) . '> '.esc_html($sub_label).'</label>';
                            echo '<select name="wpd_fields['.esc_attr($index).'][identity_settings]['.$sub_key.'][width]">';
                            echo '<option value="full" '.selected('full', $sub_settings['width'], false).'>عرض کامل</option>';
                            echo '<option value="half" '.selected('half', $sub_settings['width'], false).'>یک دوم</option>';
                            echo '<option value="third" '.selected('third', $sub_settings['width'], false).'>یک سوم</option>';
                            echo '<option value="quarter" '.selected('quarter', $sub_settings['width'], false).'>یک چهارم</option>';
                            echo '</select>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <div class="wpd-field-settings-panel wpd-product-settings" style="<?php echo ($type === 'product') ? '' : 'display:none;'; ?>">
                        <h4><?php _e('تنظیمات فیلد محصول/خدمت', 'wp-directory'); ?></h4>
                        <div class="wpd-compound-sub-field-setting">
                            <label><?php _e('حالت قیمت‌گذاری:', 'wp-directory'); ?></label>
                            <select class="wpd-product-pricing-mode" name="wpd_fields[<?php echo esc_attr($index); ?>][product_settings][pricing_mode]">
                                <option value="fixed" <?php selected('fixed', $product_settings['pricing_mode']); ?>><?php _e('قیمت ثابت', 'wp-directory'); ?></option>
                                <option value="user_defined" <?php selected('user_defined', $product_settings['pricing_mode']); ?>><?php _e('قیمت توسط کاربر', 'wp-directory'); ?></option>
                            </select>
                        </div>
                        <div class="wpd-compound-sub-field-setting wpd-product-fixed-price-wrapper" style="<?php echo $product_settings['pricing_mode'] === 'fixed' ? '' : 'display:none;'; ?>">
                            <label><?php _e('قیمت ثابت:', 'wp-directory'); ?></label>
                            <input type="number" name="wpd_fields[<?php echo esc_attr($index); ?>][product_settings][fixed_price]" value="<?php echo esc_attr($product_settings['fixed_price']); ?>" class="small-text">
                        </div>
                        <div class="wpd-compound-sub-field-setting">
                            <label>
                                <input type="checkbox" name="wpd_fields[<?php echo esc_attr($index); ?>][product_settings][enable_quantity]" value="1" <?php checked(1, $product_settings['enable_quantity']); ?>>
                                <?php _e('فعال‌سازی انتخاب تعداد', 'wp-directory'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="wpd-field-rules">
                        <h4><?php _e('قوانین اعتبارسنجی', 'wp-directory'); ?></h4>
                        <label>
                            <input type="checkbox" name="wpd_fields[<?php echo esc_attr($index); ?>][required]" value="1" <?php checked(1, $required); ?>>
                            <?php _e('الزامی', 'wp-directory'); ?>
                        </label>
                        <label style="margin-right: 15px;">
                            <input type="checkbox" name="wpd_fields[<?php echo esc_attr($index); ?>][unique]" value="1" <?php checked(1, $unique); ?>>
                            <?php _e('مقدار یکتا', 'wp-directory'); ?>
                        </label>
                         <label style="margin-right: 15px;">
                            <input type="checkbox" name="wpd_fields[<?php echo esc_attr($index); ?>][show_in_filter]" value="1" <?php checked(1, $show_in_filter); ?>>
                            <?php _e('نمایش در فیلتر', 'wp-directory'); ?>
                        </label>
                    </div>

                    <div class="wpd-field-rules wpd-conditional-logic-wrapper">
                        <h4><?php _e('منطق شرطی', 'wp-directory'); ?></h4>
                        <label>
                            <input type="checkbox" class="wpd-conditional-enable" name="wpd_fields[<?php echo esc_attr($index); ?>][conditional_logic][enabled]" value="1" <?php checked(1, $conditional_logic['enabled']); ?>>
                            <?php _e('فعال کردن منطق شرطی', 'wp-directory'); ?>
                        </label>
                        <div class="wpd-conditional-rules" style="<?php echo $conditional_logic['enabled'] ? '' : 'display:none;'; ?>">
                            <select name="wpd_fields[<?php echo esc_attr($index); ?>][conditional_logic][action]">
                                <option value="show" <?php selected('show', $conditional_logic['action']); ?>><?php _e('نمایش بده', 'wp-directory'); ?></option>
                                <option value="hide" <?php selected('hide', $conditional_logic['action']); ?>><?php _e('پنهان کن', 'wp-directory'); ?></option>
                            </select>
                            <?php _e('این فیلد را اگر', 'wp-directory'); ?>
                            <select class="wpd-conditional-target-field" name="wpd_fields[<?php echo esc_attr($index); ?>][conditional_logic][target_field]">
                                <option value=""><?php _e('یک فیلد انتخاب کنید', 'wp-directory'); ?></option>
                                <?php
                                if (!empty($all_fields)) {
                                    foreach ($all_fields as $other_field) {
                                        if (empty($other_field['key']) || $other_field['key'] === $key) continue;
                                        echo '<option value="' . esc_attr($other_field['key']) . '" ' . selected($other_field['key'], $conditional_logic['target_field'], false) . '>' . esc_html($other_field['label']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <input type="text" name="wpd_fields[<?php echo esc_attr($index); ?>][conditional_logic][value]" value="<?php echo esc_attr($conditional_logic['value']); ?>" placeholder="<?php _e('مقدار', 'wp-directory'); ?>" style="<?php echo in_array($conditional_logic['operator'], ['is_empty', 'is_not_empty']) ? 'display:none;' : ''; ?>">
                        </div>
                    </div>

                    <div class="wpd-repeater-fields-wrapper" style="<?php echo ($type === 'repeater') ? '' : 'display:none;'; ?>">
                        <h4><?php _e('فیلدهای داخلی تکرارشونده', 'wp-directory'); ?></h4>
                        <div class="wpd-sortable-list wpd-repeater-sub-fields">
                            <?php if (!empty($sub_fields)): foreach($sub_fields as $sub_index => $sub_field): ?>
                                <?php $this->render_field_builder_row($index . '][sub_fields][' . $sub_index, $sub_field, $sub_fields); ?>
                            <?php endforeach; endif; ?>
                        </div>
                        <a href="#" class="button wpd-add-sub-field"><?php _e('افزودن فیلد داخلی', 'wp-directory'); ?></a>
                    </div>

                    <a href="#" class="button wpd-remove-field" style="margin: 10px;"><?php _e( 'حذف فیلد', 'wp-directory' ); ?></a>
                </div>
            </div>
            <?php
        }

        public function render_listing_type_metabox( $post ) {
            wp_nonce_field( 'wpd_save_listing_meta', 'wpd_listing_nonce' );
            
            $listing_types = get_posts(['post_type' => 'wpd_listing_type', 'numberposts' => -1]);
            $selected_type = get_post_meta($post->ID, '_wpd_listing_type', true);
            ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="wpd_listing_type_selector"><?php _e('نوع آگهی', 'wp-directory'); ?></label></th>
                        <td>
                            <select name="wpd_listing_type" id="wpd_listing_type_selector" style="width:100%;">
                                <option value=""><?php _e('-- انتخاب کنید --', 'wp-directory'); ?></option>
                                <?php foreach($listing_types as $type): ?>
                                    <option value="<?php echo esc_attr($type->ID); ?>" <?php selected($selected_type, $type->ID); ?>><?php echo esc_html($type->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('با انتخاب نوع آگهی، فیلدهای سفارشی و طبقه‌بندی‌های مربوطه در ستون کناری نمایش داده می‌شوند.', 'wp-directory'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <hr>
            <div id="wpd-admin-custom-fields-wrapper">
                <?php 
                if(!empty($selected_type)) {
                    echo $this->get_admin_fields_html($selected_type, $post->ID);
                }
                ?>
            </div>
            <?php
        }

        public function render_upgrade_details_metabox($post) {
            wp_nonce_field( 'wpd_save_upgrade_meta', 'wpd_upgrade_nonce' );
            $meta = get_post_meta( $post->ID );
            $currency = Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'];
            $upgrade_type = $meta['_upgrade_type'][0] ?? '';
            ?>
            <table class="form-table">
                <tr>
                    <th><label for="wpd_price"><?php printf(__( 'قیمت (%s)', 'wp-directory' ), esc_html($currency)); ?></label></th>
                    <td><input type="number" id="wpd_price" name="wpd_meta[_price]" value="<?php echo esc_attr( $meta['_price'][0] ?? '0' ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="wpd_upgrade_type"><?php _e('نوع ارتقا', 'wp-directory'); ?></label></th>
                    <td>
                        <select id="wpd_upgrade_type" name="wpd_meta[_upgrade_type]">
                            <option value="featured" <?php selected($upgrade_type, 'featured'); ?>><?php _e('ویژه کردن (Featured)', 'wp-directory'); ?></option>
                            <option value="bump_up" <?php selected($upgrade_type, 'bump_up'); ?>><?php _e('نردبان (Bump Up)', 'wp-directory'); ?></option>
                            <option value="top_of_category" <?php selected($upgrade_type, 'top_of_category'); ?>><?php _e('پین در بالای دسته (Top of Category)', 'wp-directory'); ?></option>
                            <option value="urgent" <?php selected($upgrade_type, 'urgent'); ?>><?php _e('برچسب فوری (Urgent)', 'wp-directory'); ?></option>
                        </select>
                        <p class="description"><?php _e('عملکرد این بسته ارتقا را مشخص کنید.', 'wp-directory'); ?></p>
                    </td>
                </tr>
                <tr class="wpd-duration-row" style="<?php echo ($upgrade_type === 'bump_up') ? 'display:none;' : ''; ?>">
                    <th><label for="wpd_duration"><?php _e( 'مدت اعتبار (روز)', 'wp-directory' ); ?></label></th>
                    <td>
                        <input type="number" id="wpd_duration" name="wpd_meta[_duration]" value="<?php echo esc_attr( $meta['_duration'][0] ?? '7' ); ?>" class="regular-text">
                        <p class="description"><?php _e( 'این ارتقا برای چند روز فعال خواهد بود؟ (برای نردبان کاربرد ندارد)', 'wp-directory'); ?></p>
                    </td>
                </tr>
            </table>
            <?php
        }
        
        public function save_listing_type_meta_data( $post_id ) {
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
            if ( get_post_type($post_id) !== 'wpd_listing_type' ) return;
            if ( ! current_user_can( 'edit_post', $post_id ) ) return;

            if (isset($_FILES['wpd_import_fields_file']) && !empty($_FILES['wpd_import_fields_file']['tmp_name'])) {
                if (!current_user_can('manage_options')) {
                    return;
                }
                $file = $_FILES['wpd_import_fields_file'];
                if ($file['type'] === 'application/json') {
                    $content = file_get_contents($file['tmp_name']);
                    $fields_data = json_decode($content, true);
                    if (is_array($fields_data)) {
                        $sanitized_fields = $this->sanitize_field_builder_data($fields_data);
                        update_post_meta($post_id, '_wpd_custom_fields', $sanitized_fields);
                        return;
                    }
                }
            }

            if ( isset( $_POST['wpd_listing_type_nonce'] ) && wp_verify_nonce( $_POST['wpd_listing_type_nonce'], 'wpd_save_listing_type_meta' ) ) {
                if(isset($_POST['wpd_meta']) && is_array($_POST['wpd_meta'])) {
                    foreach($_POST['wpd_meta'] as $key => $value) {
                        update_post_meta($post_id, $key, sanitize_text_field($value));
                    }
                }
            }

            $notif_settings = [];
            if(isset($_POST['wpd_notifications']) && is_array($_POST['wpd_notifications'])) {
                foreach($_POST['wpd_notifications'] as $event => $settings) {
                    $notif_settings[sanitize_key($event)]['email'] = isset($settings['email']) ? 1 : 0;
                    $notif_settings[sanitize_key($event)]['sms'] = isset($settings['sms']) ? 1 : 0;
                }
            }
            update_post_meta($post_id, '_notification_settings', $notif_settings);

            if ( isset( $_POST['wpd_field_builder_nonce'] ) && wp_verify_nonce( $_POST['wpd_field_builder_nonce'], 'wpd_save_field_builder_meta' ) ) {
                $sanitized_fields = [];
                if ( isset( $_POST['wpd_fields'] ) && is_array($_POST['wpd_fields']) ) {
                    $keys = [];
                    $has_duplicates = false;
                    $fields_to_process = $_POST['wpd_fields'];
                    
                    $find_keys = function($fields_array) use (&$keys, &$has_duplicates, &$find_keys) {
                        foreach ($fields_array as $field) {
                            if (isset($field['key']) && !empty($field['key'])) {
                                if (in_array($field['key'], $keys)) {
                                    $has_duplicates = true;
                                    return;
                                }
                                $keys[] = $field['key'];
                            }
                            if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
                                $find_keys($field['sub_fields']);
                            }
                        }
                    };
                    $find_keys($fields_to_process);

                    if ($has_duplicates) {
                        add_settings_error('wpd_fields', 'duplicate_keys', 'خطا: کلیدهای متا تکراری یافت شد. لطفاً کلیدهای متا را منحصر به فرد کنید.', 'error');
                        return;
                    }
                    
                    $sanitized_fields = $this->sanitize_field_builder_data($_POST['wpd_fields']);
                }
                update_post_meta( $post_id, '_wpd_custom_fields', $sanitized_fields );
            }
            
            if ( isset( $_POST['wpd_taxonomy_builder_nonce'] ) && wp_verify_nonce( $_POST['wpd_taxonomy_builder_nonce'], 'wpd_save_taxonomy_builder_meta' ) ) {
                if ( isset( $_POST['wpd_taxonomies'] ) && is_array($_POST['wpd_taxonomies']) ) {
                    $sanitized_taxs = [];
                    foreach ( $_POST['wpd_taxonomies'] as $tax ) {
                        if ( ! empty( $tax['name'] ) && ! empty( $tax['slug'] ) ) {
                            $sanitized_taxs[] = [ 'name' => sanitize_text_field( $tax['name'] ), 'slug' => sanitize_title( $tax['slug'] ), 'hierarchical' => intval( $tax['hierarchical'] ) ];
                        }
                    }
                    update_post_meta( $post_id, '_defined_taxonomies', $sanitized_taxs );
                } else {
                    delete_post_meta($post_id, '_defined_taxonomies');
                }
            }
        }

        private function sanitize_field_builder_data($fields) {
            $sanitized_data = [];
            if (!is_array($fields)) return $sanitized_data;

            foreach ($fields as $field) {
                if (empty($field['label']) || empty($field['key'])) continue;

                $sanitized_field = [
                    'label'   => sanitize_text_field($field['label']),
                    'key'     => sanitize_key($field['key']),
                    'type'    => sanitize_text_field($field['type']),
                    'options' => sanitize_textarea_field($field['options']),
                    'required' => isset($field['required']) ? 1 : 0,
                    'unique'   => isset($field['unique']) ? 1 : 0,
                    'show_in_filter' => isset($field['show_in_filter']) ? 1 : 0,
                    'show_in_frontend' => isset($field['show_in_frontend']) ? 1 : 0,
                    'width_class' => isset($field['width_class']) ? sanitize_key($field['width_class']) : 'full',
                    'help_text' => isset($field['help_text']) ? sanitize_text_field($field['help_text']) : '',
                    'placeholder' => isset($field['placeholder']) ? sanitize_text_field($field['placeholder']) : '',
                    'default_value' => isset($field['default_value']) ? sanitize_text_field($field['default_value']) : '',
                ];

                if (isset($field['conditional_logic']['enabled'])) {
                    $sanitized_field['conditional_logic'] = [
                        'enabled'      => 1,
                        'action'       => sanitize_text_field($field['conditional_logic']['action']),
                        'target_field' => sanitize_key($field['conditional_logic']['target_field']),
                        'operator'     => sanitize_text_field($field['conditional_logic']['operator']),
                        'value'        => sanitize_text_field($field['conditional_logic']['value']),
                    ];
                } else {
                    $sanitized_field['conditional_logic'] = ['enabled' => 0];
                }
                
                if (in_array($sanitized_field['type'], ['image', 'file', 'gallery']) && isset($field['file_settings'])) {
                    $sanitized_field['file_settings'] = [
                        'allowed_formats' => sanitize_text_field($field['file_settings']['allowed_formats']),
                        'max_size'        => absint($field['file_settings']['max_size']),
                        'use_as_featured_image' => isset($field['file_settings']['use_as_featured_image']) ? 1 : 0,
                        'max_files'       => isset($field['file_settings']['max_files']) ? absint($field['file_settings']['max_files']) : 1
                    ];
                }

                if (isset($field['address_settings']) && is_array($field['address_settings'])) {
                    foreach ($field['address_settings'] as $sub_key => $sub_settings) {
                        $sanitized_field['address_settings'][sanitize_key($sub_key)] = [
                            'enabled' => isset($sub_settings['enabled']) ? 1 : 0,
                            'width'   => isset($sub_settings['width']) ? sanitize_key($sub_settings['width']) : 'full',
                        ];
                    }
                }
                if (isset($field['identity_settings']) && is_array($field['identity_settings'])) {
                     foreach ($field['identity_settings'] as $sub_key => $sub_settings) {
                        $sanitized_field['identity_settings'][sanitize_key($sub_key)] = [
                            'enabled' => isset($sub_settings['enabled']) ? 1 : 0,
                            'width'   => isset($sub_settings['width']) ? sanitize_key($sub_settings['width']) : 'full',
                        ];
                    }
                }
                
                if (isset($field['product_settings']) && is_array($field['product_settings'])) {
                    $sanitized_field['product_settings'] = [
                        'pricing_mode' => sanitize_text_field($field['product_settings']['pricing_mode']),
                        'fixed_price'   => sanitize_text_field($field['product_settings']['fixed_price']),
                        'enable_quantity' => isset($field['product_settings']['enable_quantity']) ? 1 : 0,
                    ];
                }

                if ($sanitized_field['type'] === 'repeater' && !empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                    $sanitized_field['sub_fields'] = $this->sanitize_field_builder_data($field['sub_fields']);
                }
                $sanitized_data[] = $sanitized_field;
            }
            return $sanitized_data;
        }
        
        public function save_listing_meta_data( $post_id ) {
            if ( ! isset( $_POST['wpd_listing_nonce'] ) || ! wp_verify_nonce( $_POST['wpd_listing_nonce'], 'wpd_save_listing_meta' ) ) return;
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
            if ( ! current_user_can( 'edit_post', $post_id ) ) return;

            if ( isset( $_POST['wpd_listing_type'] ) ) {
                update_post_meta($post_id, '_wpd_listing_type', intval($_POST['wpd_listing_type']));
            }

            $listing_type_id = get_post_meta($post_id, '_wpd_listing_type', true);
            if (empty($listing_type_id)) return;

            $field_definitions = get_post_meta($listing_type_id, '_wpd_custom_fields', true);
            if (empty($field_definitions) || !is_array($field_definitions)) return;
            
            $posted_data = $_POST['wpd_custom'] ?? [];

            $this->process_and_save_fields($post_id, $field_definitions, $posted_data);
        }

        private function process_and_save_fields($post_id, $field_definitions, $posted_data, $meta_prefix = '_wpd_') {
            $featured_image_id = null;

            foreach ($field_definitions as $field_def) {
                $field_key = $field_def['key'];
                $meta_key = $meta_prefix . sanitize_key($field_key);
                $value_exists = isset($posted_data[$field_def['key']]);
                $posted_value = $value_exists ? $posted_data[$field_def['key']] : null;

                if (!$value_exists) {
                    if (in_array($field_def['type'], ['checkbox', 'multiselect', 'repeater', 'social_networks', 'simple_list', 'gallery', 'image', 'file', 'product'])) {
                        delete_post_meta($post_id, $meta_key);
                    }
                    continue;
                }

                if (in_array($field_def['type'], ['repeater', 'social_networks', 'simple_list'])) {
                    $repeater_data = [];
                    if (is_array($posted_value)) {
                        $posted_value = array_values($posted_value);
                        foreach ($posted_value as $index => $row_data) {
                            if ($index === '__INDEX__') continue;
                            
                            $sanitized_row = [];
                            if ($field_def['type'] === 'repeater' && !empty($field_def['sub_fields'])) {
                                foreach ($field_def['sub_fields'] as $sub_field_def) {
                                    $sub_field_key = $sub_field_def['key'];
                                    if (isset($row_data[$sub_field_key])) {
                                        $sanitized_row[$sub_field_key] = $this->sanitize_field_value($row_data[$sub_field_key], $sub_field_def['type']);
                                    }
                                }
                            } else { 
                                $sanitized_row = array_map('sanitize_text_field', $row_data);
                            }
                            
                            $is_valid_row = false;
                            if ($field_def['type'] === 'social_networks') {
                                if (!empty($sanitized_row['url'])) {
                                    $is_valid_row = true;
                                }
                            } else {
                                if (!empty(array_filter($sanitized_row))) {
                                    $is_valid_row = true;
                                }
                            }
    
                            if ($is_valid_row) {
                                $repeater_data[] = $sanitized_row;
                            }
                        }
                    }
                    
                    if (empty($repeater_data)) {
                        delete_post_meta($post_id, $meta_key);
                    } else {
                        update_post_meta($post_id, $meta_key, $repeater_data);
                    }
                } elseif (in_array($field_def['type'], ['address', 'identity'])) {
                    $sanitized_value = is_array($posted_value) ? array_map('sanitize_text_field', $posted_value) : sanitize_text_field($posted_value);
                    update_post_meta($post_id, $meta_key, $sanitized_value);
                } else {
                    $sanitized_value = $this->sanitize_field_value($posted_value, $field_def['type']);
                    update_post_meta($post_id, $meta_key, $sanitized_value);

                    if ($field_def['type'] === 'image' && isset($field_def['file_settings']['use_as_featured_image']) && !empty($sanitized_value)) {
                         $featured_image_id = $sanitized_value;
                    }
                }
            }
            
            if ($featured_image_id) {
                set_post_thumbnail($post_id, $featured_image_id);
            } else {
                 delete_post_thumbnail($post_id);
            }
        }

        private function sanitize_field_value($value, $type) {
            switch ($type) {
                case 'email':
                    return sanitize_email($value);
                case 'number':
                    return intval($value);
                case 'url':
                    return esc_url_raw($value);
                case 'textarea':
                    return sanitize_textarea_field($value);
                case 'checkbox':
                case 'multiselect':
                    return is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
                case 'gallery':
                case 'image':
                case 'file':
                    return implode(',', array_map('absint', explode(',', $value)));
                case 'mobile':
                case 'phone':
                case 'postal_code':
                case 'national_id':
                    return sanitize_text_field(preg_replace('/[^0-9]/', '', $value));
                case 'date':
                    return sanitize_text_field($value);
                case 'product':
                    if (!is_array($value)) return null;
                    return [
                        'selected' => isset($value['selected']) ? 1 : 0,
                        'quantity' => isset($value['quantity']) ? absint($value['quantity']) : 1,
                        'price'    => isset($value['price']) ? sanitize_text_field($value['price']) : 0,
                    ];
                case 'map':
                case 'text':
                case 'select':
                case 'radio':
                case 'time':
                default:
                    return sanitize_text_field($value);
            }
        }

        public function save_upgrade_meta_data($post_id) {
            if ( ! isset( $_POST['wpd_upgrade_nonce'] ) || ! wp_verify_nonce( $_POST['wpd_upgrade_nonce'], 'wpd_save_upgrade_meta' ) ) return;
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
            if ( get_post_type($post_id) !== 'wpd_upgrade' ) return;
            if ( ! current_user_can( 'edit_post', $post_id ) ) return;

            if(isset($_POST['wpd_meta']) && is_array($_POST['wpd_meta'])) {
                foreach($_POST['wpd_meta'] as $key => $value) {
                    update_post_meta($post_id, $key, sanitize_text_field($value));
                }
            }
        }

        public function enqueue_admin_scripts($hook) {
            global $pagenow, $post_type;
            if ( ($pagenow == 'post-new.php' || $pagenow == 'post.php') && in_array($post_type, ['wpd_listing', 'wpd_listing_type']) ) {
                wp_enqueue_script('tags-box');
                wp_enqueue_media();
                wp_enqueue_script('jquery-ui-sortable');

                wp_enqueue_script('jquery-ui-datepicker');
                wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
                
                wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css', [], '1.7.1');
                wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], '1.7.1', true);

                wp_localize_script('jquery', 'wpd_admin_params', [
                    'ajax_nonce' => wp_create_nonce("wpd_admin_fields_nonce"),
                ]);
                
                wp_add_inline_style('wpd-admin-style', '
                    .wpd-fields-container { display: flex; flex-wrap: wrap; margin: 0 -10px; }
                    .wpd-admin-field-wrapper { padding: 0 10px; box-sizing: border-box; }
                    .wpd-admin-field-col-full { width: 100%; }
                    .wpd-admin-field-col-half { width: 50%; }
                    .wpd-admin-field-col-third { width: 33.33%; }
                    .wpd-admin-field-col-quarter { width: 25%; }
                    @media (max-width: 782px) {
                        .wpd-admin-field-col-half, .wpd-admin-field-col-third, .wpd-admin-field-col-quarter { width: 100%; }
                    }
                ');
            }
        }

        public function admin_scripts() {
            global $pagenow, $post_type;
            
            if ( ( $pagenow == 'post-new.php' || $pagenow == 'post.php' ) && $post_type == 'wpd_listing_type' ) {
                 ?>
                <style>
                    .wpd-field-row { margin-bottom: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;}
                    .wpd-field-header { display: flex; align-items: center; padding: 10px; gap: 10px; }
                    .wpd-field-header .handle { cursor: move; color: #888; }
                    .wpd-field-header .wpd-toggle-field-details, .wpd-field-header .wpd-copy-field { margin-left: auto; text-decoration: none; }
                    .wpd-field-header .wpd-copy-field { margin-left: 10px; }
                    .wpd-field-details { padding: 10px; border-top: 1px solid #ddd; }
                    .wpd-field-inputs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
                    .wpd-field-inputs input, .wpd-field-inputs select, .wpd-field-inputs textarea { margin: 0; }
                    .wpd-field-options { width: 100%; height: 60px; }
                    .wpd-repeater-fields-wrapper { padding: 10px; margin-top: 10px; border: 1px dashed #ccc; background: #fff; }
                    .wpd-repeater-sub-fields .wpd-field-row { border-style: dashed; }
                    .wpd-field-key-error { border-color: red !important; }
                    .wpd-field-rules, .wpd-field-settings-panel { padding: 10px; margin-top: 10px; border: 1px solid #e0e0e0; background: #fff; }
                    .wpd-conditional-rules { padding-top: 10px; margin-top: 10px; border-top: 1px dashed #ccc; }
                    .wpd-conditional-rules select, .wpd-conditional-rules input { vertical-align: middle; }
                    .wpd-compound-sub-field-setting { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
                </style>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {

                        function toggleOptionsField(element) {
                            var fieldType = $(element).val();
                            var $details = $(element).closest('.wpd-field-details');
                            $details.find('.wpd-field-options').toggle(['select', 'multiselect', 'checkbox', 'radio', 'html_content'].includes(fieldType));
                            $details.find('.wpd-repeater-fields-wrapper').toggle(fieldType === 'repeater');
                            $details.find('.wpd-address-settings').toggle(fieldType === 'address');
                            $details.find('.wpd-identity-settings').toggle(fieldType === 'identity');
                            $details.find('.wpd-product-settings').toggle(fieldType === 'product');
                            
                            var $fileSettings = $details.find('.wpd-file-settings');
                            $fileSettings.toggle(['image', 'file', 'gallery'].includes(fieldType));

                            $fileSettings.find('.wpd-image-settings').toggle(fieldType === 'image');
                            $fileSettings.find('.wpd-gallery-settings').toggle(fieldType === 'gallery');

                        }
                        
                        function initSortable() {
                            $('.wpd-sortable-list').sortable({
                                handle: '.handle',
                                opacity: 0.7,
                                placeholder: 'wpd-sortable-placeholder',
                                start: function(e, ui) {
                                    ui.placeholder.height(ui.item.height());
                                }
                            });
                        }
                        initSortable();

                        function updateConditionalFieldDropdowns() {
                            var fields = [];
                            $('#wpd-fields-container > .wpd-field-row').each(function(){
                                var key = $(this).find('.field-key-input').first().val();
                                var label = $(this).find('.field-label-input').first().val();
                                if (key && label) {
                                    fields.push({ key: key, label: label });
                                }
                            });

                            $('.wpd-conditional-target-field').each(function(){
                                var $select = $(this);
                                var currentTarget = $select.val();
                                var parentFieldKey = $select.closest('.wpd-field-row').data('field-key');

                                $select.html('<option value=""><?php _e('یک فیلد انتخاب کنید', 'wp-directory'); ?></option>');

                                fields.forEach(function(field) {
                                    if (field.key !== parentFieldKey) {
                                        var selected = (field.key === currentTarget) ? ' selected' : '';
                                        $select.append('<option value="' + field.key + '"' + selected + '>' + field.label + '</option>');
                                    }
                                });
                            });
                        }

                        function getNewIndex() {
                            var container = $('#wpd-fields-container');
                            var maxIndex = -1;
                            container.children().each(function() {
                                var index = parseInt($(this).data('index'));
                                if (!isNaN(index) && index > maxIndex) {
                                    maxIndex = index;
                                }
                            });
                            return maxIndex + 1;
                        }


                        $('#wpd-field-builder-wrapper').on('change', '.wpd-field-type-selector', function() {
                            toggleOptionsField(this);
                            $(this).closest('.wpd-field-row').find('.wpd-field-type-display').text($(this).val());
                        });
                        $('#wpd-field-builder-wrapper').on('click', '.wpd-toggle-field-details', function(e) {
                            e.preventDefault();
                            $(this).closest('.wpd-field-row').find('.wpd-field-details').slideToggle('fast');
                        });
                        $('#wpd-field-builder-wrapper').on('keyup', '.field-label-input', function() {
                            var newTitle = $(this).val() || 'فیلد جدید';
                            $(this).closest('.wpd-field-row').find('.wpd-field-header strong').text(newTitle);
                            updateConditionalFieldDropdowns();
                        });
                        $('#wpd-field-builder-wrapper').on('keyup', '.field-key-input', function() {
                            var newKey = $(this).val().replace(/[^a-z0-9_]/gi, '').toLowerCase();
                            $(this).val(newKey);
                            $(this).closest('.wpd-field-row').data('field-key', newKey);
                            updateConditionalFieldDropdowns();
                        });

                        $('#wpd-field-builder-wrapper').on('click', '.wpd-remove-field', function(e) {
                            e.preventDefault();
                            if (confirm('آیا از حذف این فیلد مطمئن هستید؟')) {
                                $(this).closest('.wpd-field-row').remove();
                                updateConditionalFieldDropdowns();
                            }
                        });
                        $('#wpd-field-builder-wrapper').on('click', '.wpd-quick-remove-field', function(e) {
                            e.preventDefault();
                             if (confirm('<?php _e('آیا از حذف این فیلد مطمئن هستید؟', 'wp-directory'); ?>')) {
                                $(this).closest('.wpd-field-row').remove();
                                updateConditionalFieldDropdowns();
                            }
                        });


                        $('#wpd-add-field').on('click', function(e) {
                            e.preventDefault();
                            var newIndex = getNewIndex();
                            var field_html = $(<?php echo json_encode($this->get_field_builder_row_html('__INDEX__', [], $fields)); ?>.replace(/__INDEX__/g, newIndex));
                            field_html.find('.wpd-field-details').show();
                            $('#wpd-fields-container').append(field_html);
                            field_html.find('.wpd-file-settings').hide();
                            updateConditionalFieldDropdowns();
                        });
                        
                        $('#wpd-add-taxonomy').on('click', function(e) {
                            e.preventDefault();
                            var container = $('#wpd-taxonomies-container');
                            var newIndex = container.children('.wpd-field-row').length;
                            var newRowHtml = `
                                <div class="wpd-field-row">
                                    <span class="dashicons dashicons-move handle"></span>
                                    <div class="wpd-field-inputs">
                                        <input type="text" name="wpd_taxonomies[${newIndex}][name]" value="" placeholder="<?php _e( 'نام طبقه‌بندی (فارسی)', 'wp-directory' ); ?>">
                                        <input type="text" name="wpd_taxonomies[${newIndex}][slug]" value="" placeholder="<?php _e( 'نامک (انگلیسی)', 'wp-directory' ); ?>">
                                        <select name="wpd_taxonomies[${newIndex}][hierarchical]">
                                            <option value="1"><?php _e('سلسله مراتبی', 'wp-directory'); ?></option>
                                            <option value="0"><?php _e('غیر سلسله مراتبی (تگ)', 'wp-directory'); ?></option>
                                        </select>
                                    </div>
                                    <a href="#" class="button wpd-remove-taxonomy" style="margin: 10px;"><?php _e( 'حذف', 'wp-directory' ); ?></a>
                                </div>
                            `;
                            container.append(newRowHtml);
                            initSortable();
                        });

                        $('#wpd-taxonomy-builder-wrapper').on('click', '.wpd-remove-taxonomy', function(e) {
                             e.preventDefault();
                             $(this).closest('.wpd-field-row').remove();
                        });


                        $('#wpd-field-builder-wrapper').on('click', '.wpd-copy-field', function(e) {
                            e.preventDefault();
                            var $originalRow = $(this).closest('.wpd-field-row');
                            var $clonedRow = $originalRow.clone();
                            var newIndex = getNewIndex();
                            
                            $clonedRow.attr('data-index', newIndex);
                            $clonedRow.find('[name]').each(function() {
                                this.name = this.name.replace(/\[\d+\]/, '[' + newIndex + ']');
                            });

                            var oldKey = $clonedRow.find('.field-key-input').val();
                            var newKey = oldKey + '_copy';
                            $clonedRow.find('.field-key-input').val(newKey);
                            $clonedRow.attr('data-field-key', newKey);

                            var oldLabel = $clonedRow.find('.field-label-input').val();
                            $clonedRow.find('.field-label-input').val(oldLabel + ' (کپی)');
                            $clonedRow.find('.wpd-field-header strong').text(oldLabel + ' (کپی)');

                            $clonedRow.insertAfter($originalRow);
                            updateConditionalFieldDropdowns();
                        });

                        $('#wpd-field-builder-wrapper').on('click', '.wpd-add-sub-field', function(e) {
                            e.preventDefault();
                            var subContainer = $(this).prev('.wpd-repeater-sub-fields');
                            var parentIndex = $(this).closest('.wpd-field-row').data('index');
                            var newSubIndex = subContainer.children().length;
                            var namePrefix = parentIndex + '][sub_fields][' + newSubIndex;
                            var field_html = $(<?php echo json_encode($this->get_field_builder_row_html('__INDEX__')); ?>.replace(/__INDEX__/g, namePrefix));
                            field_html.find('.wpd-field-details').show();
                            subContainer.append(field_html);
                        });

                        $('#wpd-field-builder-wrapper').on('change', '.wpd-conditional-enable', function() {
                            $(this).closest('.wpd-conditional-logic-wrapper').find('.wpd-conditional-rules').toggle($(this).is(':checked'));
                        });

                        $('#wpd-field-builder-wrapper').on('change', '.wpd-conditional-rules select[name*="[operator]"]', function() {
                            var operator = $(this).val();
                            var $valueInput = $(this).siblings('input[name*="[value]"]');
                            if (operator === 'is_empty' || operator === 'is_not_empty') {
                                $valueInput.hide();
                            } else {
                                $valueInput.show();
                            }
                        });
                        
                        $('#wpd-field-builder-wrapper').on('change', '.wpd-product-pricing-mode', function() {
                            var mode = $(this).val();
                            $(this).closest('.wpd-product-settings').find('.wpd-product-fixed-price-wrapper').toggle(mode === 'fixed');
                        });
                        
                        $('form#post').on('submit', function(e){
                            var keys = {};
                            var duplicateFound = false;
                            $('.field-key-input').each(function(){
                                var key = $(this).val();
                                if(key){
                                    if(keys[key]){
                                        duplicateFound = true;
                                        $(this).addClass('wpd-field-key-error');
                                    } else {
                                        keys[key] = true;
                                        $(this).removeClass('wpd-field-key-error');
                                    }
                                }
                            });

                            if(duplicateFound){
                                alert('خطا: کلیدهای متا تکراری یافت شد. لطفاً کلیدهای متا را منحصر به فرد کنید.');
                                e.preventDefault();
                            }
                        });
                        
                        updateConditionalFieldDropdowns();
                    });
                </script>
                <?php
            }

            if ( ( $pagenow == 'post-new.php' || $pagenow == 'post.php' ) && $post_type == 'wpd_listing' ) {
                ?>
                <style>
                    .wpd-fields-container {
                        display: flex;
                        flex-wrap: wrap;
                        margin: 0 -10px;
                    }
                    .wpd-admin-field-wrapper {
                        padding: 0 10px;
                        margin-bottom: 20px;
                        box-sizing: border-box;
                    }
                    .wpd-admin-field-col-full { width: 100%; }
                    .wpd-admin-field-col-half { width: 50%; }
                    .wpd-admin-field-col-third { width: 33.33%; }
                    .wpd-admin-field-col-quarter { width: 25%; }
                    @media (max-width: 782px) {
                        .wpd-admin-field-col-half,
                        .wpd-admin-field-col-third,
                        .wpd-admin-field-col-quarter {
                            width: 100%;
                        }
                    }
                    .wpd-admin-field-wrapper label { font-weight: bold; display: block; margin-bottom: 5px; }
                    .wpd-admin-field-wrapper input[type="text"],
                    .wpd-admin-field-wrapper input[type="url"],
                    .wpd-admin-field-wrapper input[type="email"],
                    .wpd-admin-field-wrapper input[type="number"],
                    .wpd-admin-field-wrapper input[type="time"],
                    .wpd-admin-field-wrapper select,
                    .wpd-admin-field-wrapper textarea {
                        width: 100%;
                    }
                    .wpd-gallery-field-wrapper .gallery-preview { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
                    .wpd-gallery-field-wrapper .gallery-preview .image-container { position: relative; }
                    .wpd-gallery-field-wrapper .gallery-preview img { width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd; }
                    .wpd-gallery-field-wrapper .gallery-preview .remove-image { position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; cursor: pointer; width: 20px; height: 20px; text-align: center; line-height: 20px; }
                    .wpd-repeater-row, .wpd-compound-field-row { border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #fdfdfd; }
                    .wpd-repeater-row-actions { margin-top: 10px; text-align: left; }
                    .ui-datepicker {
                        direction: rtl;
                    }
                    .ui-datepicker-header {
                        direction: ltr;
                    }
                    .wpd-map-field-wrapper .map-preview {
                        width: 100%;
                        height: 250px;
                        background: #eee;
                        margin-top: 10px;
                    }
                </style>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {

                        const ajaxNonce = '<?php echo wp_create_nonce("wpd_admin_fields_nonce"); ?>';
                        
                        // BUG FIX: Function to handle taxonomy metabox visibility
                        function toggleTaxonomyMetaboxes(visible_tax_slugs) {
                            // Hide all potential dynamic taxonomy metaboxes first
                            $('div[id^="taxonomy-"]').each(function() {
                                var taxId = $(this).attr('id');
                                // Exclude the two global taxonomies
                                if (taxId !== 'taxonomy-wpd_listing_categorydiv' && taxId !== 'taxonomy-wpd_listing_locationdiv') {
                                    $(this).hide();
                                }
                            });

                            // Show only the taxonomies relevant to the selected type
                            if (Array.isArray(visible_tax_slugs)) {
                                visible_tax_slugs.forEach(function(slug) {
                                    $('#taxonomy-' + slug + 'div').show();
                                });
                            }
                        }

                        function initializeWpdComponents(container) {
                            container.find('.wpd-upload-gallery-button:not([data-initialized])').each(function() {
                                var $button = $(this);
                                $button.attr('data-initialized', 'true');
                                $button.off('click').on('click', function(e) {
                                    e.preventDefault();
                                    var input = $button.siblings('input[type="hidden"]');
                                    var preview = $button.siblings('.gallery-preview');
                                    var image_ids = input.val() ? input.val().split(',').map(Number).filter(Boolean) : [];
                                    var frame = wp.media({
                                        title: '<?php _e("انتخاب تصاویر گالری", "wp-directory"); ?>',
                                        button: { text: '<?php _e("استفاده از این تصاویر", "wp-directory"); ?>' },
                                        multiple: 'add'
                                    });
                                    frame.on('open', function() {
                                        var selection = frame.state().get('selection');
                                        image_ids.forEach(function(id) {
                                            var attachment = wp.media.attachment(id);
                                            attachment.fetch();
                                            selection.add(attachment ? [attachment] : []);
                                        });
                                    });
                                    frame.on('select', function() {
                                        var selection = frame.state().get('selection');
                                        var new_ids = [];
                                        preview.html('');
                                        selection.each(function(attachment) {
                                            new_ids.push(attachment.id);
                                            preview.append('<div class="image-container"><img src="' + attachment.attributes.sizes.thumbnail.url + '"><span class="remove-image" data-id="' + attachment.id + '">×</span></div>');
                                        });
                                        input.val(new_ids.join(','));
                                    });
                                    frame.open();
                                });
                            });
                            container.find('.wpd-upload-file-button:not([data-initialized])').each(function() {
                                var $button = $(this);
                                $button.attr('data-initialized', 'true');
                                $button.on('click', function(e) {
                                    e.preventDefault();
                                    var input = $button.siblings('input[type="hidden"]');
                                    var preview = $button.siblings('.file-preview');
                                    var frame = wp.media({
                                        title: '<?php _e("انتخاب فایل", "wp-directory"); ?>',
                                        multiple: false,
                                        library: { type: $button.data('mime') }
                                    });
                                    frame.on('select', function() {
                                        var attachment = frame.state().get('selection').first().toJSON();
                                        input.val(attachment.id);
                                        preview.html('<p><?php _e("فایل فعلی:", "wp-directory"); ?> <a href="' + attachment.url + '" target="_blank">' + attachment.filename + '</a> <a href="#" class="wpd-remove-file" data-field-id="' + input.attr('id') + '">(<?php _e("حذف", "wp-directory"); ?>)</a></p>');
                                    });
                                    frame.open();
                                });
                            });
                            container.on('click', '.wpd-remove-file', function(e) {
                                e.preventDefault();
                                var fieldId = $(this).data('field-id');
                                $('#' + fieldId).val('');
                                $(this).closest('.file-preview').html('');
                            });

                            container.off('click', '.remove-image').on('click', '.remove-image', function() {
                                var id_to_remove = $(this).data('id');
                                var wrapper = $(this).closest('.wpd-gallery-field-wrapper');
                                var input = wrapper.find('input[type="hidden"]');
                                var image_ids = input.val().split(',').map(Number).filter(Boolean);
                                var new_ids = image_ids.filter(id => id !== id_to_remove);
                                input.val(new_ids.join(','));
                                $(this).parent().remove();
                            });

                            container.find('.wpd-map-field-wrapper:not([data-initialized])').each(function() {
                                var $wrapper = $(this);
                                $wrapper.attr('data-initialized', 'true');
                                var input = $wrapper.find('input[type="text"]');
                                var mapContainer = $wrapper.find('.map-preview')[0];
                                var latlng = input.val() ? input.val().split(',') : [32.4279, 53.6880];
                                var map = L.map(mapContainer).setView(latlng, 5);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                                var marker = L.marker(latlng, { draggable: true }).addTo(map);
                                marker.on('dragend', function(e) { input.val(e.target.getLatLng().lat.toFixed(6) + ',' + e.target.getLatLng().lng.toFixed(6)); });
                                map.on('click', function(e) { marker.setLatLng(e.latlng); input.val(e.latlng.lat.toFixed(6) + ',' + e.latlng.lng.toFixed(6)); });
                                setTimeout(function() { map.invalidateSize() }, 200);
                            });
                            
                            container.find('.wpd-date-picker:not([data-initialized])').each(function() {
                                $(this).attr('data-initialized', 'true').datepicker({
                                    dateFormat: 'yy-mm-dd'
                                });
                            });
                        }
                        
                        function checkAdminConditionalLogic() {
                            $('.wpd-admin-field-wrapper[data-conditional-logic]').each(function () {
                                var $dependentFieldWrapper = $(this);
                                var logic;
                                try {
                                    logic = JSON.parse($dependentFieldWrapper.attr('data-conditional-logic'));
                                } catch (e) { return; }

                                if (!logic.enabled || !logic.target_field) return;

                                var $targetFieldWrapper = $('.wpd-admin-field-wrapper[data-field-key="' + logic.target_field + '"]');
                                var $targetField = $targetFieldWrapper.find('[name^="wpd_custom[' + logic.target_field + ']"]');
                                
                                if (!$targetField.length) return;

                                var targetValue;
                                if ($targetField.is(':radio') || $targetField.is(':checkbox')) {
                                    targetValue = $targetField.filter(':checked').val() || '';
                                } else {
                                    targetValue = $targetField.val() || '';
                                }

                                var conditionMet = false;
                                switch (logic.operator) {
                                    case 'is': conditionMet = (targetValue == logic.value); break;
                                    case 'is_not': conditionMet = (targetValue != logic.value); break;
                                    case 'is_empty': conditionMet = (targetValue === '' || targetValue === null || (Array.isArray(targetValue) && targetValue.length === 0)); break;
                                    case 'is_not_empty': conditionMet = (targetValue !== '' && targetValue !== null && (!Array.isArray(targetValue) || targetValue.length > 0)); break;
                                }

                                var shouldShow = (logic.action === 'show') ? conditionMet : !conditionMet;

                                if (shouldShow) {
                                    $dependentFieldWrapper.slideDown('fast');
                                } else {
                                    $dependentFieldWrapper.slideUp('fast');
                                }
                            });
                        }

                        function initializeAdminConditionalLogic(container) {
                            container.find('input, select, textarea').off('change.wpd_conditional').on('change.wpd_conditional', function() {
                                setTimeout(checkAdminConditionalLogic, 50);
                            });
                            checkAdminConditionalLogic();
                        }

                        $('#wpd_listing_type_selector').on('change', function() {
                            var type_id = $(this).val();
                            var post_id = $('#post_ID').val();
                            var fields_container = $('#wpd-admin-custom-fields-wrapper');
                            
                            fields_container.html('<p class="spinner is-active" style="float:none;"></p>');
                            toggleTaxonomyMetaboxes([]); // Hide all taxonomies before the new ones are loaded
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'wpd_load_admin_fields_and_taxonomies',
                                    type_id: type_id,
                                    post_id: post_id,
                                    _ajax_nonce: ajaxNonce
                                },
                                success: function(response) {
                                    if(response.success) {
                                        fields_container.html(response.data.fields);
                                        initializeWpdComponents(fields_container);
                                        initializeAdminConditionalLogic(fields_container);
                                        // BUG FIX: Show the correct taxonomies after AJAX success
                                        toggleTaxonomyMetaboxes(response.data.taxonomies);
                                    } else {
                                        fields_container.html('<p style="color:red;">' + response.data.message + '</p>');
                                    }
                                },
                                error: function() {
                                     fields_container.html('<p style="color:red;">خطا در برقراری ارتباط.</p>');
                                }
                            });
                        });
                        
                        // BUG FIX: On page load, trigger the change event to load correct fields and taxonomies if a type is already selected
                        if ($('#wpd_listing_type_selector').val()) {
                            $('#wpd_listing_type_selector').trigger('change');
                        } else {
                            // If no type is selected, still hide all dynamic taxonomies
                            toggleTaxonomyMetaboxes([]);
                        }
                        
                        initializeWpdComponents($('#wpd-admin-custom-fields-wrapper'));
                        initializeAdminConditionalLogic($('#wpd-admin-custom-fields-wrapper'));

                        $('#wpd-admin-custom-fields-wrapper').on('click', '.wpd-repeater-remove-row-btn', function(e) {
                            e.preventDefault();
                            if(confirm('آیا از حذف این ردیف مطمئن هستید؟')) {
                                $(this).closest('.wpd-repeater-row').remove();
                            }
                        });

                        $('#wpd-admin-custom-fields-wrapper').on('click', '.wpd-repeater-add-row-btn', function(e) {
                            e.preventDefault();
                            var template = $(this).siblings('.wpd-repeater-template');
                            var container = $(this).siblings('.wpd-repeater-rows-container');
                            var newIndex = container.children('.wpd-repeater-row').length;
                            var newRowHtml = template.html().replace(/__INDEX__/g, newIndex);
                            var newRow = $(newRowHtml).appendTo(container);
                            initializeWpdComponents(newRow);
                        });

                        $('#wpd-admin-custom-fields-wrapper').on('change', '.wpd-product-admin-wrapper input[type="checkbox"]', function() {
                            $(this).closest('.wpd-product-admin-wrapper').find('.wpd-product-details').slideToggle($(this).is(':checked'));
                        });
                        $('.wpd-product-admin-wrapper input[type="checkbox"]').each(function() {
                            if (!$(this).is(':checked')) {
                                $(this).closest('.wpd-product-admin-wrapper').find('.wpd-product-details').hide();
                            }
                        });

                        initializeWpdComponents($(document.body));
                    });
                </script>
                <?php
            }
        }
        
        private function get_field_builder_row_html($index_placeholder, $field_data = [], $all_fields = []) {
            ob_start();
            $this->render_field_builder_row($index_placeholder, $field_data, $all_fields);
            return ob_get_clean();
        }

        public function ajax_load_admin_fields_and_taxonomies() {
            check_ajax_referer('wpd_admin_fields_nonce');
            
            require_once( ABSPATH . 'wp-admin/includes/meta-boxes.php' );

            $type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            
            // BUG FIX: Handle the case where no type is selected gracefully
            if(empty($type_id)) {
                wp_send_json_success(['fields' => '<p>' . __('لطفا یک نوع آگهی انتخاب کنید.', 'wp-directory') . '</p>', 'taxonomies' => []]);
                return;
            }

            $fields_html = $this->get_admin_fields_html($type_id, $post_id);
            $taxonomies = get_post_meta($type_id, '_defined_taxonomies', true);
            $tax_slugs = !empty($taxonomies) && is_array($taxonomies) ? wp_list_pluck($taxonomies, 'slug') : [];

            wp_send_json_success(['fields' => $fields_html, 'taxonomies' => $tax_slugs]);
        }

        private function get_admin_fields_html($type_id, $post_id) {
            if (empty($type_id)) return '';
            $fields = get_post_meta($type_id, '_wpd_custom_fields', true);
            if (!is_array($fields) || empty($fields)) return '<p>' . __('هیچ فیلد سفارشی برای این نوع آگهی تعریف نشده است.', 'wp-directory') . '</p>';

            ob_start();
            echo '<div class="wpd-fields-container">';
            $this->render_admin_fields_recursive($fields, $post_id);
            echo '</div>';
            return ob_get_clean();
        }

        private function render_admin_fields_recursive($fields, $post_id, $row_data = [], $name_prefix = 'wpd_custom', $meta_prefix = '_wpd_') {
            foreach ($fields as $field) {
                $field_key = $field['key'];
                $field_name = $name_prefix . '[' . $field_key . ']';
                
                $field_id = preg_replace('/\]\[|\[|\]/', '_', $name_prefix . '_' . $field_key);
                $field_id = rtrim($field_id, '_');

                $value = ($meta_prefix === '') ? ($row_data[$field_key] ?? '') : get_post_meta($post_id, $meta_prefix . sanitize_key($field_key), true);
                
                if (empty($value)) {
                    $default_value = $field['default_value'] ?? '';
                    if (strpos($default_value, '[') !== false && strpos($default_value, ']') !== false) {
                        $value = do_shortcode($default_value);
                    }
                    if (strpos($default_value, '{') !== false && strpos($default_value, '}') !== false) {
                        $meta_key_to_fetch = str_replace(['{', '}'], '', $default_value);
                        $value = get_post_meta($post_id, $meta_key_to_fetch, true);
                    } else {
                        $value = $default_value;
                    }
                }

                $width_class = 'wpd-admin-field-col-' . ($field['width_class'] ?? 'full');

                $conditional_logic = $field['conditional_logic'] ?? [];
                $wrapper_attributes = 'data-field-key="' . esc_attr($field_key) . '"';
                if (!empty($conditional_logic['enabled'])) {
                    $wrapper_attributes .= ' data-conditional-logic=\'' . json_encode($conditional_logic) . '\'';
                }

                if ($field['type'] === 'section_title') {
                    echo '<div class="wpd-admin-field-wrapper wpd-admin-field-col-full" ' . $wrapper_attributes . '><h3 class="wpd-section-title">' . esc_html($field['label']) . '</h3></div>';
                    continue;
                }
                if ($field['type'] === 'html_content') {
                    echo '<div class="wpd-admin-field-wrapper wpd-admin-field-col-full" ' . $wrapper_attributes . '>' . wp_kses_post($field['options']) . '</div>';
                    continue;
                }

                $placeholder = $field['placeholder'] ?? '';
                $help_text = $field['help_text'] ?? '';
                $input_attributes = !empty($placeholder) ? ' placeholder="' . esc_attr($placeholder) . '"' : '';

                ?>
                <div class="wpd-admin-field-wrapper <?php echo esc_attr($width_class); ?>" <?php echo $wrapper_attributes; ?>>
                    <?php if ($field['type'] !== 'product'): ?>
                    <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field['label']); ?></label>
                    <?php endif; ?>
                    <div class="wpd-field-input-wrapper">
                        <?php
                        $options = !empty($field['options']) ? array_map('trim', explode(',', $field['options'])) : [];
                        switch($field['type']) {
                            case 'textarea': echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" class="large-text"' . $input_attributes . '>' . esc_textarea($value) . '</textarea>'; break;
                            case 'select':
                                echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '">';
                                echo '<option value="">-- انتخاب کنید --</option>';
                                foreach($options as $option) echo '<option value="'.esc_attr($option).'" '.selected($value, $option, false).'>'.esc_html($option).'</option>';
                                echo '</select>';
                                break;
                            case 'multiselect':
                                echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '[]" multiple class="large-text">';
                                $saved_values = is_array($value) ? $value : [];
                                foreach($options as $option) echo '<option value="'.esc_attr($option).'" '.(in_array($option, $saved_values) ? 'selected' : '').'>'.esc_html($option).'</option>';
                                echo '</select>';
                                break;
                            case 'checkbox':
                                $saved_values = is_array($value) ? $value : [];
                                foreach($options as $option) echo '<label><input type="checkbox" name="' . esc_attr($field_name) . '[]" value="'.esc_attr($option).'" '.(in_array($option, $saved_values) ? 'checked' : '').'> '.esc_html($option).'</label><br>';
                                break;
                            case 'radio':
                                foreach($options as $option) echo '<label><input type="radio" name="' . esc_attr($field_name) . '" value="'.esc_attr($option).'" '.checked($value, $option, false).'> '.esc_html($option).'</label><br>';
                                break;
                            
                            case 'time':
                                echo '<input type="time" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text" style="direction: ltr;"' . $input_attributes . '>';
                                break;
                            
                            case 'date':
                                echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text wpd-date-picker" autocomplete="off"' . $input_attributes . '>';
                                break;

                            case 'product':
                                $product_settings = $field['product_settings'] ?? [];
                                $product_value = is_array($value) ? $value : ['selected' => 0, 'quantity' => 1, 'price' => ''];
                                $is_selected = !empty($product_value['selected']);
                                ?>
                                <div class="wpd-product-admin-wrapper">
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr($field_name); ?>[selected]" value="1" <?php checked(1, $is_selected); ?>>
                                        <strong><?php echo esc_html($field['label']); ?></strong>
                                    </label>
                                    <div class="wpd-product-details" style="margin-top: 10px; padding: 10px; border: 1px solid #e0e0e0; background: #fafafa;">
                                        
                                        <p>
                                            <label for="<?php echo esc_attr($field_id); ?>_quantity"><?php _e('تعداد:', 'wp-directory'); ?></label>
                                            <input id="<?php echo esc_attr($field_id); ?>_quantity" type="number" name="<?php echo esc_attr($field_name); ?>[quantity]" value="<?php echo esc_attr($product_value['quantity']); ?>" class="small-text">
                                            <?php if (empty($product_settings['enable_quantity'])): ?>
                                                <small>(<?php _e('برای کاربران غیرفعال است', 'wp-directory'); ?>)</small>
                                            <?php endif; ?>
                                        </p>
                                        
                                        <p>
                                            <label for="<?php echo esc_attr($field_id); ?>_price">
                                                <?php 
                                                if ($product_settings['pricing_mode'] === 'user_defined') {
                                                    _e('قیمت:', 'wp-directory');
                                                } else {
                                                    _e('قیمت (ثابت):', 'wp-directory');
                                                }
                                                ?>
                                            </label>
                                            <input id="<?php echo esc_attr($field_id); ?>_price" type="number" name="<?php echo esc_attr($field_name); ?>[price]" value="<?php echo esc_attr($product_value['price'] ?: ($product_settings['fixed_price'] ?? '')); ?>" class="regular-text" <?php if ($product_settings['pricing_mode'] === 'fixed') echo 'readonly'; ?>>
                                             <?php if ($product_settings['pricing_mode'] === 'fixed'): ?>
                                                <small>(<?php _e('در فیلدساز تعریف شده', 'wp-directory'); ?>)</small>
                                            <?php endif; ?>
                                        </p>

                                    </div>
                                </div>
                                <?php
                                break;
                            
                            case 'image':
                            case 'file':
                                $file_settings = $field['file_settings'] ?? [];
                                $button_text = ($field['type'] === 'image') ? __('آپلود/انتخاب تصویر', 'wp-directory') : __('آپلود/انتخاب فایل', 'wp-directory');
                                $file_mime = ($field['type'] === 'image') ? 'image' : '';
                                
                                $file_id = absint($value);
                                $file_url = ($file_id) ? wp_get_attachment_url($file_id) : '';

                                echo '<div class="wpd-file-field-wrapper">';
                                echo '<a href="#" class="button wpd-upload-file-button" data-mime="'.esc_attr($file_mime).'">'.esc_html($button_text).'</a>';
                                echo '<input type="hidden" name="'.esc_attr($field_name).'" value="'.esc_attr($file_id).'">';
                                echo '<div class="file-preview" style="margin-top:10px;">';
                                if ($file_url) {
                                    echo '<p>'.__('فایل فعلی:', 'wp-directory').' <a href="'.esc_url($file_url).'" target="_blank">'.esc_html(basename($file_url)).'</a> <a href="#" class="wpd-remove-file" data-field-id="'.esc_attr($field_id).'">('.__('حذف', 'wp-directory').')</a></p>';
                                }
                                echo '</div></div>';
                                break;

                            case 'gallery':
                                echo '<div class="wpd-gallery-field-wrapper">';
                                echo '<a href="#" class="button wpd-upload-gallery-button">'.__('مدیریت گالری', 'wp-directory').'</a>';
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
                                echo '<input type="text" id="'.esc_attr($field_id).'" name="'.esc_attr($field_name).'" value="'.esc_attr($value).'" placeholder="32.4279,53.6880">';
                                echo '<div class="map-preview" style="width:100%; height:250px; background:#eee; margin-top:10px;"></div>';
                                echo '</div>';
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
                                            $this->render_admin_fields_recursive($field['sub_fields'], $post_id, $row_data, $field_name . '[' . $index . ']', '');
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
                                        echo '<div class="wpd-repeater-row-actions"><a href="#" class="button button-small wpd-repeater-remove-row-btn">' . __('حذف', 'wp-directory') . '</a></div>';
                                        echo '</div>';
                                    }
                                }
                                echo '</div>';
                                echo '<div class="wpd-repeater-template" style="display:none;">';
                                echo '<div class="wpd-repeater-row">';
                                if ($field['type'] === 'repeater') {
                                    echo '<div class="wpd-fields-container">';
                                    $this->render_admin_fields_recursive($field['sub_fields'], $post_id, [], $field_name . '[__INDEX__]', '');
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
                                echo '<div class="wpd-repeater-row-actions"><a href="#" class="button button-small wpd-repeater-remove-row-btn">' . __('حذف', 'wp-directory') . '</a></div>';
                                echo '</div>';
                                echo '</div>';
                                echo '<a href="#" class="button wpd-repeater-add-row-btn">' . __('افزودن ردیف جدید', 'wp-directory') . '</a>';
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
                                        $sub_field_width_class = 'wpd-admin-field-col-' . ($sub_settings['width'] ?? 'full');
                                        echo '<div class="wpd-admin-field-wrapper ' . esc_attr($sub_field_width_class) . '">';
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

                            default: echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text"' . $input_attributes . '>'; break;
                        }
                        ?>
                        <?php if(!empty($help_text)): ?>
                            <p class="description"><?php echo esc_html($help_text); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
        }

        public function add_listing_columns($columns) {
            unset($columns['author'], $columns['comments'], $columns['date']);
            $columns['listing_type'] = __('نوع آگهی', 'wp-directory');
            $columns['author'] = __('نویسنده', 'wp-directory');
            $columns['expiration_date'] = __('تاریخ انقضا', 'wp-directory');
            $columns['date'] = __('تاریخ ثبت', 'wp-directory');
            return $columns;
        }

        public function render_listing_columns($column, $post_id) {
            switch ($column) {
                case 'listing_type':
                    $type_id = get_post_meta($post_id, '_wpd_listing_type', true);
                    echo $type_id ? esc_html(get_the_title($type_id)) : '---';
                    break;
                case 'expiration_date':
                    $date = get_post_meta($post_id, '_wpd_expiration_date', true);
                    echo $date ? esc_html(date_i18n('Y/m/d', strtotime($date))) : __('نامحدود', 'wp-directory');
                    break;
            }
        }

        public function add_package_columns($columns) {
            unset($columns['date']);
            $columns['price'] = __('قیمت (تومان)', 'wp-directory');
            $columns['duration'] = __('مدت اعتبار (روز)', 'wp-directory');
            $columns['limit'] = __('تعداد آگهی', 'wp-directory');
            $columns['date'] = __('تاریخ ثبت', 'wp-directory');
            return $columns;
        }

        public function render_package_columns($column, $post_id) {
            switch ($column) {
                case 'price': echo esc_html(number_format(get_post_meta($post_id, '_price', true))); break;
                case 'duration': echo esc_html(get_post_meta($post_id, '_duration', true)) ?: __('نامحدود', 'wp-directory'); break;
                case 'limit': echo esc_html(get_post_meta($post_id, '_listing_limit', true)) ?: __('نامحدود', 'wp-directory'); break;
            }
        }

        public function add_upgrade_columns($columns) {
            unset($columns['date']);
            $columns['price'] = __('قیمت', 'wp-directory') . ' (' . Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'] . ')';
            $columns['upgrade_type'] = __('نوع ارتقا', 'wp-directory');
            $columns['duration'] = __('مدت اعتبار (روز)', 'wp-directory');
            $columns['date'] = __('تاریخ ثبت', 'wp-directory');
            return $columns;
        }

        public function render_upgrade_columns($column, $post_id) {
            switch ($column) {
                case 'price':
                    echo esc_html(number_format(get_post_meta($post_id, '_price', true)));
                    break;
                case 'upgrade_type':
                    $type = get_post_meta($post_id, '_upgrade_type', true);
                    $types = [
                        'featured' => __('ویژه کردن', 'wp-directory'),
                        'bump_up' => __('نردبان', 'wp-directory'),
                        'top_of_category' => __('پین در بالای دسته', 'wp-directory'),
                        'urgent' => __('برچسب فوری', 'wp-directory'),
                    ];
                    echo esc_html($types[$type] ?? $type);
                    break;
                case 'duration':
                    $type = get_post_meta($post_id, '_upgrade_type', true);
                    if ($type === 'bump_up') {
                        echo '---';
                    } else {
                        echo esc_html(get_post_meta($post_id, '_duration', true)) ?: __('نامحدود', 'wp-directory');
                    }
                    break;
            }
        }

        public function add_listing_type_filter($post_type) {
            if ('wpd_listing' !== $post_type) {
                return;
            }
            $listing_types = get_posts(['post_type' => 'wpd_listing_type', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
            if (empty($listing_types)) {
                return;
            }
            $current_filter = $_GET['wpd_listing_type_filter'] ?? '';
            ?>
            <select name="wpd_listing_type_filter" id="wpd_listing_type_filter">
                <option value=""><?php _e('همه انواع آگهی', 'wp-directory'); ?></option>
                <?php foreach ($listing_types as $type): ?>
                    <option value="<?php echo esc_attr($type->ID); ?>" <?php selected($current_filter, $type->ID); ?>>
                        <?php echo esc_html($type->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
        }

        public function filter_listings_by_type($query) {
            global $pagenow;
            if (is_admin() && $pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'wpd_listing' && isset($_GET['wpd_listing_type_filter']) && !empty($_GET['wpd_listing_type_filter'])) {
                $query->set('meta_key', '_wpd_listing_type');
                $query->set('meta_value', intval($_GET['wpd_listing_type_filter']));
            }
        }

        public function add_listing_bulk_actions($bulk_actions) {
            $bulk_actions['wpd_approve'] = __('تایید آگهی‌ها', 'wp-directory');
            $bulk_actions['wpd_reject'] = __('رد کردن آگهی‌ها (بازگشت به در انتظار)', 'wp-directory');
            return $bulk_actions;
        }

        public function handle_listing_bulk_actions($redirect_to, $action, $post_ids) {
            if ($action !== 'wpd_approve' && $action !== 'wpd_reject') {
                return $redirect_to;
            }

            $count = 0;
            foreach ($post_ids as $post_id) {
                if ($action === 'wpd_approve') {
                    wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
                } else { // wpd_reject
                    wp_update_post(['ID' => $post_id, 'post_status' => 'pending']);
                }
                $count++;
            }

            $message = ($action === 'wpd_approve') ? 'approved' : 'rejected';
            $redirect_to = add_query_arg(['bulk_action_completed' => $message, 'ids' => implode(',', $post_ids)], $redirect_to);
            return $redirect_to;
        }

        public function bulk_action_admin_notice() {
            if (!empty($_REQUEST['bulk_action_completed'])) {
                $count = isset($_REQUEST['ids']) ? count(explode(',', $_REQUEST['ids'])) : 0;
                $message = '';
                if ($_REQUEST['bulk_action_completed'] === 'approved') {
                    $message = sprintf(_n('%d آگهی تایید و منتشر شد.', '%d آگهی تایید و منتشر شدند.', $count, 'wp-directory'), $count);
                } elseif ($_REQUEST['bulk_action_completed'] === 'rejected') {
                    $message = sprintf(_n('%d آگهی به وضعیت "در انتظار تایید" بازگشت.', '%d آگهی به وضعیت "در انتظار تایید" بازگشتند.', $count, 'wp-directory'), $count);
                }
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }

        public function handle_field_builder_export() {
            if (isset($_GET['wpd_action'], $_GET['post_id'], $_GET['_wpnonce']) && $_GET['wpd_action'] === 'export_fields') {
                if (!wp_verify_nonce($_GET['_wpnonce'], 'wpd_export_fields_nonce') || !current_user_can('manage_options')) {
                    wp_die('شما اجازه انجام این کار را ندارید.');
                }
                $post_id = intval($_GET['post_id']);
                $fields = get_post_meta($post_id, '_wpd_custom_fields', true);
                $post = get_post($post_id);
                $filename = 'wpd-fields-' . ($post ? $post->post_name : $post_id) . '-' . date('Y-m-d') . '.json';

                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename=' . $filename);
                wp_send_json($fields, 200);
                exit;
            }
        }
    }
}
