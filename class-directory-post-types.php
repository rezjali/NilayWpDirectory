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
            
            // Save hooks
            add_action( 'save_post_wpd_listing', [ $this, 'save_listing_meta_data' ] );
            add_action( 'save_post_wpd_listing_type', [ $this, 'save_listing_type_meta_data' ] );
            add_action( 'save_post_wpd_upgrade', [ $this, 'save_upgrade_meta_data' ] );

            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
            add_action( 'admin_footer', [ $this, 'admin_scripts' ] );
            add_action( 'wp_ajax_wpd_load_admin_fields_and_taxonomies', [ $this, 'ajax_load_admin_fields_and_taxonomies' ] );

            // افزودن ستون‌های سفارشی
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
                'show_in_menu'       => false, // Will be added as submenu manually
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
            
            // Meta boxes for Listing Type CPT
            add_meta_box( 'wpd_listing_type_settings_mb', __( 'تنظیمات اصلی نوع آگهی', 'wp-directory' ), [ $this, 'render_listing_type_settings_metabox' ], 'wpd_listing_type', 'normal', 'high' );
            add_meta_box( 'wpd_field_builder_mb', __( 'فیلد ساز', 'wp-directory' ), [ $this, 'render_field_builder_metabox' ], 'wpd_listing_type', 'normal', 'default' );
            add_meta_box( 'wpd_taxonomy_builder_mb', __( 'طبقه‌بندی ساز', 'wp-directory' ), [ $this, 'render_taxonomy_builder_metabox' ], 'wpd_listing_type', 'normal', 'default' );
            add_meta_box( 'wpd_notification_settings_mb', __( 'تنظیمات اعلان‌های این نوع', 'wp-directory' ), [ $this, 'render_notification_settings_metabox' ], 'wpd_listing_type', 'side', 'default' );

            // Add meta box for Upgrade Package CPT
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
                    <th><label for="wpd_cost"><?php printf(__( 'هزینه ثبت (%s)', 'wp-directory' ), $currency); ?></label></th>
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
                            <input type="checkbox" name="wpd_notifications[<?php echo $id; ?>][email]" value="1" <?php checked(1, $settings[$id]['email'] ?? 1); ?>>
                            <?php _e('ارسال ایمیل', 'wp-directory'); ?>
                        </label>
                    </li>
                    <li>
                        <label>
                            <input type="checkbox" name="wpd_notifications[<?php echo $id; ?>][sms]" value="1" <?php checked(1, $settings[$id]['sms'] ?? 1); ?>>
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
                            <input type="text" name="wpd_taxonomies[<?php echo $index; ?>][name]" value="<?php echo esc_attr( $tax['name'] ?? '' ); ?>" placeholder="<?php _e( 'نام طبقه‌بندی (فارسی)', 'wp-directory' ); ?>">
                            <input type="text" name="wpd_taxonomies[<?php echo $index; ?>][slug]" value="<?php echo esc_attr( $tax['slug'] ?? '' ); ?>" placeholder="<?php _e( 'نامک (انگلیسی)', 'wp-directory' ); ?>">
                            <select name="wpd_taxonomies[<?php echo $index; ?>][hierarchical]">
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
                <div id="wpd-fields-container">
                    <?php if ( ! empty( $fields ) ) : foreach ( $fields as $index => $field ) : ?>
                        <div class="wpd-field-row">
                            <span class="dashicons dashicons-move handle"></span>
                            <div class="wpd-field-inputs">
                                <input type="text" name="wpd_fields[<?php echo $index; ?>][label]" value="<?php echo esc_attr( $field['label'] ?? '' ); ?>" placeholder="<?php _e( 'عنوان فیلد', 'wp-directory' ); ?>">
                                <input type="text" name="wpd_fields[<?php echo $index; ?>][key]" value="<?php echo esc_attr( $field['key'] ?? '' ); ?>" placeholder="<?php _e( 'کلید متا (انگلیسی)', 'wp-directory' ); ?>">
                                <select class="wpd-field-type-selector" name="wpd_fields[<?php echo $index; ?>][type]">
                                    <optgroup label="<?php _e('فیلدهای پایه', 'wp-directory'); ?>">
                                        <option value="text" <?php selected( $field['type'], 'text' ); ?>><?php _e( 'متن', 'wp-directory' ); ?></option>
                                        <option value="textarea" <?php selected( $field['type'], 'textarea' ); ?>><?php _e( 'متن بلند', 'wp-directory' ); ?></option>
                                        <option value="number" <?php selected( $field['type'], 'number' ); ?>><?php _e( 'عدد', 'wp-directory' ); ?></option>
                                        <option value="email" <?php selected( $field['type'], 'email' ); ?>><?php _e( 'ایمیل', 'wp-directory' ); ?></option>
                                        <option value="url" <?php selected( $field['type'], 'url' ); ?>><?php _e( 'وب‌سایت', 'wp-directory' ); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php _e('فیلدهای اعتبارسنجی', 'wp-directory'); ?>">
                                        <option value="mobile" <?php selected( $field['type'], 'mobile' ); ?>><?php _e( 'شماره موبایل', 'wp-directory' ); ?></option>
                                        <option value="phone" <?php selected( $field['type'], 'phone' ); ?>><?php _e( 'شماره تلفن ثابت', 'wp-directory' ); ?></option>
                                        <option value="postal_code" <?php selected( $field['type'], 'postal_code' ); ?>><?php _e( 'کد پستی', 'wp-directory' ); ?></option>
                                        <option value="national_id" <?php selected( $field['type'], 'national_id' ); ?>><?php _e( 'کد ملی', 'wp-directory' ); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php _e('فیلدهای انتخاب', 'wp-directory'); ?>">
                                        <option value="select" <?php selected( $field['type'], 'select' ); ?>><?php _e( 'لیست کشویی', 'wp-directory' ); ?></option>
                                        <option value="multiselect" <?php selected( $field['type'], 'multiselect' ); ?>><?php _e( 'چند انتخابی', 'wp-directory' ); ?></option>
                                        <option value="checkbox" <?php selected( $field['type'], 'checkbox' ); ?>><?php _e( 'چک‌باکس', 'wp-directory' ); ?></option>
                                        <option value="radio" <?php selected( $field['type'], 'radio' ); ?>><?php _e( 'دکمه رادیویی', 'wp-directory' ); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php _e('فیلدهای زمان و تاریخ', 'wp-directory'); ?>">
                                        <option value="date" <?php selected( $field['type'], 'date' ); ?>><?php _e( 'تاریخ (شمسی)', 'wp-directory' ); ?></option>
                                        <option value="time" <?php selected( $field['type'], 'time' ); ?>><?php _e( 'ساعت', 'wp-directory' ); ?></option>
                                        <option value="datetime" <?php selected( $field['type'], 'datetime' ); ?>><?php _e( 'تاریخ و ساعت (شمسی)', 'wp-directory' ); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php _e('فیلدهای پیشرفته', 'wp-directory'); ?>">
                                        <option value="gallery" <?php selected( $field['type'], 'gallery' ); ?>><?php _e( 'گالری تصاویر', 'wp-directory' ); ?></option>
                                        <option value="map" <?php selected( $field['type'], 'map' ); ?>><?php _e( 'نقشه', 'wp-directory' ); ?></option>
                                    </optgroup>
                                </select>
                                <textarea name="wpd_fields[<?php echo $index; ?>][options]" class="wpd-field-options" placeholder="<?php _e( 'گزینه‌ها (جدا شده با کاما)', 'wp-directory' ); ?>" style="<?php echo in_array(($field['type'] ?? ''), ['select', 'multiselect', 'checkbox', 'radio']) ? '' : 'display:none;'; ?>"><?php echo esc_textarea( $field['options'] ?? '' ); ?></textarea>
                            </div>
                            <a href="#" class="button wpd-remove-field"><?php _e( 'حذف', 'wp-directory' ); ?></a>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <a href="#" id="wpd-add-field" class="button button-primary"><?php _e( 'افزودن فیلد جدید', 'wp-directory' ); ?></a>
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
                                    <option value="<?php echo $type->ID; ?>" <?php selected($selected_type, $type->ID); ?>><?php echo esc_html($type->post_title); ?></option>
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
                    <th><label for="wpd_price"><?php printf(__( 'قیمت (%s)', 'wp-directory' ), $currency); ?></label></th>
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
                if ( isset( $_POST['wpd_fields'] ) && is_array($_POST['wpd_fields']) ) {
                    $sanitized_fields = [];
                    foreach ( $_POST['wpd_fields'] as $field ) {
                        if ( ! empty( $field['label'] ) && ! empty( $field['key'] ) ) {
                            $sanitized_fields[] = [ 'label' => sanitize_text_field( $field['label'] ), 'key' => sanitize_key( $field['key'] ), 'type' => sanitize_text_field( $field['type'] ), 'options' => sanitize_textarea_field( $field['options'] ) ];
                        }
                    }
                    update_post_meta( $post_id, '_wpd_custom_fields', $sanitized_fields );
                } else {
                    delete_post_meta($post_id, '_wpd_custom_fields');
                }
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
        
        public function save_listing_meta_data( $post_id ) {
            if ( ! isset( $_POST['wpd_listing_nonce'] ) || ! wp_verify_nonce( $_POST['wpd_listing_nonce'], 'wpd_save_listing_meta' ) ) return;
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
            if ( ! current_user_can( 'edit_post', $post_id ) ) return;

            if ( isset( $_POST['wpd_listing_type'] ) ) {
                update_post_meta($post_id, '_wpd_listing_type', intval($_POST['wpd_listing_type']));
            }

            if ( !isset($_POST['wpd_custom']) || !is_array($_POST['wpd_custom'])) {
                return;
            }

            $listing_type_id = get_post_meta($post_id, '_wpd_listing_type', true);
            $field_definitions = get_post_meta($listing_type_id, '_wpd_custom_fields', true);
            $fields_map = [];
            if (is_array($field_definitions)) {
                foreach ($field_definitions as $def) {
                    $fields_map['_wpd_' . $def['key']] = $def['type'];
                }
            }

            foreach ($_POST['wpd_custom'] as $key => $value) {
                if(strpos($key, '_wpd_') !== 0) continue;

                $sanitized_value = null;
                $field_type = $fields_map[$key] ?? 'text';

                // Sanitize based on field type
                switch ($field_type) {
                    case 'email':
                        $sanitized_value = sanitize_email($value);
                        break;
                    case 'number':
                        $sanitized_value = intval($value);
                        break;
                    case 'url':
                        $sanitized_value = esc_url_raw($value);
                        break;
                    case 'textarea':
                        $sanitized_value = sanitize_textarea_field($value);
                        break;
                    case 'checkbox':
                    case 'multiselect':
                        $sanitized_value = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
                        break;
                    case 'gallery':
                        $sanitized_value = implode(',', array_map('absint', explode(',', $value)));
                        break;
                    case 'mobile':
                    case 'phone':
                    case 'postal_code':
                    case 'national_id':
                        $sanitized_value = sanitize_text_field(preg_replace('/[^0-9]/', '', $value));
                        break;
                    case 'map':
                    case 'text':
                    case 'select':
                    case 'radio':
                    case 'date':
                    case 'time':
                    case 'datetime':
                    default:
                        $sanitized_value = sanitize_text_field($value);
                        break;
                }
                
                update_post_meta($post_id, sanitize_key($key), $sanitized_value);
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

                wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
                wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], '1.7.1', true);
            }
        }

        public function admin_scripts() {
            global $pagenow, $post_type;
            
            if ( ( $pagenow == 'post-new.php' || $pagenow == 'post.php' ) && $post_type == 'wpd_listing_type' ) {
                 ?>
                <style>
                    .wpd-field-row { display: flex; align-items: flex-start; margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;}
                    .wpd-field-row .handle { cursor: move; color: #888; margin-right: 10px; padding-top: 5px;}
                    .wpd-field-inputs { display: flex; flex-wrap: wrap; flex-grow: 1; gap: 5px; }
                    .wpd-field-inputs input, .wpd-field-inputs select, .wpd-field-inputs textarea { margin: 0; }
                    .wpd-field-options { width: 100%; height: 60px; }
                </style>
                <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        function toggleOptionsField(element) {
                            var fieldType = $(element).val();
                            var optionsTextarea = $(element).siblings('.wpd-field-options');
                            if (['select', 'multiselect', 'checkbox', 'radio'].includes(fieldType)) {
                                optionsTextarea.show();
                            } else {
                                optionsTextarea.hide();
                            }
                        }
                        $('#wpd-fields-container').on('change', '.wpd-field-type-selector', function() { toggleOptionsField(this); });
                        $('.wpd-field-type-selector').each(function() { toggleOptionsField(this); });
                        $('#wpd-fields-container, #wpd-taxonomies-container').sortable({ handle: '.handle', opacity: 0.6, cursor: 'move' });
                        
                        $('#wpd-add-field').on('click', function(e) {
                            e.preventDefault();
                            var field_count = $('#wpd-fields-container .wpd-field-row').length;
                            var new_field = `
                                <div class="wpd-field-row">
                                    <span class="dashicons dashicons-move handle"></span>
                                    <div class="wpd-field-inputs">
                                        <input type="text" name="wpd_fields[${field_count}][label]" placeholder="<?php _e( 'عنوان فیلد', 'wp-directory' ); ?>">
                                        <input type="text" name="wpd_fields[${field_count}][key]" placeholder="<?php _e( 'کلید متا (انگلیسی)', 'wp-directory' ); ?>">
                                        <select class="wpd-field-type-selector" name="wpd_fields[${field_count}][type]">
                                            <optgroup label="<?php _e('فیلدهای پایه', 'wp-directory'); ?>">
                                                <option value="text"><?php _e( 'متن', 'wp-directory' ); ?></option>
                                                <option value="textarea"><?php _e( 'متن بلند', 'wp-directory' ); ?></option>
                                                <option value="number"><?php _e( 'عدد', 'wp-directory' ); ?></option>
                                                <option value="email"><?php _e( 'ایمیل', 'wp-directory' ); ?></option>
                                                <option value="url"><?php _e( 'وب‌سایت', 'wp-directory' ); ?></option>
                                            </optgroup>
                                            <optgroup label="<?php _e('فیلدهای اعتبارسنجی', 'wp-directory'); ?>">
                                                <option value="mobile"><?php _e( 'شماره موبایل', 'wp-directory' ); ?></option>
                                                <option value="phone"><?php _e( 'شماره تلفن ثابت', 'wp-directory' ); ?></option>
                                                <option value="postal_code"><?php _e( 'کد پستی', 'wp-directory' ); ?></option>
                                                <option value="national_id"><?php _e( 'کد ملی', 'wp-directory' ); ?></option>
                                            </optgroup>
                                            <optgroup label="<?php _e('فیلدهای انتخاب', 'wp-directory'); ?>">
                                                <option value="select"><?php _e( 'لیست کشویی', 'wp-directory' ); ?></option>
                                                <option value="multiselect"><?php _e( 'چند انتخابی', 'wp-directory' ); ?></option>
                                                <option value="checkbox"><?php _e( 'چک‌باکس', 'wp-directory' ); ?></option>
                                                <option value="radio"><?php _e( 'دکمه رادیویی', 'wp-directory' ); ?></option>
                                            </optgroup>
                                            <optgroup label="<?php _e('فیلدهای زمان و تاریخ', 'wp-directory'); ?>">
                                                <option value="date"><?php _e( 'تاریخ (شمسی)', 'wp-directory' ); ?></option>
                                                <option value="time"><?php _e( 'ساعت', 'wp-directory' ); ?></option>
                                                <option value="datetime"><?php _e( 'تاریخ و ساعت (شمسی)', 'wp-directory' ); ?></option>
                                            </optgroup>
                                            <optgroup label="<?php _e('فیلدهای پیشرفته', 'wp-directory'); ?>">
                                                <option value="gallery"><?php _e( 'گالری تصاویر', 'wp-directory' ); ?></option>
                                                <option value="map"><?php _e( 'نقشه', 'wp-directory' ); ?></option>
                                            </optgroup>
                                        </select>
                                        <textarea name="wpd_fields[${field_count}][options]" class="wpd-field-options" placeholder="<?php _e( 'گزینه‌ها (جدا شده با کاما)', 'wp-directory' ); ?>" style="display:none;"></textarea>
                                    </div>
                                    <a href="#" class="button wpd-remove-field"><?php _e( 'حذف', 'wp-directory' ); ?></a>
                                </div>
                            `;
                            $('#wpd-fields-container').append(new_field);
                        });

                        $('#wpd-add-taxonomy').on('click', function(e) {
                            e.preventDefault();
                            var tax_count = $('#wpd-taxonomies-container .wpd-field-row').length;
                            var new_tax = `
                                <div class="wpd-field-row">
                                    <span class="dashicons dashicons-move handle"></span>
                                    <div class="wpd-field-inputs">
                                        <input type="text" name="wpd_taxonomies[${tax_count}][name]" placeholder="<?php _e( 'نام طبقه‌بندی (فارسی)', 'wp-directory' ); ?>">
                                        <input type="text" name="wpd_taxonomies[${tax_count}][slug]" placeholder="<?php _e( 'نامک (انگلیسی)', 'wp-directory' ); ?>">
                                        <select name="wpd_taxonomies[${tax_count}][hierarchical]">
                                            <option value="1"><?php _e('سلسله مراتبی', 'wp-directory'); ?></option>
                                            <option value="0"><?php _e('غیر سلسله مراتبی (تگ)', 'wp-directory'); ?></option>
                                        </select>
                                    </div>
                                    <a href="#" class="button wpd-remove-field"><?php _e( 'حذف', 'wp-directory' ); ?></a>
                                </div>
                            `;
                            $('#wpd-taxonomies-container').append(new_tax);
                        });

                        $('#wpd-field-builder-wrapper, #wpd-taxonomy-builder-wrapper').on('click', '.wpd-remove-field', function(e) { e.preventDefault(); $(this).closest('.wpd-field-row').remove(); });
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
                </style>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        function initAdminFields(container) {
                            // Gallery Field Logic
                            container.find('.wpd-upload-gallery-button').on('click', function(e) {
                                e.preventDefault();
                                var button = $(this);
                                var input = button.next('input[type="hidden"]');
                                var preview = button.siblings('.gallery-preview');
                                var image_ids = input.val() ? input.val().split(',').filter(Number) : [];

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
                                var container = $(this).closest('.wpd-gallery-field-wrapper');
                                var input = container.find('input[type="hidden"]');
                                var image_ids = input.val().split(',').filter(Number);
                                var new_ids = image_ids.filter(function(id) {
                                    return id !== id_to_remove;
                                });
                                input.val(new_ids.join(','));
                                $(this).parent().remove();
                            });

                            // Map Field Logic (LeafletJS)
                            container.find('.wpd-map-field-wrapper').each(function() {
                                var wrapper = $(this);
                                var input = wrapper.find('input[type="text"]');
                                var mapContainer = wrapper.find('.map-preview')[0];
                                var latlng = input.val() ? input.val().split(',') : [32.4279, 53.6880];

                                var map = L.map(mapContainer).setView(latlng, 5);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                                }).addTo(map);

                                var marker = L.marker(latlng, { draggable: true }).addTo(map);

                                function updateInput(latlng) {
                                    input.val(latlng.lat.toFixed(6) + ',' + latlng.lng.toFixed(6));
                                }

                                marker.on('dragend', function(e) {
                                    updateInput(e.target.getLatLng());
                                });

                                map.on('click', function(e) {
                                    marker.setLatLng(e.latlng);
                                    updateInput(e.latlng);
                                });
                            });
                        }

                        function setupPage() {
                            hideAllTaxonomyMetaboxes();
                            $('#wpd_listing_type_selector').on('change', function() {
                                var type_id = $(this).val();
                                var post_id = $('#post_ID').val();
                                var fields_container = $('#wpd-admin-custom-fields-wrapper');
                                
                                hideAllTaxonomyMetaboxes();

                                if(type_id === "") {
                                    fields_container.html('');
                                    return;
                                }
                                
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
                                            showSelectedTaxonomies(response.data.taxonomies);
                                            initAdminFields(fields_container);
                                        } else {
                                            fields_container.html('<p style="color:red;">' + response.data.message + '</p>');
                                        }
                                    },
                                    error: function() {
                                         fields_container.html('<p style="color:red;">خطا در برقراری ارتباط.</p>');
                                    }
                                });
                            }).trigger('change');
                        }

                        function hideAllTaxonomyMetaboxes() {
                             $('#side-sortables').find('.postbox').each(function(){
                                var id = $(this).attr('id');
                                var keep_ids = ['submitdiv', 'wpd_listing_categorydiv', 'wpd_listing_locationdiv'];
                                if( ! keep_ids.includes(id) ) {
                                    $(this).hide();
                                }
                             });
                        }
                        
                        function showSelectedTaxonomies(tax_slugs) {
                            if(tax_slugs && tax_slugs.length > 0) {
                                tax_slugs.forEach(function(tax_slug) {
                                    $('#' + tax_slug + 'div, #tagsdiv-' + tax_slug).show();
                                });
                            }
                        }
                        
                        setupPage();
                    });
                </script>
                <?php
            }

            if ( ( $pagenow == 'post-new.php' || $pagenow == 'post.php' ) && $post_type == 'wpd_upgrade' ) {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#wpd_upgrade_type').on('change', function() {
                            if ($(this).val() === 'bump_up') {
                                $('.wpd-duration-row').hide();
                            } else {
                                $('.wpd-duration-row').show();
                            }
                        });
                    });
                </script>
                <?php
            }
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
            foreach ($fields as $field) {
                $meta_key = '_wpd_' . sanitize_key($field['key']);
                $value = get_post_meta($post_id, $meta_key, true);
                ?>
                <tr>
                    <th><label for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($field['label']); ?></label></th>
                    <td>
                        <?php
                        $options = !empty($field['options']) ? array_map('trim', explode(',', $field['options'])) : [];
                        switch($field['type']) {
                            case 'textarea': echo '<textarea id="' . esc_attr($meta_key) . '" name="wpd_custom[' . esc_attr($meta_key) . ']" class="large-text">' . esc_textarea($value) . '</textarea>'; break;
                            case 'select':
                                echo '<select id="' . esc_attr($meta_key) . '" name="wpd_custom[' . esc_attr($meta_key) . ']">';
                                echo '<option value="">-- انتخاب کنید --</option>';
                                foreach($options as $option) echo '<option value="'.esc_attr($option).'" '.selected($value, $option, false).'>'.esc_html($option).'</option>';
                                echo '</select>';
                                break;
                            case 'checkbox':
                                $saved_values = is_array($value) ? $value : (array)$value;
                                foreach($options as $option) echo '<label><input type="checkbox" name="wpd_custom[' . esc_attr($meta_key) . '][]" value="'.esc_attr($option).'" '.(in_array($option, $saved_values) ? 'checked' : '').'> '.esc_html($option).'</label><br>';
                                break;
                            case 'radio':
                                foreach($options as $option) echo '<label><input type="radio" name="wpd_custom[' . esc_attr($meta_key) . ']" value="'.esc_attr($option).'" '.checked($value, $option, false).'> '.esc_html($option).'</label><br>';
                                break;
                            case 'gallery':
                                $image_ids = array_filter(explode(',', $value));
                                echo '<div class="wpd-gallery-field-wrapper">';
                                echo '<a href="#" class="button wpd-upload-gallery-button">'.__('مدیریت گالری', 'wp-directory').'</a>';
                                echo '<input type="hidden" id="'.esc_attr($meta_key).'" name="wpd_custom['.esc_attr($meta_key).']" value="'.esc_attr($value).'">';
                                echo '<div class="gallery-preview">';
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
                                echo '<input type="text" id="'.esc_attr($meta_key).'" name="wpd_custom['.esc_attr($meta_key).']" value="'.esc_attr($value).'" placeholder="32.4279,53.6880">';
                                echo '<div class="map-preview" style="width:100%; height:250px; background:#eee; margin-top:10px;"></div>';
                                echo '</div>';
                                break;
                            default: echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($meta_key) . '" name="wpd_custom[' . esc_attr($meta_key) . ']" value="' . esc_attr($value) . '" class="regular-text">'; break;
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
            echo '</tbody></table>';
            return ob_get_clean();
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
                    echo $type_id ? get_the_title($type_id) : '---';
                    break;
                case 'expiration_date':
                    $date = get_post_meta($post_id, '_wpd_expiration_date', true);
                    echo $date ? date_i18n('Y/m/d', strtotime($date)) : __('نامحدود', 'wp-directory');
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
                case 'price': echo number_format(get_post_meta($post_id, '_price', true)); break;
                case 'duration': echo get_post_meta($post_id, '_duration', true) ?: __('نامحدود', 'wp-directory'); break;
                case 'limit': echo get_post_meta($post_id, '_listing_limit', true) ?: __('نامحدود', 'wp-directory'); break;
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
                    echo number_format(get_post_meta($post_id, '_price', true));
                    break;
                case 'upgrade_type':
                    $type = get_post_meta($post_id, '_upgrade_type', true);
                    $types = [
                        'featured' => __('ویژه کردن', 'wp-directory'),
                        'bump_up' => __('نردبان', 'wp-directory'),
                        'top_of_category' => __('پین در بالای دسته', 'wp-directory'),
                        'urgent' => __('برچسب فوری', 'wp-directory'),
                    ];
                    echo $types[$type] ?? esc_html($type);
                    break;
                case 'duration':
                    $type = get_post_meta($post_id, '_upgrade_type', true);
                    if ($type === 'bump_up') {
                        echo '---';
                    } else {
                        echo get_post_meta($post_id, '_duration', true) ?: __('نامحدود', 'wp-directory');
                    }
                    break;
            }
        }
    }
}
