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
                <?php submit_button( Directory_Main::get_term('button_filter'), 'button', 'filter_action', false, [ 'id' => 'post-query-submit' ] ); ?>
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
        
        add_action( 'admin_notices', [ $this, 'show_admin_notices' ] );
    }

    public function add_admin_menu() {
        add_menu_page( 
            Directory_Main::get_term('admin_menu_main'), 
            Directory_Main::get_term('admin_menu_main'), 
            'manage_options', 
            'wpd-main-menu',
            null,
            'dashicons-location-alt', 
            20 
        );

        // Submenus in the desired order
        add_submenu_page( 'wpd-main-menu', Directory_Main::get_term('admin_menu_packages'), Directory_Main::get_term('admin_menu_packages'), 'manage_options', 'edit.php?post_type=wpd_package' );
        add_submenu_page( 'wpd-main-menu', Directory_Main::get_term('admin_menu_upgrades'), Directory_Main::get_term('admin_menu_upgrades'), 'manage_options', 'edit.php?post_type=wpd_upgrade' );
        $hook = add_submenu_page( 'wpd-main-menu', Directory_Main::get_term('admin_menu_transactions'), Directory_Main::get_term('admin_menu_transactions'), 'manage_options', 'wpd-transactions', [ $this, 'render_transactions_page' ] );
        add_action( "load-$hook", [ $this, 'screen_options' ] );
        add_submenu_page( 'wpd-main-menu', Directory_Main::get_term('admin_menu_reports'), Directory_Main::get_term('admin_menu_reports'), 'manage_options', 'wpd-reports', [ $this, 'render_reports_page' ] );
        add_submenu_page( 'wpd-main-menu', Directory_Main::get_term('admin_menu_appearance'), Directory_Main::get_term('admin_menu_appearance'), 'manage_options', 'wpd-appearance', [ $this, 'render_appearance_page' ] );
        add_submenu_page( 'wpd-main-menu', Directory_Main::get_term('admin_menu_settings'), Directory_Main::get_term('admin_menu_settings'), 'manage_options', 'wpd-settings', [ $this, 'render_settings_page' ] );
        add_submenu_page( 'wpd-main-menu', Directory_Main::get_term('admin_menu_tools'), Directory_Main::get_term('admin_menu_tools'), 'manage_options', 'wpd-tools', [ $this, 'render_tools_page' ] );
        
        remove_submenu_page('wpd-main-menu', 'wpd-main-menu');
    }

    public function enqueue_admin_scripts( $hook ) {
        // BUG FIX: Corrected condition to check for main menu page as well.
        if ( strpos($hook, 'page_wpd-') === false && $hook !== 'toplevel_page_wpd-main-menu' ) {
            return;
        }

        if ( strpos($hook, 'wpd-transactions') !== false ) {
            $css = '.wpd-status-completed { color: green; } .wpd-status-failed { color: red; } .wpd-status-pending { color: orange; }';
            wp_add_inline_style('common', $css);
        }
        
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        
        // BUG FIX: Corrected hook name for reports page.
        if ($hook === 'نیلای-دایرکتوری_page_wpd-reports' || $hook === 'wpd-main-menu_page_wpd-reports') {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.7.1', true);
        }
        
        wp_add_inline_script( 'jquery-core', $this->get_settings_page_js() );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap wpd-settings-wrap">
            <h1><?php echo Directory_Main::get_term('settings_page_title'); ?></h1>
            
            <h2 class="nav-tab-wrapper wpd-nav-tab-wrapper">
                <a href="#general" data-tab="general" class="nav-tab"><?php echo Directory_Main::get_term('settings_tab_general'); ?></a>
                <a href="#payments" data-tab="payments" class="nav-tab"><?php echo Directory_Main::get_term('settings_tab_payments'); ?></a>
                <a href="#sms" data-tab="sms" class="nav-tab"><?php echo Directory_Main::get_term('settings_tab_sms'); ?></a>
                <a href="#notifications" data-tab="notifications" class="nav-tab"><?php echo Directory_Main::get_term('settings_tab_notifications'); ?></a>
                <a href="#terminology" data-tab="terminology" class="nav-tab"><?php echo Directory_Main::get_term('settings_tab_terminology'); ?></a>
                <a href="#help" data-tab="help" class="nav-tab"><?php echo Directory_Main::get_term('settings_tab_help'); ?></a>
            </h2>

            <form action="options.php" method="post">
                <?php settings_fields( 'wpd_settings_group' ); ?>
                <div id="tab-general" class="wpd-settings-tab"><table class="form-table"><?php do_settings_sections( 'wpd_settings_general' ); ?></table></div>
                <div id="tab-payments" class="wpd-settings-tab"><?php do_settings_sections( 'wpd_settings_payments' ); ?></div>
                <div id="tab-sms" class="wpd-settings-tab"><?php do_settings_sections( 'wpd_settings_sms' ); ?></div>
                <div id="tab-notifications" class="wpd-settings-tab"><?php do_settings_sections( 'wpd_settings_notifications' ); ?></div>
                <div id="tab-terminology" class="wpd-settings-tab"><table class="form-table"><?php do_settings_sections( 'wpd_settings_terminology' ); ?></table></div>
                <div id="tab-help" class="wpd-settings-tab"><?php $this->render_help_tab_content(); ?></div>
                <?php submit_button(); ?>
            </form>
        </div>
        <style> <?php echo $this->get_settings_page_css(); ?> </style>
        <?php
    }
    
    public function render_appearance_page() {
        ?>
        <div class="wrap wpd-settings-wrap">
            <h1><?php echo Directory_Main::get_term('appearance_page_title'); ?></h1>
            <form action="options.php" method="post">
                <?php settings_fields( 'wpd_settings_group' ); ?>
                <?php $this->render_appearance_settings(); ?>
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
            <h1 class="wp-heading-inline"><?php echo Directory_Main::get_term('transactions_page_title'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpd-transactions&action=add_new')); ?>" class="page-title-action"><?php echo Directory_Main::get_term('button_add_manual_transaction'); ?></a>
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
        
        // General Tab
        add_settings_section( 'wpd_general_section', null, '__return_false', 'wpd_settings_general' );
        $this->add_general_fields('wpd_general_section');

        // Payments Tab
        add_settings_section( 'wpd_payments_section', null, [$this, 'render_payments_ui'], 'wpd_settings_payments' );
        
        // SMS Tab
        add_settings_section( 'wpd_sms_section', null, [$this, 'render_sms_ui'], 'wpd_settings_sms' );

        // Notifications Tab
        add_settings_section( 'wpd_notifications_section', null, [$this, 'render_notifications_ui'], 'wpd_settings_notifications' );

        // Terminology Tab
        add_settings_section( 'wpd_terminology_section', null, '__return_false', 'wpd_settings_terminology' );
        $this->add_terminology_fields('wpd_terminology_section');

        // Appearance Tab (New Structure)
        $this->register_appearance_settings();
    }

    public function render_appearance_settings() {
        ?>
        <div class="wpd-sub-nav-tab-wrapper">
            <a href="#appearance/global" data-sub-tab="global" class="nav-tab"><?php echo Directory_Main::get_term('appearance_tab_global'); ?></a>
            <a href="#appearance/archive" data-sub-tab="archive" class="nav-tab"><?php echo Directory_Main::get_term('appearance_tab_archive'); ?></a>
            <a href="#appearance/single" data-sub-tab="single" class="nav-tab"><?php echo Directory_Main::get_term('appearance_tab_single'); ?></a>
            <a href="#appearance/forms" data-sub-tab="forms" class="nav-tab"><?php echo Directory_Main::get_term('appearance_tab_forms'); ?></a>
            <a href="#appearance/dashboard" data-sub-tab="dashboard" class="nav-tab"><?php echo Directory_Main::get_term('appearance_tab_dashboard'); ?></a>
            <a href="#appearance/custom_css" data-sub-tab="custom_css" class="nav-tab"><?php echo Directory_Main::get_term('appearance_tab_custom_css'); ?></a>
        </div>

        <div id="sub-tab-global" class="wpd-settings-sub-tab"><table class="form-table"><?php do_settings_sections('wpd_settings_appearance_global'); ?></table></div>
        <div id="sub-tab-archive" class="wpd-settings-sub-tab"><table class="form-table"><?php do_settings_sections('wpd_settings_appearance_archive'); ?></table></div>
        <div id="sub-tab-single" class="wpd-settings-sub-tab"><table class="form-table"><?php do_settings_sections('wpd_settings_appearance_single'); ?></table></div>
        <div id="sub-tab-forms" class="wpd-settings-sub-tab"><table class="form-table"><?php do_settings_sections('wpd_settings_appearance_forms'); ?></table></div>
        <div id="sub-tab-dashboard" class="wpd-settings-sub-tab"><table class="form-table"><?php do_settings_sections('wpd_settings_appearance_dashboard'); ?></table></div>
        <div id="sub-tab-custom_css" class="wpd-settings-sub-tab"><table class="form-table"><?php do_settings_sections('wpd_settings_appearance_custom_css'); ?></table></div>
        <?php
    }

    public function register_appearance_settings() {
        // Global
        add_settings_section('wpd_appearance_global_section', null, '__return_false', 'wpd_settings_appearance_global');
        $this->add_appearance_global_fields('wpd_appearance_global_section');
        
        // Archive
        add_settings_section('wpd_appearance_archive_section', null, '__return_false', 'wpd_settings_appearance_archive');
        $this->add_appearance_archive_fields('wpd_appearance_archive_section');

        // Single
        add_settings_section('wpd_appearance_single_section', null, '__return_false', 'wpd_settings_appearance_single');
        $this->add_appearance_single_fields('wpd_appearance_single_section');

        // Forms
        add_settings_section('wpd_appearance_forms_section', null, '__return_false', 'wpd_settings_appearance_forms');
        $this->add_appearance_forms_fields('wpd_appearance_forms_section');

        // Dashboard
        add_settings_section('wpd_appearance_dashboard_section', null, '__return_false', 'wpd_settings_appearance_dashboard');
        $this->add_appearance_dashboard_fields('wpd_appearance_dashboard_section');

        // Custom CSS
        add_settings_section('wpd_appearance_custom_css_section', null, '__return_false', 'wpd_settings_appearance_custom_css');
        $this->add_appearance_custom_css_fields('wpd_appearance_custom_css_section');
    }
    
    public function render_help_tab_content() {
        ?>
        <h2><?php _e('راهنمای استفاده از افزونه نیلای دایرکتوری', 'wp-directory'); ?></h2>
        <p><?php _e('به راهنمای جامع افزونه دایرکتوری خوش آمدید. در اینجا می‌توانید با مراحل راه‌اندازی، شورت‌کدها و ابزارهای کلیدی افزونه آشنا شوید.', 'wp-directory'); ?></p>
        
        <h3><?php _e('مراحل راه‌اندازی اولیه', 'wp-directory'); ?></h3>
        <ol>
            <li><?php printf(__('<strong>۱. ساخت انواع %s:</strong> از منوی "نیلای دایرکتوری > انواع %s"، نوع‌های مختلف %s خود (مثلا املاک، خودرو) را بسازید. در صفحه ویرایش هر نوع، می‌توانید فیلدهای سفارشی، طبقه‌بندی‌ها، هزینه ثبت و اعلان‌های اختصاصی آن را تعریف کنید.', 'wp-directory'), Directory_Main::get_term('listing'), Directory_Main::get_term('listing'), Directory_Main::get_term('listing')); ?></li>
            <li><?php _e('<strong>۲. پیکربندی مدل درآمدی:</strong> به "تنظیمات > عمومی" بروید. مشخص کنید که آیا می‌خواهید از <strong>سیستم بسته‌های عضویت</strong> استفاده کنید یا خیر. اگر این گزینه را غیرفعال کنید، درآمد شما فقط از طریق "هزینه ثبت" که برای هر نوع آگهی تعریف کرده‌اید، خواهد بود.', 'wp-directory'); ?></li>
            <li><?php printf(__('<strong>۳. ساخت بسته‌های عضویت و ارتقا:</strong> از منوهای "%s" و "%s"، بسته‌های ثبت %s و بسته‌های ارتقا (ویژه کردن، نردبان و...) را تعریف کنید.', 'wp-directory'), Directory_Main::get_term('admin_menu_packages'), Directory_Main::get_term('admin_menu_upgrades'), Directory_Main::get_term('listing')); ?></li>
            <li><?php printf(__('<strong>۴. ایجاد برگه‌های اصلی:</strong> سه برگه جدید در وردپرس بسازید: یکی برای "%s"، یکی برای "داشبورد کاربری" و یکی برای "آرشیو %s".', 'wp-directory'), Directory_Main::get_term('submit_listing'), Directory_Main::get_term('listings')); ?></li>
            <li><?php _e('<strong>۵. قرار دادن شورت‌کدها:</strong> شورت‌کد مربوط به هر برگه را (از لیست زیر) در محتوای آن قرار دهید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>۶. تنظیمات عمومی:</strong> از تب "عمومی" در همین صفحه، برگه‌هایی که ساختید را به افزونه معرفی کنید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>۷. پیکربندی درگاه پرداخت و پیامک:</strong> کلیدهای API خود را در تب‌های "پرداخت" و "پیامک" وارد کنید.', 'wp-directory'); ?></li>
        </ol>

        <h3><?php _e('ابزارهای مفید', 'wp-directory'); ?></h3>
        <ul>
            <li><?php printf(__('<strong>وضعیت سیستم:</strong> در منوی "%s"، وضعیت محیط وردپرس و سرور خود را بررسی کنید تا از عملکرد صحیح افزونه مطمئن شوید.', 'wp-directory'), Directory_Main::get_term('admin_menu_tools')); ?></li>
            <li><?php printf(__('<strong>مدیریت داده‌ها:</strong> در تب "%s" در صفحه ابزارها، می‌توانید از تنظیمات افزونه، انواع %s و خود %s، فایل پشتیبان (Export) تهیه کنید و یا فایل‌های پشتیبان قبلی را بارگذاری (Import) نمایید.', 'wp-directory'), Directory_Main::get_term('tools_tab_data_management'), Directory_Main::get_term('listings'), Directory_Main::get_term('listings')); ?></li>
            <li><?php _e('<strong>پاک کردن کش:</strong> از ابزارهای نگهداری در صفحه ابزارها، تمام کش افزونه را پاک کنید.', 'wp-directory'); ?></li>
            <li><?php printf(__('<strong>اجرای رویدادهای روزانه:</strong> با استفاده از ابزار مربوطه، می‌توانید رویدادهای زمان‌بندی شده مانند منقضی کردن %s را به صورت دستی اجرا کنید.', 'wp-directory'), Directory_Main::get_term('listings')); ?></li>
            <li><?php _e('<strong>ساخت برگه‌های پیش‌فرض:</strong> از ابزارهای نگهداری، برگه‌های مورد نیاز افزونه را به صورت خودکار بسازید.', 'wp-directory'); ?></li>
        </ul>

        <h3><?php _e('لیست شورت‌کدها', 'wp-directory'); ?></h3>
        <p><?php _e('شورت‌کدهای زیر به شما امکان می‌دهند تا قابلیت‌های افزونه را در برگه‌های سایت خود نمایش دهید. این شورت‌کدها قابلیت‌های پیشرفته‌تری دارند که در ادامه به آن‌ها اشاره شده است.', 'wp-directory'); ?></p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('شورت‌کد', 'wp-directory'); ?></th>
                    <th><?php _e('کارکرد و پارامترها', 'wp-directory'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[wpd_submit_form]</code></td>
                    <td>
                        <p><?php printf(__('نمایش فرم ثبت %s. با استفاده از پارامتر <code>type</code> می‌توانید فرم ثبت برای یک نوع خاص را نمایش دهید.', 'wp-directory'), Directory_Main::get_term('listing')); ?></p>
                        <p><strong><?php _e('مثال:', 'wp-directory'); ?></strong> <code>[wpd_submit_form type="car"]</code> (car نامک نوع آگهی است)</p>
                    </td>
                </tr>
                <tr>
                    <td><code>[wpd_dashboard]</code></td>
                    <td>
                        <p><?php _e('نمایش داشبورد کاربری. با پارامتر <code>tab</code> می‌توانید تب پیش‌فرض را تعیین کنید.', 'wp-directory'); ?></p>
                        <p><strong><?php _e('پارامترهای ممکن برای tab:', 'wp-directory'); ?></strong> <code>my-listings</code>, <code>my-transactions</code>, <code>my-profile</code></p>
                        <p><strong><?php _e('مثال:', 'wp-directory'); ?></strong> <code>[wpd_dashboard tab="my-transactions"]</code></p>
                    </td>
                </tr>
                <tr>
                    <td><code>[wpd_listing_archive]</code></td>
                    <td>
                        <p><?php printf(__('نمایش صفحه آرشیو %s. با پارامتر <code>type</code> می‌توانید آرشیو یک نوع خاص را نمایش دهید.', 'wp-directory'), Directory_Main::get_term('listings')); ?></p>
                        <p><strong><?php _e('مثال:', 'wp-directory'); ?></strong> <code>[wpd_listing_archive type="real-estate"]</code> (real-estate نامک نوع آگهی است)</p>
                        <p><?php _e('همچنین می‌توانید با استفاده از شورت‌کدهای اختصاصی هر نوع آگهی، صفحه آرشیو آن را نمایش دهید. این کار باعث می‌شود که فرم فیلتر نیز به صورت خودکار بر اساس آن نوع آگهی تنظیم شود.', 'wp-directory'); ?></p>
                        <p><strong><?php _e('مثال:', 'wp-directory'); ?></strong> <code>[wpd_archive_car]</code></p>
                    </td>
                </tr>
                <tr>
                    <td><code>[wpd_listings_list]</code></td>
                    <td>
                        <p><?php printf(__('نمایش یک لیست از %s در هر نقطه از سایت. این شورت‌کد پارامترهای متنوعی دارد:', 'wp-directory'), Directory_Main::get_term('listings')); ?></p>
                        <ul>
                            <li><code>type</code>: نامک نوع آگهی</li>
                            <li><code>count</code>: تعداد موارد برای نمایش (پیش‌فرض: 5)</li>
                            <li><code>orderby</code>: مرتب‌سازی بر اساس (پیش‌فرض: date). مقادیر ممکن: title, date, rand, comment_count.</li>
                            <li><code>order</code>: ترتیب نمایش (پیش‌فرض: DESC). مقادیر ممکن: ASC, DESC.</li>
                            <li><code>[taxonomy_slug]</code>: برای فیلتر بر اساس طبقه‌بندی. نامک طبقه‌بندی را به عنوان پارامتر و نامک ترم(ها) را به عنوان مقدار قرار دهید. (مثال: <code>wpd_listing_location="tehran"</code>)</li>
                        </ul>
                        <p><strong><?php _e('مثال:', 'wp-directory'); ?></strong> <code>[wpd_listings_list type="car" count="5" orderby="date" order="desc"]</code></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3><?php _e('قابلیت‌های کلیدی', 'wp-directory'); ?></h3>
        <p><?php _e('<strong>سیستم پرداخت انعطاف‌پذیر:</strong> شما می‌توانید انتخاب کنید که کاربران برای ثبت آگهی، بسته عضویت بخرند، یا فقط هزینه ثابتی برای هر نوع آگهی پرداخت کنند، و یا ترکیبی از هر دو!', 'wp-directory'); ?></p>
        <p><?php _e('<strong>سیستم اعلان‌های هوشمند:</strong> از تب "اعلان‌ها"، تمام ایمیل‌ها و پیامک‌های ارسالی به کاربران را مدیریت کنید. همچنین می‌توانید از صفحه ویرایش "نوع آگهی"، مشخص کنید که برای آن نوع خاص کدام اعلان‌ها ارسال شوند.', 'wp-directory'); ?></p>
        <p><?php _e('<strong>مدیریت ظاهری پیشرفته:</strong> از تب "تنظیمات ظاهری" می‌توانید رنگ‌ها، فونت‌ها و استایل‌های اصلی افزونه را بدون نیاز به کدنویسی تغییر دهید.', 'wp-directory'); ?></p>
        <?php
    }

    public function sanitize_settings( $input ) {
        $new_input = [];
        if (empty($input) || !is_array($input)) {
            return $new_input;
        }
    
        foreach ($input as $section => $values) {
            if (!is_array($values)) {
                $new_input[$section] = sanitize_text_field($values);
                continue;
            }
    
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    // This handles the nested 'appearance' section
                    foreach ($value as $sub_key => $sub_value) {
                        if (strpos($sub_key, '_color') !== false) {
                            $new_input[$section][$key][$sub_key] = sanitize_hex_color($sub_value);
                        } elseif ($sub_key === 'custom_css') {
                             $new_input[$section][$key][$sub_key] = wp_strip_all_tags($sub_value);
                        } else {
                            $new_input[$section][$key][$sub_key] = sanitize_text_field($sub_value);
                        }
                    }
                } else {
                    // This handles top-level sections like 'general', 'payments', etc.
                    if (strpos($key, '_color') !== false) {
                        $new_input[$section][$key] = sanitize_hex_color($value);
                    } elseif (strpos($key, 'email_body_') === 0) {
                        $new_input[$section][$key] = wp_kses_post($value);
                    } else {
                        $new_input[$section][$key] = sanitize_text_field($value);
                    }
                }
            }
        }
        return $new_input;
    }
    
    public function add_general_fields($section_id){
        add_settings_field('submit_page', 'صفحه ثبت آگهی', [$this, 'render_page_dropdown_element'], 'wpd_settings_general', $section_id, ['section' => 'general', 'id' => 'submit_page']);
        add_settings_field('dashboard_page', 'صفحه داشبورد کاربری', [$this, 'render_page_dropdown_element'], 'wpd_settings_general', $section_id, ['section' => 'general', 'id' => 'dashboard_page']);
        add_settings_field('archive_page', 'صفحه آرشیو آگهی‌ها', [$this, 'render_page_dropdown_element'], 'wpd_settings_general', $section_id, ['section' => 'general', 'id' => 'archive_page']);
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

    private function add_appearance_global_fields($section_id) {
        add_settings_field('primary_color', __('رنگ اصلی', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_global', $section_id, ['section' => 'appearance', 'sub_section' => 'global', 'id' => 'primary_color']);
        add_settings_field('secondary_color', __('رنگ ثانویه', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_global', $section_id, ['section' => 'appearance', 'sub_section' => 'global', 'id' => 'secondary_color']);
        add_settings_field('text_color', __('رنگ متن', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_global', $section_id, ['section' => 'appearance', 'sub_section' => 'global', 'id' => 'text_color']);
        add_settings_field('background_color', __('رنگ پس‌زمینه', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_global', $section_id, ['section' => 'appearance', 'sub_section' => 'global', 'id' => 'background_color']);
        add_settings_field('border_color', __('رنگ خطوط', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_global', $section_id, ['section' => 'appearance', 'sub_section' => 'global', 'id' => 'border_color']);
        add_settings_field('main_font', 'فونت اصلی', [$this, 'render_select_element'], 'wpd_settings_appearance_global', $section_id, ['section' => 'appearance', 'sub_section' => 'global', 'id' => 'main_font', 'options' => ['vazir' => 'وزیرمتن', 'iransans' => 'ایران سنس', 'dana' => 'دانا', 'custom' => 'استفاده از فونت قالب']]);
        add_settings_field('base_font_size', __('اندازه فونت پایه', 'wp-directory'), [$this, 'render_text_input_element'], 'wpd_settings_appearance_global', $section_id, ['section' => 'appearance', 'sub_section' => 'global', 'id' => 'base_font_size', 'desc' => 'مثال: 16px']);
        add_settings_field('main_border_radius', __('شعاع لبه عناصر', 'wp-directory'), [$this, 'render_text_input_element'], 'wpd_settings_appearance_global', $section_id, ['section' => 'appearance', 'sub_section' => 'global', 'id' => 'main_border_radius', 'desc' => 'مثال: 8px']);
        add_settings_field('button_border_radius', __('شعاع لبه دکمه‌ها', 'wp-directory'), [$this, 'render_text_input_element'], 'wpd_settings_appearance_global', $section_id, ['section' => 'appearance', 'sub_section' => 'global', 'id' => 'button_border_radius', 'desc' => 'مثال: 5px']);
    }

    private function add_appearance_archive_fields($section_id) {
        add_settings_field('card_bg_color', __('رنگ پس‌زمینه کارت', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_archive', $section_id, ['section' => 'appearance', 'sub_section' => 'archive', 'id' => 'card_bg_color']);
        add_settings_field('card_border_shadow', __('حاشیه و سایه کارت', 'wp-directory'), [$this, 'render_text_input_element'], 'wpd_settings_appearance_archive', $section_id, ['section' => 'appearance', 'sub_section' => 'archive', 'id' => 'card_border_shadow', 'desc' => 'مثال: 1px solid #eee یا 0 5px 15px rgba(0,0,0,0.1)']);
        add_settings_field('title_color', __('رنگ عنوان', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_archive', $section_id, ['section' => 'appearance', 'sub_section' => 'archive', 'id' => 'title_color']);
        add_settings_field('title_font_size', __('اندازه فونت عنوان', 'wp-directory'), [$this, 'render_text_input_element'], 'wpd_settings_appearance_archive', $section_id, ['section' => 'appearance', 'sub_section' => 'archive', 'id' => 'title_font_size', 'desc' => 'مثال: 1.2em']);
        add_settings_field('meta_color', __('رنگ متاداده', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_archive', $section_id, ['section' => 'appearance', 'sub_section' => 'archive', 'id' => 'meta_color']);
        add_settings_field('meta_font_size', __('اندازه فونت متاداده', 'wp-directory'), [$this, 'render_text_input_element'], 'wpd_settings_appearance_archive', $section_id, ['section' => 'appearance', 'sub_section' => 'archive', 'id' => 'meta_font_size', 'desc' => 'مثال: 0.9em']);
        add_settings_field('featured_badge_bg', __('پس‌زمینه برچسب ویژه', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_archive', $section_id, ['section' => 'appearance', 'sub_section' => 'archive', 'id' => 'featured_badge_bg']);
        add_settings_field('featured_badge_color', __('رنگ متن برچسب ویژه', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_archive', $section_id, ['section' => 'appearance', 'sub_section' => 'archive', 'id' => 'featured_badge_color']);
    }

    private function add_appearance_single_fields($section_id) {
        add_settings_field('main_title_color', __('رنگ عنوان اصلی', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_single', $section_id, ['section' => 'appearance', 'sub_section' => 'single', 'id' => 'main_title_color']);
        add_settings_field('main_title_font_size', __('اندازه فونت عنوان اصلی', 'wp-directory'), [$this, 'render_text_input_element'], 'wpd_settings_appearance_single', $section_id, ['section' => 'appearance', 'sub_section' => 'single', 'id' => 'main_title_font_size', 'desc' => 'مثال: 2em']);
        add_settings_field('section_title_color', __('رنگ عناوین بخش‌ها', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_single', $section_id, ['section' => 'appearance', 'sub_section' => 'single', 'id' => 'section_title_color']);
    }

    private function add_appearance_forms_fields($section_id) {
        add_settings_field('input_bg_color', __('پس‌زمینه فیلدها', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_forms', $section_id, ['section' => 'appearance', 'sub_section' => 'forms', 'id' => 'input_bg_color']);
        add_settings_field('input_text_color', __('رنگ متن فیلدها', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_forms', $section_id, ['section' => 'appearance', 'sub_section' => 'forms', 'id' => 'input_text_color']);
        add_settings_field('input_border_color', __('رنگ حاشیه فیلدها', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_forms', $section_id, ['section' => 'appearance', 'sub_section' => 'forms', 'id' => 'input_border_color']);
        add_settings_field('input_focus_border_color', __('رنگ حاشیه در حالت فوکوس', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_forms', $section_id, ['section' => 'appearance', 'sub_section' => 'forms', 'id' => 'input_focus_border_color']);
        add_settings_field('primary_button_bg_color', __('پس‌زمینه دکمه اصلی', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_forms', $section_id, ['section' => 'appearance', 'sub_section' => 'forms', 'id' => 'primary_button_bg_color']);
        add_settings_field('primary_button_text_color', __('رنگ متن دکمه اصلی', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_forms', $section_id, ['section' => 'appearance', 'sub_section' => 'forms', 'id' => 'primary_button_text_color']);
    }

    private function add_appearance_dashboard_fields($section_id) {
        add_settings_field('nav_bg_color', __('پس‌زمینه تب عادی', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_dashboard', $section_id, ['section' => 'appearance', 'sub_section' => 'dashboard', 'id' => 'nav_bg_color']);
        add_settings_field('nav_text_color', __('رنگ متن تب عادی', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_dashboard', $section_id, ['section' => 'appearance', 'sub_section' => 'dashboard', 'id' => 'nav_text_color']);
        add_settings_field('nav_active_bg_color', __('پس‌زمینه تب فعال', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_dashboard', $section_id, ['section' => 'appearance', 'sub_section' => 'dashboard', 'id' => 'nav_active_bg_color']);
        add_settings_field('nav_active_text_color', __('رنگ متن تب فعال', 'wp-directory'), [$this, 'render_color_picker_element'], 'wpd_settings_appearance_dashboard', $section_id, ['section' => 'appearance', 'sub_section' => 'dashboard', 'id' => 'nav_active_text_color']);
    }

    private function add_appearance_custom_css_fields($section_id) {
        add_settings_field('custom_css', __('کد CSS سفارشی', 'wp-directory'), [$this, 'render_textarea_element'], 'wpd_settings_appearance_custom_css', $section_id, ['section' => 'appearance', 'sub_section' => 'custom_css', 'id' => 'custom_css', 'desc' => 'کدهای CSS وارد شده در این بخش، در انتهای تمام استایل‌های افزونه بارگذاری می‌شوند.']);
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
                                    <?php echo Directory_Main::get_term('button_check_connection'); ?>
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
        $sms_providers = ['kavenegar' => 'کاوه نگار', 'farazsms' => 'فراز اس ام اس'];
        echo '<div class="wpd-accordion">';
        foreach ($sms_providers as $id => $label) {
            ?>
            <div class="wpd-accordion-item">
                <button type="button" class="wpd-accordion-header"><?php echo esc_html($label); ?></button>
                <div class="wpd-accordion-content">
                    <table class="form-table">
                        <tr>
                            <th><?php _e('فعال‌سازی این سرویس‌دهنده', 'wp-directory'); ?></th>
                            <td><?php $this->render_radio_element_for_provider(['section' => 'sms', 'id' => 'provider', 'value' => $id]); ?></td>
                        </tr>
                        <?php if ($id === 'kavenegar'): ?>
                        <tr>
                            <th><label for="wpd_settings_sms_kavenegar_api_key"><?php _e('کلید API کاوه نگار', 'wp-directory'); ?></label></th>
                            <td>
                                <?php $this->render_text_input_element(['section' => 'sms', 'id' => 'kavenegar_api_key']); ?>
                                <button type="button" class="button button-secondary wpd-verify-service-btn" data-service="kavenegar" data-field-id="wpd_settings_sms_kavenegar_api_key">
                                    <?php echo Directory_Main::get_term('button_check_connection'); ?>
                                </button>
                                <span class="wpd-verification-status"></span>
                            </td>
                        </tr>
                        <?php elseif ($id === 'farazsms'): ?>
                        <tr>
                            <th><label for="wpd_settings_sms_farazsms_api_key"><?php _e('کلید API فراز اس ام اس', 'wp-directory'); ?></label></th>
                            <td>
                                <?php $this->render_text_input_element(['section' => 'sms', 'id' => 'farazsms_api_key']); ?>
                                 <button type="button" class="button button-secondary wpd-verify-service-btn" data-service="farazsms" data-field-id="wpd_settings_sms_farazsms_api_key" data-extra-field-id="wpd_settings_sms_farazsms_sender_number">
                                    <?php echo Directory_Main::get_term('button_check_connection'); ?>
                                </button>
                                <span class="wpd-verification-status"></span>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="wpd_settings_sms_farazsms_sender_number"><?php _e('شماره فرستنده فراز اس ام اس', 'wp-directory'); ?></label></th>
                            <td><?php $this->render_text_input_element(['section' => 'sms', 'id' => 'farazsms_sender_number']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            <?php
        }
        echo '</div>';
        echo '<p class="description">' . __('تنها یک سرویس‌دهنده می‌تواند فعال باشد. با انتخاب یک گزینه، سرویس‌دهنده قبلی غیرفعال می‌شود.', 'wp-directory') . '</p>';
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
                        <button type="button" class="button button-secondary wpd-modal-trigger" data-modal-target="#wpd-email-modal-<?php echo esc_attr($id); ?>"><?php echo Directory_Main::get_term('button_edit_template'); ?></button>
                    </td>
                    <td>
                        <?php $this->render_switch_element(['section' => 'notifications', 'id' => "sms_enable_{$id}"]); ?>
                        <button type="button" class="button button-secondary wpd-modal-trigger" data-modal-target="#wpd-sms-modal-<?php echo esc_attr($id); ?>"><?php echo Directory_Main::get_term('button_settings'); ?></button>
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
                            <td><?php $this->render_text_input_element(['section' => 'notifications', 'id' => "sms_pattern_{$id}"]); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endforeach;
    }
    
    public function render_page_dropdown_element($args) {
        $options = Directory_Main::get_option($args['section'], []);
        $id = $args['id'];
        $value = $options[$id] ?? '';
        wp_dropdown_pages([
            'name' => 'wpd_settings['.$args['section'].']['.$id.']',
            'id' => 'wpd_settings_'.$args['section'].'_'.$id,
            'selected' => $value,
            'show_option_none' => '— ' . __('انتخاب کنید', 'wp-directory') . ' —'
        ]);
    }

    public function render_checkbox_element($args){
        $options = Directory_Main::get_option($args['section'], []);
        $value = $options[$args['id']] ?? '0';
        $desc = $args['desc'] ?? '';
        echo '<label><input type="checkbox" name="wpd_settings['.$args['section'].']['.$args['id'].']" value="1" '.checked(1, $value, false).' /> '.wp_kses_post($desc).'</label>';
    }

    public function render_radio_element_for_provider($args) {
        $options = Directory_Main::get_option($args['section'], []);
        $id = $args['id'];
        $current_value = $options[$id] ?? '';
        $radio_value = $args['value'];
        echo '<label><input type="radio" name="wpd_settings['.$args['section'].']['.$id.']" value="'.esc_attr($radio_value).'" '.checked($current_value, $radio_value, false).' /> '. __('فعال', 'wp-directory') .'</label>';
    }

    public function render_text_input_element($args){
        $section = $args['section'];
        $sub_section = $args['sub_section'] ?? null;
        $id = $args['id'];
        $full_id_name = $sub_section ? "{$section}[{$sub_section}][{$id}]" : "{$section}[{$id}]";
        $full_id_attr = str_replace(['[', ']'], '_', $full_id_name);
        
        $options = Directory_Main::get_option($section, []);
        $value = $sub_section ? ($options[$sub_section][$id] ?? ($args['default'] ?? '')) : ($options[$id] ?? ($args['default'] ?? ''));
        
        $placeholder = $args['placeholder'] ?? $args['default'] ?? '';
        echo '<input type="text" id="wpd_settings_'.esc_attr($full_id_attr).'" name="wpd_settings['.$full_id_name.']" value="'.esc_attr($value).'" class="regular-text" placeholder="'.esc_attr($placeholder).'" />';
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
        $section = $args['section'];
        $sub_section = $args['sub_section'] ?? null;
        $id = $args['id'];
        $full_id_name = $sub_section ? "{$section}[{$sub_section}][{$id}]" : "{$section}[{$id}]";
        
        $options = Directory_Main::get_option($section, []);
        $value = $sub_section ? ($options[$sub_section][$id] ?? '') : ($options[$id] ?? '');
        
        echo '<input type="text" name="wpd_settings['.$full_id_name.']" value="'.esc_attr($value).'" class="wpd-color-picker" />';
    }
    
    public function render_select_element($args){
        $section = $args['section'];
        $sub_section = $args['sub_section'] ?? null;
        $id = $args['id'];
        $full_id_name = $sub_section ? "{$section}[{$sub_section}][{$id}]" : "{$section}[{$id}]";
        $full_id_attr = str_replace(['[', ']'], '_', $full_id_name);

        $options = Directory_Main::get_option($section, []);
        $value = $sub_section ? ($options[$sub_section][$id] ?? '') : ($options[$id] ?? '');
        $select_options = $args['options'] ?? [];
         
        echo '<select id="wpd_settings_'.esc_attr($full_id_attr).'" name="wpd_settings['.$full_id_name.']">';
        foreach($select_options as $opt_key => $opt_name){
            echo '<option value="'.esc_attr($opt_key).'" '.selected($value, $opt_key, false).'>'.esc_html($opt_name).'</option>';
        }
        echo '</select>';
        if(isset($args['desc'])) echo '<p class="description">'.wp_kses_post($args['desc']).'</p>';
    }

    public function render_textarea_element($args) {
        $section = $args['section'];
        $sub_section = $args['sub_section'] ?? null;
        $id = $args['id'];
        $full_id_name = $sub_section ? "{$section}[{$sub_section}][{$id}]" : "{$section}[{$id}]";
        $full_id_attr = str_replace(['[', ']'], '_', $full_id_name);

        $options = Directory_Main::get_option($section, []);
        $value = $sub_section ? ($options[$sub_section][$id] ?? '') : ($options[$id] ?? '');

        echo '<textarea id="wpd_settings_'.esc_attr($full_id_attr).'" name="wpd_settings['.$full_id_name.']" class="large-text" rows="10" style="direction: ltr; text-align: left;">'.esc_textarea($value).'</textarea>';
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
            .wpd-settings-tab, .wpd-settings-sub-tab { display: none; }
            .wpd-settings-tab.active, .wpd-settings-sub-tab.active { display: block; }
            .wpd-sub-nav-tab-wrapper { margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 0; display: inline-block; width: 100%; }
            .wpd-sub-nav-tab-wrapper .nav-tab { float: right; }
            .wpd-settings-wrap form .form-table { margin-top: 0; }
            .wpd-settings-wrap form { clear: both; }
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
        // BUG FIX: Changed heredoc from <<<'JS' to <<<JS to allow PHP variable parsing.
        return <<<JS
            jQuery(document).ready(function($){
                // --- Tabbing Logic ---
                function setupTabs(wrapperSelector, contentClass, contentIdPrefix, activeClass, isSubTab = false) {
                    var \$wrapper = $(wrapperSelector);
                    if (!\$wrapper.length) return;

                    \$wrapper.on('click', '.nav-tab', function(e) {
                        e.preventDefault();
                        var \$this = $(this);
                        var tabId = isSubTab ? \$this.data('sub-tab') : \$this.data('tab');
                        
                        \$wrapper.find('.nav-tab').removeClass(activeClass);
                        \$this.addClass(activeClass);
                        
                        $(contentClass).hide().removeClass('active');
                        $(contentIdPrefix + tabId).show().addClass('active');

                        var newHash = '#';
                        if (isSubTab) {
                            var mainHash = window.location.hash.split('/')[0].replace('#', '') || 'appearance';
                            newHash += mainHash + '/' + tabId;
                        } else {
                            newHash += tabId;
                        }
                        
                        if (history.replaceState) {
                            history.replaceState(null, null, newHash);
                        } else {
                            window.location.hash = newHash;
                        }
                    });
                }

                function activateTabsFromHash() {
                    var hash = window.location.hash.replace('#', '');
                    var mainTabId, subTabId;

                    if (hash) {
                        var parts = hash.split('/');
                        mainTabId = parts[0];
                        subTabId = parts[1];
                    } else {
                        // Default to the first main tab if no hash
                        mainTabId = $('.wpd-nav-tab-wrapper .nav-tab:first').data('tab');
                    }

                    // Activate main tab
                    var \$mainTab = $('.wpd-nav-tab-wrapper .nav-tab[data-tab="' + mainTabId + '"]');
                    if (\$mainTab.length) {
                         $('.wpd-nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                         $('.wpd-settings-tab').hide().removeClass('active');
                         \$mainTab.addClass('nav-tab-active');
                         $('#tab-' + mainTabId).show().addClass('active');
                    }

                    // Activate sub-tab if we are on the appearance page
                    if ($('.wpd-sub-nav-tab-wrapper').length) {
                        subTabId = subTabId || 'global'; // Default to global sub-tab
                        var \$subTab = $('.wpd-sub-nav-tab-wrapper .nav-tab[data-sub-tab="' + subTabId + '"]');
                        if (\$subTab.length) {
                           $('.wpd-sub-nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                           $('.wpd-settings-sub-tab').hide().removeClass('active');
                           \$subTab.addClass('nav-tab-active');
                           $('#sub-tab-' + subTabId).show().addClass('active');
                        }
                    }
                }

                setupTabs('.wpd-nav-tab-wrapper', '.wpd-settings-tab', '#tab-', 'nav-tab-active', false);
                setupTabs('.wpd-sub-nav-tab-wrapper', '.wpd-settings-sub-tab', '#sub-tab-', 'nav-tab-active', true);
                
                // Set initial state on page load
                if ($('.wpd-settings-wrap').length) {
                    activateTabsFromHash();
                }

                // --- Other Scripts ---
                $('.wpd-color-picker').wpColorPicker();

                $('.wpd-accordion-header').on('click', function(){
                    $(this).toggleClass('active');
                    $(this).next('.wpd-accordion-content').slideToggle('fast');
                });

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

                $('.wpd-verify-service-btn').on('click', function(e) {
                    e.preventDefault();
                    var \$button = $(this);
                    var service = \$button.data('service');
                    var fieldId = \$button.data('field-id');
                    var extraFieldId = \$button.data('extra-field-id');
                    var apiKey = $('#' + fieldId).val();
                    var extraData = extraFieldId ? $('#' + extraFieldId).val() : '';
                    var \$statusSpan = \$button.siblings('.wpd-verification-status');

                    \$statusSpan.removeClass('success error').text('در حال بررسی...').css('color', 'orange');
                    \$button.prop('disabled', true);

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
                                \$statusSpan.addClass('success').text(response.data.message);
                            } else {
                                \$statusSpan.addClass('error').text(response.data.message);
                            }
                        },
                        error: function() {
                            \$statusSpan.addClass('error').text('خطای ارتباط با سرور.');
                        },
                        complete: function() {
                            \$button.prop('disabled', false);
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

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'wpd_dashboard_widget',
            'خلاصه وضعیت نیلای دایرکتوری',
            [ $this, 'render_dashboard_widget' ]
        );
    }

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
                    <span><a href="<?php echo admin_url('edit.php?post_status=pending&post_type=wpd_listing'); ?>"><?php printf(__('%s در انتظار تایید', 'wp-directory'), Directory_Main::get_term('listing')); ?></a></span>
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
            <h1><?php echo Directory_Main::get_term('reports_page_title'); ?></h1>
            <div id="wpd-reports-overview" class="wpd-reports-row">
                <div class="wpd-report-card"><h3><?php _e('درآمد کل', 'wp-directory'); ?></h3><p><?php echo esc_html(number_format($total_revenue ?? 0)); ?> <?php echo esc_html($currency); ?></p></div>
                <div class="wpd-report-card"><h3><?php _e('تراکنش‌های موفق', 'wp-directory'); ?></h3><p><?php echo esc_html(number_format($total_transactions ?? 0)); ?></p></div>
                <div class="wpd-report-card"><h3><?php printf(__('کل %s فعال', 'wp-directory'), Directory_Main::get_term('listings')); ?></h3><p><?php echo esc_html(number_format($total_listings ?? 0)); ?></p></div>
            </div>
            <div id="wpd-reports-main" class="wpd-reports-row">
                <div class="wpd-report-card main-chart">
                    <h3><?php _e('درآمد ۶ ماه اخیر', 'wp-directory'); ?></h3>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="wpd-report-card">
                    <h3><?php printf(__('%s بر اساس نوع', 'wp-directory'), Directory_Main::get_term('listings')); ?></h3>
                    <table class="widefat striped">
                        <thead><tr><th><?php echo Directory_Main::get_term('listing_type'); ?></th><th><?php _e('تعداد', 'wp-directory'); ?></th></tr></thead>
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

    public function render_tools_page() {
        ?>
        <div class="wrap">
            <h1><?php echo Directory_Main::get_term('tools_page_title'); ?></h1>
            
            <h2 class="nav-tab-wrapper wpd-tools-tab-wrapper">
                <a href="#system-status" data-tab="system-status" class="nav-tab nav-tab-active"><?php echo Directory_Main::get_term('tools_tab_system_status'); ?></a>
                <a href="#tools" data-tab="tools" class="nav-tab"><?php echo Directory_Main::get_term('tools_tab_maintenance'); ?></a>
                <a href="#data-management" data-tab="data-management" class="nav-tab"><?php echo Directory_Main::get_term('tools_tab_data_management'); ?></a>
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
                    if (activeTab === '' || $tabWrapper.find('.nav-tab[data-tab="' + activeTab + '"]').length === 0) {
                        activeTab = $tabWrapper.find('.nav-tab:first').data('tab');
                    }
                    
                    $tabWrapper.find('.nav-tab').removeClass('nav-tab-active');
                    $('.wpd-settings-tab').removeClass('active').hide();
                    
                    $tabWrapper.find('.nav-tab[data-tab="' + activeTab + '"]').addClass('nav-tab-active');
                    $('#tab-' + activeTab).addClass('active').show();

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
                
                $('.wpd-import-form').on('submit', function(e) {
                    var $form = $(this);
                    var fileInput = $form.find('input[type="file"]');
                    if (fileInput.val() === '') {
                        e.preventDefault();
                        alert('<?php _e('لطفا یک فایل برای وارد کردن انتخاب کنید.', 'wp-directory'); ?>');
                    } else {
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
        <h3 style="margin-top:20px;"><?php echo Directory_Main::get_term('tools_tab_maintenance'); ?></h3>
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
                        <p class="description"><?php printf(__('این ابزار به صورت دستی رویدادهای زمان‌بندی شده روزانه (مانند منقضی کردن %s) را اجرا می‌کند.', 'wp-directory'), Directory_Main::get_term('listings')); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('ساخت برگه‌های پیش‌فرض', 'wp-directory'); ?></th>
                    <td>
                         <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpd-tools&wpd_tool_action=create_default_pages'), 'wpd_create_default_pages_nonce'); ?>" class="button button-primary"><?php _e('ساخت برگه‌ها', 'wp-directory'); ?></a>
                        <p class="description"><?php printf(__('این ابزار برگه‌های ضروری برای ثبت %s، داشبورد کاربری و آرشیو %s را می‌سازد.', 'wp-directory'), Directory_Main::get_term('listing'), Directory_Main::get_term('listings')); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

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
            
            <h3><?php echo Directory_Main::get_term('listing_type'); ?></h3>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><?php printf(__('برون‌بری انواع %s', 'wp-directory'), Directory_Main::get_term('listing')); ?></th>
                        <td>
                            <form method="get" class="wpd-export-form" style="display: flex; gap: 10px;">
                                <input type="hidden" name="page" value="wpd-tools">
                                <input type="hidden" name="wpd_tool_action" value="export_listing_types_selected">
                                <?php wp_nonce_field('wpd_export_listing_types_nonce_selected'); ?>
                                <select name="listing_type_id" id="export-listing-type" required>
                                    <option value=""><?php printf(__('انتخاب نوع %s...', 'wp-directory'), Directory_Main::get_term('listing')); ?></option>
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
                            <p class="description"><?php printf(__('یک نوع %s را انتخاب کرده یا همه را برون‌بری کنید. این شامل فیلدهای سفارشی و طبقه‌بندی‌های مرتبط نیز می‌شود.', 'wp-directory'), Directory_Main::get_term('listing')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php printf(__('درون‌ریزی انواع %s', 'wp-directory'), Directory_Main::get_term('listing')); ?></th>
                        <td>
                            <form method="post" enctype="multipart/form-data" class="wpd-import-form">
                                <?php wp_nonce_field('wpd_import_listing_types_nonce'); ?>
                                <input type="hidden" name="wpd_tool_action" value="import_listing_types">
                                <input type="file" name="import_file" accept=".json" required>
                                <button type="submit" class="button button-primary"><?php _e('آپلود و وارد کردن', 'wp-directory'); ?></button>
                            </form>
                            <p class="description"><?php printf(__('فایل JSON انواع %s را برای بارگذاری انتخاب کنید.', 'wp-directory'), Directory_Main::get_term('listing')); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h3><?php printf(__('%s ثبت شده', 'wp-directory'), Directory_Main::get_term('listings')); ?></h3>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><?php printf(__('برون‌بری %s', 'wp-directory'), Directory_Main::get_term('listings')); ?></th>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpd-tools&wpd_tool_action=export_listings'), 'wpd_export_listings_nonce'); ?>" class="button"><?php _e('دانلود فایل', 'wp-directory'); ?></a>
                            <p class="description"><?php printf(__('تمام %s، محتوا و اطلاعات متا آن‌ها را برون‌بری می‌کند. (ممکن است زمان‌بر باشد)', 'wp-directory'), Directory_Main::get_term('listings')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php printf(__('درون‌ریزی %s', 'wp-directory'), Directory_Main::get_term('listings')); ?></th>
                        <td>
                            <form method="post" enctype="multipart/form-data" class="wpd-import-form">
                                <?php wp_nonce_field('wpd_import_listings_nonce'); ?>
                                <input type="hidden" name="wpd_tool_action" value="import_listings">
                                <input type="file" name="import_file" accept=".json" required>
                                <button type="submit" class="button button-primary"><?php _e('آپلود و وارد کردن', 'wp-directory'); ?>...</button>
                            </form>
                            <p class="description"><?php printf(__('فایل JSON %s را برای بارگذاری انتخاب کنید.', 'wp-directory'), Directory_Main::get_term('listings')); ?></p>
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
                        set_transient('wpd_admin_notice', ['message' => __('کش افزونه با موفقیت پاک شد.', 'wp-directory'), 'type' => 'success'], 5);
                        wp_redirect(remove_query_arg(['wpd_tool_action', '_wpnonce']));
                        exit;
                    }
                    break;
                case 'run_daily_events':
                    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'wpd_run_daily_events_nonce')) {
                        do_action('wpd_daily_scheduled_events');
                        set_transient('wpd_admin_notice', ['message' => __('رویدادهای روزانه با موفقیت اجرا شدند.', 'wp-directory'), 'type' => 'success'], 5);
                        wp_redirect(remove_query_arg(['wpd_tool_action', '_wpnonce']));
                        exit;
                    }
                    break;
                case 'create_default_pages':
                     if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'wpd_create_default_pages_nonce')) {
                        $this->create_default_pages_handler();
                        set_transient('wpd_admin_notice', ['message' => __('برگه‌های پیش‌فرض افزونه با موفقیت ساخته یا به‌روزرسانی شدند.', 'wp-directory'), 'type' => 'success'], 5);
                        wp_redirect(remove_query_arg(['wpd_tool_action', '_wpnonce']));
                        exit;
                     }
                    break;
                // Other cases remain unchanged
                case 'export_settings': $this->handle_export_settings(); break;
                case 'import_settings': $this->handle_import_settings(); break;
                case 'export_listing_types_all': $this->handle_export_listing_types(); break;
                case 'export_listing_types_selected': $this->handle_export_listing_types_selected(); break;
                case 'import_listing_types': $this->handle_import_listing_types(); break;
                case 'export_listings': $this->handle_export_listings(); break;
                case 'import_listings': $this->handle_import_listings(); break;
            }
        }
    }
    
    public function show_admin_notices() {
        if ( $notice = get_transient( 'wpd_admin_notice' ) ) {
            ?>
            <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
                <p><?php echo esc_html( $notice['message'] ); ?></p>
            </div>
            <?php
            delete_transient( 'wpd_admin_notice' );
        }
    }

    public function create_default_pages_handler() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $pages = [
            'submit_page' => [
                'title' => Directory_Main::get_term('submit_listing'),
                'content' => '[wpd_submit_form]',
            ],
            'dashboard_page' => [
                'title' => 'داشبورد کاربری',
                'content' => '[wpd_dashboard]',
            ],
            'archive_page' => [
                'title' => sprintf(__('آرشیو %s', 'wp-directory'), Directory_Main::get_term('listings')),
                'content' => '[wpd_listing_archive]',
            ],
        ];

        $page_ids = [];
        $existing_pages = Directory_Main::get_option('general', []);
        
        foreach ($pages as $option_key => $page_data) {
            $existing_page_id = $existing_pages[$option_key] ?? 0;
            if (empty($existing_page_id) || get_post_status($existing_page_id) === false) {
                 $new_page_id = wp_insert_post([
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                 ]);
                if (!is_wp_error($new_page_id)) {
                    $page_ids[$option_key] = $new_page_id;
                }
            } else {
                $page_ids[$option_key] = $existing_page_id;
            }
        }

        $all_settings = get_option('wpd_settings', []);
        $all_settings['general'] = array_merge($existing_pages, $page_ids);
        update_option('wpd_settings', $all_settings);
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
                         update_post_meta($post_id, $key, $value);
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
                         update_post_meta($post_id, $key, $value);
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
            <h1><?php echo Directory_Main::get_term('add_manual_transaction_title'); ?></h1>
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
