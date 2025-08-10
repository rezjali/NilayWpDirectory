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
         * @param int $listing_id شناسه آگهی
         */
        public static function process_payment( $amount, $transaction_id, $description, $listing_id ) {
            $dashboard_page_id = Directory_Main::get_option('general', [])['dashboard_page'] ?? 0;
            $redirect_url = $dashboard_page_id ? get_permalink($dashboard_page_id) : home_url();

            $callback_url = add_query_arg( [
                'wpd_action' => 'verify_payment',
                'transaction_id'   => $transaction_id,
            ], site_url( '/' ) );

            $payment_settings = Directory_Main::get_option( 'payments', [] );

            if ( !empty($payment_settings['zarinpal_enable']) ) {
                self::zarinpal_request( $amount, $callback_url, $description );
            } elseif ( !empty($payment_settings['zibal_enable']) ) {
                self::zibal_request( $amount, $callback_url, $description, $transaction_id );
            } else {
                // اگر هیچ درگاهی فعال نبود، خطا بده
                wp_redirect( add_query_arg('wpd_error', 'no_gateway', $redirect_url) );
                exit;
            }
        }

        private static function zarinpal_request( $amount, $callback_url, $description ) {
            $merchant_id = Directory_Main::get_option( 'payments', [] )['zarinpal_apikey'] ?? '';
            if ( empty( $merchant_id ) ) {
                 self::redirect_with_error('zarinpal_not_configured');
            }

            $data = [
                'merchant_id'  => $merchant_id,
                'amount'       => $amount,
                'callback_url' => add_query_arg( 'gateway', 'zarinpal', $callback_url ),
                'description'  => $description,
            ];

            $response = wp_remote_post( 'https://api.zarinpal.com/pg/v4/payment/request.json', [ 'body' => json_encode( $data ), 'headers' => [ 'Content-Type' => 'application/json' ] ] );

            if ( is_wp_error( $response ) ) {
                self::redirect_with_error('connection_failed');
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset($body['data']['code']) && $body['data']['code'] == 100 ) {
                wp_redirect( 'https://www.zarinpal.com/pg/StartPay/' . $body['data']['authority'] );
                exit;
            } else {
                $error_message = $body['errors']['message'] ?? 'خطای نامشخص از درگاه';
                self::redirect_with_error(urlencode($error_message));
            }
        }
        
        private static function zibal_request( $amount, $callback_url, $description, $transaction_id ) {
            $merchant_id = Directory_Main::get_option( 'payments', [] )['zibal_apikey'] ?? '';
            if ( empty( $merchant_id ) ) {
                 self::redirect_with_error('zibal_not_configured');
            }

            $data = [
                'merchant'     => $merchant_id,
                'amount'       => $amount,
                'callbackUrl'  => add_query_arg( 'gateway', 'zibal', $callback_url ),
                'description'  => $description,
                'orderId'      => $transaction_id,
            ];

            $response = wp_remote_post( 'https://gateway.zibal.ir/v1/request', [ 'body' => json_encode( $data ), 'headers' => [ 'Content-Type' => 'application/json' ] ] );

            if ( is_wp_error( $response ) ) {
                self::redirect_with_error('connection_failed');
            }
            
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset($body['result']) && $body['result'] == 100 ) {
                wp_redirect( 'https://gateway.zibal.ir/start/' . $body['trackId'] );
                exit;
            } else {
                $error_message = $body['message'] ?? 'خطای نامشخص از درگاه زیبال';
                self::redirect_with_error(urlencode($error_message));
            }
        }

        public function handle_payment_verification() {
            if ( ! isset( $_GET['wpd_action'] ) || $_GET['wpd_action'] !== 'verify_payment' ) return;

            $gateway = sanitize_key( $_GET['gateway'] ?? '' );
            $transaction_id = intval( $_GET['transaction_id'] ?? 0 );
            if (empty($transaction_id)) {
                self::redirect_with_error('invalid_transaction');
            }

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
                self::redirect_with_error('transaction_not_found');
            }

            $authority = sanitize_text_field( $_GET['Authority'] ?? '' );
            if ( empty( $_GET['Status'] ) || $_GET['Status'] !== 'OK' ) {
                $wpdb->update( $table_name, [ 'status' => 'failed' ], [ 'id' => $transaction_id ] );
                self::redirect_with_error('payment_cancelled');
            }

            $merchant_id = Directory_Main::get_option( 'payments', [] )['zarinpal_apikey'] ?? '';
            $data = [ 'merchant_id' => $merchant_id, 'amount' => $transaction->amount, 'authority' => $authority ];
            $response = wp_remote_post( 'https://api.zarinpal.com/pg/v4/payment/verify.json', [ 'body' => json_encode( $data ), 'headers' => [ 'Content-Type' => 'application/json' ] ] );
            
            if ( is_wp_error( $response ) ) {
                self::redirect_with_error('verify_failed');
            }
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset($body['data']['code']) && ($body['data']['code'] == 100 || $body['data']['code'] == 101) ) {
                $ref_id = $body['data']['ref_id'];
                $wpdb->update( $table_name, [ 'status' => 'completed', 'transaction_id' => $ref_id ], [ 'id' => $transaction_id ] );
                
                self::activate_listing_after_payment( $transaction->listing_id, $transaction->package_id );
                
                Directory_Main::trigger_notification('listing_approved', ['user_id' => $transaction->user_id, 'listing_id' => $transaction->listing_id]);

                self::redirect_with_success('payment_successful');
            } else {
                $wpdb->update( $table_name, [ 'status' => 'failed' ], [ 'id' => $transaction_id ] );
                $error_message = $body['errors']['message'] ?? 'خطای نامشخص در تایید تراکنش';
                self::redirect_with_error(urlencode($error_message));
            }
        }

        private function zibal_verify( $transaction_id ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wpd_transactions';
            $transaction = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $transaction_id ) );
            if ( ! $transaction || $transaction->status !== 'pending' ) {
                self::redirect_with_error('transaction_not_found');
            }

            if ( empty($_GET['success']) || $_GET['success'] != 1 ) {
                $wpdb->update( $table_name, [ 'status' => 'failed' ], [ 'id' => $transaction_id ] );
                self::redirect_with_error('payment_cancelled');
            }

            $merchant_id = Directory_Main::get_option( 'payments', [] )['zibal_apikey'] ?? '';
            $trackId = intval($_GET['trackId']);
            $data = [ 'merchant' => $merchant_id, 'trackId' => $trackId ];
            $response = wp_remote_post( 'https://gateway.zibal.ir/v1/verify', [ 'body' => json_encode( $data ), 'headers' => [ 'Content-Type' => 'application/json' ] ] );

            if ( is_wp_error( $response ) ) {
                self::redirect_with_error('verify_failed');
            }
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset($body['result']) && $body['result'] == 100 ) {
                $ref_id = $body['refNumber'];
                $wpdb->update( $table_name, [ 'status' => 'completed', 'transaction_id' => $ref_id ], [ 'id' => $transaction_id ] );
                
                self::activate_listing_after_payment( $transaction->listing_id, $transaction->package_id );
                
                Directory_Main::trigger_notification('listing_approved', ['user_id' => $transaction->user_id, 'listing_id' => $transaction->listing_id]);

                self::redirect_with_success('payment_successful');
            } else {
                $wpdb->update( $table_name, [ 'status' => 'failed' ], [ 'id' => $transaction_id ] );
                $error_message = $body['message'] ?? 'خطای نامشخص در تایید تراکنش';
                self::redirect_with_error(urlencode($error_message));
            }
        }

        private static function activate_listing_after_payment($listing_id, $package_id) {
            if(empty($listing_id)) return;
            
            wp_update_post(['ID' => $listing_id, 'post_status' => 'publish']);

            $duration = get_post_meta($package_id, '_duration', true);
            if($duration > 0) {
                $expiration_date = date('Y-m-d H:i:s', strtotime("+$duration days"));
                update_post_meta($listing_id, '_wpd_expiration_date', $expiration_date);
            } else {
                delete_post_meta($listing_id, '_wpd_expiration_date');
            }
        }
        
        public static function send_email($to, $subject, $body) {
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($to, $subject, $body, $headers);
        }

        public static function send_sms($to, $pattern_code, $vars = []) {
            $sms_settings = Directory_Main::get_option('sms', []);
            $provider = $sms_settings['provider'] ?? '';
            
            if ($provider === 'kavenegar') {
                self::kavenegar_send($to, $pattern_code, $vars);
            } elseif ($provider === 'farazsms') {
                self::farazsms_send($to, $pattern_code, $vars);
            }
        }

        private static function kavenegar_send($to, $pattern_code, $vars) {
            $api_key = Directory_Main::get_option('sms', [])['api_key'] ?? '';
            if(empty($api_key)) return;
            
            // کاوه نگار از ارسال متن خام در پترن پشتیبانی می‌کند
            $template = $pattern_code; // در اینجا کد الگو همان متن است
            $message = vsprintf($template, $vars);
            
            $url = sprintf('https://api.kavenegar.com/v1/%s/sms/send.json?receptor=%s&message=%s', $api_key, urlencode($to), urlencode($message));
            wp_remote_get($url, ['timeout' => 15]);
        }

        private static function farazsms_send($to, $pattern_code, $vars) {
            $api_key = Directory_Main::get_option('sms', [])['api_key'] ?? '';
            $sender = Directory_Main::get_option('sms', [])['sender_number'] ?? '';
            if(empty($api_key) || empty($sender) || empty($pattern_code)) return;

            $input_data = [];
            foreach($vars as $index => $var) {
                // فراز اس ام اس متغیرها را با نام‌های از پیش تعریف شده می‌شناسد
                $input_data["var".($index+1)] = $var;
            }

            $url = "https://ippanel.com/patterns/api/v1/send";
            $body = [
                'pattern_code' => $pattern_code,
                'originator' => $sender,
                'recipient' => $to,
                'values' => $input_data,
            ];

            wp_remote_post($url, [
                'method' => 'POST',
                'timeout' => 15,
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'AccessKey ' . $api_key],
                'body' => json_encode($body)
            ]);
        }

        private static function redirect_with_error($error_code) {
            $dashboard_page_id = Directory_Main::get_option('general', [])['dashboard_page'] ?? 0;
            $redirect_url = $dashboard_page_id ? get_permalink($dashboard_page_id) : home_url();
            wp_redirect(add_query_arg('wpd_error', $error_code, $redirect_url));
            exit;
        }

        private static function redirect_with_success($success_code) {
            $dashboard_page_id = Directory_Main::get_option('general', [])['dashboard_page'] ?? 0;
            $redirect_url = $dashboard_page_id ? get_permalink($dashboard_page_id) : home_url();
            wp_redirect(add_query_arg('wpd_success', $success_code, $redirect_url));
            exit;
        }
    }
}
