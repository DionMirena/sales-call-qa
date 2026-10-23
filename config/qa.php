<?php

return [
    'language'    => env('QA_LANGUAGE', 'en'),
    'fake_ai'     => env('QA_FAKE_AI', false),
    'model'       => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    'deepgram_key' => env('DEEPGRAM_API_KEY'),
    'anthropic_key' => env('ANTHROPIC_API_KEY'),

    'audio' => [
        'ffmpeg_path'  => env('FFMPEG_PATH', 'ffmpeg'),
        'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),
        'disk'         => 'private',
    ],

    'scoring' => [
        'needs_review_confidence_threshold' => 0.6,
        'fuzzy_verified_threshold'          => 0.85,
        'fuzzy_partial_threshold'           => 0.60,
    ],
];
