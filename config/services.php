<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Google Analytics 4 (mã công khai, nằm sẵn trong HTML). Chỉ bật mặc định ở production
    // để local / test không gửi số liệu ảo; GA_MEASUREMENT_ID (khác rỗng) ghi đè nếu cần.
    'google_analytics' => [
        'measurement_id' => env('GA_MEASUREMENT_ID') ?: (env('APP_ENV') === 'production' ? 'G-N77C3ZFV99' : null),
    ],

    // Gửi file trực tiếp (/gui-file): máy chủ STUN công cộng giúp hai máy tìm đường nối thẳng.
    'transfer' => [
        'stun_urls' => array_values(array_filter(explode(',', (string) env('TRANSFER_STUN_URLS', 'stun:stun.cloudflare.com:3478,stun:stun.l.google.com:19302')))),

        // Máy chủ trung chuyển (Cloudflare TURN) khi mạng chặn kết nối thẳng. Để trống là tắt.
        'turn_key_id' => env('CLOUDFLARE_TURN_KEY_ID'),
        'turn_api_token' => env('CLOUDFLARE_TURN_API_TOKEN'),
        'turn_ttl' => (int) env('TRANSFER_TURN_TTL', 3600),
        // Giới hạn băng thông trung chuyển (tốn tiền của chủ trang).
        'turn_max_bytes' => (int) env('TRANSFER_TURN_MAX_GB', 2) * 1024 ** 3,
        'turn_daily_per_ip' => (int) env('TRANSFER_TURN_DAILY_PER_IP', 5),
        'turn_monthly_sessions' => (int) env('TRANSFER_TURN_MONTHLY_SESSIONS', 400),
    ],

];
