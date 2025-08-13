<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Directory_Dynamic_Styles' ) ) {

    /**
     * کلاس مسئول تولید و بارگذاری استایل‌های CSS داینامیک
     * بر اساس تنظیمات ظاهری در پنل مدیریت.
     */
    class Directory_Dynamic_Styles {
        
        /**
         * سازنده کلاس که هوک وردپرس را برای تزریق استایل‌ها ثبت می‌کند.
         */
        public function __construct() {
            // استایل‌ها با اولویت بالا (99) اضافه می‌شوند تا پس از استایل اصلی افزونه قرار گیرند.
            add_action( 'wp_enqueue_scripts', [ $this, 'load_dynamic_styles' ], 99 );
        }

        /**
         * تمام تنظیمات ظاهری را از پایگاه داده خوانده، کدهای CSS مربوطه را تولید
         * و به صورت inline به صفحه اضافه می‌کند.
         */
        public function load_dynamic_styles() {
            $options = Directory_Main::get_option('appearance', []);
            $custom_css = '';

            // بخش 1: تنظیمات عمومی و متغیرهای CSS
            //--------------------------------------------------
            $global = $options['global'] ?? [];
            $font_family = $this->get_font_family_by_name($global['main_font'] ?? 'vazir');

            // تعریف متغیرهای CSS اصلی برای استفاده در سراسر افزونه
            $custom_css .= ":root {";
            if (!empty($global['primary_color'])) $custom_css .= "--wpd-primary-color: " . esc_attr($global['primary_color']) . ";";
            if (!empty($global['secondary_color'])) $custom_css .= "--wpd-secondary-color: " . esc_attr($global['secondary_color']) . ";";
            if (!empty($global['text_color'])) $custom_css .= "--wpd-text-color: " . esc_attr($global['text_color']) . ";";
            if (!empty($global['background_color'])) $custom_css .= "--wpd-bg-color: " . esc_attr($global['background_color']) . ";";
            if (!empty($global['border_color'])) $custom_css .= "--wpd-border-color: " . esc_attr($global['border_color']) . ";";
            if (!empty($global['button_border_radius'])) $custom_css .= "--wpd-button-border-radius: " . esc_attr($global['button_border_radius']) . ";";
            if (!empty($global['main_border_radius'])) $custom_css .= "--wpd-main-border-radius: " . esc_attr($global['main_border_radius']) . ";";
            if (!empty($font_family)) $custom_css .= "--wpd-main-font-family: " . esc_attr($font_family) . ";";
            $custom_css .= "}";

            // اعمال اندازه فونت پایه
            if (!empty($global['base_font_size'])) {
                $custom_css .= "body .wpd-container, body .wpd-archive-container { font-size: " . esc_attr($global['base_font_size']) . "; }";
            }

            // بخش 2: آرشیو و آیتم‌ها
            //--------------------------------------------------
            $archive = $options['archive'] ?? [];
            if (!empty($archive['card_bg_color'])) $custom_css .= ".wpd-listing-item { background-color: " . esc_attr($archive['card_bg_color']) . "; }";
            if (!empty($archive['card_border_shadow'])) $custom_css .= ".wpd-listing-item { border: none; box-shadow: " . esc_attr($archive['card_border_shadow']) . "; }";
            if (!empty($archive['title_color'])) $custom_css .= ".wpd-listing-item .wpd-item-content h3 a { color: " . esc_attr($archive['title_color']) . "; }";
            if (!empty($archive['title_font_size'])) $custom_css .= ".wpd-listing-item .wpd-item-content h3 { font-size: " . esc_attr($archive['title_font_size']) . "; }";
            if (!empty($archive['meta_color'])) $custom_css .= ".wpd-listing-item .wpd-item-meta { color: " . esc_attr($archive['meta_color']) . "; }";
            if (!empty($archive['meta_font_size'])) $custom_css .= ".wpd-listing-item .wpd-item-meta { font-size: " . esc_attr($archive['meta_font_size']) . "; }";
            if (!empty($archive['featured_badge_bg'])) $custom_css .= ".wpd-listing-item .wpd-featured-badge { background-color: " . esc_attr($archive['featured_badge_bg']) . "; }";
            if (!empty($archive['featured_badge_color'])) $custom_css .= ".wpd-listing-item .wpd-featured-badge { color: " . esc_attr($archive['featured_badge_color']) . "; }";

            // بخش 3: صفحه تکی آگهی
            //--------------------------------------------------
            $single = $options['single'] ?? [];
            if (!empty($single['main_title_color'])) $custom_css .= ".wpd-single-listing .wpd-listing-header h1 { color: " . esc_attr($single['main_title_color']) . "; }";
            if (!empty($single['main_title_font_size'])) $custom_css .= ".wpd-single-listing .wpd-listing-header h1 { font-size: " . esc_attr($single['main_title_font_size']) . "; }";
            if (!empty($single['section_title_color'])) $custom_css .= ".wpd-single-listing .wpd-custom-fields-group h4 { color: " . esc_attr($single['section_title_color']) . "; }";

            // بخش 4: فرم‌ها و فیلترها
            //--------------------------------------------------
            $forms = $options['forms'] ?? [];
            $form_selectors = '.wpd-form-group input[type="text"], .wpd-form-group input[type="email"], .wpd-form-group input[type="number"], .wpd-form-group input[type="url"], .wpd-form-group input[type="date"], .wpd-form-group input[type="time"], .wpd-form-group select, .wpd-form-group textarea';
            if (!empty($forms['input_bg_color'])) $custom_css .= "$form_selectors { background-color: " . esc_attr($forms['input_bg_color']) . "; }";
            if (!empty($forms['input_text_color'])) $custom_css .= "$form_selectors { color: " . esc_attr($forms['input_text_color']) . "; }";
            if (!empty($forms['input_border_color'])) $custom_css .= "$form_selectors { border-color: " . esc_attr($forms['input_border_color']) . "; }";
            if (!empty($forms['input_focus_border_color'])) $custom_css .= "$form_selectors:focus { border-color: " . esc_attr($forms['input_focus_border_color']) . "; }";
            if (!empty($forms['primary_button_bg_color'])) $custom_css .= ".wpd-button, button[type=\"submit\"] { background-color: " . esc_attr($forms['primary_button_bg_color']) . "; border-color: " . esc_attr($forms['primary_button_bg_color']) . "; }";
            if (!empty($forms['primary_button_text_color'])) $custom_css .= ".wpd-button, button[type=\"submit\"] { color: " . esc_attr($forms['primary_button_text_color']) . "; }";
            
            // بخش 5: داشبورد کاربری
            //--------------------------------------------------
            $dashboard = $options['dashboard'] ?? [];
            if (!empty($dashboard['nav_bg_color'])) $custom_css .= ".wpd-dashboard-nav a { background-color: " . esc_attr($dashboard['nav_bg_color']) . "; }";
            if (!empty($dashboard['nav_text_color'])) $custom_css .= ".wpd-dashboard-nav a { color: " . esc_attr($dashboard['nav_text_color']) . "; }";
            if (!empty($dashboard['nav_active_bg_color'])) $custom_css .= ".wpd-dashboard-nav li.active a, .wpd-dashboard-nav a:hover { background-color: " . esc_attr($dashboard['nav_active_bg_color']) . "; }";
            if (!empty($dashboard['nav_active_text_color'])) $custom_css .= ".wpd-dashboard-nav li.active a, .wpd-dashboard-nav a:hover { color: " . esc_attr($dashboard['nav_active_text_color']) . "; }";

            // بخش 6: CSS سفارشی
            //--------------------------------------------------
            $custom_css_section = $options['custom_css'] ?? [];
            if (!empty($custom_css_section['custom_css'])) {
                // استفاده از wp_strip_all_tags برای امنیت بیشتر و حذف تگ‌های ناخواسته
                $custom_css .= "\n/* --- Custom CSS --- */\n" . wp_strip_all_tags($custom_css_section['custom_css']);
            }

            // اضافه کردن استایل‌های تولید شده به صفحه در صورتی که خالی نباشند
            if (!empty($custom_css)) {
                wp_add_inline_style( 'wpd-main-style', $custom_css );
            }
        }

        /**
         * نام فونت را به رشته font-family در CSS تبدیل می‌کند.
         * @param string $font_name نام انتخابی فونت
         * @return string رشته CSS برای font-family
         */
        private function get_font_family_by_name($font_name) {
            switch ($font_name) {
                case 'vazir':
                    return '"Vazirmatn", sans-serif';
                case 'iransans':
                    return '"IRANSans", sans-serif';
                case 'dana':
                    return '"Dana", sans-serif';
                case 'custom':
                    // در این حالت، فرض بر این است که فونت توسط قالب یا افزونه دیگری بارگذاری شده است.
                    return 'inherit'; // از والد ارث‌بری می‌کند
                default:
                    return '"Vazirmatn", sans-serif';
            }
        }
    }
}
