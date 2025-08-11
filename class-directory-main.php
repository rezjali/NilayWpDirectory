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
            add_action( 'wpd_daily_scheduled_events', [ $this, 'run_daily_events' ] );
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
         * @param string $section_name نام بخش (تب) تنظیمات
         * @param array $defaults مقادیر پیش‌فرض برای آن بخش
         * @return array
         */
        public static function get_option( $section_name, $defaults = [] ) {
            $options = get_option( 'wpd_settings', [] );
            $section_options = isset( $options[ $section_name ] ) ? $options[ $section_name ] : [];
            return wp_parse_args($section_options, $defaults);
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
         * بررسی می‌کند که آیا باید از تقویم شمسی استفاده شود یا خیر
         * @return bool
         */
        public static function is_shamsi_calendar_enabled() {
            $general_settings = self::get_option('general', ['enable_shamsi_calendar' => 0]);
            $is_enabled_by_setting = (bool) $general_settings['enable_shamsi_calendar'];
            $is_parsidate_active = function_exists('parsidate');

            return $is_enabled_by_setting && $is_parsidate_active;
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
                'publish' => __( 'منتشر شده', 'wp-directory' ),
                'pending' => __( 'در انتظار تایید', 'wp-directory' ),
                'expired' => __( 'منقضی شده', 'wp-directory' ),
                'draft' => __( 'پیش‌نویس', 'wp-directory' ),
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
         * لیست رویدادهای اعلان‌ها
         * @return array
         */
        public static function get_notification_events() {
            return [
                'new_user' => ['label' => 'ثبت‌نام کاربر جدید', 'vars' => ['{site_name}', '{user_name}']],
                'new_listing' => ['label' => 'ثبت آگهی جدید (در انتظار تایید)', 'vars' => ['{site_name}', '{user_name}', '{listing_title}']],
                'listing_approved' => ['label' => 'تایید آگهی', 'vars' => ['{site_name}', '{user_name}', '{listing_title}', '{listing_url}']],
                'listing_rejected' => ['label' => 'رد شدن آگهی', 'vars' => ['{site_name}', '{user_name}', '{listing_title}']],
                'listing_expired' => ['label' => 'انقضای آگهی', 'vars' => ['{site_name}', '{user_name}', '{listing_title}']],
                'listing_near_expiration' => ['label' => 'نزدیک شدن به انقضای آگهی', 'vars' => ['{site_name}', '{user_name}', '{listing_title}', '{expiration_date}']]
            ];
        }

        /**
         * تابع مرکزی برای ارسال اعلان‌ها
         * @param string $event نام رویداد
         * @param array $args آرگومان‌ها (user_id, listing_id, etc.)
         */
        public static function trigger_notification($event, $args = []) {
            $user_id = $args['user_id'] ?? 0;
            $listing_id = $args['listing_id'] ?? 0;
            if (empty($user_id)) return;

            $user_info = get_userdata($user_id);
            if (!$user_info) return;

            // دریافت تنظیمات سراسری
            $global_settings = self::get_option('notifications', []);

            // دریافت تنظیمات اختصاصی نوع آگهی
            $type_settings = [];
            if ($listing_id) {
                $listing_type_id = get_post_meta($listing_id, '_wpd_listing_type', true);
                if ($listing_type_id) {
                    $type_settings = get_post_meta($listing_type_id, '_notification_settings', true);
                }
            }
            
            // بررسی فعال بودن اعلان
            $is_email_enabled = !empty($global_settings["email_enable_{$event}"]) && !empty($type_settings[$event]['email']);
            $is_sms_enabled = !empty($global_settings["sms_enable_{$event}"]) && !empty($type_settings[$event]['sms']);

            if (!$is_email_enabled && !$is_sms_enabled) {
                return;
            }

            // آماده‌سازی متغیرهای پویا
            $replacements = [
                '{site_name}'   => get_bloginfo('name'),
                '{user_name}'   => $user_info->display_name,
                '{listing_title}' => $listing_id ? get_the_title($listing_id) : '',
                '{listing_url}' => $listing_id ? get_permalink($listing_id) : '',
                '{expiration_date}' => $args['expiration_date'] ?? '',
            ];

            // ارسال ایمیل
            if ($is_email_enabled) {
                $subject = $global_settings["email_subject_{$event}"] ?? '';
                $body = $global_settings["email_body_{$event}"] ?? '';
                if (!empty($subject) && !empty($body)) {
                    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
                    $body = wpautop(str_replace(array_keys($replacements), array_values($replacements), $body));
                    Directory_Gateways::send_email($user_info->user_email, $subject, $body);
                }
            }

            // ارسال پیامک
            if ($is_sms_enabled) {
                $pattern_code = $global_settings["sms_pattern_{$event}"] ?? '';
                $user_phone = get_user_meta($user_id, 'phone_number', true);
                if (!empty($pattern_code) && !empty($user_phone)) {
                    // متغیرها باید به ترتیب تعریف شده در پترن باشند
                    $sms_vars = [];
                    $event_vars = self::get_notification_events()[$event]['vars'];
                    foreach($event_vars as $var) {
                        $sms_vars[] = $replacements[$var] ?? '';
                    }
                    Directory_Gateways::send_sms($user_phone, $pattern_code, $sms_vars);
                }
            }
        }

        /**
         * اجرای رویدادهای روزانه (Cron Job)
         */
        public function run_daily_events() {
            $today = date('Y-m-d H:i:s');

            // 1. یافتن آگهی‌هایی که به زودی منقضی می‌شوند (مثلا ۳ روز دیگر)
            $near_expiration_date = date('Y-m-d', strtotime('+3 days'));
            $near_listings = get_posts([
                'post_type' => 'wpd_listing',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_wpd_expiration_date',
                        'value' => $near_expiration_date . ' 00:00:00',
                        'compare' => '>=',
                        'type' => 'DATETIME'
                    ],
                    [
                        'key' => '_wpd_expiration_date',
                        'value' => $near_expiration_date . ' 23:59:59',
                        'compare' => '<=',
                        'type' => 'DATETIME'
                    ],
                ]
            ]);

            foreach ($near_listings as $listing) {
                self::trigger_notification('listing_near_expiration', [
                    'user_id' => $listing->post_author,
                    'listing_id' => $listing->ID,
                    'expiration_date' => date_i18n('Y/m/d', strtotime(get_post_meta($listing->ID, '_wpd_expiration_date', true)))
                ]);
            }

            // 2. یافتن و منقضی کردن آگهی‌هایی که تاریخ انقضایشان گذشته است
            $expired_listings = get_posts([
                'post_type' => 'wpd_listing',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_wpd_expiration_date',
                        'compare' => 'EXISTS'
                    ],
                    [
                        'key' => '_wpd_expiration_date',
                        'value' => $today,
                        'compare' => '<',
                        'type' => 'DATETIME'
                    ]
                ]
            ]);

            foreach ($expired_listings as $listing) {
                wp_update_post(['ID' => $listing->ID, 'post_status' => 'expired']);
                self::trigger_notification('listing_expired', [
                    'user_id' => $listing->post_author,
                    'listing_id' => $listing->ID
                ]);
            }

            // 3. یافتن و منقضی کردن ارتقاهای آگهی
            $upgrade_meta_keys = [
                '_wpd_is_featured' => '_wpd_featured_expires_on',
                '_wpd_is_urgent' => '_wpd_urgent_expires_on',
                '_wpd_is_top_of_category' => '_wpd_top_of_category_expires_on',
            ];

            foreach ($upgrade_meta_keys as $status_key => $expiry_key) {
                $expired_upgrades = get_posts([
                    'post_type' => 'wpd_listing',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        'relation' => 'AND',
                        [
                            'key' => $status_key,
                            'value' => '1',
                            'compare' => '='
                        ],
                        [
                            'key' => $expiry_key,
                            'value' => $today,
                            'compare' => '<',
                            'type' => 'DATETIME'
                        ]
                    ]
                ]);

                foreach ($expired_upgrades as $listing) {
                    delete_post_meta($listing->ID, $status_key);
                    delete_post_meta($listing->ID, $expiry_key);
                }
            }
        }
    }
}
