<?php
/**
 * Template for displaying the WPD Listings Archive page.
 * This file is used by the `[wpd_listing_archive]` shortcode and also serves as the main
 * archive template for `wpd_listing` post type.
 *
 * @package Wp_Directory
 * @version 2.1.0
 */

// This file must not be called directly.
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Get the main listing type from shortcode or query var if available.
$listing_type_id = get_query_var('wpd_listing_type_id', 0);
if (isset($atts['type'])) {
    $type_post_by_slug = get_page_by_path(sanitize_title($atts['type']), OBJECT, 'wpd_listing_type');
    $listing_type_id = $type_post_by_slug ? $type_post_by_slug->ID : 0;
}
$listing_type_post = get_post($listing_type_id);

?>

<div class="wpd-archive-container">
    <div class="wpd-archive-sidebar">
        <form id="wpd-filter-form" method="post" action="">
            <h3><?php _e('جستجو و فیلتر', 'wp-directory'); ?></h3>

            <div class="wpd-form-group">
                <label for="filter-listing-type"><?php echo Directory_Main::get_term('listing_type'); ?></label>
                <select name="listing_type_id" id="filter-listing-type">
                    <option value=""><?php _e('همه انواع', 'wp-directory'); ?></option>
                    <?php
                    $listing_types = get_posts(['post_type' => 'wpd_listing_type', 'posts_per_page' => -1]);
                    foreach ($listing_types as $type) :
                        $selected = selected($listing_type_id, $type->ID, false);
                        echo '<option value="' . esc_attr($type->ID) . '" ' . $selected . '>' . esc_html($type->post_title) . '</option>';
                    endforeach;
                    ?>
                </select>
            </div>
            
            <div id="wpd-filter-form-dynamic-fields">
                <?php
                // Pre-populate filter form if a type is already chosen.
                if (!empty($listing_type_id)) {
                     // **اصلاح شده:** فراخوانی تابع از طریق کلاس صحیح
                     $filter_form_html = Directory_Main::instance()->frontend->ajax_load_filter_form_content($listing_type_id);
                     echo $filter_form_html;
                }
                ?>
            </div>

            <button type="submit"><?php _e('اعمال فیلتر', 'wp-directory'); ?></button>
            <input type="hidden" name="action" value="wpd_filter_listings">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('wpd_ajax_nonce'); ?>">
        </form>
    </div>

    <div class="wpd-archive-main">
        <h1>
            <?php
            if ($listing_type_post) {
                echo esc_html($listing_type_post->post_title);
            } else {
                echo Directory_Main::get_term('listings');
            }
            ?>
        </h1>
        <div id="wpd-listings-result-container">
            <?php
            // **اصلاح شده:** فراخوانی تابع از طریق کلاس صحیح
            $listings_html = Directory_Main::instance()->frontend->get_listings_html(['listing_type' => $listing_type_id]);
            echo $listings_html;
            ?>
        </div>
    </div>
</div>

<?php get_footer(); ?>
