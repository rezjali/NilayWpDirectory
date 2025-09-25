<?php
/**
 * Template for displaying a single listing item in a list or archive view.
 *
 * @package Wp_Directory
 * @version 2.1.0
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

// Get listing type to display its name
$listing_type_id = get_post_meta($post_id, '_wpd_listing_type', true);
$listing_type_name = $listing_type_id ? get_the_title($listing_type_id) : '';

// Get a sample location field (you might need to adjust the meta key)
$location_terms = wp_get_post_terms($post_id, 'wpd_listing_location', ['fields' => 'names']);
$location = !empty($location_terms) ? $location_terms[0] : '';


// Get the featured image URL.
$thumbnail_url = get_the_post_thumbnail_url($post_id, 'medium_large');
if (!$thumbnail_url) {
    // Placeholder image if no featured image is set.
    $placeholder_text = urlencode(get_the_title($post_id));
    $thumbnail_url = "https://placehold.co/400x250/EFEFEF/AAAAAA?text={$placeholder_text}";
}

$is_featured = get_post_meta($post_id, '_wpd_is_featured', true);

?>

<div id="listing-<?php echo esc_attr($post_id); ?>" class="wpd-listing-item">
    
    <?php if ($is_featured): ?>
        <div class="wpd-featured-badge"><?php _e('ویژه', 'wp-directory'); ?></div>
    <?php endif; ?>

    <div class="wpd-item-thumbnail">
        <a href="<?php the_permalink(); ?>">
            <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php the_title_attribute(); ?>">
        </a>
    </div>

    <div class="wpd-item-content">
        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        
        <div class="wpd-item-meta">
            <?php if (!empty($listing_type_name)): ?>
                <span><span class="dashicons dashicons-admin-post"></span><?php echo esc_html($listing_type_name); ?></span>
            <?php endif; ?>
            
            <?php if (!empty($location)): ?>
                <span><span class="dashicons dashicons-location-alt"></span><?php echo esc_html($location); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
