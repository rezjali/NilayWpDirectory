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
                'rewrite'            => [ 'slug' => 'listing' ],
                'capability_type'    => 'post',
                'has_archive'        => true,
                'hierarchical'       => false,
                'menu_position'      => 5,
                'supports'           => [ 'title', 'author' ],
                'menu_icon'          => 'dashicons-list-view',
            ];
            register_post_type( 'wpd_listing', $listing_args );

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
                        <a href="#" class="button wpd-remove-field"><?php _e( 'حذف', 'wp-directory' ); ?></a>
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
            <div id="wpd-field-builder-wrapper">
                <div id="wpd-fields-container" class="wpd-sortable-list">
                    <?php if ( ! empty( $fields ) ) : foreach ( $fields as $index => $field ) : ?>
                        <?php $this->render_field_builder_row($index, $field); ?>
                    <?php endforeach; endif; ?>
                </div>
                <a href="#" id="wpd-add-field" class="button button-primary"><?php _e( 'افزودن فیلد جدید', 'wp-directory' ); ?></a>
            </div>
            <?php
        }

        private function render_field_builder_row($index, $field_data = []) {
            $label = $field_data['label'] ?? '';
            $key = $field_data['key'] ?? '';
            $type = $field_data['type'] ?? 'text';
            $options = $field_data['options'] ?? '';
            $sub_fields = $field_data['sub_fields'] ?? [];
            ?>
            <div class="wpd-field-row" data-index="<?php echo esc_attr($index); ?>">
                <div class="wpd-field-header">
                    <span class="dashicons dashicons-move handle"></span>
                    <strong><?php echo esc_html($label) ?: __('فیلد جدید', 'wp-directory'); ?></strong> (<?php echo esc_html($type); ?>)
                    <a href="#" class="wpd-toggle-field-details"><?php _e('جزئیات', 'wp-directory'); ?></a>
                </div>
                <div class="wpd-field-details" style="display:none;">
                    <div class="wpd-field-inputs">
                        <input type="text" name="wpd_fields[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php _e( 'عنوان فیلد', 'wp-directory' ); ?>" class="field-label-input">
                        <input type="text" name="wpd_fields[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($key); ?>" placeholder="<?php _e( 'کلید متا (انگلیسی)', 'wp-directory' ); ?>">
                        <select class="wpd-field-type-selector" name="wpd_fields[<?php echo esc_attr($index); ?>][type]">
                            <optgroup label="<?php _e('فیلدهای پایه', 'wp-directory'); ?>">
                                <option value="text" <?php selected( $type, 'text' ); ?>><?php _e( 'متن', 'wp-directory' ); ?></option>
                                <option value="textarea" <?php selected( $type, 'textarea' ); ?>><?php _e( 'متن بلند', 'wp-directory' ); ?></option>
                                <option value="number" <?php selected( $type, 'number' ); ?>><?php _e( 'عدد', 'wp-directory' ); ?></option>
                                <option value="email" <?php selected( $type, 'email' ); ?>><?php _e( 'ایمیل', 'wp-directory' ); ?></option>
                                <option value="url" <?php selected( $type, 'url' ); ?>><?php _e( 'وب‌سایت', 'wp-directory' ); ?></option>
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
                            <optgroup label="<?php _e('فیلدهای زمان و تاریخ', 'wp-directory'); ?>">
                                <option value="date" <?php selected( $type, 'date' ); ?>><?php _e( 'تاریخ', 'wp-directory' ); ?></option>
                                <option value="time" <?php selected( $type, 'time' ); ?>><?php _e( 'ساعت', 'wp-directory' ); ?></option>
                                <option value="datetime" <?php selected( $type, 'datetime' ); ?>><?php _e( 'تاریخ و ساعت', 'wp-directory' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('فیلدهای ساختاری', 'wp-directory'); ?>">
                                <option value="section_title" <?php selected( $type, 'section_title' ); ?>><?php _e('عنوان بخش (Heading)', 'wp-directory'); ?></option>
                                <option value="html_content" <?php selected( $type, 'html_content' ); ?>><?php _e('محتوای HTML', 'wp-directory'); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('فیلدهای پیشرفته', 'wp-directory'); ?>">
                                <option value="gallery" <?php selected( $type, 'gallery' ); ?>><?php _e( 'گالری تصاویر', 'wp-directory' ); ?></option>
                                <option value="map" <?php selected( $type, 'map' ); ?>><?php _e( 'نقشه', 'wp-directory' ); ?></option>
                                <option value="repeater" <?php selected( $type, 'repeater' ); ?>><?php _e( 'تکرار شونده', 'wp-directory' ); ?></option>
                            </optgroup>
                        </select>
                        <textarea name="wpd_fields[<?php echo esc_attr($index); ?>][options]" class="wpd-field-options" placeholder="<?php _e( 'گزینه‌ها (جدا شده با کاما) یا محتوای HTML', 'wp-directory' ); ?>" style="<?php echo in_array($type, ['select', 'multiselect', 'checkbox', 'radio', 'html_content']) ? '' : 'display:none;'; ?>"><?php echo esc_textarea($options); ?></textarea>
                    </div>

                    <div class="wpd-repeater-fields-wrapper" style="<?php echo ($type === 'repeater') ? '' : 'display:none;'; ?>">
                        <h4><?php _e('فیلدهای داخلی تکرارشونده', 'wp-directory'); ?></h4>
                        <div class="wpd-sortable-list wpd-repeater-sub-fields">
                            <?php if (!empty($sub_fields)): foreach($sub_fields as $sub_index => $sub_field): ?>
                                <?php $this->render_field_builder_row($index . '][sub_fields][' . $sub_index, $sub_field); ?>
                            <?php endforeach; endif; ?>
                        </div>
                        <a href="#" class="button wpd-add-sub-field"><?php _e('افزودن فیلد داخلی', 'wp-directory'); ?></a>
                    </div>

                    <a href="#" class="button wpd-remove-field"><?php _e( 'حذف فیلد', 'wp-directory' ); ?></a>
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
                        <p class="description"><?php _e( 'این ارتقا برای چند روز فعال خواهد بود؟ (برای نردبان کاربرد ندارد)', 'wp-directory' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php
        }
        
        public function save_listing_type_meta_data( $post_id ) {
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
            if ( get_post_type($post_id) !== 'wpd_listing_type' ) return;
            if ( ! current_user_can( 'edit_post', $post_id ) ) return;

            // Save main settings
            if ( isset( $_POST['wpd_listing_type_nonce'] ) && wp_verify_nonce( $_POST['wpd_listing_type_nonce'], 'wpd_save_listing_type_meta' ) ) {
                if(isset($_POST['wpd_meta']) && is_array($_POST['wpd_meta'])) {
                    foreach($_POST['wpd_meta'] as $key => $value) {
                        update_post_meta($post_id, $key, sanitize_text_field($value));
                    }
                }
            }

            // Save notification settings
            $notif_settings = [];
            if(isset($_POST['wpd_notifications']) && is_array($_POST['wpd_notifications'])) {
                foreach($_POST['wpd_notifications'] as $event => $settings) {
                    $notif_settings[sanitize_key($event)]['email'] = isset($settings['email']) ? 1 : 0;
                    $notif_settings[sanitize_key($event)]['sms'] = isset($settings['sms']) ? 1 : 0;
                }
            }
            update_post_meta($post_id, '_notification_settings', $notif_settings);

            // Save field builder
            if ( isset( $_POST['wpd_field_builder_nonce'] ) && wp_verify_nonce( $_POST['wpd_field_builder_nonce'], 'wpd_save_field_builder_meta' ) ) {
                $sanitized_fields = [];
                if ( isset( $_POST['wpd_fields'] ) && is_array($_POST['wpd_fields']) ) {
                    $sanitized_fields = $this->sanitize_field_builder_data($_POST['wpd_fields']);
                }
                update_post_meta( $post_id, '_wpd_custom_fields', $sanitized_fields );
            }
            
            // Save taxonomy builder
            if ( isset( $_POST['wpd_taxonomy_builder_nonce'] ) && wp_verify_nonce( $_POST['wpd_taxonomy_builder_nonce'], 'wpd_save_taxonomy_builder_meta' ) ) {
                if ( isset( $_POST['wpd_taxonomies'] ) && is_array($_POST['wpd_taxonomies']) ) {
                    $sanitized_taxs = [];
                    foreach ( $_POST['wpd_taxonomies'] as $tax ) {
                        if ( ! empty( $tax['name'] ) && ! empty( $tax['slug'] ) ) {
                            $sanitized_taxs[] = [ 'name' => sanitize_text_field( $tax['name'] ), 'slug' => sanitize_key( $tax['slug'] ), 'hierarchical' => intval( $tax['hierarchical'] ) ];
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
                ];

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
            foreach ($field_definitions as $field_def) {
                $field_key = $field_def['key'];
                $meta_key = $meta_prefix . sanitize_key($field_key);
                $value_exists = isset($posted_data[$field_def['key']]);
                $posted_value = $value_exists ? $posted_data[$field_def['key']] : null;

                if (!$value_exists && in_array($field_def['type'], ['checkbox', 'multiselect'])) {
                    delete_post_meta($post_id, $meta_key);
                    continue;
                }

                if (!$value_exists) {
                    continue;
                }

                if ($field_def['type'] === 'datetime' && is_array($posted_value)) {
                    $date = sanitize_text_field($posted_value['date'] ?? '');
                    $time = sanitize_text_field($posted_value['time'] ?? '00:00');
                    if (!empty($date)) {
                        $posted_value = $date . ' ' . $time;
                    } else {
                        $posted_value = '';
                    }
                }

                if ($field_def['type'] === 'repeater') {
                    $repeater_data = [];
                    if (is_array($posted_value) && !empty($field_def['sub_fields'])) {
                        foreach ($posted_value as $index => $row_data) {
                            if ($index === '__INDEX__') {
                                continue;
                            }
                            $sanitized_row = [];
                            foreach ($field_def['sub_fields'] as $sub_field_def) {
                                $sub_field_key = $sub_field_def['key'];
                                if (isset($row_data[$sub_field_key])) {
                                    $sanitized_row[$sub_field_key] = $this->sanitize_field_value($row_data[$sub_field_key], $sub_field_def['type']);
                                }
                            }
                            if (!empty($sanitized_row)) {
                                $repeater_data[] = $sanitized_row;
                            }
                        }
                    }
                    update_post_meta($post_id, $meta_key, $repeater_data);
                } else {
                    $sanitized_value = $this->sanitize_field_value($posted_value, $field_def['type']);
                    update_post_meta($post_id, $meta_key, $sanitized_value);
                }
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
                    return implode(',', array_map('absint', explode(',', $value)));
                case 'mobile':
                case 'phone':
                case 'postal_code':
                case 'national_id':
                    return sanitize_text_field(preg_replace('/[^0-9]/', '', $value));
                case 'map':
                case 'text':
                case 'select':
                case 'radio':
                case 'date':
                case 'time':
                case 'datetime':
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
                wp_enqueue_style('wp-jquery-ui-dialog');
                wp_enqueue_style( 'jquery-ui-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css', true);

                wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
                wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], '1.7.1', true);

                if ( Directory_Main::is_shamsi_calendar_enabled() && function_exists('wpp_enqueue_scripts') ) {
                    wpp_enqueue_scripts();
                }
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
                    .wpd-field-header .wpd-toggle-field-details { margin-left: auto; text-decoration: none; }
                    .wpd-field-details { padding: 10px; border-top: 1px solid #ddd; }
                    .wpd-field-inputs { display: flex; flex-wrap: wrap; gap: 10px; }
                    .wpd-field-inputs input, .wpd-field-inputs select, .wpd-field-inputs textarea { margin: 0; }
                    .wpd-field-options { width: 100%; height: 60px; }
                    .wpd-repeater-fields-wrapper { padding: 10px; margin-top: 10px; border: 1px dashed #ccc; background: #fff; }
                    .wpd-repeater-sub-fields .wpd-field-row { border-style: dashed; }
                </style>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        function toggleOptionsField(element) {
                            var fieldType = $(element).val();
                            var optionsTextarea = $(element).closest('.wpd-field-inputs').find('.wpd-field-options');
                            if (['select', 'multiselect', 'checkbox', 'radio', 'html_content'].includes(fieldType)) {
                                optionsTextarea.show();
                            } else {
                                optionsTextarea.hide();
                            }
                        }

                        function toggleRepeaterFields(element) {
                            var fieldType = $(element).val();
                            var repeaterWrapper = $(element).closest('.wpd-field-details').find('.wpd-repeater-fields-wrapper');
                            if (fieldType === 'repeater') {
                                repeaterWrapper.show();
                            } else {
                                repeaterWrapper.hide();
                            }
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

                        $('#wpd-field-builder-wrapper').on('change', '.wpd-field-type-selector', function() {
                            toggleOptionsField(this);
                            toggleRepeaterFields(this);
                        });
                        $('#wpd-field-builder-wrapper').on('click', '.wpd-toggle-field-details', function(e) {
                            e.preventDefault();
                            $(this).closest('.wpd-field-row').find('.wpd-field-details').slideToggle('fast');
                        });
                        $('#wpd-field-builder-wrapper').on('keyup', '.field-label-input', function() {
                            var newTitle = $(this).val() || 'فیلد جدید';
                            $(this).closest('.wpd-field-row').find('.wpd-field-header strong').text(newTitle);
                        });
                        $('#wpd-field-builder-wrapper').on('click', '.wpd-remove-field', function(e) {
                            e.preventDefault();
                            if (confirm('آیا از حذف این فیلد مطمئن هستید؟')) {
                                $(this).closest('.wpd-field-row').remove();
                            }
                        });

                        $('#wpd-add-field').on('click', function(e) {
                            e.preventDefault();
                            var container = $('#wpd-fields-container');
                            var newIndex = container.children().length ? (Math.max.apply(null, container.children().map(function() { return $(this).data('index'); }).get()) + 1) : 0;
                            var field_html = <?php echo json_encode($this->get_field_builder_row_html('__INDEX__')); ?>;
                            container.append(field_html.replace(/__INDEX__/g, newIndex));
                        });

                        $('#wpd-field-builder-wrapper').on('click', '.wpd-add-sub-field', function(e) {
                            e.preventDefault();
                            var subContainer = $(this).prev('.wpd-repeater-sub-fields');
                            var parentIndex = $(this).closest('.wpd-field-row').data('index');
                            var newSubIndex = subContainer.children().length;
                            var namePrefix = parentIndex + '][sub_fields][' + newSubIndex;
                            var field_html = <?php echo json_encode($this->get_field_builder_row_html('__INDEX__')); ?>;
                            subContainer.append(field_html.replace(/__INDEX__/g, namePrefix));
                        });
                    });
                </script>
                <?php
            }

            if ( ( $pagenow == 'post-new.php' || $pagenow == 'post.php' ) && $post_type == 'wpd_listing' ) {
                 ?>
                <style>
                    .wpd-gallery-field-wrapper .gallery-preview { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
                    .wpd-gallery-field-wrapper .gallery-preview .image-container { position: relative; }
                    .wpd-gallery-field-wrapper .gallery-preview img { width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd; }
                    .wpd-gallery-field-wrapper .gallery-preview .remove-image { position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; cursor: pointer; width: 20px; height: 20px; text-align: center; line-height: 20px; }
                    .wpd-repeater-row { border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #fdfdfd; }
                    .wpd-datepicker-wrapper { display: flex; align-items: center; }
                    .wpd-datepicker-icon { cursor: pointer; vertical-align: middle; margin-right: 5px; }
                    .ui-datepicker { z-index: 9999 !important; }
                </style>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {

                        function initGregorianDatepicker(container) {
                            container.find('.wpd-gregorian-datepicker').each(function() {
                                if (!$(this).hasClass('hasDatepicker')) {
                                    $(this).datepicker({
                                        dateFormat: 'yy-mm-dd'
                                    });
                                }
                            });
                        }

                        function initShamsiDatepicker(container) {
                            if (typeof $.fn.persianDatepicker === 'function') {
                                container.find('.persian-datepicker').each(function() {
                                    var $this = $(this);
                                    if (!$this.data('has-pd')) {
                                        var altField = $this.next('.persian-datepicker-alt');
                                        $this.persianDatepicker({
                                            format: 'YYYY/MM/DD',
                                            autoClose: true,
                                            altField: altField,
                                            altFormat: 'YYYY-MM-DD'
                                        });
                                        $this.data('has-pd', true);
                                    }
                                });
                            }
                        }

                        function initAllFields(container) {
                            initGregorianDatepicker(container);
                            initShamsiDatepicker(container);
                            initOtherAdvancedFields(container);
                        }

                        function initOtherAdvancedFields(container) {
                            try {
                                container.find('.wpd-upload-gallery-button').off('click').on('click', function(e) {
                                    e.preventDefault();
                                    var button = $(this);
                                    var input = button.siblings('input[type="hidden"]');
                                    var preview = button.siblings('.gallery-preview');
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

                                container.on('click', '.remove-image', function() {
                                    var id_to_remove = $(this).data('id');
                                    var wrapper = $(this).closest('.wpd-gallery-field-wrapper');
                                    var input = wrapper.find('input[type="hidden"]');
                                    var image_ids = input.val().split(',').map(Number).filter(Boolean);
                                    var new_ids = image_ids.filter(id => id !== id_to_remove);
                                    input.val(new_ids.join(','));
                                    $(this).parent().remove();
                                });
                            } catch (e) {
                                console.error('WPD Error: Initializing gallery failed.', e);
                            }

                            try {
                                container.find('.wpd-map-field-wrapper').each(function() {
                                    var wrapper = $(this);
                                    var input = wrapper.find('input[type="text"]');
                                    var mapContainer = wrapper.find('.map-preview')[0];

                                    if ($(mapContainer).data('leaflet-initialized')) return;
                                    $(mapContainer).data('leaflet-initialized', true);

                                    var latlng = input.val() ? input.val().split(',') : [32.4279, 53.6880];
                                    var map = L.map(mapContainer).setView(latlng, 5);
                                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                                    var marker = L.marker(latlng, { draggable: true }).addTo(map);

                                    marker.on('dragend', function(e) {
                                        input.val(e.target.getLatLng().lat.toFixed(6) + ',' + e.target.getLatLng().lng.toFixed(6));
                                    });
                                    map.on('click', function(e) {
                                        marker.setLatLng(e.latlng);
                                        input.val(e.latlng.lat.toFixed(6) + ',' + e.latlng.lng.toFixed(6));
                                    });

                                    setTimeout(function() { map.invalidateSize() }, 200);
                                });
                            } catch(e) {
                                console.error('WPD Error: Initializing map failed.', e);
                            }
                        }

                        $('#wpd_listing_type_selector').on('change', function() {
                            var type_id = $(this).val();
                            var post_id = $('#post_ID').val();
                            var fields_container = $('#wpd-admin-custom-fields-wrapper');
                            
                            fields_container.html('<p class="spinner is-active" style="float:none;"></p>');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'wpd_load_admin_fields_and_taxonomies',
                                    type_id: type_id,
                                    post_id: post_id,
                                    _ajax_nonce: '<?php echo wp_create_nonce("wpd_admin_fields_nonce"); ?>'
                                },
                                success: function(response) {
                                    if(response.success) {
                                        fields_container.html(response.data.fields);
                                        initAllFields(fields_container);
                                    } else {
                                        fields_container.html('<p style="color:red;">' + response.data.message + '</p>');
                                    }
                                },
                                error: function() {
                                     fields_container.html('<p style="color:red;">خطا در برقراری ارتباط.</p>');
                                }
                            });
                        });

                        $('#wpd-admin-custom-fields-wrapper').on('click', '.wpd-repeater-add-row', function(e) {
                            e.preventDefault();
                            var template = $(this).siblings('.wpd-repeater-template');
                            var container = $(this).siblings('.wpd-repeater-rows-container');
                            var newIndex = container.children().length;
                            var newRowHtml = template.html().replace(/__INDEX__/g, newIndex);
                            var newRow = $(newRowHtml).appendTo(container);
                            initAllFields(newRow);
                        });

                        $('#wpd-admin-custom-fields-wrapper').on('click', '.wpd-repeater-remove-row', function(e) {
                            e.preventDefault();
                            if(confirm('آیا از حذف این ردیف مطمئن هستید؟')) {
                                $(this).closest('.wpd-repeater-row').remove();
                            }
                        });

                        // Initial call for fields already on the page
                        initAllFields($('#wpd-admin-custom-fields-wrapper'));
                    });
                </script>
                <?php
            }
        }
        
        private function get_field_builder_row_html($index_placeholder) {
            ob_start();
            $this->render_field_builder_row($index_placeholder);
            return ob_get_clean();
        }

        public function ajax_load_admin_fields_and_taxonomies() {
            check_ajax_referer('wpd_admin_fields_nonce');
            
            require_once( ABSPATH . 'wp-admin/includes/meta-boxes.php' );

            $type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            
            if(empty($type_id)) wp_send_json_error(['message' => __('نوع آگهی انتخاب نشده است.', 'wp-directory')]);

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
            echo '<table class="form-table"><tbody>';
            $this->render_admin_fields_recursive($fields, $post_id);
            echo '</tbody></table>';
            return ob_get_clean();
        }

        private function render_admin_fields_recursive($fields, $post_id, $row_data = [], $name_prefix = 'wpd_custom', $meta_prefix = '_wpd_') {
            foreach ($fields as $field) {
                $field_key = $field['key'];
                $field_name = $name_prefix . '[' . $field_key . ']';
                
                $field_id = preg_replace('/\]\[|\[|\]/', '_', $field_name);
                $field_id = rtrim($field_id, '_');

                $value = ($meta_prefix === '') ? ($row_data[$field_key] ?? '') : get_post_meta($post_id, $meta_prefix . sanitize_key($field_key), true);

                if ($field['type'] === 'section_title') {
                    echo '<tr><td colspan="2"><h3 class="wpd-section-title">' . esc_html($field['label']) . '</h3></td></tr>';
                    continue;
                }
                if ($field['type'] === 'html_content') {
                    echo '<tr><td colspan="2">' . wp_kses_post($field['options']) . '</td></tr>';
                    continue;
                }

                ?>
                <tr>
                    <th><label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field['label']); ?></label></th>
                    <td>
                        <?php
                        $options = !empty($field['options']) ? array_map('trim', explode(',', $field['options'])) : [];
                        switch($field['type']) {
                            case 'textarea': echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" class="large-text">' . esc_textarea($value) . '</textarea>'; break;
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
                            case 'date': 
                                $is_shamsi = Directory_Main::is_shamsi_calendar_enabled();
                                if ($is_shamsi) {
                                    $display_value = !empty($value) && function_exists('parsidate') ? parsidate('Y/m/d', $value, 'gregorian') : '';
                                    echo '<input type="text" id="' . esc_attr($field_id) . '_display" value="' . esc_attr($display_value) . '" class="regular-text persian-datepicker" autocomplete="off" readonly="readonly" style="direction: ltr; text-align: right;">';
                                    echo '<input type="hidden" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="persian-datepicker-alt">';
                                } else {
                                    echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text wpd-gregorian-datepicker" autocomplete="off">';
                                }
                                break;
                            case 'time':
                                echo '<input type="time" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text" style="direction: ltr;">';
                                break;
                            case 'datetime':
                                $is_shamsi = Directory_Main::is_shamsi_calendar_enabled();
                                $date_val = !empty($value) ? date('Y-m-d', strtotime($value)) : '';
                                $time_val = !empty($value) ? date('H:i', strtotime($value)) : '';
                                
                                if ($is_shamsi) {
                                    $display_date = !empty($date_val) && function_exists('parsidate') ? parsidate('Y/m/d', $date_val, 'gregorian') : '';
                                    echo '<div class="wpd-datepicker-wrapper" style="display:inline-block; margin-left: 10px;">';
                                    echo '<input type="text" id="' . esc_attr($field_id) . '_date_display" value="' . esc_attr($display_date) . '" class="regular-text persian-datepicker" autocomplete="off" readonly="readonly" style="width: 150px; direction: ltr; text-align: right;">';
                                    echo '<input type="hidden" id="' . esc_attr($field_id) . '_date" name="' . esc_attr($field_name) . '[date]" value="' . esc_attr($date_val) . '" class="persian-datepicker-alt">';
                                    echo '</div>';
                                } else {
                                    echo '<div class="wpd-datepicker-wrapper" style="display:inline-block; margin-left: 10px;">';
                                    echo '<input type="text" id="' . esc_attr($field_id) . '_date" name="' . esc_attr($field_name) . '[date]" value="' . esc_attr($date_val) . '" class="regular-text wpd-gregorian-datepicker" autocomplete="off" style="width: 150px;">';
                                    echo '</div>';
                                }
                                echo '<input type="time" id="' . esc_attr($field_id) . '_time" name="' . esc_attr($field_name) . '[time]" value="' . esc_attr($time_val) . '" class="regular-text" style="direction: ltr;">';
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
                                $rows = is_array($value) ? $value : [];
                                echo '<div class="wpd-repeater-field-wrapper">';
                                echo '<div class="wpd-repeater-rows-container">';
                                if (!empty($rows)) {
                                    foreach ($rows as $index => $row_data) {
                                        echo '<div class="wpd-repeater-row"><table class="form-table">';
                                        $this->render_admin_fields_recursive($field['sub_fields'], $post_id, $row_data, $field_name . '[' . $index . ']', '');
                                        echo '</table><a href="#" class="button button-small wpd-repeater-remove-row">' . __('حذف ردیف', 'wp-directory') . '</a></div>';
                                    }
                                }
                                echo '</div>';
                                echo '<div class="wpd-repeater-template" style="display:none;">';
                                echo '<div class="wpd-repeater-row"><table class="form-table">';
                                $this->render_admin_fields_recursive($field['sub_fields'], $post_id, [], $field_name . '[__INDEX__]', '');
                                echo '</table><a href="#" class="button button-small wpd-repeater-remove-row">' . __('حذف ردیف', 'wp-directory') . '</a></div>';
                                echo '</div>';
                                echo '<a href="#" class="button wpd-repeater-add-row">' . __('افزودن ردیف جدید', 'wp-directory') . '</a>';
                                echo '</div>';
                                break;
                            default: echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text">'; break;
                        }
                        ?>
                    </td>
                </tr>
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
    }
}
