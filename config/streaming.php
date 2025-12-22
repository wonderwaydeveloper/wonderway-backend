<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Streaming Configuration
    |--------------------------------------------------------------------------
    */

    'rtmp_url' => env('RTMP_URL', 'rtmp://localhost:1935/live'),
    'hls_url' => env('HLS_URL', 'http://localhost:8080/hls'),
    'nginx_control_url' => env('NGINX_CONTROL_URL', 'http://localhost:8080/control'),

    'qualities' => [
        'low' => [
            'resolution' => '480x270',
            'bitrate' => '256k',
            'audio_bitrate' => '32k',
        ],
        'medium' => [
            'resolution' => '854x480',
            'bitrate' => '768k',
            'audio_bitrate' => '96k',
        ],
        'high' => [
            'resolution' => '1280x720',
            'bitrate' => '1024k',
            'audio_bitrate' => '128k',
        ],
        'source' => [
            'resolution' => 'original',
            'bitrate' => 'original',
            'audio_bitrate' => 'original',
        ],
    ],

    'categories' => [
        'gaming' => 'Gaming',
        'music' => 'Music',
        'talk' => 'Talk Shows',
        'education' => 'Education',
        'entertainment' => 'Entertainment',
        'sports' => 'Sports',
        'technology' => 'Technology',
        'art' => 'Art & Creative',
        'cooking' => 'Cooking',
        'fitness' => 'Fitness',
        'travel' => 'Travel',
        'other' => 'Other',
    ],

    'limits' => [
        'max_concurrent_streams' => env('MAX_CONCURRENT_STREAMS', 1),
        'max_stream_duration' => env('MAX_STREAM_DURATION', 14400), // 4 hours in seconds
        'max_viewers_per_stream' => env('MAX_VIEWERS_PER_STREAM', 10000),
        'min_stream_title_length' => 3,
        'max_stream_title_length' => 255,
        'max_description_length' => 1000,
    ],

    'recording' => [
        'enabled' => env('STREAM_RECORDING_ENABLED', true),
        'max_file_size' => env('MAX_RECORDING_SIZE', 5368709120), // 5GB in bytes
        'retention_days' => env('RECORDING_RETENTION_DAYS', 30),
        'formats' => ['mp4', 'flv'],
        'auto_delete' => env('AUTO_DELETE_RECORDINGS', true),
    ],

    'thumbnails' => [
        'enabled' => env('STREAM_THUMBNAILS_ENABLED', true),
        'interval' => env('THUMBNAIL_INTERVAL', 30), // seconds
        'quality' => env('THUMBNAIL_QUALITY', 80),
        'width' => env('THUMBNAIL_WIDTH', 320),
        'height' => env('THUMBNAIL_HEIGHT', 180),
    ],

    'chat' => [
        'enabled' => env('STREAM_CHAT_ENABLED', true),
        'max_message_length' => env('MAX_CHAT_MESSAGE_LENGTH', 500),
        'rate_limit' => env('CHAT_RATE_LIMIT', 10), // messages per minute
        'moderation' => env('CHAT_MODERATION_ENABLED', true),
    ],

    'notifications' => [
        'stream_started' => env('NOTIFY_STREAM_STARTED', true),
        'stream_ended' => env('NOTIFY_STREAM_ENDED', false),
        'follower_threshold' => env('STREAM_NOTIFICATION_THRESHOLD', 10),
    ],
];
