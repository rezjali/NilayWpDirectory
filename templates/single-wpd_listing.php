<?php
/**
 * Template for displaying a single WPD Listing.
 * This file handles the display of a single listing post, including all custom fields.
 *
 * @package Wp_Directory
 * @version 2.1.0
 */

// This file must not be called directly.
if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $post;

if ($post && $post->post_type === 'wpd_listing') :
    // Get the listing type ID to retrieve custom fields definitions.
    $listing_type_id = get_post_meta($post->ID, '_wpd_listing_type', true);
    $fields = get_post_meta($listing_type_id, '_wpd_custom_fields', true);

    function get_translated_label($key) {
        $labels = [
            'province' => __('استان', 'wp-directory'),
            'city' => __('شهر', 'wp-directory'),
            'street' => __('آدرس دقیق', 'wp-directory'),
            'postal_code' => __('کد پستی', 'wp-directory'),
            'first_name' => __('نام', 'wp-directory'),
            'last_name' => __('نام خانوادگی', 'wp-directory'),
            'phone' => __('شماره تماس', 'wp-directory'),
            'national_id' => __('کد ملی', 'wp-directory'),
            'age' => __('سن', 'wp-directory'),
            'gender' => __('جنسیت', 'wp-directory'),
            'address' => __('آدرس', 'wp-directory'),
        ];
        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
?>

<div class="wpd-single-listing">
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <div class="wpd-listing-header">
            <h1><?php the_title(); ?></h1>
            <div class="wpd-listing-meta">
                <?php
                $author_id = $post->post_author;
                $author_name = get_the_author_meta('display_name', $author_id);
                $listing_type_name = $listing_type_id ? get_the_title($listing_type_id) : '';

                if ($listing_type_name) {
                    echo '<span><span class="dashicons dashicons-admin-post"></span>' . esc_html($listing_type_name) . '</span>';
                }
                if ($author_name) {
                    echo '<span><span class="dashicons dashicons-admin-users"></span>' . esc_html($author_name) . '</span>';
                }
                echo '<span><span class="dashicons dashicons-calendar"></span>' . get_the_date() . '</span>';
                ?>
            </div>
        </div>

        <?php
        if (!empty($fields) && is_array($fields)) :
            foreach ($fields as $field) :
                $field_key = $field['key'];
                $meta_value = get_post_meta($post->ID, '_wpd_' . sanitize_key($field_key), true);

                if (empty($meta_value) && !in_array($field['type'], ['html_content', 'section_title'])) {
                    continue;
                }
        ?>
        <div class="wpd-custom-fields-group field-type-<?php echo esc_attr($field['type']); ?>">
            <?php
            switch ($field['type']) {
                case 'section_title':
                    echo '<h4>' . esc_html($field['label']) . '</h4>';
                    break;

                case 'html_content':
                    echo '<div class="wpd-html-content">' . wp_kses_post($field['options']) . '</div>';
                    break;
                
                case 'image':
                    if (!empty($meta_value)) {
                        echo '<h4>' . esc_html($field['label']) . '</h4>';
                        echo '<div class="wpd-field-output-image">';
                        echo wp_get_attachment_image(absint($meta_value), 'large');
                        echo '</div>';
                    }
                    break;

                case 'gallery':
                    $image_ids = array_filter(explode(',', $meta_value));
                    if (!empty($image_ids)) :
                        echo '<h4>' . esc_html($field['label']) . '</h4>';
                        echo '<div class="wpd-gallery">';
                        foreach ($image_ids as $id) :
                            $image_full_url = wp_get_attachment_image_url($id, 'full');
                            $image_thumb_url = wp_get_attachment_image_url($id, 'medium');
                            if ($image_full_url && $image_thumb_url) :
                                echo '<a href="' . esc_url($image_full_url) . '" data-lightbox="wpd-listing-gallery" data-title="' . esc_attr(get_the_title($id)) . '"><img src="' . esc_url($image_thumb_url) . '" alt="' . esc_attr(get_the_title($id)) . '"></a>';
                            endif;
                        endforeach;
                        echo '</div>';
                    endif;
                    break;

                case 'map':
                    $lat_lng = explode(',', $meta_value);
                    if (count($lat_lng) === 2 && is_numeric($lat_lng[0]) && is_numeric($lat_lng[1])) :
                        echo '<h4>' . esc_html($field['label']) . '</h4>';
                        echo '<div class="wpd-map-container" id="wpd-map-' . esc_attr($field_key) . '" data-lat="' . esc_attr(trim($lat_lng[0])) . '" data-lng="' . esc_attr(trim($lat_lng[1])) . '"></div>';
                        ?>
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                var mapDiv = document.getElementById('wpd-map-<?php echo esc_js($field_key); ?>');
                                if (mapDiv && typeof L !== 'undefined') {
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
                
                case 'image_checkbox':
                    $saved_values = is_array($meta_value) ? $meta_value : [];
                    $image_options = $field['image_options'] ?? [];
                    $columns = $field['display_columns'] ?? 3;

                    if (!empty($saved_values) && !empty($image_options)) {
                        $selected_options = array_filter($image_options, function($option) use ($saved_values) {
                            return in_array($option['value'], $saved_values);
                        });

                        if (!empty($selected_options)) {
                            echo '<h4>' . esc_html($field['label']) . '</h4>';
                            echo '<div class="wpd-image-checkbox-display-wrapper" style="--wpd-grid-columns: ' . esc_attr($columns) . ';">';
                            
                            foreach ($selected_options as $option) {
                                $image_url = wp_get_attachment_image_url($option['image_id'], 'thumbnail');
                                if (!$image_url) {
                                    $placeholder_text = urlencode($option['title']);
                                    $image_url = "https://placehold.co/150x150/EFEFEF/AAAAAA?text={$placeholder_text}";
                                }
                                echo '<div class="wpd-image-checkbox-display-item">';
                                echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($option['title']) . '">';
                                echo '<span>' . esc_html($option['title']) . '</span>';
                                echo '</div>';
                            }
                            
                            echo '</div>';
                        }
                    }
                    break;

                case 'repeater':
                    if (!empty($meta_value) && is_array($meta_value)) :
                        $sub_field_defs = $field['sub_fields'] ?? [];
                        $sub_field_labels = [];
                        if (!empty($sub_field_defs)) {
                            foreach ($sub_field_defs as $def) {
                                if (isset($def['key']) && isset($def['label'])) {
                                    $sub_field_labels[$def['key']] = $def['label'];
                                }
                            }
                        }

                        echo '<h4>' . esc_html($field['label']) . '</h4>';
                        echo '<div class="wpd-repeater-output">';
                        foreach ($meta_value as $row) :
                            echo '<div class="wpd-repeater-row-output">';
                            foreach ($row as $sub_key => $sub_value) :
                                $label = $sub_field_labels[$sub_key] ?? ucfirst(str_replace('_', ' ', $sub_key));
                                echo '<div class="wpd-field-item"><strong>' . esc_html($label) . ':</strong> ' . esc_html($sub_value) . '</div>';
                            endforeach;
                            echo '</div>';
                        endforeach;
                        echo '</div>';
                    endif;
                    break;

                case 'social_networks':
                    if (!empty($meta_value) && is_array($meta_value)) :
                        echo '<h4>' . esc_html($field['label']) . '</h4>';
                        echo '<div class="wpd-social-list">';
                        foreach ($meta_value as $row) :
                            if (!empty($row['url']) && !empty($row['type'])) :
                                echo '<a href="' . esc_url($row['url']) . '" target="_blank" class="wpd-social-icon wpd-social-icon-' . esc_attr($row['type']) . '" title="' . esc_attr(ucfirst($row['type'])) . '"></a>';
                            endif;
                        endforeach;
                        echo '</div>';
                    endif;
                    break;

                case 'simple_list':
                    if (!empty($meta_value) && is_array($meta_value)) :
                        echo '<h4>' . esc_html($field['label']) . '</h4>';
                        echo '<ul class="wpd-simple-list">';
                        foreach ($meta_value as $row) :
                            if (!empty($row['text'])) :
                                echo '<li>' . esc_html($row['text']) . '</li>';
                            endif;
                        endforeach;
                        echo '</ul>';
                    endif;
                    break;
                
                case 'product':
                    if (!empty($meta_value['selected'])) :
                        echo '<h4>' . esc_html($field['label']) . '</h4>';
                        echo '<div class="wpd-field-output">';
                        echo '<div class="wpd-field-item"><strong>' . __('قیمت:', 'wp-directory') . '</strong> ' . number_format($meta_value['price']) . ' ' . Directory_Main::get_option('general', ['currency' => 'تومان'])['currency'] . '</div>';
                        if (!empty($meta_value['quantity'])):
                            echo '<div class="wpd-field-item"><strong>' . __('تعداد:', 'wp-directory') . '</strong> ' . number_format($meta_value['quantity']) . '</div>';
                        endif;
                        echo '</div>';
                    endif;
                    break;

                case 'address':
                case 'identity':
                    if (!empty($meta_value) && is_array($meta_value)) :
                        echo '<h4>' . esc_html($field['label']) . '</h4>';
                        echo '<div class="wpd-field-output">';
                        foreach ($meta_value as $sub_key => $sub_value) :
                            if (!empty($sub_value)) :
                                 echo '<div class="wpd-field-item"><strong>' . esc_html(get_translated_label($sub_key)) . ':</strong> ' . esc_html($sub_value) . '</div>';
                            endif;
                        endforeach;
                        echo '</div>';
                    endif;
                    break;

                default:
                    echo '<h4>' . esc_html($field['label']) . '</h4>';
                    echo '<div class="wpd-field-output-text">' . wpautop(esc_html($meta_value)) . '</div>';
                    break;
            }
            ?>
        </div>
        <?php
            endforeach;
        endif;
        ?>
    </article>
</div>

<?php
else :
    ?>
    <div class="wpd-alert wpd-alert-danger">
        <p><?php _e('آگهی مورد نظر یافت نشد.', 'wp-directory'); ?></p>
    </div>
<?php
endif;

get_footer();
