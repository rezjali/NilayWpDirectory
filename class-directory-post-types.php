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
            $width_class = $field_data['width_class'] ?? 'full';
            $conditional_logic = wp_parse_args($field_data['conditional_logic'] ?? [], [
                'enabled'      => 0,
                'action'       => 'show',
                'target_field' => '',
                'operator'     => 'is',
                'value'        => '',
            ]);
            $address_settings = $field_data['address_settings'] ?? [];
            $identity_settings = $field_data['identity_settings'] ?? [];
            ?>
            <div class="wpd-field-row" data-index="<?php echo esc_attr($index); ?>" data-field-key="<?php echo esc_attr($key); ?>">
                <div class="wpd-field-header">
                    <span class="dashicons dashicons-move handle"></span>
                    <strong><?php echo esc_html($label) ?: __('فیلد جدید', 'wp-directory'); ?></strong> (<span class="wpd-field-type-display"><?php echo esc_html($type); ?></span>)
                    <a href="#" class="wpd-toggle-field-details"><?php _e('جزئیات', 'wp-directory'); ?></a>
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
                            <optgroup label="<?php _e('فیلدهای پیشرفته', 'wp-directory'); ?>">
                                <option value="gallery" <?php selected( $type, 'gallery' ); ?>><?php _e( 'گالری تصاویر', 'wp-directory' ); ?></option>
                                <option value="map" <?php selected( $type, 'map' ); ?>><?php _e( 'نقشه', 'wp-directory' ); ?></option>
                                <option value="repeater" <?php selected( $type, 'repeater' ); ?>><?php _e( 'تکرار شونده', 'wp-directory' ); ?></option>
                                <option value="social_networks" <?php selected( $type, 'social_networks' ); ?>><?php _e( 'لیست شبکه‌های اجتماعی', 'wp-directory' ); ?></option>
                                <option value="simple_list" <?php selected( $type, 'simple_list' ); ?>><?php _e( 'فیلد لیستی', 'wp-directory' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('فیلدهای ترکیبی', 'wp-directory'); ?>">
                                <option value="address" <?php selected( $type, 'address' ); ?>><?php _e( 'آدرس پستی', 'wp-directory' ); ?></option>
                                <option value="identity" <?php selected( $type, 'identity' ); ?>><?php _e( 'اطلاعات هویتی', 'wp-directory' ); ?></option>
                            </optgroup>
                        </select>
                        <textarea name="wpd_fields[<?php echo esc_attr($index); ?>][options]" class="wpd-field-options" placeholder="<?php _e( 'گزینه‌ها (جدا شده با کاما) یا محتوای HTML', 'wp-directory' ); ?>" style="<?php echo in_array($type, ['select', 'multiselect', 'checkbox', 'radio', 'html_content']) ? '' : 'display:none;'; ?>"><?php echo esc_textarea($options); ?></textarea>
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
                            <select name="wpd_fields[<?php echo esc_attr($index); ?>][conditional_logic][operator]">
                                <option value="is" <?php selected('is', $conditional_logic['operator']); ?>><?php _e('برابر باشد با', 'wp-directory'); ?></option>
                                <option value="is_not" <?php selected('is_not', $conditional_logic['operator']); ?>><?php _e('برابر نباشد با', 'wp-directory'); ?></option>
                                <option value="is_empty" <?php selected('is_empty', $conditional_logic['operator']); ?>><?php _e('خالی باشد', 'wp-directory'); ?></option>
                                <option value="is_not_empty" <?php selected('is_not_empty', $conditional_logic['operator']); ?>><?php _e('خالی نباشد', 'wp-directory'); ?></option>
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
            
            // Save taxonomy builder
            if ( isset( $_POST['wpd_taxonomy_builder_nonce'] ) && wp_verify_nonce( $_POST['wpd_taxonomy_builder_nonce'], 'wpd_save_taxonomy_builder_meta' ) ) {
                if ( isset( $_POST['wpd_taxonomies'] ) && is_array($_POST['wpd_taxonomies']) ) {
                    $sanitized_taxs = [];
                    foreach ( $_POST['wpd_taxonomies'] as $tax ) {
                        if ( ! empty( $tax['name'] ) && ! empty( $tax['slug'] ) ) {
                            // START OF CHANGE: Sanitize slug for URL-friendliness
                            $sanitized_taxs[] = [ 'name' => sanitize_text_field( $tax['name'] ), 'slug' => sanitize_title( $tax['slug'] ), 'hierarchical' => intval( $tax['hierarchical'] ) ];
                            // END OF CHANGE
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
                    'width_class' => isset($field['width_class']) ? sanitize_key($field['width_class']) : 'full',
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

                            if (!empty(array_filter($sanitized_row))) {
                                $repeater_data[] = $sanitized_row;
                            }
                        }
                    }
                    update_post_meta($post_id, $meta_key, $repeater_data);
                } elseif (in_array($field_def['type'], ['address', 'identity'])) {
                    $sanitized_value = is_array($posted_value) ? array_map('sanitize_text_field', $posted_value) : sanitize_text_field($posted_value);
                    update_post_meta($post_id, $meta_key, $sanitized_value);
                }
                else {
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

                wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
                wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], '1.7.1', true);

                wp_localize_script('jquery', 'wpd_admin_params', [
                    'ajax_nonce' => wp_create_nonce("wpd_admin_fields_nonce"),
                ]);
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
                            $(this).closest('.wpd-field-row').data('field-key', $(this).val());
                            updateConditionalFieldDropdowns();
                        });

                        $('#wpd-field-builder-wrapper').on('click', '.wpd-remove-field', function(e) {
                            e.preventDefault();
                            if (confirm('آیا از حذف این فیلد مطمئن هستید؟')) {
                                $(this).closest('.wpd-field-row').remove();
                                updateConditionalFieldDropdowns();
                            }
                        });

                        $('#wpd-add-field').on('click', function(e) {
                            e.preventDefault();
                            var container = $('#wpd-fields-container');
                            var newIndex = container.children().length ? (Math.max.apply(null, container.children().map(function() { return $(this).data('index'); }).get()) + 1) : 0;
                            var field_html = $(<?php echo json_encode($this->get_field_builder_row_html('__INDEX__', [], $fields)); ?>.replace(/__INDEX__/g, newIndex));
                            field_html.find('.wpd-field-details').show();
                            container.append(field_html);
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
                </style>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {

                        const ajaxNonce = '<?php echo wp_create_nonce("wpd_admin_fields_nonce"); ?>';

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
                                    _ajax_nonce: ajaxNonce
                                },
                                success: function(response) {
                                    if(response.success) {
                                        fields_container.html(response.data.fields);
                                        initializeWpdComponents(fields_container);
                                    } else {
                                        fields_container.html('<p style="color:red;">' + response.data.message + '</p>');
                                    }
                                },
                                error: function() {
                                     fields_container.html('<p style="color:red;">خطا در برقراری ارتباط.</p>');
                                }
                            });
                        });

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
                
                $width_class = 'wpd-admin-field-col-' . ($field['width_class'] ?? 'full');

                if ($field['type'] === 'section_title') {
                    echo '<div class="wpd-admin-field-wrapper wpd-admin-field-col-full"><h3 class="wpd-section-title">' . esc_html($field['label']) . '</h3></div>';
                    continue;
                }
                if ($field['type'] === 'html_content') {
                    echo '<div class="wpd-admin-field-wrapper wpd-admin-field-col-full">' . wp_kses_post($field['options']) . '</div>';
                    continue;
                }

                ?>
                <div class="wpd-admin-field-wrapper <?php echo esc_attr($width_class); ?>">
                    <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field['label']); ?></label>
                    <div class="wpd-field-input-wrapper">
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
                            
                            case 'time':
                                echo '<input type="time" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text" style="direction: ltr;">';
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

                            default: echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text">'; break;
                        }
                        ?>
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
    }
}
