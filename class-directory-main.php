<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// جلوگیری از تعریف مجدد کلاس
if ( ! class_exists( 'Directory_Main' ) ) {

    /**
     * کلاس اصلی و هماهنگ‌کننده افزونه دایرکتوری.
     * این کلاس از الگوی Singleton پیروی می‌کند.
     */
    final class Directory_Main {

        /**
         * نمونه‌ای از این کلاس
         * @var Directory_Main|null
         */
        private static $instance = null;

        /**
         * @var Directory_Admin
         */
        public $admin;

        /**
         * @var Directory_Frontend
         */
        public $frontend;

        /**
         * @var Directory_Post_Types
         */
        public $post_types;

        /**
         * @var Directory_Gateways
         */
        public $gateways;

        /**
         * @var Directory_User
         */
        public $user;

        /**
         * متد اصلی برای دریافت نمونه کلاس
         * @return Directory_Main
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * سازنده کلاس
         */
        private function __construct() {
            $this->init_classes();
            $this->init_hooks();
        }

        /**
         * نمونه‌سازی تمام کلاس‌های مورد نیاز افزونه
         */
        private function init_classes() {
            $this->post_types = new Directory_Post_Types();
            $this->admin      = new Directory_Admin();
            $this->frontend   = new Directory_Frontend();
            $this->gateways   = new Directory_Gateways();
            $this->user       = new Directory_User();
        }

        /**
         * ثبت هوک‌های عمومی
         */
        private function init_hooks() {
            add_action( 'init', [ $this, 'load_textdomain' ] );
        }

        /**
         * بارگذاری فایل ترجمه
         */
        public function load_textdomain() {
            load_plugin_textdomain( 'wp-directory', false, dirname( plugin_basename( WPD_PLUGIN_FILE ) ) . '/languages' );
        }

        /**
         * تابع کمکی برای دریافت یک گزینه خاص از تنظیمات افزونه
         *
         * @param string $option_name نام آپشن
         * @param mixed $default مقدار پیش‌فرض
         * @return mixed
         */
        public static function get_option( $option_name, $default = false ) {
            $options = get_option( 'wpd_settings', [] );
            return isset( $options[ $option_name ] ) ? $options[ $option_name ] : $default;
        }
        
        /**
         * تابع کمکی برای دریافت یک گزینه از تب ظاهری
         *
         * @param string $option_name نام آپشن
         * @param mixed $default مقدار پیش‌فرض
         * @return mixed
         */
        public static function get_style_option( $option_name, $default = false ) {
            $options = self::get_option('appearance', []);
            return isset( $options[ $option_name ] ) && !empty($options[ $option_name ]) ? $options[ $option_name ] : $default;
        }

        /**
         * تابع کمکی برای دریافت اصطلاح سفارشی یا پیش‌فرض
         *
         * @param string $key کلید اصطلاح
         * @param bool $return_key_on_empty اگر مقدار خالی بود، کلید را برگرداند؟
         * @return string
         */
        public static function get_term( $key, $return_key_on_empty = false ) {
            $terms = self::get_option( 'terminology', [] );
            $default_terms = self::get_default_terms();

            if ( ! empty( $terms[ $key ] ) ) {
                return esc_html( $terms[ $key ] );
            }

            if ( isset( $default_terms[ $key ] ) ) {
                return esc_html( $default_terms[ $key ] );
            }
            
            return $return_key_on_empty ? esc_html($key) : '';
        }

        /**
         * لیست کامل اصطلاحات پیش‌فرض افزونه
         *
         * @return array
         */
        public static function get_default_terms() {
            return [
                // General
                'listing' => __( 'آگهی', 'wp-directory' ),
                'listings' => __( 'آگهی‌ها', 'wp-directory' ),
                'listing_singular' => __( 'آگهی', 'wp-directory' ),
                'listing_plural' => __( 'آگهی‌ها', 'wp-directory' ),
                'featured_listing' => __( 'آگهی ویژه', 'wp-directory' ),
                'price' => __( 'قیمت', 'wp-directory' ),
                'free' => __( 'رایگان', 'wp-directory' ),

                // Actions
                'submit_listing' => __( 'ثبت آگهی', 'wp-directory' ),
                'edit_listing' => __( 'ویرایش آگهی', 'wp-directory' ),
                'delete_listing' => __( 'حذف آگهی', 'wp-directory' ),
                'renew_listing' => __( 'تمدید آگهی', 'wp-directory' ),
                'search' => __( 'جستجو', 'wp-directory' ),
                'filter' => __( 'فیلتر', 'wp-directory' ),
                'submit' => __( 'ثبت', 'wp-directory' ),
                'update' => __( 'به‌روزرسانی', 'wp-directory' ),
                'pay' => __( 'پرداخت', 'wp-directory' ),
                'login' => __( 'ورود', 'wp-directory' ),
                'register' => __( 'ثبت‌نام', 'wp-directory' ),
                'logout' => __( 'خروج', 'wp-directory' ),

                // Form Labels
                'listing_title' => __( 'عنوان آگهی', 'wp-directory' ),
                'listing_description' => __( 'توضیحات', 'wp-directory' ),
                'listing_type' => __( 'نوع آگهی', 'wp-directory' ),
                'listing_category' => __( 'دسته‌بندی', 'wp-directory' ),
                'listing_location' => __( 'منطقه', 'wp-directory' ),
                'featured_image' => __( 'تصویر شاخص', 'wp-directory' ),
                'gallery_images' => __( 'گالری تصاویر', 'wp-directory' ),
                'contact_info' => __( 'اطلاعات تماس', 'wp-directory' ),
                'phone_number' => __( 'شماره تلفن', 'wp-directory' ),
                'email_address' => __( 'آدرس ایمیل', 'wp-directory' ),
                'website' => __( 'وب‌سایت', 'wp-directory' ),

                // User Dashboard
                'dashboard' => __( 'داشبورد من', 'wp-directory' ),
                'my_listings' => __( 'آگهی‌های من', 'wp-directory' ),
                'my_profile' => __( 'پروفایل من', 'wp-directory' ),
                'my_transactions' => __( 'تراکنش‌ها', 'wp-directory' ),

                // Statuses & Labels
                'status' => __( 'وضعیت', 'wp-directory' ),
                'active' => __( 'فعال', 'wp-directory' ),
                'pending' => __( 'در انتظار تایید', 'wp-directory' ),
                'expired' => __( 'منقضی شده', 'wp-directory' ),
                'date_published' => __( 'تاریخ انتشار', 'wp-directory' ),
                'expires_on' => __( 'تاریخ انقضا', 'wp-directory' ),
                
                // Packages
                'membership_package' => __( 'بسته عضویت', 'wp-directory' ),
                'select_package' => __( 'انتخاب بسته', 'wp-directory' ),
                'listing_duration' => __( 'مدت اعتبار آگهی', 'wp-directory' ),
                'days' => __( 'روز', 'wp-directory' ),
                'unlimited' => __( 'نامحدود', 'wp-directory' ),
            ];
        }

        /**
         * تابع کمکی برای ارسال اعلان (ایمیل یا پیامک)
         *
         * @param string $event نام رویداد (مثلاً 'new_listing')
         * @param int $user_id شناسه کاربر
         * @param int $listing_id شناسه آگهی
         */
        public static function send_notification($event, $user_id, $listing_id = 0) {
            // این تابع در کلاس Directory_Gateways پیاده‌سازی خواهد شد
            // Directory_Gateways::send_notification($event, $user_id, $listing_id);
        }
    }
}
