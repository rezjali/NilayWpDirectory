<?php
/**
 * Template for displaying a single listing item in a list or archive view.
 * This file should be placed in `wp-directory/templates/partials/listing-item.php`.
 *
 * @package Wp_Directory
 * @version 2.0.0
 * @param int $post_id The ID of the listing post.
 */

// This file must not be called directly.
if (!defined('ABSPATH')) {
    exit;
}

// Ensure the post is set correctly.
$post = get_post($post_id);
if (!$post || $post->post_type !== 'wpd_listing') {
    return;
}

$listing_type_id = get_post_meta($post_id, '_wpd_listing_type', true);
$listing_type_name = $listing_type_id ? get_the_title($listing_type_id) : '';

// Get a few key custom fields to display in the archive list.
$address_field = get_post_meta($post_id, '_wpd_address', true);
$phone_field = get_post_meta($post_id, '_wpd_phone_number', true); // Assuming this is a standard field
$email_field = get_post_meta($post_id, '_wpd_email', true); // Assuming this is a standard field

// Get the featured image URL.
$thumbnail_url = get_the_post_thumbnail_url($post_id, 'medium');
if (!$thumbnail_url) {
    // Placeholder image if no featured image is set.
    $thumbnail_url = 'https://via.placeholder.com/300x200?text=' . urlencode(get_the_title($post_id));
}
?>

<div id="listing-<?php echo esc_attr($post_id); ?>" class="wpd-listing-item">
    <div class="wpd-item-thumbnail">
        <a href="<?php the_permalink(); ?>">
            <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php the_title_attribute(); ?>">
        </a>
    </div>
    <div class="wpd-item-content">
        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        <div class="wpd-item-excerpt">
            <?php echo wp_trim_words(get_the_content(), 20, '...'); ?>
        </div>
        <div class="wpd-item-meta">
            <?php if (!empty($listing_type_name)): ?>
                <span><span class="dashicons dashicons-admin-post"></span><?php echo esc_html($listing_type_name); ?></span>
            <?php endif; ?>
            <?php if (!empty($address_field['city'])): ?>
                <span><span class="dashicons dashicons-location-alt"></span><?php echo esc_html($address_field['city']); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
