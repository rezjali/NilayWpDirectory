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
            'amount'         => __( 'مبلغ (تومان)', 'wp-directory' ),
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
                $status_label = __(ucfirst($item[$column_name]), 'wp-directory');
                $status_class = sanitize_key($item[$column_name]);
                return '<span class="wpd-status-' . $status_class . '">' . $status_label . '</span>';
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
    }

    public function add_admin_menu() {
        add_menu_page( 'نیلای دایرکتوری', 'نیلای دایرکتوری', 'manage_options', 'wpd-main-menu', [ $this, 'render_settings_page' ], 'dashicons-location-alt', 20 );
        add_submenu_page( 'wpd-main-menu', __( 'تنظیمات', 'wp-directory' ), __( 'تنظیمات', 'wp-directory' ), 'manage_options', 'wpd-main-menu' );
        add_submenu_page( 'wpd-main-menu', __( 'بسته‌های عضویت', 'wp-directory' ), __( 'بسته‌های عضویت', 'wp-directory' ), 'manage_options', 'edit.php?post_type=wpd_package' );
        $hook = add_submenu_page( 'wpd-main-menu', __( 'تراکنش‌ها', 'wp-directory' ), __( 'تراکنش‌ها', 'wp-directory' ), 'manage_options', 'wpd-transactions', [ $this, 'render_transactions_page' ] );
        add_action( "load-$hook", [ $this, 'screen_options' ] );
    }

    public function enqueue_admin_scripts( $hook ) {
        // **تغییر:** استایل‌ها را فقط در صفحه تراکنش‌ها بارگذاری می‌کنیم
        if ( strpos($hook, 'wpd-transactions') !== false ) {
            $css = '.wpd-status-completed { color: green; } .wpd-status-failed { color: red; } .wpd-status-pending { color: orange; }';
            wp_add_inline_style('common', $css);
        }

        // **تغییر:** شرط را اصلاح می‌کنیم تا اسکریپت فقط در صفحه تنظیمات اصلی بارگذاری شود
        if ( $hook !== 'toplevel_page_wpd-main-menu' ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        
        wp_add_inline_script( 'wp-color-picker', '
            jQuery(document).ready(function($){
                console.log("Admin script for Nilay Directory loaded."); // دیباگ
                
                $(".wpd-color-picker").wpColorPicker();
                
                var activeTab = window.location.hash.replace("#", "");
                if (activeTab === "" || $(".wpd-nav-tab-wrapper .nav-tab[data-tab=\'" + activeTab + "\']").length === 0) {
                    activeTab = $(".wpd-nav-tab-wrapper .nav-tab:first").data("tab");
                    console.log("No valid hash found, defaulting to first tab:", activeTab); // دیباگ
                } else {
                    console.log("Active tab from hash:", activeTab); // دیباگ
                }
                
                $(".wpd-nav-tab-wrapper .nav-tab").removeClass("nav-tab-active");
                $(".wpd-settings-tab").removeClass("active");

                $(".wpd-nav-tab-wrapper .nav-tab[data-tab=\'" + activeTab + "\']").addClass("nav-tab-active");
                $("#tab-" + activeTab).addClass("active");

                $(".wpd-nav-tab-wrapper .nav-tab").click(function(e){
                    e.preventDefault();
                    var tab_id = $(this).data("tab");
                    console.log("Tab clicked:", tab_id); // دیباگ
                    
                    $(".wpd-nav-tab-wrapper .nav-tab").removeClass("nav-tab-active");
                    $(".wpd-settings-tab").removeClass("active");
                    
                    $(this).addClass("nav-tab-active");
                    $("#tab-" + tab_id).addClass("active");
                    window.location.hash = tab_id;
                });
            });
        ');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
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
                <div id="tab-general" class="wpd-settings-tab"> <?php do_settings_sections( 'wpd_settings_general' ); ?> </div>
                <div id="tab-payments" class="wpd-settings-tab"> <?php do_settings_sections( 'wpd_settings_payments' ); ?> </div>
                <div id="tab-sms" class="wpd-settings-tab"> <?php do_settings_sections( 'wpd_settings_sms' ); ?> </div>
                <div id="tab-notifications" class="wpd-settings-tab"> <?php do_settings_sections( 'wpd_settings_notifications' ); ?> </div>
                <div id="tab-terminology" class="wpd-settings-tab"> <?php do_settings_sections( 'wpd_settings_terminology' ); ?> </div>
                <div id="tab-appearance" class="wpd-settings-tab"> <?php do_settings_sections( 'wpd_settings_appearance' ); ?> </div>
                <div id="tab-help" class="wpd-settings-tab"> <?php $this->render_help_tab_content(); ?> </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <style> .wpd-settings-tab { display: none; } .wpd-settings-tab.active { display: block; } </style>
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
        
        add_settings_section( 'wpd_general_section', __( 'تنظیمات عمومی', 'wp-directory' ), null, 'wpd_settings_general' );
        $this->add_page_selection_fields('general_section');

        add_settings_section( 'wpd_payments_section', __( 'تنظیمات درگاه پرداخت', 'wp-directory' ), null, 'wpd_settings_payments' );
        $this->add_gateway_fields('payments', 'zarinpal', 'زرین پال');
        $this->add_gateway_fields('payments', 'zibal', 'زیبال');
        
        add_settings_section( 'wpd_sms_section', __( 'تنظیمات پنل پیامکی', 'wp-directory' ), null, 'wpd_settings_sms' );
        $this->add_gateway_fields('sms', 'kavenegar', 'کاوه نگار');
        $this->add_gateway_fields('sms', 'farazsms', 'فراز اس ام اس');

        add_settings_section( 'wpd_notifications_section', __( 'قالب‌های اعلان‌ها', 'wp-directory' ), '__return_null', 'wpd_settings_notifications' );
        $this->add_notification_template_fields('notifications');

        add_settings_section( 'wpd_terminology_section', __( 'سفارشی‌سازی اصطلاحات', 'wp-directory' ), '__return_null', 'wpd_settings_terminology' );
        $this->add_terminology_fields('terminology');

        add_settings_section( 'wpd_appearance_section', __( 'تنظیمات ظاهری', 'wp-directory' ), null, 'wpd_settings_appearance' );
        $this->add_appearance_fields('appearance');
    }
    
    public function render_help_tab_content() {
        ?>
        <h2><?php _e('راهنمای استفاده از افزونه نیلای دایرکتوری', 'wp-directory'); ?></h2>
        <p><?php _e('به راهنمای افزونه دایرکتوری خوش آمدید. در اینجا می‌توانید با روش کار و شورت‌کدهای افزونه آشنا شوید.', 'wp-directory'); ?></p>
        
        <h3><?php _e('مراحل راه‌اندازی اولیه', 'wp-directory'); ?></h3>
        <ol>
            <li><?php _e('<strong>ساخت انواع آگهی:</strong> از منوی "نیلای دایرکتوری > انواع آگهی"، نوع‌های مختلف آگهی خود (مثلا املاک، خودرو) را بسازید. در صفحه ویرایش هر نوع، می‌توانید فیلدهای سفارشی و <strong>طبقه‌بندی‌های اختصاصی</strong> آن را با استفاده از "فیلد ساز" و "طبقه‌بندی ساز" تعریف کنید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>ساخت بسته‌های عضویت:</strong> از منوی "نیلای دایرکتوری > بسته‌های عضویت"، بسته‌های رایگان یا پولی خود را تعریف کنید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>ایجاد برگه‌ها:</strong> سه برگه جدید در وردپرس بسازید: یکی برای "ثبت آگهی"، یکی برای "داشبورد کاربری" و یکی برای "آرشیو آگهی‌ها".', 'wp-directory'); ?></li>
            <li><?php _e('<strong>قرار دادن شورت‌کدها:</strong> شورت‌کد مربوط به هر برگه را (از لیست زیر) در آن قرار دهید.', 'wp-directory'); ?></li>
            <li><?php _e('<strong>تنظیمات عمومی:</strong> از تب "عمومی" در همین صفحه، برگه‌هایی که ساختید را به افزونه معرفی کنید.', 'wp-directory'); ?></li>
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
                    <td><?php _e('فرم چند مرحله‌ای ثبت آگهی را نمایش می‌دهد. این شورت‌کد را در برگه "ثبت آگهی" قرار دهید.', 'wp-directory'); ?></td>
                </tr>
                <tr>
                    <td><code>[wpd_dashboard]</code></td>
                    <td><?php _e('داشبورد کاربری را برای مدیریت آگهی‌ها، پروفایل و... نمایش می‌دهد. این شورت‌کد را در برگه "داشبورد کاربری" قرار دهید.', 'wp-directory'); ?></td>
                </tr>
                <tr>
                    <td><code>[wpd_listing_archive]</code></td>
                    <td><?php _e('صفحه آرشیو آگهی‌ها را به همراه فرم جستجو و فیلتر پیشرفته نمایش می‌دهد. این شورت‌کد را در برگه "آرشیو آگهی‌ها" قرار دهید.', 'wp-directory'); ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function sanitize_settings( $input ) {
        $new_input = [];
        if (empty($input) || !is_array($input)) return $new_input;
        
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $new_input[$key] = $this->sanitize_settings($value);
            } else {
                $new_input[$key] = sanitize_text_field($value);
            }
        }
        return $new_input;
    }
    
    private function add_page_selection_fields($section_id){
        $pages = ['submit_page' => 'صفحه ثبت آگهی', 'dashboard_page' => 'صفحه داشبورد کاربری', 'archive_page' => 'صفحه آرشیو آگهی‌ها'];
        foreach($pages as $id => $label){
            add_settings_field($id, $label, [$this, 'render_page_dropdown'], 'wpd_settings_general', $section_id, ['section' => 'general', 'id' => $id]);
        }
    }

    private function add_gateway_fields($section, $gateway_id, $gateway_label){
        add_settings_field($gateway_id.'_enable', sprintf(__('فعال‌سازی %s', 'wp-directory'), $gateway_label), [$this, 'render_checkbox'], 'wpd_settings_'.$section, $section.'_section', ['section' => $section, 'id' => $gateway_id.'_enable']);
        add_settings_field($gateway_id.'_apikey', __('کلید API / مرچنت کد', 'wp-directory'), [$this, 'render_text_input'], 'wpd_settings_'.$section, $section.'_section', ['section' => $section, 'id' => $gateway_id.'_apikey']);
    }

    private function add_notification_template_fields($section){
        $events = ['new_user' => 'ثبت‌نام کاربر جدید', 'new_listing' => 'ثبت آگهی جدید', 'listing_approved' => 'تایید آگهی', 'listing_expired' => 'انقضای آگهی'];
        foreach($events as $id => $label){
            add_settings_field('email_subject_'.$id, sprintf(__('موضوع ایمیل: %s', 'wp-directory'), $label), [$this, 'render_text_input'], 'wpd_settings_'.$section, $section.'_section', ['section' => $section, 'id' => 'email_subject_'.$id]);
            add_settings_field('email_body_'.$id, sprintf(__('متن ایمیل: %s', 'wp-directory'), $label), [$this, 'render_textarea'], 'wpd_settings_'.$section, $section.'_section', ['section' => $section, 'id' => 'email_body_'.$id, 'desc' => 'متغیرهای مجاز: {site_name}, {user_name}, {listing_title}']);
            add_settings_field('sms_body_'.$id, sprintf(__('متن پیامک: %s', 'wp-directory'), $label), [$this, 'render_textarea'], 'wpd_settings_'.$section, $section.'_section', ['section' => $section, 'id' => 'sms_body_'.$id, 'desc' => 'متغیرهای مجاز: {site_name}, {user_name}, {listing_title}']);
        }
    }
    
    private function add_terminology_fields($section){
        $default_terms = Directory_Main::get_default_terms();
        foreach($default_terms as $key => $label){
             add_settings_field($key, $label, [$this, 'render_text_input'], 'wpd_settings_'.$section, $section.'_section', ['section' => $section, 'id' => $key, 'label' => $label]);
        }
    }

    private function add_appearance_fields($section){
        $colors = ['primary_color' => 'رنگ اصلی', 'text_color' => 'رنگ متن', 'background_color' => 'رنگ پس‌زمینه'];
        foreach($colors as $id => $label){
            add_settings_field($id, $label, [$this, 'render_color_picker'], 'wpd_settings_'.$section, $section.'_section', ['section' => $section, 'id' => $id]);
        }
        add_settings_field('main_font', 'فونت اصلی', [$this, 'render_font_selector'], 'wpd_settings_'.$section, $section.'_section', ['section' => $section, 'id' => 'main_font']);
    }

    public function render_page_dropdown($args){
        $options = Directory_Main::get_option($args['section'], []);
        $value = $options[$args['id']] ?? '';
        wp_dropdown_pages(['name' => 'wpd_settings['.$args['section'].']['.$args['id'].']', 'selected' => $value, 'show_option_none' => '— انتخاب کنید —']);
    }

    public function render_checkbox($args){
        $options = Directory_Main::get_option($args['section'], []);
        $value = $options[$args['id']] ?? '0';
        echo '<input type="checkbox" name="wpd_settings['.$args['section'].']['.$args['id'].']" value="1" '.checked(1, $value, false).' />';
    }

    public function render_text_input($args){
        $options = Directory_Main::get_option($args['section'], []);
        $value = $options[$args['id']] ?? '';
        $placeholder = $args['label'] ?? '';
        echo '<input type="text" name="wpd_settings['.$args['section'].']['.$args['id'].']" value="'.esc_attr($value).'" class="regular-text" placeholder="'.esc_attr($placeholder).'" />';
    }
    
    public function render_textarea($args){
        $options = Directory_Main::get_option($args['section'], []);
        $value = $options[$args['id']] ?? '';
        echo '<textarea name="wpd_settings['.$args['section'].']['.$args['id'].']" rows="5" class="large-text">'.esc_textarea($value).'</textarea>';
        if(isset($args['desc'])) echo '<p class="description">'.$args['desc'].'</p>';
    }

    public function render_color_picker($args){
        $options = Directory_Main::get_option($args['section'], []);
        $value = $options[$args['id']] ?? '';
        echo '<input type="text" name="wpd_settings['.$args['section'].']['.$args['id'].']" value="'.esc_attr($value).'" class="wpd-color-picker" />';
    }
    
    public function render_font_selector($args){
         $options = Directory_Main::get_option($args['section'], []);
         $value = $options[$args['id']] ?? 'vazir';
         $fonts = ['vazir' => 'وزیرمتن', 'iransans' => 'ایران سنس', 'dana' => 'دانا'];
         echo '<select name="wpd_settings['.$args['section'].']['.$args['id'].']">';
         foreach($fonts as $font_key => $font_name){
             echo '<option value="'.$font_key.'" '.selected($value, $font_key, false).'>'.$font_name.'</option>';
         }
         echo '</select>';
    }
}
