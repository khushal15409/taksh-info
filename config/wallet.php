<?php

// Same keys and defaults as taksh-admin's config/wallet.php.
return [

    // Minimum points required to request a withdrawal (1 point = 1 INR).
    'min_withdrawal_points' => (int) env('WALLET_MIN_WITHDRAWAL_POINTS', 100),

    // Reward: points earned = floor(order_total_rupees / divisor). Example: Rs 1000 -> 10 points.
    'order_reward_divisor' => (int) env('WALLET_ORDER_REWARD_DIVISOR', 100),

    // 1 point equals this many Indian rupees (for display / payout).
    'rupees_per_point' => (float) env('WALLET_RUPEES_PER_POINT', 1),

];
