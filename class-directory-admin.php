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
                return $user ? '<a href="' . get_edit_user_link($user->ID) . '">' . esc_html($user->display_name) . '</a>' : __( 'کاربر حذف شده', 'wp-directory' );
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

        add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widget' ] );
        add_action( 'admin_init', [ $this, 'handle_manual_transaction_submission' ] );
        add_action( 'admin_init', [ $this, 'handle_tools_actions' ] );
    }

    public function add_admin_menu() {
        add_menu_page( 'نیلای دایرکتوری', 'نیلای دایرکتوری', 'manage_options', 'wpd-main-menu', [ $this, 'render_settings_page' ], 'dashicons-location-alt', 20 );
        add_submenu_page( 'wpd-main-menu', __( 'تنظیمات', 'wp-directory' ), __( 'تنظیمات', 'wp-directory' ), 'manage_options', 'wpd-main-menu' );
        add_submenu_page( 'wpd-main-menu', __( 'بسته‌های عضویت', 'wp-directory' ), __( 'بسته‌های عضویت', 'wp-directory' ), 'manage_options', 'edit.php?post_type=wpd_package' );
        add_submenu_page( 'wpd-main-menu', __( 'بسته‌های ارتقا', 'wp-directory' ), __( 'بسته‌های ارتقا', 'wp-directory' ), 'manage_options', 'edit.php?post_type=wpd_upgrade' );
        $hook = add_submenu_page( 'wpd-main-menu', __( 'تراکنش‌ها', 'wp-directory' ), __( 'تراکنش‌ها', 'wp-directory' ), 'manage_options', 'wpd-transactions', [ $this, 'render_transactions_page' ] );
        
        add_submenu_page( 'wpd-main-menu', __( 'گزارشات', 'wp-directory' ), __( 'گزارشات', 'wp-directory' ), 'manage_options', 'wpd-reports', [ $this, 'render_reports_page' ] );
        add_submenu_page( 'wpd-main-menu', __( 'ابزارها', 'wp-directory' ), __( 'ابزارها', 'wp-directory' ), 'manage_options', 'wpd-tools', [ $this, 'render_tools_page' ] );
        
        add_action( "load-$hook", [ $this, 'screen_options' ] );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( strpos($hook, 'wpd-transactions') !== false ) {
            $css = '.wpd-status-completed { color: green; } .wpd-status-failed { color: red; } .wpd-status-pending { color: orange; }';
            wp_add_inline_style('common', $css);
        }
        
        if ( strpos($hook, 'wpd-') !== false ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            
            if ($hook === 'نیلای-دایرکتوری_page_wpd-reports') {
                wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.7.1', true);
            }
            
            wp_add_inline_script( 'jquery-core', $this->get_settings_page_js() );
        }
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
                <div id="tab-general" class="wpd-settings-tab active"><table class="form-table"><?php do_settings_sections( 'wpd_settings_general' ); ?></table></div>
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
        $action = $_GET['action'] ?? 'list';

        if ($action === 'add_new') {
            $this->render_add_transaction_page();
            return;
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e( 'تراکنش‌ها', 'wp-directory' ); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpd-transactions&action=add_new')); ?>" class="page-title-action"><?php _e('افزودن تراکنش دستی', 'wp-directory'); ?></a>
            <hr class="wp-header-end">

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
        <p><?php _e('به راهنمای جامع افزونه دایرکتوری خوش آمدید. در اینجا می‌توانید با مراحل راه‌اندازی، شورت‌کدها و ابزارهای کلیدی افزونه آشنا شوید.', 'wp-directory'); ?></p>
        
        <h3><?php _e('مراحل راه‌اندازی اولیه', 'wp-directory'); ?></h3>
        <ol>
            <li><?php _e('<strong>ساخت انواع آگهی:</strong> از منوی "نیلای دایرکتوری > انواع آگهی"، نوع‌های مختلف آگهی خود (مثلا املاک، خودرو) را بسازید. در صفحه ویرایش هر نوع، می‌توانید فیلدهای سفارشی، طبقه‌بندی‌ها، هزینه ثبت و اعلان‌های اختصاصی آن را تعریف کنید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>پیکربندی مدل درآمدی:</strong> به "تنظیمات > عمومی" بروید. مشخص کنید که آیا می‌خواهید از <strong>سیستم بسته‌های عضویت</strong> استفاده کنید یا خیر. اگر این گزینه را غیرفعال کنید، درآمد شما فقط از طریق "هزینه ثبت" که برای هر نوع آگهی تعریف کرده‌اید، خواهد بود.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>ساخت بسته‌های عضویت و ارتقا:</strong> از منوهای مربوطه، بسته‌های ثبت آگهی و بسته‌های ارتقا (ویژه کردن، نردبان و...) را تعریف کنید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>ایجاد برگه‌های اصلی:</strong> سه برگه جدید در وردپرس بسازید: یکی برای "ثبت آگهی"، یکی برای "داشبورد کاربری" و یکی برای "آرشیو آگهی‌ها".', 'wp-directory'); ?></li>
            <li><?php _e('<strong>قرار دادن شورت‌کدها:</strong> شورت‌کد مربوط به هر برگه را (از لیست زیر) در محتوای آن قرار دهید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>تنظیمات عمومی:</strong> از تب "عمومی" در همین صفحه، برگه‌هایی که ساختید را به افزونه معرفی کنید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>پیکربندی درگاه پرداخت و پیامک:</strong> کلیدهای API خود را در تب‌های "پرداخت" و "پیامک" وارد کنید.', 'wp-directory'); ?></li>
        </ol>

        <h3><?php _e('ابزارهای مفید', 'wp-directory'); ?></h3>
        <ul>
            <li><?php _e('<strong>وضعیت سیستم:</strong> در منوی "ابزارها"، وضعیت محیط وردپرس و سرور خود را بررسی کنید تا از عملکرد صحیح افزونه مطمئن شوید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>مدیریت داده‌ها:</strong> در تب "مدیریت داده‌ها" در صفحه ابزارها، می‌توانید از تنظیمات افزونه، انواع آگهی‌ها و خود آگهی‌ها، فایل پشتیبان (Export) تهیه کنید و یا فایل‌های پشتیبان قبلی را بارگذاری (Import) نمایید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>پاک کردن کش:</strong> از ابزارهای نگهداری در صفحه ابزارها، تمام کش افزونه را پاک کنید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>اجرای رویدادهای روزانه:</strong> با استفاده از ابزار مربوطه، می‌توانید رویدادهای زمان‌بندی شده مانند منقضی کردن آگهی‌ها را به صورت دستی اجرا کنید.', 'wp-directory'); ?></li>
        </ul>

        <h3><?php _e('لیست شورت‌کدها', 'wp-directory'); ?></h3>
        <p><?php _e('شورت‌کدهای زیر به شما امکان می‌دهند تا قابلیت‌های افزونه را در برگه‌های سایت خود نمایش دهید. این شورت‌کدها قابلیت‌های پیشرفته‌تری دارند که در ادامه به آن‌ها اشاره شده است.', 'wp-directory'); ?></p>
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
                    <td>
                        <p><?php _e('نمایش فرم ثبت آگهی. با استفاده از پارامتر <code>type</code> می‌توانید فرم ثبت آگهی برای یک نوع آگهی خاص را نمایش دهید.', 'wp-directory'); ?></p>
                        <p><strong><?php _e('مثال:', 'wp-directory'); ?></strong> <code>[wpd_submit_form type="car"]</code></p>
                    </td>
                </tr>
                <tr>
                    <td><code>[wpd_dashboard]</code></td>
                    <td>
                        <p><?php _e('نمایش داشبورد کاربری. با پارامتر <code>tab</code> می‌توانید تب پیش‌فرض را تعیین کنید.', 'wp-directory'); ?></p>
                        <p><strong><?php _e('مثال:', 'wp-directory'); ?></strong> <code>[wpd_dashboard tab="my-transactions"]</code></p>
                    </td>
                </tr>
                <tr>
                    <td><code>[wpd_listing_archive]</code></td>
                    <td>
                        <p><?php _e('نمایش صفحه آرشیو آگهی‌ها. با پارامتر <code>type</code> می‌توانید آرشیو یک نوع آگهی خاص را نمایش دهید.', 'wp-directory'); ?></p>
                        <p><strong><?php _e('مثال:', 'wp-directory'); ?></strong> <code>[wpd_listing_archive type="real-estate"]</code></p>
                        <p><?php _e('همچنین می‌توانید با استفاده از شورت‌کدهای اختصاصی هر نوع آگهی، صفحه آرشیو آن را نمایش دهید. این کار باعث می‌شود که فرم فیلتر نیز به صورت خودکار بر اساس آن نوع آگهی تنظیم شود.', 'wp-directory'); ?></p>
                        <p><strong><?php _e('مثال:', 'wp-directory'); ?></strong> <code>[wpd_archive_car]</code></p>
                    </td>
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
                function handleTabs() {
                    var $tabWrapper = $('.nav-tab-wrapper');
                    if (!$tabWrapper.length) return;

                    var activeTab = window.location.hash.replace('#', '');
                    if (activeTab === '' || $tabWrapper.find('.nav-tab[data-tab="' + activeTab + '"]').length === 0) {
                        activeTab = $tabWrapper.find('.nav-tab:first').data('tab');
                    }
                    
                    $tabWrapper.find('.nav-tab').removeClass('nav-tab-active');
                    $('.wpd-settings-tab').removeClass('active');
                    
                    $tabWrapper.find('.nav-tab[data-tab="' + activeTab + '"]').addClass('nav-tab-active');
                    $('#tab-' + activeTab).addClass('active');

                    $tabWrapper.find('.nav-tab').off('click').on('click', function(e){
                        e.preventDefault();
                        var tab_id = $(this).data('tab');
                        
                        $tabWrapper.find('.nav-tab').removeClass('nav-tab-active');
                        $('.wpd-settings-tab').removeClass('active');
                        
                        $(this).addClass('nav-tab-active');
                        $('#tab-' + tab_id).addClass('active');
                        
                        window.location.hash = tab_id;
                    });
                }
                handleTabs();

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

    /**
     * Adds the dashboard widget.
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'wpd_dashboard_widget',
            'خلاصه وضعیت نیلای دایرکتوری',
            [ $this, 'render_dashboard_widget' ]
        );
    }

    /**
     * Renders the content of the dashboard widget.
     */
    public function render_dashboard_widget() {
        global $wpdb;
        $currency = Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'];

        // Pending listings count
        $pending_count = get_posts(['post_type' => 'wpd_listing', 'post_status' => 'pending', 'fields' => 'ids', 'posts_per_page' => -1]);
        $pending_count = count($pending_count);

        // This month's revenue
        $start_of_month = date('Y-m-01 00:00:00');
        $revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}wpd_transactions WHERE status = 'completed' AND created_at >= %s",
            $start_of_month
        ));

        // Recent transactions
        $recent_transactions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpd_transactions ORDER BY created_at DESC LIMIT 5");

        ?>
        <div class="wpd-dashboard-widget">
            <div class="wpd-widget-stats">
                <div class="wpd-stat-item">
                    <strong><?php echo esc_html($pending_count); ?></strong>
                    <span><a href="<?php echo admin_url('edit.php?post_status=pending&post_type=wpd_listing'); ?>"><?php _e('آگهی در انتظار تایید', 'wp-directory'); ?></a></span>
                </div>
                <div class="wpd-stat-item">
                    <strong><?php echo esc_html(number_format($revenue ?? 0)); ?></strong>
                    <span><?php printf(__('درآمد این ماه (%s)', 'wp-directory'), $currency); ?></span>
                </div>
            </div>
            <hr>
            <h4><?php _e('آخرین تراکنش‌ها', 'wp-directory'); ?></h4>
            <?php if (!empty($recent_transactions)): ?>
            <ul class="wpd-recent-transactions">
                <?php foreach ($recent_transactions as $tx): ?>
                <li>
                    <span class="wpd-status-<?php echo esc_attr($tx->status); ?>"><?php echo esc_html($tx->status); ?></span> - 
                    <?php echo esc_html(number_format($tx->amount)); ?> <?php echo esc_html($currency); ?> - 
                    <small><?php echo date_i18n('Y/m/d', strtotime($tx->created_at)); ?></small>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p><?php _e('هیچ تراکنشی یافت نشد.', 'wp-directory'); ?></p>
            <?php endif; ?>
        </div>
        <style>
            .wpd-widget-stats { display: flex; justify-content: space-around; text-align: center; padding: 10px 0; }
            .wpd-stat-item strong { font-size: 24px; display: block; }
            .wpd-recent-transactions { margin: 0; padding: 0; list-style: none; }
            .wpd-recent-transactions li { border-bottom: 1px solid #eee; padding: 5px 0; }
        </style>
        <?php
    }

    /**
     * Renders the Reports page.
     */
    public function render_reports_page() {
        global $wpdb;
        $currency = Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'];
        
        // General Stats
        $total_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}wpd_transactions WHERE status = 'completed'");
        $total_transactions = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}wpd_transactions WHERE status = 'completed'");
        $total_listings = wp_count_posts('wpd_listing')->publish;

        // Monthly Revenue for Chart
        $monthly_revenue = $wpdb->get_results(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total
            FROM {$wpdb->prefix}wpd_transactions
            WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month ORDER BY month ASC"
        );

        $chart_labels = [];
        $chart_data = [];
        if ($monthly_revenue) {
            foreach ($monthly_revenue as $row) {
                $chart_labels[] = date_i18n('F Y', strtotime($row->month . '-01'));
                $chart_data[] = $row->total;
            }
        }

        // Listings per Type
        $listings_per_type_raw = $wpdb->get_results(
            "SELECT p.post_title, COUNT(pm.post_id) as count
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.meta_value = p.ID
            WHERE pm.meta_key = '_wpd_listing_type'
            GROUP BY pm.meta_value"
        );
        ?>
        <div class="wrap">
            <h1><?php _e('گزارشات نیلای دایرکتوری', 'wp-directory'); ?></h1>
            <div id="wpd-reports-overview" class="wpd-reports-row">
                <div class="wpd-report-card"><h3><?php _e('درآمد کل', 'wp-directory'); ?></h3><p><?php echo esc_html(number_format($total_revenue ?? 0)); ?> <?php echo esc_html($currency); ?></p></div>
                <div class="wpd-report-card"><h3><?php _e('تراکنش‌های موفق', 'wp-directory'); ?></h3><p><?php echo esc_html(number_format($total_transactions ?? 0)); ?></p></div>
                <div class="wpd-report-card"><h3><?php _e('کل آگهی‌های فعال', 'wp-directory'); ?></h3><p><?php echo esc_html(number_format($total_listings ?? 0)); ?></p></div>
            </div>
            <div id="wpd-reports-main" class="wpd-reports-row">
                <div class="wpd-report-card main-chart">
                    <h3><?php _e('درآمد ۶ ماه اخیر', 'wp-directory'); ?></h3>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="wpd-report-card">
                    <h3><?php _e('آگهی‌ها بر اساس نوع', 'wp-directory'); ?></h3>
                    <table class="widefat striped">
                        <thead><tr><th><?php _e('نوع آگهی', 'wp-directory'); ?></th><th><?php _e('تعداد', 'wp-directory'); ?></th></tr></thead>
                        <tbody>
                            <?php if (!empty($listings_per_type_raw)): foreach ($listings_per_type_raw as $row): ?>
                                <tr><td><?php echo esc_html($row->post_title); ?></td><td><?php echo esc_html(number_format($row->count)); ?></td></tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="2"><?php _e('داده‌ای یافت نشد.', 'wp-directory'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <style>
            .wpd-reports-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
            .wpd-report-card { background: #fff; padding: 20px; border: 1px solid #ddd; }
            .wpd-report-card h3 { margin-top: 0; }
            .wpd-report-card p { font-size: 24px; margin: 0; font-weight: bold; color: #0073aa; }
            .wpd-report-card.main-chart { grid-column: 1 / -1; }
            @media (min-width: 782px) { .wpd-report-card.main-chart { grid-column: 1 / 3; } }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var ctx = document.getElementById('revenueChart').getContext('2d');
                var revenueChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: '<?php printf(__('درآمد (%s)', 'wp-directory'), $currency); ?>',
                            data: <?php echo json_encode($chart_data); ?>,
                            backgroundColor: 'rgba(0, 115, 170, 0.5)',
                            borderColor: 'rgba(0, 115, 170, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: { y: { beginAtZero: true } },
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Renders the Tools page with System Status and import/export tools.
     */
    public function render_tools_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('ابزارها و وضعیت سیستم', 'wp-directory'); ?></h1>
            
            <h2 class="nav-tab-wrapper wpd-tools-tab-wrapper">
                <a href="#system-status" data-tab="system-status" class="nav-tab nav-tab-active"><?php _e('وضعیت سیستم', 'wp-directory'); ?></a>
                <a href="#tools" data-tab="tools" class="nav-tab"><?php _e('ابزارهای نگهداری', 'wp-directory'); ?></a>
                <a href="#data-management" data-tab="data-management" class="nav-tab"><?php _e('مدیریت داده‌ها', 'wp-directory'); ?></a>
            </h2>

            <div id="tab-system-status" class="wpd-settings-tab active">
                <?php $this->render_system_status_content(); ?>
            </div>

            <div id="tab-tools" class="wpd-settings-tab">
                <?php $this->render_tools_content(); ?>
            </div>
            
            <div id="tab-data-management" class="wpd-settings-tab">
                <?php $this->render_data_management_content(); ?>
            </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($){
                function handleToolsTabs() {
                    var $tabWrapper = $('.wpd-tools-tab-wrapper');
                    if (!$tabWrapper.length) return;

                    var activeTab = window.location.hash.replace('#', '');
                    // اگر تب فعال در URL نبود یا معتبر نبود، تب اول را فعال کن
                    if (activeTab === '' || $tabWrapper.find('.nav-tab[data-tab="' + activeTab + '"]').length === 0) {
                        activeTab = $tabWrapper.find('.nav-tab:first').data('tab');
                    }
                    
                    $tabWrapper.find('.nav-tab').removeClass('nav-tab-active');
                    $('.wpd-settings-tab').removeClass('active').hide(); // همه تب‌ها را پنهان کن
                    
                    $tabWrapper.find('.nav-tab[data-tab="' + activeTab + '"]').addClass('nav-tab-active');
                    $('#tab-' + activeTab).addClass('active').show(); // تب فعال را نمایش بده

                    $tabWrapper.find('.nav-tab').off('click').on('click', function(e){
                        e.preventDefault();
                        var tab_id = $(this).data('tab');
                        
                        $tabWrapper.find('.nav-tab').removeClass('nav-tab-active');
                        $('.wpd-settings-tab').removeClass('active').hide();
                        
                        $(this).addClass('nav-tab-active');
                        $('#tab-' + tab_id).addClass('active').show();
                        
                        window.location.hash = tab_id;
                    });
                }
                handleToolsTabs();
                
                // Import
                $('.wpd-import-form').on('submit', function(e) {
                    var $form = $(this);
                    var fileInput = $form.find('input[type="file"]');
                    if (fileInput.val() === '') {
                        e.preventDefault();
                        alert('<?php _e('لطفا یک فایل برای وارد کردن انتخاب کنید.', 'wp-directory'); ?>');
                    } else {
                        // نمایش لودینگ
                        $form.find('button').prop('disabled', true).text('<?php _e('در حال وارد کردن...', 'wp-directory'); ?>');
                    }
                });
            });
        </script>
        <?php
    }

    private function render_system_status_content() {
        if (!method_exists('Directory_Main', 'get_system_status_info')) {
            echo '<div class="notice notice-error"><p>' . __('خطا: لطفاً فایل class-directory-main.php را نیز بروزرسانی کنید تا این بخش به درستی کار کند.', 'wp-directory') . '</p></div>';
            return;
        }
        $status = Directory_Main::get_system_status_info();
        ?>
        <table class="widefat" cellspacing="0" style="margin-top:20px;">
            <thead>
                <tr>
                    <th colspan="2"><b><?php _e('محیط وردپرس', 'wp-directory'); ?></b></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($status['wp'] as $name => $value): ?>
                <tr>
                    <td style="width: 250px;"><?php echo esc_html($name); ?>:</td>
                    <td><?php echo wp_kses_post($value); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <thead>
                <tr>
                    <th colspan="2"><b><?php _e('محیط سرور', 'wp-directory'); ?></b></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($status['server'] as $name => $value): ?>
                <tr>
                    <td><?php echo esc_html($name); ?>:</td>
                    <td><?php echo wp_kses_post($value); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_tools_content() {
        ?>
        <h3 style="margin-top:20px;"><?php _e('ابزارهای نگهداری', 'wp-directory'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><?php _e('پاک کردن کش افزونه', 'wp-directory'); ?></th>
                    <td>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpd-tools&wpd_tool_action=clear_transients'), 'wpd_clear_transients_nonce'); ?>" class="button"><?php _e('پاک کردن کش', 'wp-directory'); ?></a>
                        <p class="description"><?php _e('این ابزار تمام داده‌های موقت (transients) که توسط افزونه نیلای دایرکتوری ایجاد شده را حذف می‌کند.', 'wp-directory'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('اجرای مجدد رویدادهای روزانه', 'wp-directory'); ?></th>
                    <td>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpd-tools&wpd_tool_action=run_daily_events'), 'wpd_run_daily_events_nonce'); ?>" class="button"><?php _e('اجرای رویدادها', 'wp-directory'); ?></a>
                        <p class="description"><?php _e('این ابزار به صورت دستی رویدادهای زمان‌بندی شده روزانه (مانند منقضی کردن آگهی‌ها) را اجرا می‌کند.', 'wp-directory'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Renders the new data management tab content.
     */
    private function render_data_management_content() {
        $listing_types = get_posts([
            'post_type' => 'wpd_listing_type',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        ?>
        <div class="wpd-data-management-tools" style="margin-top:20px;">
            <h3><?php _e('تنظیمات افزونه', 'wp-directory'); ?></h3>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><?php _e('برون‌بری تنظیمات', 'wp-directory'); ?></th>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpd-tools&wpd_tool_action=export_settings'), 'wpd_export_settings_nonce'); ?>" class="button"><?php _e('دانلود فایل', 'wp-directory'); ?></a>
                            <p class="description"><?php _e('از تمام تنظیمات افزونه یک فایل پشتیبان تهیه می‌کند.', 'wp-directory'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('درون‌ریزی تنظیمات', 'wp-directory'); ?></th>
                        <td>
                            <form method="post" enctype="multipart/form-data" class="wpd-import-form">
                                <?php wp_nonce_field('wpd_import_settings_nonce'); ?>
                                <input type="hidden" name="wpd_tool_action" value="import_settings">
                                <input type="file" name="import_file" accept=".json" required>
                                <button type="submit" class="button button-primary"><?php _e('آپلود و وارد کردن', 'wp-directory'); ?></button>
                            </form>
                            <p class="description"><?php _e('فایل JSON تنظیمات را برای بارگذاری انتخاب کنید.', 'wp-directory'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h3><?php _e('انواع آگهی', 'wp-directory'); ?></h3>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><?php _e('برون‌بری انواع آگهی', 'wp-directory'); ?></th>
                        <td>
                            <form method="get" class="wpd-export-form" style="display: flex; gap: 10px;">
                                <input type="hidden" name="page" value="wpd-tools">
                                <input type="hidden" name="wpd_tool_action" value="export_listing_types_selected">
                                <?php wp_nonce_field('wpd_export_listing_types_nonce_selected'); ?>
                                <select name="listing_type_id" id="export-listing-type" required>
                                    <option value=""><?php _e('انتخاب نوع آگهی...', 'wp-directory'); ?></option>
                                    <?php
                                    if (!empty($listing_types)) {
                                        foreach ($listing_types as $type) {
                                            echo '<option value="' . esc_attr($type->ID) . '">' . esc_html($type->post_title) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <button type="submit" class="button"><?php _e('دانلود فایل', 'wp-directory'); ?></button>
                            </form>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpd-tools&wpd_tool_action=export_listing_types_all'), 'wpd_export_listing_types_all_nonce'); ?>" class="button" style="margin-top: 10px;"><?php _e('برون‌بری همه', 'wp-directory'); ?></a>
                            <p class="description"><?php _e('یک نوع آگهی را انتخاب کرده یا همه را برون‌بری کنید. این شامل فیلدهای سفارشی و طبقه‌بندی‌های مرتبط نیز می‌شود.', 'wp-directory'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('درون‌ریزی انواع آگهی', 'wp-directory'); ?></th>
                        <td>
                            <form method="post" enctype="multipart/form-data" class="wpd-import-form">
                                <?php wp_nonce_field('wpd_import_listing_types_nonce'); ?>
                                <input type="hidden" name="wpd_tool_action" value="import_listing_types">
                                <input type="file" name="import_file" accept=".json" required>
                                <button type="submit" class="button button-primary"><?php _e('آپلود و وارد کردن', 'wp-directory'); ?></button>
                            </form>
                            <p class="description"><?php _e('فایل JSON انواع آگهی را برای بارگذاری انتخاب کنید.', 'wp-directory'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h3><?php _e('آگهی‌های ثبت شده', 'wp-directory'); ?></h3>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><?php _e('برون‌بری آگهی‌ها', 'wp-directory'); ?></th>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpd-tools&wpd_tool_action=export_listings'), 'wpd_export_listings_nonce'); ?>" class="button"><?php _e('دانلود فایل', 'wp-directory'); ?></a>
                            <p class="description"><?php _e('تمام آگهی‌ها، محتوا و اطلاعات متا آن‌ها را برون‌بری می‌کند. (ممکن است زمان‌بر باشد)', 'wp-directory'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('درون‌ریزی آگهی‌ها', 'wp-directory'); ?></th>
                        <td>
                            <form method="post" enctype="multipart/form-data" class="wpd-import-form">
                                <?php wp_nonce_field('wpd_import_listings_nonce'); ?>
                                <input type="hidden" name="wpd_tool_action" value="import_listings">
                                <input type="file" name="import_file" accept=".json" required>
                                <button type="submit" class="button button-primary"><?php _e('آپلود و وارد کردن', 'wp-directory'); ?>...</button>
                            </form>
                            <p class="description"><?php _e('فایل JSON آگهی‌ها را برای بارگذاری انتخاب کنید.', 'wp-directory'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

        </div>
        <?php
    }
    
    public function handle_tools_actions() {
        if (isset($_GET['wpd_tool_action']) || isset($_POST['wpd_tool_action'])) {
            $action = sanitize_key($_REQUEST['wpd_tool_action']);

            if (!current_user_can('manage_options')) {
                return;
            }

            switch ($action) {
                case 'clear_transients':
                    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'wpd_clear_transients_nonce')) {
                        Directory_Main::clear_transients();
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-success is-dismissible"><p>' . __('کش افزونه با موفقیت پاک شد.', 'wp-directory') . '</p></div>';
                        });
                    }
                    break;
                case 'run_daily_events':
                    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'wpd_run_daily_events_nonce')) {
                        do_action('wpd_daily_scheduled_events');
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-success is-dismissible"><p>' . __('رویدادهای روزانه با موفقیت اجرا شدند.', 'wp-directory') . '</p></div>';
                        });
                    }
                    break;
                case 'export_settings':
                    $this->handle_export_settings();
                    break;
                case 'import_settings':
                    $this->handle_import_settings();
                    break;
                case 'export_listing_types_all':
                    $this->handle_export_listing_types();
                    break;
                case 'export_listing_types_selected':
                    $this->handle_export_listing_types_selected();
                    break;
                case 'import_listing_types':
                    $this->handle_import_listing_types();
                    break;
                case 'export_listings':
                    $this->handle_export_listings();
                    break;
                case 'import_listings':
                    $this->handle_import_listings();
                    break;
            }
        }
    }
    
    private function handle_export_listing_types_selected() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpd_export_listing_types_nonce_selected')) {
            wp_die('شما اجازه انجام این کار را ندارید.');
        }

        $listing_type_id = isset($_GET['listing_type_id']) ? intval($_GET['listing_type_id']) : 0;
        if (empty($listing_type_id)) {
            wp_die('نوع آگهی نامعتبر است.');
        }
        
        $post = get_post($listing_type_id);
        if (!$post || $post->post_type !== 'wpd_listing_type') {
            wp_die('نوع آگهی پیدا نشد.');
        }
        
        $post_meta = get_post_meta($post->ID);
        $meta_data = [];
        foreach ($post_meta as $key => $value) {
            if (strpos($key, '_wpd_') === 0 || $key === '_defined_taxonomies' || $key === '_cost' || $key === '_notification_settings') {
                $meta_data[$key] = maybe_unserialize($value[0]);
            }
        }

        $export_data = [[
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_name' => $post->post_name,
            'meta' => $meta_data
        ]];

        $filename = 'wpd-listing-type-' . ($post->post_name ?: $post->ID) . '-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    private function handle_export_settings() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpd_export_settings_nonce')) {
            wp_die('شما اجازه انجام این کار را ندارید.');
        }

        $settings = get_option('wpd_settings');
        $filename = 'wpd-settings-' . date('Y-m-d') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($settings, JSON_PRETTY_PRINT);
        exit;
    }

    private function handle_import_settings() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wpd_import_settings_nonce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('خطا: دسترسی غیرمجاز.', 'wp-directory') . '</p></div>';
            });
            return;
        }

        if (empty($_FILES['import_file']['tmp_name'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('خطا: لطفا یک فایل برای آپلود انتخاب کنید.', 'wp-directory') . '</p></div>';
            });
            return;
        }

        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $settings_data = json_decode($file_content, true);

        if ($settings_data === null) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('خطا: فایل JSON نامعتبر است.', 'wp-directory') . '</p></div>';
            });
            return;
        }

        update_option('wpd_settings', $settings_data);
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('تنظیمات افزونه با موفقیت وارد شد.', 'wp-directory') . '</p></div>';
        });
    }

    private function handle_export_listing_types() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpd_export_listing_types_all_nonce')) {
            wp_die('شما اجازه انجام این کار را ندارید.');
        }

        $args = [
            'post_type' => 'wpd_listing_type',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft']
        ];
        $listing_types = get_posts($args);
        $export_data = [];

        foreach ($listing_types as $post) {
            $post_meta = get_post_meta($post->ID);
            $meta_data = [];
            foreach ($post_meta as $key => $value) {
                // فقط متاهای مرتبط با افزونه را برون‌بری کن
                if (strpos($key, '_wpd_') === 0 || $key === '_defined_taxonomies' || $key === '_cost' || $key === '_notification_settings') {
                    $meta_data[$key] = maybe_unserialize($value[0]);
                }
            }

            $export_data[] = [
                'post_title' => $post->post_title,
                'post_content' => $post->post_content,
                'post_name' => $post->post_name,
                'meta' => $meta_data
            ];
        }

        $filename = 'wpd-listing-types-all-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    private function handle_import_listing_types() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wpd_import_listing_types_nonce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('خطا: دسترسی غیرمجاز.', 'wp-directory') . '</p></div>';
            });
            return;
        }

        if (empty($_FILES['import_file']['tmp_name'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('خطا: لطفا یک فایل برای آپلود انتخاب کنید.', 'wp-directory') . '</p></div>';
            });
            return;
        }

        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);

        if (!is_array($import_data)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('خطا: فایل JSON نامعتبر است.', 'wp-directory') . '</p></div>';
            });
            return;
        }

        foreach ($import_data as $data) {
            $post_data = [
                'post_title' => sanitize_text_field($data['post_title']),
                'post_content' => wp_kses_post($data['post_content']),
                'post_name' => sanitize_title($data['post_name']),
                'post_type' => 'wpd_listing_type',
                'post_status' => 'publish',
            ];
            $post_id = wp_insert_post($post_data);
            
            if (!is_wp_error($post_id) && isset($data['meta'])) {
                foreach ($data['meta'] as $key => $value) {
                    if (is_string($value)) {
                         update_post_meta($post_id, $key, sanitize_text_field($value));
                    } elseif (is_array($value)) {
                         update_post_meta($post_id, $key, $value); // باید در مرحله ذخیره و خواندن، sanitize شود
                    }
                }
            }
        }

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('انواع آگهی‌ها با موفقیت وارد شدند.', 'wp-directory') . '</p></div>';
        });
    }

    private function handle_export_listings() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpd_export_listings_nonce')) {
            wp_die('شما اجازه انجام این کار را ندارید.');
        }

        $args = [
            'post_type' => 'wpd_listing',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'pending', 'expired', 'draft']
        ];
        $listings = get_posts($args);
        $export_data = [];

        foreach ($listings as $post) {
            $post_meta = get_post_meta($post->ID);
            $meta_data = [];
            foreach ($post_meta as $key => $value) {
                if (strpos($key, '_wpd_') === 0) {
                    $meta_data[$key] = maybe_unserialize($value[0]);
                }
            }

            $export_data[] = [
                'post_title' => $post->post_title,
                'post_content' => $post->post_content,
                'post_status' => $post->post_status,
                'post_author' => $post->post_author,
                'meta' => $meta_data,
                'terms' => wp_get_post_terms($post->ID, get_object_taxonomies('wpd_listing'), ['fields' => 'names'])
            ];
        }

        $filename = 'wpd-listings-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    private function handle_import_listings() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wpd_import_listings_nonce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('خطا: دسترسی غیرمجاز.', 'wp-directory') . '</p></div>';
            });
            return;
        }

        if (empty($_FILES['import_file']['tmp_name'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('خطا: لطفا یک فایل برای آپلود انتخاب کنید.', 'wp-directory') . '</p></div>';
            });
            return;
        }
        
        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);

        if (!is_array($import_data)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('خطا: فایل JSON نامعتبر است.', 'wp-directory') . '</p></div>';
            });
            return;
        }

        foreach ($import_data as $data) {
            $post_data = [
                'post_title' => sanitize_text_field($data['post_title']),
                'post_content' => wp_kses_post($data['post_content']),
                'post_status' => sanitize_key($data['post_status']),
                'post_author' => intval($data['post_author']),
                'post_type' => 'wpd_listing',
            ];
            $post_id = wp_insert_post($post_data);

            if (!is_wp_error($post_id) && isset($data['meta'])) {
                foreach ($data['meta'] as $key => $value) {
                    if (is_string($value)) {
                         update_post_meta($post_id, $key, sanitize_text_field($value));
                    } elseif (is_array($value)) {
                         update_post_meta($post_id, $key, $value); // باید در مرحله ذخیره و خواندن، sanitize شود
                    }
                }
            }
        }

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('آگهی‌ها با موفقیت وارد شدند.', 'wp-directory') . '</p></div>';
        });
    }

    private function render_add_transaction_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('افزودن تراکنش دستی', 'wp-directory'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('wpd_add_manual_transaction'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="user_id"><?php _e('کاربر', 'wp-directory'); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_users([
                                'name' => 'user_id',
                                'id' => 'user_id',
                                'show_option_none' => __('انتخاب کاربر', 'wp-directory'),
                                'class' => 'regular-text'
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amount"><?php _e('مبلغ', 'wp-directory'); ?></label></th>
                        <td><input type="number" id="amount" name="amount" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="status"><?php _e('وضعیت', 'wp-directory'); ?></label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="completed"><?php _e('موفق', 'wp-directory'); ?></option>
                                <option value="pending"><?php _e('در انتظار', 'wp-directory'); ?></option>
                                <option value="failed"><?php _e('ناموفق', 'wp-directory'); ?></option>
                            </select>
                        </td>
                    </tr>
                     <tr>
                        <th><label for="transaction_id"><?php _e('کد رهگیری (اختیاری)', 'wp-directory'); ?></label></th>
                        <td><input type="text" id="transaction_id" name="transaction_id" class="regular-text"></td>
                    </tr>
                </table>
                <input type="hidden" name="wpd_action" value="add_manual_transaction">
                <?php submit_button(__('افزودن تراکنش', 'wp-directory')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_manual_transaction_submission() {
        if (isset($_POST['wpd_action']) && $_POST['wpd_action'] === 'add_manual_transaction') {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wpd_add_manual_transaction')) {
                return;
            }
            if (!current_user_can('manage_options')) {
                return;
            }

            $user_id = intval($_POST['user_id']);
            $amount = floatval($_POST['amount']);
            $status = sanitize_key($_POST['status']);
            $transaction_id = sanitize_text_field($_POST['transaction_id']);

            if (empty($user_id) || empty($amount)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . __('لطفا کاربر و مبلغ را مشخص کنید.', 'wp-directory') . '</p></div>';
                });
                return;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'wpd_transactions';
            $wpdb->insert($table_name, [
                'user_id' => $user_id,
                'amount' => $amount,
                'status' => $status,
                'gateway' => 'manual',
                'transaction_id' => $transaction_id,
                'created_at' => current_time('mysql'),
            ]);

            wp_redirect(admin_url('admin.php?page=wpd-transactions'));
            exit;
        }
    }
}
