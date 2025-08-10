<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Directory_Gateways' ) ) {

    class Directory_Gateways {

        public function __construct() {
            add_action( 'init', [ $this, 'handle_payment_verification' ] );
        }

        /**
         * شروع فرآیند پرداخت برای یک تراکنش
         * @param float $amount مبلغ
         * @param int $transaction_id شناسه تراکنش از جدول ما
         * @param string $description توضیحات
         */
        public static function process_payment( $amount, $transaction_id, $description ) {
            $callback_url = add_query_arg( [
                'wpd_action' => 'verify_payment',
                'transaction_id'   => $transaction_id,
            ], site_url( '/' ) );

            $payment_settings = Directory_Main::get_option( 'payments', [] );

            if ( $payment_settings['zarinpal_enable'] ?? false ) {
                self::zarinpal_request( $amount, $callback_url, $description );
            } elseif ( $payment_settings['zibal_enable'] ?? false ) {
                self::zibal_request( $amount, $callback_url, $description );
            } else {
                wp_die( __( 'هیچ درگاه پرداختی فعال نیست. لطفا با مدیر سایت تماس بگیرید.', 'wp-directory' ) );
            }
        }

        private static function zarinpal_request( $amount, $callback_url, $description ) {
            $merchant_id = Directory_Main::get_option( 'payments', [] )['zarinpal_apikey'] ?? '';
            if ( empty( $merchant_id ) ) {
                 wp_die( __( 'اطلاعات درگاه زرین پال تکمیل نشده است.', 'wp-directory' ) );
            }

            $data = [
                'merchant_id'  => $merchant_id,
                'amount'       => $amount,
                'callback_url' => add_query_arg( 'gateway', 'zarinpal', $callback_url ),
                'description'  => $description,
            ];

            $response = wp_remote_post( 'https://api.zarinpal.com/pg/v4/payment/request.json', [ 'body' => json_encode( $data ), 'headers' => [ 'Content-Type' => 'application/json' ] ] );

            if ( is_wp_error( $response ) ) wp_die( __( 'خطا در اتصال به درگاه پرداخت.', 'wp-directory' ) );
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $body['data']['code'] == 100 ) {
                wp_redirect( 'https://www.zarinpal.com/pg/StartPay/' . $body['data']['authority'] );
                exit;
            } else {
                wp_die( 'خطا: ' . ($body['errors']['message'] ?? 'خطای نامشخص از درگاه') );
            }
        }
        
        private static function zibal_request( $amount, $callback_url, $description ) {
            wp_die( __( 'درگاه زیبال در حال حاضر پیاده‌سازی نشده است.', 'wp-directory' ) );
        }

        public function handle_payment_verification() {
            if ( ! isset( $_GET['wpd_action'] ) || $_GET['wpd_action'] !== 'verify_payment' ) return;

            $gateway = sanitize_key( $_GET['gateway'] ?? '' );
            $transaction_id = intval( $_GET['transaction_id'] ?? 0 );
            if (empty($transaction_id)) wp_die('شناسه تراکنش نامعتبر است.');

            if ( $gateway === 'zarinpal' ) {
                $this->zarinpal_verify( $transaction_id );
            } elseif ( $gateway === 'zibal' ) {
                $this->zibal_verify( $transaction_id );
            }
        }

        private function zarinpal_verify( $transaction_id ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wpd_transactions';
            $transaction = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $transaction_id ) );
            if ( ! $transaction || $transaction->status !== 'pending' ) {
                wp_die( __( 'تراکنش یافت نشد یا قبلا پردازش شده است.', 'wp-directory' ) );
            }

            $authority = sanitize_text_field( $_GET['Authority'] ?? '' );
            if ( empty( $_GET['Status'] ) || $_GET['Status'] !== 'OK' ) {
                $wpdb->update( $table_name, [ 'status' => 'failed' ], [ 'id' => $transaction_id ] );
                wp_die( __( 'تراکنش توسط کاربر لغو شد.', 'wp-directory' ) );
            }

            $merchant_id = Directory_Main::get_option( 'payments', [] )['zarinpal_apikey'] ?? '';
            $data = [ 'merchant_id' => $merchant_id, 'amount' => $transaction->amount, 'authority' => $authority ];
            $response = wp_remote_post( 'https://api.zarinpal.com/pg/v4/payment/verify.json', [ 'body' => json_encode( $data ), 'headers' => [ 'Content-Type' => 'application/json' ] ] );
            
            if ( is_wp_error( $response ) ) wp_die( __( 'خطا در تایید تراکنش.', 'wp-directory' ) );
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $body['data']['code'] == 100 || $body['data']['code'] == 101 ) {
                // تراکنش موفق
                $ref_id = $body['data']['ref_id'];
                $wpdb->update( $table_name, [ 'status' => 'completed', 'transaction_id' => $ref_id ], [ 'id' => $transaction_id ] );
                
                // فعال‌سازی آگهی
                self::activate_listing_after_payment( $transaction->listing_id, $transaction->package_id );
                
                // ارسال اعلان
                self::send_notification('listing_approved', $transaction->user_id, ['listing_id' => $transaction->listing_id]);

                // هدایت به داشبورد
                $dashboard_page_id = Directory_Main::get_option('general', [])['dashboard_page'] ?? 0;
                wp_redirect( get_permalink( $dashboard_page_id ) );
                exit;

            } else {
                $wpdb->update( $table_name, [ 'status' => 'failed' ], [ 'id' => $transaction_id ] );
                wp_die( 'خطا در تایید تراکنش: ' . ($body['errors']['message'] ?? 'خطای نامشخص') );
            }
        }

        private function zibal_verify( $transaction_id ) { /* منطق تایید زیبال */ }

        private static function activate_listing_after_payment($listing_id, $package_id) {
            if(empty($listing_id) || empty($package_id)) return;
            
            // انتشار آگهی
            wp_update_post(['ID' => $listing_id, 'post_status' => 'publish']);

            // تنظیم تاریخ انقضا
            $duration = get_post_meta($package_id, '_duration', true);
            if($duration > 0) {
                $expiration_date = date('Y-m-d H:i:s', strtotime("+$duration days"));
                update_post_meta($listing_id, '_wpd_expiration_date', $expiration_date);
            }
        }
        
        public static function send_notification($event, $user_id, $data = []) {
            $user_info = get_userdata($user_id);
            if(!$user_info) return;

            $settings = Directory_Main::get_option('notifications', []);
            $replacements = [
                '{site_name}'   => get_bloginfo('name'),
                '{user_name}'   => $user_info->display_name,
                '{listing_title}' => isset($data['listing_id']) ? get_the_title($data['listing_id']) : '',
            ];

            // ارسال ایمیل
            $email_subject = $settings['email_subject_'.$event] ?? '';
            $email_body = $settings['email_body_'.$event] ?? '';
            if(!empty($email_subject) && !empty($email_body)) {
                $email_subject = str_replace(array_keys($replacements), array_values($replacements), $email_subject);
                $email_body = nl2br(str_replace(array_keys($replacements), array_values($replacements), $email_body));
                wp_mail($user_info->user_email, $email_subject, $email_body, ['Content-Type: text/html; charset=UTF-8']);
            }

            // ارسال پیامک
            $sms_body = $settings['sms_body_'.$event] ?? '';
            $user_phone = get_user_meta($user_id, 'phone_number', true);
            if(!empty($sms_body) && !empty($user_phone)) {
                $sms_body = str_replace(array_keys($replacements), array_values($replacements), $sms_body);
                self::send_sms($user_phone, $sms_body);
            }
        }

        public static function send_sms($to, $message) {
            $sms_settings = Directory_Main::get_option('sms', []);
            if($sms_settings['kavenegar_enable'] ?? false) {
                self::kavenegar_send($to, $message);
            } elseif ($sms_settings['farazsms_enable'] ?? false) {
                self::farazsms_send($to, $message);
            }
        }

        private static function kavenegar_send($to, $message) {
            $api_key = Directory_Main::get_option('sms', [])['kavenegar_apikey'] ?? '';
            if(empty($api_key)) return;
            $url = sprintf('https://api.kavenegar.com/v1/%s/sms/send.json?receptor=%s&message=%s', $api_key, urlencode($to), urlencode($message));
            wp_remote_get($url, ['timeout' => 15]);
        }

        private static function farazsms_send($to, $message) {
            // منطق API فراز اس ام اس در اینجا پیاده‌سازی می‌شود
        }
    }
}
