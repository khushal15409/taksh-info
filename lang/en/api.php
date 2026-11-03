<?php

// Message keys and wording shared with the Taksh API (taksh-admin lang/en/api.php).
return [
    'success' => 'Operation successful',
    'error' => 'An error occurred',
    'validation_error' => 'Validation failed',
    'unauthenticated' => 'Please log in to continue.',

    // Auth
    'otp_sent' => 'OTP sent successfully',
    'otp_verified' => 'OTP verified successfully',
    'otp_invalid' => 'Invalid OTP',
    'logged_out' => 'Logged out successfully',
    'otp_rate_limit' => 'Too many OTP requests. Please try again later.',
    'otp_verify_rate_limit' => 'Too many wrong attempts. Please request a new OTP.',
    'otp_delivery_failed' => 'We could not send the OTP right now. Please try again shortly.',

    // Profile
    'profile_fetched' => 'Profile fetched successfully',
    'profile_updated' => 'Profile updated successfully',
    'session_fetched' => 'Session fetched successfully',

    // Home & discovery
    'dashboard' => ['loaded' => 'Dashboard data loaded successfully'],
    'banners' => ['fetched' => 'Banners fetched successfully', 'not_found' => 'No banners found'],
    'sales_fetched' => 'Sales fetched successfully',
    'sale_not_found' => 'Sale not found',
    'sale_details_loaded' => 'Sale details loaded successfully',
    'sale_products_fetched' => 'Sale products fetched successfully',
    'recent_views_fetched' => 'Recently viewed products fetched successfully',

    // Catalogue
    'categories_fetched' => 'Categories fetched successfully',
    'products_fetched' => 'Products fetched successfully',
    'product_not_found' => 'Product not found',
    'attributes_fetched' => 'Attributes fetched successfully',
    'recent_searches_fetched' => 'Recent searches fetched successfully',
    'product' => ['details_loaded' => 'Product details fetched successfully'],
    'delivery_check_no_vendor' => 'Product is not assigned to any vendor',
    'delivery_check_vendor_unavailable' => 'Vendor not found or not approved',
    'delivery_check_pincode_unknown' => 'Pincode not found or not serviceable',
    'delivery_check_not_mapped' => 'Product is not available for delivery to this pincode. Vendor is not mapped to this pincode.',
    'delivery_check_out_of_stock' => 'Product is out of stock',
    'delivery_check_ok' => 'Product is available for delivery to this pincode',
    'delivery_check_quick_on_app' => 'Quick (30-minute) delivery is available in the Taksh app. Please order this product there.',
    'review' => [
        'pending_listed' => 'Products waiting for your rating fetched successfully',
        'status_loaded' => 'Review status fetched successfully',
        'submitted' => 'Thank you! Your rating has been submitted',
        'updated' => 'Your rating has been updated',
        'not_delivered' => 'You can rate a product only after it has been delivered to you',
    ],

    // Cart
    'item_added_to_cart' => 'Item added to cart successfully',
    'cart_fetched' => 'Cart fetched successfully',
    'cart_updated' => 'Cart updated successfully',
    'item_removed_from_cart' => 'Item removed from cart',
    'cart_cleared' => 'Cart cleared',
    'cart_empty' => 'Cart is empty',
    'not_found_in_cart' => 'Item not found in cart',
    'coupon_applied' => 'Coupon applied successfully',
    'coupon_removed' => 'Coupon removed',
    'insufficient_stock' => 'Insufficient stock available',
    'product_not_available' => 'This product is not available right now',
    'product_vendor_not_set' => 'Product vendor is not set',
    'vendor_not_available' => 'This vendor is not available right now',
    'cart_quick_on_app' => 'Quick (30-minute) products can be ordered from the Taksh app for now.',
    'express' => ['bucket_mismatch' => 'This product is not available with the selected delivery option'],

    // Wishlist
    'wishlist_item_added' => 'Product added to wishlist successfully',
    'wishlist_fetched' => 'Wishlist fetched successfully',
    'wishlist_item_removed' => 'Product removed from wishlist',
    'wishlist_item_not_found' => 'Product is not in your wishlist',
    'product_not_approved' => 'This product is not approved.',

    // Orders and payments
    'order_placed' => 'Order placed successfully',
    'order_not_found' => 'Order not found',
    'address_not_found' => 'Address not found',
    'delivery_type_invalid' => 'Invalid delivery type',
    'vendor_not_found' => 'Vendor not found',
    'product_not_belongs_to_vendor' => 'Product does not belong to the specified vendor',
    'multi_vendor_online_unsupported' => 'Your cart has products from different shops. Please pay by cash on delivery, or order one shop at a time.',
    'delivery_not_serviceable' => 'Delivery is not available to the pincode of the selected address',
    'cart_no_items_for_delivery_type' => 'None of the items in your cart can be ordered with this delivery type',
    'server_error' => 'Server error occurred',

    // Orders list, cancel, returns, tracking
    'orders_fetched' => 'Orders fetched successfully',
    'order_details_fetched' => 'Order details fetched successfully',
    'order_cancelled' => 'Order cancelled successfully',
    'order_cannot_be_cancelled' => 'Order cannot be cancelled at this stage',
    'order_already_cancelled' => 'Order is already cancelled',
    'order_item_not_found' => 'Order item not found',
    'order_not_delivered' => 'Order has not been delivered yet',
    'unauthorized' => 'Unauthorized access',
    'return_requested' => 'Return requested successfully',
    'return_not_found' => 'Return request not found',
    'return_already_exists' => 'Return request already exists for this order item',
    'return_window_closed' => 'The return window for this item has closed',
    'return_quantity_too_high' => 'You cannot return more pieces than are left on this item',
    'returns_retrieved' => 'Returns retrieved successfully',
    'return_retrieved' => 'Return details retrieved successfully',
    'status_tracked' => 'Status tracked successfully',
    'media_uploaded' => 'Media uploaded successfully',
    'media_upload_failed' => 'Failed to upload media',
    'location_retrieved' => 'Location retrieved successfully',
    'location_not_available' => 'Delivery location not yet available',

    // Wallet
    'wallet_summary_fetched' => 'Wallet summary fetched successfully',
    'wallet_transactions_retrieved' => 'Wallet transactions retrieved successfully',
    'wallet_withdrawal_requests_fetched' => 'Withdrawal requests fetched successfully',
    'wallet_withdrawal_request_created' => 'Withdrawal request submitted successfully',
    'wallet_withdrawal_min_not_met' => 'Minimum points required for withdrawal not met',
    'wallet_insufficient_points' => 'Insufficient available points for this withdrawal',

    // Notifications
    'notifications_fetched' => 'Notifications fetched successfully',
    'notification_marked_read' => 'Notification marked as read',
    'notifications_marked_read' => 'All notifications marked as read',
    'notification_not_found' => 'Notification not found',

    // Support requests
    'callback_request_created' => 'Callback request created successfully',
    'callback_request_already_open' => 'You already have an open callback request. Please wait for it to be processed.',
    'development_request_created' => 'Development request submitted successfully',
    'vendor_request_created' => 'Vendor request submitted successfully',
    'delivery_boy_join_request_created' => 'Delivery boy join request submitted successfully',
];
