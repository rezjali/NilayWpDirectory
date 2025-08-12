<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Directory_Dynamic_Styles' ) ) {

    class Directory_Dynamic_Styles {
        
        public function __construct() {
            add_action( 'wp_enqueue_scripts', [ $this, 'load_dynamic_styles' ], 99 );
        }

        public function load_dynamic_styles() {
            // Get plugin's custom appearance settings.
            $options = Directory_Main::get_option('appearance', []);

            // Fallback to default values if options are not set.
            $defaults = Directory_Main::get_default_terms();
            
            $primary_color     = $options['primary_color'] ?? $defaults['appearance_primary_color'];
            $secondary_color   = $options['secondary_color'] ?? $defaults['appearance_secondary_color'];
            $text_color        = $options['text_color'] ?? $defaults['appearance_text_color'];
            $background_color  = $options['background_color'] ?? $defaults['appearance_background_color'];
            $border_color      = $options['border_color'] ?? $defaults['appearance_border_color'];
            $button_radius     = $options['button_radius_value'] ?? $defaults['appearance_button_radius'];
            $border_radius     = $options['border_radius_value'] ?? $defaults['appearance_button_radius'];
            $main_font         = $options['main_font'] ?? $defaults['appearance_main_font'];
            $google_font_url   = $options['google_font_url'] ?? '';
            $google_font_family = $options['google_font_family'] ?? '';

            $custom_css = '';

            // Load Google Fonts if selected
            if ($main_font === 'custom' && !empty($google_font_url)) {
                wp_enqueue_style('wpd-google-font', esc_url($google_font_url), [], null);
            }
            // Load custom Iranian fonts from assets folder
            elseif ($main_font === 'vazir') {
                 wp_enqueue_style('wpd-vazir-font', WPD_ASSETS_URL . 'fonts/vazirmatn/Vazirmatn.css', [], '33.0.3');
            }
            // Add more custom fonts as needed

            $font_family = $this->get_font_family_by_name($main_font, $google_font_family);

            $custom_css .= "
            :root {
                --wpd-primary-color: " . esc_attr($primary_color) . ";
                --wpd-secondary-color: " . esc_attr($secondary_color) . ";
                --wpd-text-color: " . esc_attr($text_color) . ";
                --wpd-bg-color: " . esc_attr($background_color) . ";
                --wpd-border-color: " . esc_attr($border_color) . ";
                --wpd-button-border-radius: " . esc_attr($button_radius) . ";
                --wpd-main-border-radius: " . esc_attr($border_radius) . ";
                --wpd-main-font-family: " . esc_attr($font_family) . ";
            }

            body.rtl, body.rtl p, body.rtl h1, body.rtl h2, body.rtl h3, body.rtl h4, body.rtl h5, body.rtl h6 {
                font-family: var(--wpd-main-font-family), sans-serif;
            }

            .wpd-container,
            .wpd-archive-container {
                direction: rtl;
                text-align: right;
            }
            .wpd-form-group input,
            .wpd-form-group select,
            .wpd-form-group textarea,
            .wpd-listing-item,
            .wpd-alert,
            .wpd-button {
                 border-radius: var(--wpd-main-border-radius);
            }

            .wpd-button,
            button[type=\"submit\"],
            .wpd-dashboard-nav li.active a,
            .wpd-dashboard-nav a:hover,
            .page-numbers.current,
            .page-numbers:hover {
                border-radius: " . esc_attr($button_radius) . ";
            }
            
            .wpd-dashboard-nav li.active a,
            .wpd-dashboard-nav a:hover,
            .page-numbers.current,
            .page-numbers:hover {
                background-color: var(--wpd-primary-color);
                border-color: var(--wpd-primary-color);
            }
            
            .wpd-item-content h3 a,
            .wpd-listing-meta span .dashicons,
            .wpd-payment-summary .wpd-summary-row.total,
            .page-numbers {
                 color: var(--wpd-primary-color);
            }

            .wpd-button:hover,
            button[type=\"submit\"]:hover {
                background-color: var(--wpd-primary-color);
                border-color: var(--wpd-primary-color);
            }
            
            .wpd-listing-header h1 {
                color: var(--wpd-primary-color);
            }
            
            .wpd-dashboard-nav a {
                color: var(--wpd-text-color);
            }
            
            .wpd-container {
                background-color: var(--wpd-bg-color);
                border-color: var(--wpd-border-color);
            }
            
            ";
            
            // Add custom CSS for general elements in the frontend
            wp_add_inline_style( 'wpd-main-style', $custom_css );
        }

        private function get_font_family_by_name($font_name, $custom_family = '') {
            switch ($font_name) {
                case 'vazir':
                    return '"Vazirmatn", sans-serif';
                case 'iransans':
                    return '"IRANSans", sans-serif';
                case 'dana':
                    return '"Dana", sans-serif';
                case 'custom':
                    return !empty($custom_family) ? '"' . esc_attr($custom_family) . '", sans-serif' : 'sans-serif';
                default:
                    return 'sans-serif';
            }
        }
    }
}
