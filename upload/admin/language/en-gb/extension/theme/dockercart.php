<?php
// Heading
$_['heading_title']                    = 'Store Theme One';

// Text
$_['text_extension']                   = 'Add-ons';
$_['text_success']                     = 'Success: You have modified Store Theme One settings!';
$_['text_edit']                        = 'Edit Store Theme One';
$_['text_general']                     = 'General';
$_['text_product']                     = 'Products';
$_['text_image']                       = 'Images';

// Entry
$_['entry_directory']                  = 'Theme Directory';
$_['entry_status']                     = 'Status';
$_['entry_product_limit']              = 'Default Items Per Page';
$_['entry_product_description_length'] = 'List Description Limit';
$_['entry_image_category']             = 'Category Image Size (W x H)';
$_['entry_image_product']              = 'Product Image List Size (W x H)';
$_['entry_image_additional']           = 'Additional Product Image Size (W x H)';
$_['entry_image_related']              = 'Related Product Image Size (W x H)';
$_['entry_image_compare']              = 'Compare Image Size (W x H)';
$_['entry_image_wishlist']             = 'Wish List Image Size (W x H)';
$_['entry_image_cart']                 = 'Cart Image Size (W x H)';
$_['entry_image_location']             = 'Store Image Size (W x H)';
$_['entry_image_resize_mode']          = 'Image Resize Mode';
$_['entry_image_webp_status']           = 'Convert cache images to WebP';
$_['entry_image_webp_quality']          = 'WebP quality (1-100)';
$_['entry_width']                      = 'Width';
$_['entry_height']                     = 'Height';

// Image resize mode options
$_['text_resize_contain']              = 'Contain (fit in box, add background)';
$_['text_resize_cover']                = 'Cover (fill and crop from center)';
$_['text_resize_hybrid']               = 'Hybrid (auto-detect: contain or cover per image)';
// Subtitle

$_['text_edit_subtitle'] = 'Configure extension settings and options';



// Help
$_['help_directory']                   = 'DockerCart should use the "dockercart" directory. Change this only if you are intentionally using a custom theme directory.';
$_['help_product_limit']               = 'Determines how many catalog items are shown per page (products, categories, etc)';
$_['help_product_description_length']  = 'In list view, short description character limit (categories, specials, etc)';
$_['help_image_resize_mode']           = 'Cover: scales and crops from center to fill the exact dimensions — no white background, best for product photos. Contain: scales to fit within the box and pads with background. Hybrid: automatically detects whether the image has a white/transparent background (contain) or fills the frame (cover) by analyzing border pixels.';
$_['help_image_webp_status']            = 'When enabled, resized files in image/cache are generated as WebP if server GD supports it.';
$_['help_image_webp_quality']           = 'Compression quality for generated WebP cache files. Default: 90%.';

// Error
$_['error_permission']                 = 'Warning: You do not have permission to modify Store Theme One!';
$_['error_limit']                      = 'Product Limit required!';
$_['error_image_product']              = 'Product List Size dimensions required!';
$_['error_image_category']             = 'Category List Size dimensions required!';
$_['error_image_additional']           = 'Additional Product Image Size dimensions required!';
$_['error_image_related']              = 'Related Product Image Size dimensions required!';
$_['error_image_compare']              = 'Compare Image Size dimensions required!';
$_['error_image_wishlist']             = 'Wish List Image Size dimensions required!';
$_['error_image_cart']                 = 'Cart Image Size dimensions required!';
$_['error_image_location']             = 'Store Image Size dimensions required!';
$_['error_webp_quality']                = 'WebP quality must be between 1 and 100!';
