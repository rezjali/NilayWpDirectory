<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// برای استفاده از کلاس WP_List_Table
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * کلاس لیست تراکنش‌ها برای نمایش در پنل مدیریت
 */
class WPD_Transactions_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => __( 'تراکنش', 'wp-directory' ),
            'plural'   => __( 'تراکنش‌ها', 'wp-directory' ),
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'user_id'        => __( 'کاربر', 'wp-directory' ),
            'amount'         => __( 'مبلغ', 'wp-directory' ) . ' (' . Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'] . ')',
            'gateway'        => __( 'درگاه', 'wp-directory' ),
            'transaction_id' => __( 'کد رهگیری', 'wp-directory' ),
            'status'         => __( 'وضعیت', 'wp-directory' ),
            'created_at'     => __( 'تاریخ', 'wp-directory' ),
        ];
    }
    
    protected function extra_tablenav( $which ) {
        if ( $which == "top" ){
            $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
            ?>
            <div class="alignleft actions">
                <select name="status">
                    <option value=""><?php _e('همه وضعیت‌ها', 'wp-directory'); ?></option>
                    <option value="completed" <?php selected($status, 'completed'); ?>><?php _e('موفق', 'wp-directory'); ?></option>
                    <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('در انتظار', 'wp-directory'); ?></option>
                    <option value="failed" <?php selected($status, 'failed'); ?>><?php _e('ناموفق', 'wp-directory'); ?></option>
                </select>
                <?php submit_button( __( 'فیلتر' ), 'button', 'filter_action', false, [ 'id' => 'post-query-submit' ] ); ?>
            </div>
            <?php
        }
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpd_transactions';
        $per_page = 20;

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
        
        $where = [];
        if(isset($_GET['status']) && !empty($_GET['status'])) {
            $where[] = $wpdb->prepare("status = %s", sanitize_key($_GET['status']));
        }
        if(isset($_GET['s']) && !empty($_GET['s'])) {
            $where[] = $wpdb->prepare("transaction_id LIKE %s", '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%');
        }
        $where_clause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" . $where_clause );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page
        ] );
        
        $current_page = $this->get_pagenum();
        $orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? esc_sql( $_REQUEST['orderby'] ) : 'created_at';
        $order = ( ! empty( $_REQUEST['order'] ) ) ? esc_sql( $_REQUEST['order'] ) : 'DESC';
        $offset = ( $current_page - 1 ) * $per_page;

        $this->items = $wpdb->get_results(
            "SELECT * FROM $table_name" . $where_clause . " ORDER BY $orderby $order LIMIT $per_page OFFSET $offset", ARRAY_A
        );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'amount': return number_format( $item[ $column_name ] );
            case 'user_id':
                $user = get_userdata( $item[ $column_name ] );
                return $user ? $user->display_name : __( 'کاربر حذف شده', 'wp-directory' );
            case 'status':
                $status = $item[$column_name];
                $status_label = '';
                switch ($status) {
                    case 'completed':
                        $status_label = __('موفق', 'wp-directory');
                        break;
                    case 'pending':
                        $status_label = __('در انتظار', 'wp-directory');
                        break;
                    case 'failed':
                        $status_label = __('ناموفق', 'wp-directory');
                        break;
                    default:
                        $status_label = ucfirst($status);
                        break;
                }
                $status_class = sanitize_key($status);
                return '<span class="wpd-status-' . $status_class . '">' . esc_html($status_label) . '</span>';
            case 'created_at': return date_i18n('Y/m/d H:i', strtotime($item[ $column_name ]));
            default: return esc_html( $item[ $column_name ] );
        }
    }
    
    public function column_cb( $item ) { return sprintf( '<input type="checkbox" name="transaction[]" value="%s" />', $item['id'] ); }
    public function get_sortable_columns() { return [ 'amount' => [ 'amount', false ], 'status' => [ 'status', false ], 'created_at' => [ 'created_at', true ] ]; }
}


class Directory_Admin {

    private $transactions_list_table;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_ajax_wpd_verify_service', [ $this, 'ajax_verify_service' ] );
    }

    public function add_admin_menu() {
        add_menu_page( 'نیلای دایرکتوری', 'نیلای دایرکتوری', 'manage_options', 'wpd-main-menu', [ $this, 'render_settings_page' ], 'dashicons-location-alt', 20 );
        add_submenu_page( 'wpd-main-menu', __( 'تنظیمات', 'wp-directory' ), __( 'تنظیمات', 'wp-directory' ), 'manage_options', 'wpd-main-menu' );
        add_submenu_page( 'wpd-main-menu', __( 'بسته‌های عضویت', 'wp-directory' ), __( 'بسته‌های عضویت', 'wp-directory' ), 'manage_options', 'edit.php?post_type=wpd_package' );
        add_submenu_page( 'wpd-main-menu', __( 'بسته‌های ارتقا', 'wp-directory' ), __( 'بسته‌های ارتقا', 'wp-directory' ), 'manage_options', 'edit.php?post_type=wpd_upgrade' );
        $hook = add_submenu_page( 'wpd-main-menu', __( 'تراکنش‌ها', 'wp-directory' ), __( 'تراکنش‌ها', 'wp-directory' ), 'manage_options', 'wpd-transactions', [ $this, 'render_transactions_page' ] );
        add_action( "load-$hook", [ $this, 'screen_options' ] );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( strpos($hook, 'wpd-transactions') !== false ) {
            $css = '.wpd-status-completed { color: green; } .wpd-status-failed { color: red; } .wpd-status-pending { color: orange; }';
            wp_add_inline_style('common', $css);
        }
        
        if ( $hook !== 'toplevel_page_wpd-main-menu' ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        
        wp_add_inline_script( 'wp-color-picker', $this->get_settings_page_js() );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap wpd-settings-wrap">
            <h1><?php _e( 'تنظیمات افزونه نیلای دایرکتوری', 'wp-directory' ); ?></h1>
            
            <h2 class="nav-tab-wrapper wpd-nav-tab-wrapper">
                <a href="#general" data-tab="general" class="nav-tab"><?php _e( 'عمومی', 'wp-directory' ); ?></a>
                <a href="#payments" data-tab="payments" class="nav-tab"><?php _e( 'پرداخت', 'wp-directory' ); ?></a>
                <a href="#sms" data-tab="sms" class="nav-tab"><?php _e( 'پیامک', 'wp-directory' ); ?></a>
                <a href="#notifications" data-tab="notifications" class="nav-tab"><?php _e( 'اعلان‌ها', 'wp-directory' ); ?></a>
                <a href="#terminology" data-tab="terminology" class="nav-tab"><?php _e( 'مدیریت اصطلاحات', 'wp-directory' ); ?></a>
                <a href="#appearance" data-tab="appearance" class="nav-tab"><?php _e( 'مدیریت ظاهری', 'wp-directory' ); ?></a>
                <a href="#help" data-tab="help" class="nav-tab"><?php _e( 'راهنما', 'wp-directory' ); ?></a>
            </h2>

            <form action="options.php" method="post">
                <?php settings_fields( 'wpd_settings_group' ); ?>
                <div id="tab-general" class="wpd-settings-tab"><table class="form-table"><?php do_settings_sections( 'wpd_settings_general' ); ?></table></div>
                <div id="tab-payments" class="wpd-settings-tab"><?php do_settings_sections( 'wpd_settings_payments' ); ?></div>
                <div id="tab-sms" class="wpd-settings-tab"><?php do_settings_sections( 'wpd_settings_sms' ); ?></div>
                <div id="tab-notifications" class="wpd-settings-tab"><?php do_settings_sections( 'wpd_settings_notifications' ); ?></div>
                <div id="tab-terminology" class="wpd-settings-tab"><table class="form-table"><?php do_settings_sections( 'wpd_settings_terminology' ); ?></table></div>
                <div id="tab-appearance" class="wpd-settings-tab"><table class="form-table"><?php do_settings_sections( 'wpd_settings_appearance' ); ?></table></div>
                <div id="tab-help" class="wpd-settings-tab"><?php $this->render_help_tab_content(); ?></div>
                <?php submit_button(); ?>
            </form>
        </div>
        <style> <?php echo $this->get_settings_page_css(); ?> </style>
        <?php
    }
    
    public function screen_options() {
        $this->transactions_list_table = new WPD_Transactions_List_Table();
    }

    public function render_transactions_page(){
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e( 'تراکنش‌ها', 'wp-directory' ); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <?php
                $this->transactions_list_table->prepare_items();
                $this->transactions_list_table->search_box( 'جستجوی کد رهگیری', 'transaction_search' );
                $this->transactions_list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting( 'wpd_settings_group', 'wpd_settings', [ $this, 'sanitize_settings' ] );
        
        add_settings_section( 'wpd_general_section', null, '__return_false', 'wpd_settings_general' );
        $this->add_general_fields('wpd_general_section');

        add_settings_section( 'wpd_payments_section', null, [$this, 'render_payments_ui'], 'wpd_settings_payments' );
        
        add_settings_section( 'wpd_sms_section', null, [$this, 'render_sms_ui'], 'wpd_settings_sms' );

        add_settings_section( 'wpd_notifications_section', null, [$this, 'render_notifications_ui'], 'wpd_settings_notifications' );

        add_settings_section( 'wpd_terminology_section', null, '__return_false', 'wpd_settings_terminology' );
        $this->add_terminology_fields('wpd_terminology_section');

        add_settings_section( 'wpd_appearance_section', null, '__return_false', 'wpd_settings_appearance' );
        $this->add_appearance_fields('wpd_appearance_section');
    }
    
    public function render_help_tab_content() {
        ?>
        <h2><?php _e('راهنمای استفاده از افزونه نیلای دایرکتوری', 'wp-directory'); ?></h2>
        <p><?php _e('به راهنمای افزونه دایرکتوری خوش آمدید. در اینجا می‌توانید با روش کار و شورت‌کدهای افزونه آشنا شوید.', 'wp-directory'); ?></p>
        
        <h3><?php _e('مراحل راه‌اندازی اولیه', 'wp-directory'); ?></h3>
        <ol>
            <li><?php _e('<strong>ساخت انواع آگهی:</strong> از منوی "نیلای دایرکتوری > انواع آگهی"، نوع‌های مختلف آگهی خود (مثلا املاک، خودرو) را بسازید. در صفحه ویرایش هر نوع، می‌توانید فیلدهای سفارشی، هزینه ثبت و اعلان‌های اختصاصی آن را تعریف کنید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>پیکربندی مدل درآمدی:</strong> به "تنظیمات > عمومی" بروید. مشخص کنید که آیا می‌خواهید از <strong>سیستم بسته‌های عضویت</strong> استفاده کنید یا خیر. اگر این گزینه را غیرفعال کنید، درآمد شما فقط از طریق "هزینه ثبت" که برای هر نوع آگهی تعریف کرده‌اید، خواهد بود.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>ساخت بسته‌های عضویت و ارتقا:</strong> از منوهای مربوطه، بسته‌های ثبت آگهی و بسته‌های ارتقا (ویژه کردن، نردبان و...) را تعریف کنید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>ایجاد برگه‌های اصلی:</strong> سه برگه جدید در وردپرس بسازید: یکی برای "ثبت آگهی"، یکی برای "داشبورد کاربری" و یکی برای "آرشیو آگهی‌ها".', 'wp-directory'); ?></li>
            <li><?php _e('<strong>قرار دادن شورت‌کدها:</strong> شورت‌کد مربوط به هر برگه را (از لیست زیر) در محتوای آن قرار دهید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>تنظیمات عمومی:</strong> از تب "عمومی" در همین صفحه، برگه‌هایی که ساختید را به افزونه معرفی کنید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>پیکربندی درگاه پرداخت و پیامک:</strong> کلیدهای API خود را در تب‌های "پرداخت" و "پیامک" وارد کنید.', 'wp-directory'); ?></li>
        </ol>

        <h3><?php _e('لیست شورت‌کدها', 'wp-directory'); ?></h3>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('شورت‌کد', 'wp-directory'); ?></th>
                    <th><?php _e('کارکرد', 'wp-directory'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[wpd_submit_form]</code></td>
                    <td><?php _e('فرم ثبت آگهی را نمایش می‌دهد. این شورت‌کد را در برگه "ثبت آگهی" قرار دهید.', 'wp-directory'); ?></td>
                </tr>
                <tr>
                    <td><code>[wpd_dashboard]</code></td>
                    <td><?php _e('داشبورد کاربری را برای مدیریت آگهی‌ها، پروفایل و تراکنش‌ها نمایش می‌دهد. این شورت‌کد را در برگه "داشبورد کاربری" قرار دهید.', 'wp-directory'); ?></td>
                </tr>
                <tr>
                    <td><code>[wpd_listing_archive]</code></td>
                    <td><?php _e('صفحه آرشیو آگهی‌ها را به همراه فرم جستجو و فیلتر پیشرفته نمایش می‌دهد. این شورت‌کد را در برگه "آرشیو آگهی‌ها" قرار دهید.', 'wp-directory'); ?></td>
                </tr>
            </tbody>
        </table>

        <h3><?php _e('قابلیت‌های کلیدی', 'wp-directory'); ?></h3>
        <p><?php _e('<strong>سیستم پرداخت انعطاف‌پذیر:</strong> شما می‌توانید انتخاب کنید که کاربران برای ثبت آگهی، بسته عضویت بخرند، یا فقط هزینه ثابتی برای هر نوع آگهی پرداخت کنند، و یا ترکیبی از هر دو!', 'wp-directory'); ?></p>
        <p><?php _e('<strong>سیستم اعلان‌های هوشمند:</strong> از تب "اعلان‌ها"، تمام ایمیل‌ها و پیامک‌های ارسالی به کاربران را مدیریت کنید. همچنین می‌توانید از صفحه ویرایش "نوع آگهی"، مشخص کنید که برای آن نوع خاص کدام اعلان‌ها ارسال شوند.', 'wp-directory'); ?></p>
        <?php
    }

    public function sanitize_settings( $input ) {
        Directory_Main::log("Sanitizing plugin settings...");
        $new_input = [];
        if (empty($input) || !is_array($input)) {
            Directory_Main::log("Settings input is empty or not an array. Returning empty array.");
            return $new_input;
        }
        
        foreach ($input as $section => $values) {
            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $sanitized_value = null;
                    if ( is_array($value) ) {
                        $sanitized_value = array_map('sanitize_text_field', $value);
                    } elseif ( strpos($key, '_color') !== false ) {
                        $sanitized_value = sanitize_hex_color($value);
                    } elseif ( strpos($key, 'email_body_') === 0 ) {
                        $sanitized_value = wp_kses_post($value);
                    } elseif ( strpos($key, 'font_size_') === 0 ) {
                        $sanitized_value = sanitize_text_field($value); 
                    } elseif ( in_array($key, ['font_weight_title', 'font_weight_body', 'font_weight_button']) ) {
                        $sanitized_value = absint($value); 
                    } else {
                        $sanitized_value = sanitize_text_field($value);
                    }
                    $new_input[$section][$key] = $sanitized_value;
                }
            }
        }
        Directory_Main::log("Settings sanitized successfully.");
        return $new_input;
    }
    
    private function add_general_fields($section_id){
        add_settings_field('general_pages', 'برگه‌های اصلی', [$this, 'render_page_dropdown_fields'], 'wpd_settings_general', $section_id);
        add_settings_field('general_packages_system', 'سیستم بسته‌های عضویت', [$this, 'render_checkbox_element'], 'wpd_settings_general', $section_id, ['section' => 'general', 'id' => 'enable_packages', 'desc' => 'در صورت غیرفعال بودن، مرحله انتخاب بسته حذف شده و از مدل پرداخت به ازای هر آگهی استفاده می‌شود.']);
        add_settings_field('general_currency', 'واحد پولی', [$this, 'render_text_input_element'], 'wpd_settings_general', $section_id, ['section' => 'general', 'id' => 'currency', 'default' => 'تومان']);
        add_settings_field('general_approval_method', 'روش تایید آگهی', [$this, 'render_select_element'], 'wpd_settings_general', $section_id, [
            'section' => 'general', 
            'id' => 'approval_method', 
            'options' => ['auto' => 'تایید خودکار', 'manual' => 'تایید دستی توسط مدیر']
        ]);
    }

    private function add_terminology_fields($section_id){
        $default_terms = Directory_Main::get_default_terms();
        foreach($default_terms as $key => $label){
             add_settings_field('term_'.$key, $label, [$this, 'render_text_input_element'], 'wpd_settings_terminology', $section_id, ['section' => 'terminology', 'id' => $key, 'default' => $label]);
        }
    }

    private function add_appearance_fields($section_id){
        $colors = ['primary_color' => 'رنگ اصلی', 'text_color' => 'رنگ متن', 'background_color' => 'رنگ پس‌زمینه'];
        foreach($colors as $id => $label){
            add_settings_field('appearance_color_'.$id, $label, [$this, 'render_color_picker_element'], 'wpd_settings_appearance', $section_id, ['section' => 'appearance', 'id' => $id]);
        }
        
        add_settings_field('appearance_typography_main_font', 'فونت اصلی', [$this, 'render_select_element'], 'wpd_settings_appearance', $section_id, [
            'section' => 'appearance', 
            'id' => 'main_font', 
            'options' => ['vazir' => 'وزیرمتن', 'iransans' => 'ایران سنس', 'dana' => 'دانا', 'custom' => 'استفاده از فونت قالب']
        ]);

        $typography_elements = ['title' => 'عناوین', 'body' => 'متن بدنه', 'button' => 'دکمه‌ها'];
        foreach ($typography_elements as $el_key => $el_label) {
            add_settings_field('appearance_typography_'.$el_key.'_size', sprintf('اندازه فونت %s', $el_label), [$this, 'render_text_input_element'], 'wpd_settings_appearance', $section_id, ['section' => 'appearance', 'id' => "font_size_{$el_key}", 'desc' => 'مثال: 16px یا 1.2rem']);
            add_settings_field('appearance_typography_'.$el_key.'_weight', sprintf('وزن فونت %s', $el_label), [$this, 'render_select_element'], 'wpd_settings_appearance', $section_id, [
                'section' => 'appearance', 
                'id' => "font_weight_{$el_key}", 
                'options' => ['300' => 'Light', '400' => 'Normal', '500' => 'Medium', '700' => 'Bold', '900' => 'Black']
            ]);
        }
    }

    public function render_payments_ui() {
        $gateways = ['zarinpal' => 'زرین پال', 'zibal' => 'زیبال'];
        echo '<div class="wpd-accordion">';
        foreach ($gateways as $id => $label) {
            ?>
            <div class="wpd-accordion-item">
                <button type="button" class="wpd-accordion-header"><?php echo esc_html($label); ?></button>
                <div class="wpd-accordion-content">
                    <table class="form-table">
                        <tr>
                            <th><?php _e('فعال‌سازی', 'wp-directory'); ?></th>
                            <td><?php $this->render_checkbox_element(['section' => 'payments', 'id' => $id.'_enable']); ?></td>
                        </tr>
                        <tr>
                            <th><label for="wpd_settings_payments_<?php echo esc_attr($id); ?>_apikey"><?php _e('کلید API / مرچنت کد', 'wp-directory'); ?></label></th>
                            <td>
                                <?php $this->render_text_input_element(['section' => 'payments', 'id' => $id.'_apikey']); ?>
                                <button type="button" class="button button-secondary wpd-verify-service-btn" data-service="<?php echo esc_attr($id); ?>" data-field-id="wpd_settings_payments_<?php echo esc_attr($id); ?>_apikey">
                                    <?php _e('بررسی اتصال', 'wp-directory'); ?>
                                </button>
                                <span class="wpd-verification-status"></span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }

    public function render_sms_ui() {
        ?>
        <table class="form-table">
            <tr>
                <th><label for="wpd_settings_sms_provider"><?php _e('سرویس‌دهنده پیامک', 'wp-directory'); ?></label></th>
                <td><?php $this->render_select_element(['section' => 'sms', 'id' => 'provider', 'options' => ['' => '- انتخاب کنید -', 'kavenegar' => 'کاوه نگار', 'farazsms' => 'فراز اس ام اس']]); ?></td>
            </tr>
            <tr class="sms-provider-field kavenegar-field" style="display:none;">
                <th><label for="wpd_settings_sms_kavenegar_api_key"><?php _e('کلید API کاوه نگار', 'wp-directory'); ?></label></th>
                <td>
                    <?php $this->render_text_input_element(['section' => 'sms', 'id' => 'kavenegar_api_key']); ?>
                    <button type="button" class="button button-secondary wpd-verify-service-btn" data-service="kavenegar" data-field-id="wpd_settings_sms_kavenegar_api_key">
                        <?php _e('بررسی اتصال', 'wp-directory'); ?>
                    </button>
                    <span class="wpd-verification-status"></span>
                </td>
            </tr>
            <tr class="sms-provider-field farazsms-field" style="display:none;">
                <th><label for="wpd_settings_sms_farazsms_api_key"><?php _e('کلید API فراز اس ام اس', 'wp-directory'); ?></label></th>
                <td>
                    <?php $this->render_text_input_element(['section' => 'sms', 'id' => 'farazsms_api_key']); ?>
                     <button type="button" class="button button-secondary wpd-verify-service-btn" data-service="farazsms" data-field-id="wpd_settings_sms_farazsms_api_key" data-extra-field-id="wpd_settings_sms_farazsms_sender_number">
                        <?php _e('بررسی اتصال', 'wp-directory'); ?>
                    </button>
                    <span class="wpd-verification-status"></span>
                </td>
            </tr>
            <tr class="sms-provider-field farazsms-field" style="display:none;">
                <th><label for="wpd_settings_sms_farazsms_sender_number"><?php _e('شماره فرستنده فراز اس ام اس', 'wp-directory'); ?></label></th>
                <td><?php $this->render_text_input_element(['section' => 'sms', 'id' => 'farazsms_sender_number']); ?></td>
            </tr>
        </table>
        <?php
    }

    public function render_notifications_ui() {
        $events = Directory_Main::get_notification_events();
        ?>
        <table class="wp-list-table widefat striped wpd-notifications-table">
            <thead>
                <tr>
                    <th><?php _e('رویداد', 'wp-directory'); ?></th>
                    <th><?php _e('اعلان ایمیلی', 'wp-directory'); ?></th>
                    <th><?php _e('اعلان پیامکی', 'wp-directory'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $id => $details): ?>
                <tr>
                    <td><strong><?php echo esc_html($details['label']); ?></strong></td>
                    <td>
                        <?php $this->render_switch_element(['section' => 'notifications', 'id' => "email_enable_{$id}"]); ?>
                        <button type="button" class="button button-secondary wpd-modal-trigger" data-modal-target="#wpd-email-modal-<?php echo esc_attr($id); ?>"><?php _e('ویرایش قالب', 'wp-directory'); ?></button>
                    </td>
                    <td>
                        <?php $this->render_switch_element(['section' => 'notifications', 'id' => "sms_enable_{$id}"]); ?>
                        <button type="button" class="button button-secondary wpd-modal-trigger" data-modal-target="#wpd-sms-modal-<?php echo esc_attr($id); ?>"><?php _e('تنظیمات', 'wp-directory'); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php // Modals
        foreach ($events as $id => $details): ?>
            <div id="wpd-email-modal-<?php echo esc_attr($id); ?>" class="wpd-modal">
                <div class="wpd-modal-content">
                    <button type="button" class="wpd-modal-close">&times;</button>
                    <h3><?php printf(__('قالب ایمیل: %s', 'wp-directory'), esc_html($details['label'])); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="wpd_settings_notifications_email_subject_<?php echo esc_attr($id); ?>"><?php _e('موضوع ایمیل', 'wp-directory'); ?></label></th>
                            <td><?php $this->render_text_input_element(['section' => 'notifications', 'id' => "email_subject_{$id}"]); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('متن ایمیل', 'wp-directory'); ?></th>
                            <td><?php $this->render_wp_editor_element(['section' => 'notifications', 'id' => "email_body_{$id}", 'desc' => 'متغیرهای مجاز: ' . implode(', ', $details['vars'])]); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            <div id="wpd-sms-modal-<?php echo esc_attr($id); ?>" class="wpd-modal">
                <div class="wpd-modal-content">
                    <button type="button" class="wpd-modal-close">&times;</button>
                    <h3><?php printf(__('تنظیمات پیامک: %s', 'wp-directory'), esc_html($details['label'])); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="wpd_settings_notifications_sms_pattern_<?php echo esc_attr($id); ?>"><?php _e('کد الگوی پیامک (Pattern)', 'wp-directory'); ?></label></th>
                            <td><?php $this->render_text_input_element(['section' => 'notifications', 'id' => "sms_pattern_{$id}", 'desc' => 'در صورت استفاده از پترن، متغیرها را به ترتیب در پنل پیامکی خود تعریف کنید.']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endforeach;
    }
    
    public function render_page_dropdown_fields($args) {
        $options = Directory_Main::get_option('general', []);
        $pages = ['submit_page' => 'صفحه ثبت آگهی', 'dashboard_page' => 'صفحه داشبورد کاربری', 'archive_page' => 'صفحه آرشیو آگهی‌ها'];
        
        foreach ($pages as $id => $label) {
            $value = $options[$id] ?? '';
            echo '<tr>';
            echo '<th><label for="wpd_settings_general_'.esc_attr($id).'">'.esc_html($label).'</label></th>';
            echo '<td>';
            wp_dropdown_pages([
                'name' => 'wpd_settings[general]['.$id.']', 
                'id' => 'wpd_settings_general_'.esc_attr($id),
                'selected' => $value, 
                'show_option_none' => '— ' . __('انتخاب کنید', 'wp-directory') . ' —'
            ]);
            echo '</td>';
            echo '</tr>';
        }
    }

    public function render_checkbox_element($args){
        $options = Directory_Main::get_option($args['section'], []);
        $value = $options[$args['id']] ?? '0';
        $desc = $args['desc'] ?? '';
        echo '<label><input type="checkbox" name="wpd_settings['.$args['section'].']['.$args['id'].']" value="1" '.checked(1, $value, false).' /> '.wp_kses_post($desc).'</label>';
    }

    public function render_text_input_element($args){
        $options = Directory_Main::get_option($args['section'], []);
        $value = $options[$args['id']] ?? ($args['default'] ?? '');
        $placeholder = $args['placeholder'] ?? $args['default'] ?? '';
        echo '<input type="text" id="wpd_settings_'.esc_attr($args['section']).'_'.esc_attr($args['id']).'" name="wpd_settings['.$args['section'].']['.$args['id'].']" value="'.esc_attr($value).'" class="regular-text" placeholder="'.esc_attr($placeholder).'" />';
        if(isset($args['desc'])) echo '<p class="description">'.wp_kses_post($args['desc']).'</p>';
    }
    
    public function render_wp_editor_element($args) {
        $options = Directory_Main::get_option($args['section'], []);
        $content = $options[$args['id']] ?? '';
        $editor_id = 'wpd_editor_'.$args['section'].'_'.$args['id'];
        wp_editor(wp_unslash($content), $editor_id, ['textarea_name' => 'wpd_settings['.$args['section'].']['.$args['id'].']', 'media_buttons' => false, 'textarea_rows' => 7]);
        if(isset($args['desc'])) echo '<p class="description">'.wp_kses_post($args['desc']).'</p>';
    }

    public function render_color_picker_element($args){
        $options = Directory_Main::get_option($args['section'], []);
        $value = $options[$args['id']] ?? '';
        echo '<input type="text" name="wpd_settings['.$args['section'].']['.$args['id'].']" value="'.esc_attr($value).'" class="wpd-color-picker" />';
    }
    
    public function render_select_element($args){
         $options = Directory_Main::get_option($args['section'], []);
         $value = $options[$args['id']] ?? '';
         $select_options = $args['options'] ?? [];
         
         echo '<select id="wpd_settings_'.esc_attr($args['section']).'_'.esc_attr($args['id']).'" name="wpd_settings['.$args['section'].']['.$args['id'].']">';
         foreach($select_options as $opt_key => $opt_name){
             echo '<option value="'.esc_attr($opt_key).'" '.selected($value, $opt_key, false).'>'.esc_html($opt_name).'</option>';
         }
         echo '</select>';
         if(isset($args['desc'])) echo '<p class="description">'.wp_kses_post($args['desc']).'</p>';
    }

    public function render_switch_element($args) {
        $options = Directory_Main::get_option($args['section'], []);
        $value = $options[$args['id']] ?? '0';
        ?>
        <label class="wpd-switch">
            <input type="checkbox" name="wpd_settings[<?php echo esc_attr($args['section']); ?>][<?php echo esc_attr($args['id']); ?>]" value="1" <?php checked(1, $value); ?>>
            <span class="wpd-slider"></span>
        </label>
        <?php
    }

    private function get_settings_page_css() {
        return "
            .wpd-settings-tab { display: none; } .wpd-settings-tab.active { display: block; }
            .form-table th { width: 250px; }
            .wpd-accordion-item { border: 1px solid #ddd; margin-bottom: -1px; }
            .wpd-accordion-header { background: #f6f7f7; border: none; width: 100%; text-align: right; padding: 10px 15px; cursor: pointer; font-size: 14px; font-weight: bold; }
            .wpd-accordion-header:after { content: '\\f140'; float: left; font-family: dashicons; transform: rotate(0deg); transition: transform 0.2s; }
            .wpd-accordion-header.active:after { transform: rotate(180deg); }
            .wpd-accordion-content { padding: 15px; border-top: 1px solid #ddd; display: none; }
            .wpd-switch { position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; }
            .wpd-switch input { opacity: 0; width: 0; height: 0; }
            .wpd-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
            .wpd-slider:before { position: absolute; content: ''; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
            input:checked + .wpd-slider { background-color: #2271b1; }
            input:checked + .wpd-slider:before { transform: translateX(20px); }
            .wpd-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
            .wpd-modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 60%; max-width: 700px; position: relative; }
            .wpd-modal-close { color: #aaa; float: left; font-size: 28px; font-weight: bold; cursor: pointer; }
            .wpd-notifications-table .button { margin-right: 5px; }
            .wpd-verification-status { margin-right: 10px; font-weight: bold; }
            .wpd-verification-status.success { color: green; }
            .wpd-verification-status.error { color: red; }
        ";
    }

    private function get_settings_page_js() {
        $ajax_nonce = wp_create_nonce('wpd_verify_service_nonce');
        return <<<'JS'
            jQuery(document).ready(function($){
                // General Tabs
                var activeTab = window.location.hash.replace('#', '');
                if (activeTab === '' || $('.wpd-nav-tab-wrapper .nav-tab[data-tab="' + activeTab + '"]').length === 0) {
                    activeTab = $('.wpd-nav-tab-wrapper .nav-tab:first').data('tab');
                }
                $('.wpd-nav-tab-wrapper .nav-tab[data-tab="' + activeTab + '"]').addClass('nav-tab-active');
                $('#tab-' + activeTab).addClass('active');
                $('.wpd-nav-tab-wrapper .nav-tab').click(function(e){
                    e.preventDefault();
                    var tab_id = $(this).data('tab');
                    $('.wpd-nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                    $('.wpd-settings-tab').removeClass('active');
                    $(this).addClass('nav-tab-active');
                    $('#tab-' + tab_id).addClass('active');
                    window.location.hash = tab_id;
                });

                // Color Picker
                $('.wpd-color-picker').wpColorPicker();

                // Accordion
                $('.wpd-accordion-header').on('click', function(){
                    $(this).toggleClass('active');
                    $(this).next('.wpd-accordion-content').slideToggle('fast');
                });

                // Conditional SMS fields
                var smsProviderSelect = $('#wpd_settings_sms_provider');
                function toggleSmsFields() {
                    var provider = smsProviderSelect.val();
                    $('.sms-provider-field').hide();
                    if (provider) {
                        $('.' + provider + '-field').show();
                    }
                }
                smsProviderSelect.on('change', toggleSmsFields);
                toggleSmsFields();

                // Modals
                $('.wpd-modal-trigger').on('click', function(e){
                    e.preventDefault();
                    var modalId = $(this).data('modal-target');
                    $(modalId).show();
                });
                $('.wpd-modal-close').on('click', function(){
                    $(this).closest('.wpd-modal').hide();
                });
                $(window).on('click', function(e){
                    if ($(e.target).is('.wpd-modal')) {
                        $(e.target).hide();
                    }
                });

                // Service Verification AJAX
                $('.wpd-verify-service-btn').on('click', function(e) {
                    e.preventDefault();
                    var $button = $(this);
                    var service = $button.data('service');
                    var fieldId = $button.data('field-id');
                    var extraFieldId = $button.data('extra-field-id');
                    var apiKey = $('#' + fieldId).val();
                    var extraData = extraFieldId ? $('#' + extraFieldId).val() : '';
                    var $statusSpan = $button.siblings('.wpd-verification-status');

                    $statusSpan.removeClass('success error').text('در حال بررسی...').css('color', 'orange');
                    $button.prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wpd_verify_service',
                            _ajax_nonce: '{$ajax_nonce}',
                            service: service,
                            api_key: apiKey,
                            extra_data: extraData
                        },
                        success: function(response) {
                            if (response.success) {
                                $statusSpan.addClass('success').text(response.data.message);
                            } else {
                                $statusSpan.addClass('error').text(response.data.message);
                            }
                        },
                        error: function() {
                            $statusSpan.addClass('error').text('خطای ارتباط با سرور.');
                        },
                        complete: function() {
                            $button.prop('disabled', false);
                        }
                    });
                });
            });
JS;
    }

    public function ajax_verify_service() {
        check_ajax_referer('wpd_verify_service_nonce');

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'شما دسترسی لازم را ندارید.']);
        }

        $service = isset($_POST['service']) ? sanitize_key($_POST['service']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $extra_data = isset($_POST['extra_data']) ? sanitize_text_field($_POST['extra_data']) : '';


        if (empty($service) || empty($api_key)) {
            wp_send_json_error(['message' => 'سرویس یا کلید API مشخص نشده است.']);
        }

        $result = ['success' => false, 'message' => 'سرویس نامعتبر است.'];

        switch ($service) {
            case 'zarinpal':
                $result = Directory_Gateways::verify_zarinpal_credentials($api_key);
                break;
            case 'zibal':
                $result = Directory_Gateways::verify_zibal_credentials($api_key);
                break;
            case 'kavenegar':
                $result = Directory_Gateways::verify_kavenegar_credentials($api_key);
                break;
            case 'farazsms':
                 $result = Directory_Gateways::verify_farazsms_credentials($api_key, $extra_data);
                break;
        }

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
}
