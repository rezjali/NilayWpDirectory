<?php
/**
 * Template for the dynamic filter form on the listing archive page.
 * This file should be placed in `wp-directory/templates/partials/filter-form.php`.
 * It's loaded via AJAX or directly when a listing type is specified.
 *
 * @package Wp_Directory
 * @version 2.0.0
 * @param int $listing_type_id The ID of the currently selected listing type.
 */

// This file must not be called directly.
if (!defined('ABSPATH')) {
    exit;
}

// Get the current listing type ID if available.
if (!isset($listing_type_id)) {
    $listing_type_id = isset($_POST['listing_type_id']) ? intval($_POST['listing_type_id']) : 0;
}

// Get custom fields and global taxonomies.
$fields = get_post_meta($listing_type_id, '_wpd_custom_fields', true);
$global_taxonomies = ['wpd_listing_category', 'wpd_listing_location'];

?>

<div id="wpd-filter-form-dynamic-fields">
    <?php if (!empty($listing_type_id)): ?>
        
        <div class="wpd-form-group">
            <label for="filter-s"><?php _e('جستجو در عنوان و توضیحات', 'wp-directory'); ?></label>
            <input type="text" name="s" id="filter-s" value="<?php echo isset($_POST['s']) ? esc_attr(sanitize_text_field($_POST['s'])) : ''; ?>">
        </div>

        <?php
        // Render global and dynamic taxonomies as filter dropdowns.
        $taxonomies = get_post_meta($listing_type_id, '_defined_taxonomies', true);
        $all_taxonomies = array_merge($global_taxonomies, wp_list_pluck($taxonomies ?? [], 'slug'));
        
        foreach($all_taxonomies as $tax_slug){
            $taxonomy = get_taxonomy($tax_slug);
            if(!$taxonomy) continue;
            
            $terms = get_terms(['taxonomy' => $tax_slug, 'hide_empty' => false]);
            if(empty($terms)) continue;
            
            $selected_term = isset($_POST['filter_tax'][$tax_slug]) ? sanitize_text_field($_POST['filter_tax'][$tax_slug]) : '';
            
            echo '<div class="wpd-form-group">';
            echo '<label for="filter-' . esc_attr($tax_slug) . '">' . esc_html($taxonomy->labels->name) . '</label>';
            echo '<select name="filter_tax[' . esc_attr($tax_slug) . ']" id="filter-' . esc_attr($tax_slug) . '">';
            echo '<option value="">' . __('همه', 'wp-directory') . '</option>';
            foreach($terms as $term){
                echo '<option value="' . esc_attr($term->slug) . '" ' . selected($selected_term, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
            }
            echo '</select>';
            echo '</div>';
        }

        // Render custom fields that are marked as filterable.
        if (!empty($fields) && is_array($fields)) {
            foreach ($fields as $field) {
                if (isset($field['show_in_filter']) && $field['show_in_filter']) {
                    $field_key = $field['key'];
                    $field_value = isset($_POST['filter_meta'][$field_key]) ? sanitize_text_field($_POST['filter_meta'][$field_key]) : '';
                    
                    echo '<div class="wpd-form-group">';
                    echo '<label for="filter-' . esc_attr($field_key) . '">' . esc_html($field['label']) . '</label>';
                    
                    // Simple text input for search.
                    echo '<input type="text" name="filter_meta[' . esc_attr($field_key) . ']" id="filter-' . esc_attr($field_key) . '" value="' . esc_attr($field_value) . '">';
                    
                    echo '</div>';
                }
            }
        }
        ?>
    <?php else: ?>
        <p class="wpd-alert wpd-alert-info"><?php _e('لطفا ابتدا یک نوع آگهی انتخاب کنید.', 'wp-directory'); ?></p>
    <?php endif; ?>
</div>
