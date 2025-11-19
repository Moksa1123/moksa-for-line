<?php
/**
 * WooCommerce Product Carousel Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_WooCarousel {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_moksa_line_generate_woo_carousel', array($this, 'ajax_generate_carousel'));
    }
    
    /**
     * Generate Carousel JSON via AJAX
     */
    public function ajax_generate_carousel() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce is not active');
        }
        
        $type = sanitize_text_field($_POST['type']); // latest, category, specific
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 5;
        $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $product_ids = isset($_POST['product_ids']) ? sanitize_text_field($_POST['product_ids']) : '';
        
        $args = array(
            'status' => 'publish',
            'limit' => $limit,
        );
        
        if ($type === 'category' && $category > 0) {
            $args['category'] = array($category); // WC 3.0+ uses category slug or id? wc_get_products uses category array of slugs usually, but let's check
            // Actually wc_get_products 'category' arg takes array of slugs.
            $term = get_term($category, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $args['category'] = array($term->slug);
            }
        } elseif ($type === 'specific' && !empty($product_ids)) {
            $ids = array_map('intval', explode(',', $product_ids));
            $args['include'] = $ids;
            $args['orderby'] = 'post__in'; // Keep order
        } else {
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
        }
        
        $products = wc_get_products($args);
        
        if (empty($products)) {
            wp_send_json_error('No products found');
        }
        
        $bubbles = array();
        
        foreach ($products as $product) {
            $bubbles[] = $this->create_product_bubble($product);
        }
        
        $carousel = array(
            'type' => 'carousel',
            'contents' => $bubbles
        );
        
        $flex_message = array(
            'type' => 'flex',
            'altText' => 'Product Recommendations',
            'contents' => $carousel
        );
        
        wp_send_json_success($flex_message);
    }
    
    /**
     * Create Flex Bubble for a Product
     */
    private function create_product_bubble($product) {
        $image_url = wp_get_attachment_image_url($product->get_image_id(), 'medium');
        if (!$image_url) {
            $image_url = wc_placeholder_img_src();
        }
        
        $name = $product->get_name();
        $price = $product->get_price_html();
        $price_plain = strip_tags($price); // Simple strip for now
        $permalink = $product->get_permalink();
        $short_desc = mb_strimwidth(strip_tags($product->get_short_description()), 0, 60, '...');
        
        return array(
            'type' => 'bubble',
            'hero' => array(
                'type' => 'image',
                'url' => $image_url,
                'size' => 'full',
                'aspectRatio' => '20:13',
                'aspectMode' => 'cover',
                'action' => array(
                    'type' => 'uri',
                    'uri' => $permalink
                )
            ),
            'body' => array(
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => array(
                    array(
                        'type' => 'text',
                        'text' => $name,
                        'weight' => 'bold',
                        'size' => 'xl',
                        'wrap' => true
                    ),
                    array(
                        'type' => 'text',
                        'text' => $price_plain,
                        'size' => 'lg',
                        'color' => '#ff334b',
                        'weight' => 'bold',
                        'margin' => 'md'
                    ),
                    array(
                        'type' => 'text',
                        'text' => $short_desc,
                        'size' => 'sm',
                        'color' => '#aaaaaa',
                        'wrap' => true,
                        'margin' => 'md',
                        'maxLines' => 2
                    )
                )
            ),
            'footer' => array(
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'sm',
                'contents' => array(
                    array(
                        'type' => 'button',
                        'style' => 'primary',
                        'height' => 'sm',
                        'action' => array(
                            'type' => 'uri',
                            'label' => __('View Product', 'moksa-line-login'),
                            'uri' => $permalink
                        ),
                        'color' => '#06C755'
                    )
                ),
                'flex' => 0
            )
        );
    }
}
