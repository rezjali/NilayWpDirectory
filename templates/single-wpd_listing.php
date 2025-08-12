<?php
/**
 * Template for displaying a single WPD Listing.
 * This file should be placed in `wp-directory/templates/single-wpd_listing.php`.
 * It handles the display of a single listing post, including all custom fields.
 *
 * @package Wp_Directory
 * @version 2.0.1
 */

// This file must not be called directly.
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Get the current listing post object.
global $post;

if ($post && $post->post_type === 'wpd_listing') :
    // Get the listing type ID to retrieve custom fields.
    $listing_type_id = get_post_meta($post->ID, '_wpd_listing_type', true);
    $fields = get_post_meta($listing_type_id, '_wpd_custom_fields', true);
?>

<div class="wpd-single-listing">
    <div class="wpd-listing-header">
        <h1><?php the_title(); ?></h1>
        <div class="wpd-listing-meta">
            <?php
            // Display general listing metadata
            $author_id = $post->post_author;
            $author_name = get_the_author_meta('display_name', $author_id);
            $listing_type_name = $listing_type_id ? get_the_title($listing_type_id) : '';

            if ($listing_type_name) {
                echo '<span><span class="dashicons dashicons-admin-post"></span>' . esc_html($listing_type_name) . '</span>';
            }
            if ($author_name) {
                echo '<span><span class="dashicons dashicons-admin-users"></span>' . esc_html($author_name) . '</span>';
            }
            ?>
        </div>
    </div>

    <div class="wpd-listing-content">
        <?php the_content(); ?>
    </div>

    <?php
    // Loop through custom fields and display them
    if (!empty($fields) && is_array($fields)) :
        foreach ($fields as $field) :
            $field_key = $field['key'];
            $meta_value = get_post_meta($post->ID, '_wpd_' . sanitize_key($field_key), true);

            // Hide the field if it has no value (except for structural fields)
            if (empty($meta_value) && $field['type'] !== 'html_content' && $field['type'] !== 'section_title') {
                continue;
            }
    ?>
    <div class="wpd-custom-fields-group">
        <?php
        switch ($field['type']) {
            case 'section_title':
                echo '<h4>' . esc_html($field['label']) . '</h4>';
                break;

            case 'html_content':
                echo wp_kses_post($field['options']);
                break;
            
            case 'gallery':
                $image_ids = array_filter(explode(',', $meta_value));
                if (!empty($image_ids)) :
                    echo '<h4>' . esc_html($field['label']) . '</h4>';
                    echo '<div class="wpd-gallery">';
                    foreach ($image_ids as $id) :
                        $image_url = wp_get_attachment_image_url($id, 'medium');
                        if ($image_url) :
                            echo '<a href="' . esc_url(wp_get_attachment_image_url($id, 'full')) . '" data-lightbox="wpd-gallery"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($field['label']) . '"></a>';
                        endif;
                    endforeach;
                    echo '</div>';
                endif;
                break;

            case 'map':
                $lat_lng = explode(',', $meta_value);
                if (count($lat_lng) === 2 && !empty($lat_lng[0]) && !empty($lat_lng[1])) :
                    echo '<h4>' . esc_html($field['label']) . '</h4>';
                    echo '<div class="wpd-map-container" id="wpd-map-' . esc_attr($field_key) . '" data-lat="' . esc_attr($lat_lng[0]) . '" data-lng="' . esc_attr($lat_lng[1]) . '"></div>';
                    ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            var mapDiv = document.getElementById('wpd-map-<?php echo esc_attr($field_key); ?>');
                            if (mapDiv) {
                                var lat = mapDiv.dataset.lat;
                                var lng = mapDiv.dataset.lng;
                                var map = L.map(mapDiv).setView([lat, lng], 13);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                                L.marker([lat, lng]).addTo(map);
                            }
                        });
                    </script>
                    <?php
                endif;
                break;
            
            case 'repeater':
                if (!empty($meta_value) && is_array($meta_value)) :
                    echo '<h4>' . esc_html($field['label']) . '</h4>';
                    echo '<ul class="wpd-repeater-list">';
                    foreach ($meta_value as $row) :
                        echo '<li>';
                        foreach ($row as $sub_key => $sub_value) :
                            echo '<span><strong>' . esc_html($sub_key) . ':</strong> ' . esc_html($sub_value) . '</span>';
                        endforeach;
                        echo '</li>';
                    endforeach;
                    echo '</ul>';
                endif;
                break;

            case 'social_networks':
                if (!empty($meta_value) && is_array($meta_value)) :
                    echo '<h4>' . esc_html($field['label']) . '</h4>';
                    echo '<ul class="wpd-social-list">';
                    foreach ($meta_value as $row) :
                        if (!empty($row['url'])) :
                            echo '<li><a href="' . esc_url($row['url']) . '" target="_blank">' . esc_html($row['type']) . '</a></li>';
                        endif;
                    endforeach;
                    echo '</ul>';
                endif;
                break;
            
            case 'product':
                if (!empty($meta_value['selected'])) :
                    echo '<h4>' . esc_html($field['label']) . '</h4>';
                    echo '<p><strong>' . __('قیمت:', 'wp-directory') . '</strong> ' . number_format($meta_value['price']) . ' ' . Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'] . '</p>';
                    if (!empty($meta_value['quantity'])):
                        echo '<p><strong>' . __('تعداد:', 'wp-directory') . '</strong> ' . number_format($meta_value['quantity']) . '</p>';
                    endif;
                endif;
                break;

            case 'address':
            case 'identity':
                if (!empty($meta_value) && is_array($meta_value)) :
                    echo '<h4>' . esc_html($field['label']) . '</h4>';
                    echo '<div class="wpd-field-output">';
                    foreach ($meta_value as $sub_key => $sub_value) :
                        if (!empty($sub_value)) :
                             echo '<div class="wpd-field-item"><strong>' . esc_html($sub_key) . ':</strong> ' . esc_html($sub_value) . '</div>';
                        endif;
                    endforeach;
                    echo '</div>';
                endif;
                break;

            default:
                echo '<h4>' . esc_html($field['label']) . '</h4>';
                echo '<p>' . esc_html($meta_value) . '</p>';
                break;
        }
        ?>
    </div>
    <?php
        endforeach;
    endif;
    ?>

</div>

<?php
else :
    // Handle the case where the post is not a WPD Listing.
    ?>
    <div class="wpd-alert wpd-alert-danger">
        <p><?php _e('آگهی مورد نظر یافت نشد.', 'wp-directory'); ?></p>
    </div>
<?php
endif;

get_footer();
