<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// جلوگیری از تعریف مجدد کلاس
if ( ! class_exists( 'Directory_User' ) ) {

    /**
     * کلاس مسئول مدیریت کاربران، نقش‌های کاربری و بسته‌های عضویت
     */
    class Directory_User {

        /**
         * سازنده کلاس
         */
        public function __construct() {
            add_action( 'init', [ $this, 'register_package_post_type' ] );
            add_action( 'add_meta_boxes', [ $this, 'add_package_meta_boxes' ] );
            add_action( 'save_post_wpd_package', [ $this, 'save_package_meta_data' ] );
            
            // افزودن فیلد به پروفایل کاربری
            add_action( 'show_user_profile', [ $this, 'add_custom_user_profile_fields' ] );
            add_action( 'edit_user_profile', [ $this, 'add_custom_user_profile_fields' ] );
            add_action( 'personal_options_update', [ $this, 'save_custom_user_profile_fields' ] );
            add_action( 'edit_user_profile_update', [ $this, 'save_custom_user_profile_fields' ] );

            // START OF CHANGE: هوک برای ارسال اعلان ثبت‌نام کاربر جدید
            add_action( 'user_register', [ $this, 'trigger_new_user_notification' ], 10, 1 );
            // END OF CHANGE
        }

        /**
         * ایجاد نقش کاربری سفارشی در هنگام فعال‌سازی افزونه
         */
        public static function create_roles() {
            add_role(
                'business_owner',
                __( 'صاحب کسب‌وکار', 'wp-directory' ),
                [
                    'read' => true,
                    'edit_posts' => false,
                    'delete_posts' => false,
                    'publish_posts' => false,
                    'upload_files' => true,
                    // دسترسی‌های سفارشی برای آگهی‌ها
                    'edit_wpd_listing' => true,
                    'read_wpd_listing' => true,
                    'delete_wpd_listing' => true,
                    'edit_wpd_listings' => true,
                    'publish_wpd_listings' => true,
                    'delete_wpd_listings' => true,
                ]
            );
        }

        /**
         * حذف نقش کاربری سفارشی در هنگام غیرفعال‌سازی افزونه
         */
        public static function remove_roles() {
            remove_role( 'business_owner' );
        }

        /**
         * ثبت CPT برای بسته‌های عضویت
         */
        public function register_package_post_type() {
            $labels = [
                'name'               => __( 'بسته‌های عضویت', 'wp-directory' ),
                'singular_name'      => __( 'بسته عضویت', 'wp-directory' ),
                'add_new_item'       => __( 'افزودن بسته جدید', 'wp-directory' ),
                'edit_item'          => __( 'ویرایش بسته', 'wp-directory' ),
            ];
            $args = [
                'labels'             => $labels,
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => false, // به صورت دستی در زیرمنو اضافه شده است
                'capability_type'    => 'post',
                'supports'           => [ 'title' ],
            ];
            register_post_type( 'wpd_package', $args );
        }

        /**
         * افزودن متا باکس برای جزئیات بسته
         */
        public function add_package_meta_boxes() {
            add_meta_box(
                'wpd_package_details_mb',
                __( 'جزئیات بسته', 'wp-directory' ),
                [ $this, 'render_package_details_metabox' ],
                'wpd_package',
                'normal',
                'high'
            );
        }

        /**
         * رندر کردن متا باکس جزئیات بسته
         */
        public function render_package_details_metabox( $post ) {
            wp_nonce_field( 'wpd_save_package_meta', 'wpd_package_nonce' );
            $meta = get_post_meta( $post->ID );
            $currency = Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'];
            ?>
            <table class="form-table">
                <tr>
                    <th><label for="wpd_price"><?php printf(__( 'قیمت (%s)', 'wp-directory' ), $currency); ?></label></th>
                    <td><input type="number" id="wpd_price" name="wpd_meta[_price]" value="<?php echo esc_attr( $meta['_price'][0] ?? '0' ); ?>" class="regular-text">
                    <p class="description"><?php _e( 'برای بسته رایگان، 0 وارد کنید.', 'wp-directory' ); ?></p></td>
                </tr>
                <tr>
                    <th><label for="wpd_duration"><?php _e( 'مدت اعتبار آگهی (روز)', 'wp-directory' ); ?></label></th>
                    <td><input type="number" id="wpd_duration" name="wpd_meta[_duration]" value="<?php echo esc_attr( $meta['_duration'][0] ?? '30' ); ?>" class="regular-text">
                    <p class="description"><?php _e( 'برای اعتبار نامحدود، 0 یا خالی بگذارید.', 'wp-directory' ); ?></p></td>
                </tr>
                <tr>
                    <th><label for="wpd_listing_limit"><?php _e( 'تعداد آگهی مجاز', 'wp-directory' ); ?></label></th>
                    <td><input type="number" id="wpd_listing_limit" name="wpd_meta[_listing_limit]" value="<?php echo esc_attr( $meta['_listing_limit'][0] ?? '1' ); ?>" class="regular-text">
                    <p class="description"><?php _e( 'برای تعداد نامحدود، 0 یا خالی بگذارید.', 'wp-directory' ); ?></p></td>
                </tr>
                <tr>
                    <th><label for="wpd_is_featured"><?php _e( 'آگهی ویژه؟', 'wp-directory' ); ?></label></th>
                    <td><input type="checkbox" id="wpd_is_featured" name="wpd_meta[_is_featured]" value="1" <?php checked( $meta['_is_featured'][0] ?? 0, '1' ); ?>>
                    <span class="description"><?php _e( 'آیا آگهی‌های این بسته به صورت ویژه نمایش داده شوند؟', 'wp-directory' ); ?></span></td>
                </tr>
            </table>
            <?php
        }

        /**
         * ذخیره کردن متا دیتای بسته‌ها
         */
        public function save_package_meta_data( $post_id ) {
            if ( ! isset( $_POST['wpd_package_nonce'] ) || ! wp_verify_nonce( $_POST['wpd_package_nonce'], 'wpd_save_package_meta' ) ) return;
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
            if ( get_post_type($post_id) !== 'wpd_package' ) return;
            if ( ! current_user_can( 'edit_post', $post_id ) ) return;

            if(isset($_POST['wpd_meta']) && is_array($_POST['wpd_meta'])) {
                foreach($_POST['wpd_meta'] as $key => $value) {
                    if ($key === '_is_featured') {
                        update_post_meta($post_id, $key, '1');
                    } else {
                        update_post_meta($post_id, $key, sanitize_text_field($value));
                    }
                }
            }
            // اگر چک‌باکس ارسال نشده بود، یعنی تیک نخورده است
            if (!isset($_POST['wpd_meta']['_is_featured'])) {
                update_post_meta($post_id, '_is_featured', '0');
            }
        }

        /**
         * افزودن فیلد سفارشی به پروفایل کاربری
         */
        public function add_custom_user_profile_fields( $user ) {
            ?>
            <h3><?php _e( 'اطلاعات تکمیلی دایرکتوری', 'wp-directory' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="phone_number"><?php _e( 'شماره موبایل', 'wp-directory' ); ?></label></th>
                    <td>
                        <input type="text" name="phone_number" id="phone_number" value="<?php echo esc_attr( get_the_author_meta( 'phone_number', $user->ID ) ); ?>" class="regular-text" />
                        <p class="description"><?php _e( 'برای دریافت اعلان‌های پیامکی استفاده می‌شود. (با فرمت 09123456789)', 'wp-directory' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php
        }

        /**
         * ذخیره فیلد سفارشی پروفایل کاربری
         */
        public function save_custom_user_profile_fields( $user_id ) {
            if ( ! current_user_can( 'edit_user', $user_id ) ) {
                return false;
            }
            if ( isset( $_POST['phone_number'] ) ) {
                update_user_meta( $user_id, 'phone_number', sanitize_text_field( $_POST['phone_number'] ) );
            }
        }

        /**
         * ارسال اعلان برای کاربر جدید
         * @param int $user_id
         */
        public function trigger_new_user_notification( $user_id ) {
            Directory_Main::trigger_notification('new_user', ['user_id' => $user_id]);
        }
    }
}
