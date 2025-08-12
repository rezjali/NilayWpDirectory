<?php
/**
 * Plugin Name:       نیلای - افزونه دایرکتوری و مشاغل
 * Plugin URI:        https://your-website.com/
 * Description:       یک افزونه کامل و مستقل برای ساخت دایرکتوری و سایت ثبت آگهی با امکانات پیشرفته بومی‌سازی شده برای ایران.
 * Version:           2.0.0
 * Author:            نام شما
 * Author URI:        https://your-website.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-directory
 * Domain Path:       /languages
 */

// جلوگیری از دسترسی مستقیم به فایل برای امنیت
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// جلوگیری از تعریف مجدد کلاس در صورت وجود
if ( ! class_exists( 'Wp_Directory_Main_Loader' ) ) {

    /**
     * کلاس اصلی افزونه که تمام بخش‌ها را مدیریت و راه‌اندازی می‌کند.
     */
    final class Wp_Directory_Main_Loader {

        const VERSION = '2.0.0';
        private static $instance = null;

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->define_constants();
            $this->includes();
            $this->init_hooks();
        }

        private function define_constants() {
            define( 'WPD_PLUGIN_VERSION', self::VERSION );
            define( 'WPD_PLUGIN_FILE', __FILE__ );
            define( 'WPD_PLUGIN_PATH', plugin_dir_path( WPD_PLUGIN_FILE ) );
            define( 'WPD_PLUGIN_URL', plugin_dir_url( WPD_PLUGIN_FILE ) );
            define( 'WPD_ASSETS_PATH', WPD_PLUGIN_PATH . 'assets/' );
            define( 'WPD_ASSETS_URL', WPD_PLUGIN_URL . 'assets/' );
        }

        private function includes() {
            require_once WPD_PLUGIN_PATH . 'class-directory-main.php';
            require_once WPD_PLUGIN_PATH . 'class-directory-post-types.php';
            require_once WPD_PLUGIN_PATH . 'class-directory-admin.php';
            require_once WPD_PLUGIN_PATH . 'class-directory-frontend.php';
            require_once WPD_PLUGIN_PATH . 'class-directory-gateways.php';
            require_once WPD_PLUGIN_PATH . 'class-directory-user.php';
        }

        private function init_hooks() {
            add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
            register_activation_hook( WPD_PLUGIN_FILE, [ $this, 'activate' ] );
            register_deactivation_hook( WPD_PLUGIN_FILE, [ $this, 'deactivate' ] );

            // START OF CHANGE: هوک برای نمایش اعلان راه‌اندازی
            add_action( 'admin_notices', [ $this, 'show_setup_notice' ] );
            add_action( 'admin_init', [ $this, 'dismiss_setup_notice' ] );
            // END OF CHANGE
        }

        public function init_plugin() {
            Directory_Main::instance();
        }

        public function activate() {
            $this->includes();
            self::create_transactions_table();
            Directory_User::create_roles();
            
            // START OF CHANGE: ثبت رویداد زمان‌بندی شده روزانه
            if ( ! wp_next_scheduled( 'wpd_daily_scheduled_events' ) ) {
                wp_schedule_event( time(), 'daily', 'wpd_daily_scheduled_events' );
            }
            // END OF CHANGE

            // آپشن برای نمایش اعلان راه‌اندازی
            add_option('wpd_show_setup_notice', true);

            flush_rewrite_rules();
        }

        public function deactivate() {
            $this->includes();
            Directory_User::remove_roles();

            // START OF CHANGE: حذف رویداد زمان‌بندی شده
            wp_clear_scheduled_hook( 'wpd_daily_scheduled_events' );
            // END OF CHANGE

            flush_rewrite_rules();
        }

        public static function create_transactions_table() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wpd_transactions';
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                listing_id bigint(20) UNSIGNED DEFAULT 0,
                package_id bigint(20) UNSIGNED NOT NULL,
                amount decimal(10, 2) NOT NULL,
                gateway varchar(50) NOT NULL,
                transaction_id varchar(100) DEFAULT '' NOT NULL,
                status varchar(20) NOT NULL,
                created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }

        // START OF CHANGE: توابع جدید برای مدیریت اعلان راه‌اندازی
        public function show_setup_notice() {
            if ( get_option( 'wpd_show_setup_notice' ) ) {
                $settings_url = admin_url( 'admin.php?page=wpd-main-menu' );
                $dismiss_url = add_query_arg( 'wpd_dismiss_notice', 'setup' );
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php _e( 'از نصب افزونه نیلای دایرکتوری متشکریم! برای شروع، لطفا به', 'wp-directory' ); ?>
                        <a href="<?php echo esc_url( $settings_url ); ?>"><strong><?php _e( 'صفحه تنظیمات', 'wp-directory' ); ?></strong></a>
                        <?php _e( 'بروید و برگه‌های اصلی افزونه را مشخص کنید.', 'wp-directory' ); ?>
                        <a href="<?php echo esc_url( $dismiss_url ); ?>" style="text-decoration: none; margin-right: 10px;"><?php _e( '(بستن این پیام)', 'wp-directory' ); ?></a>
                    </p>
                </div>
                <?php
            }
        }

        public function dismiss_setup_notice() {
            if ( isset( $_GET['wpd_dismiss_notice'] ) && $_GET['wpd_dismiss_notice'] === 'setup' ) {
                delete_option( 'wpd_show_setup_notice' );
            }
        }
        // END OF CHANGE
    }
}

function wp_directory_run() {
    return Wp_Directory_Main_Loader::instance();
}

// اجرای افزونه
wp_directory_run();
