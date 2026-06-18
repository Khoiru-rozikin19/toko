<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'myxl' => [
        'base_api_url' => env('MYXL_BASE_API_URL', 'https://api.myxl.xlaxiata.co.id'),
        'base_ciam_url' => env('MYXL_BASE_CIAM_URL', 'https://gede.ciam.xlaxiata.co.id'),
        'basic_auth' => env('MYXL_BASIC_AUTH', ''),
        'ax_fp_key' => env('MYXL_AX_FP_KEY', ''),
        'ua' => env('MYXL_UA', 'myXL / 8.9.0(1202); com.android.vending'),
        'api_key' => env('MYXL_API_KEY', ''),
        'encrypted_field_key' => env('MYXL_ENCRYPTED_FIELD_KEY', ''),
        'xdata_key' => env('MYXL_XDATA_KEY', ''),
        'ax_api_sig_key' => env('MYXL_AX_API_SIG_KEY', ''),
        'x_api_base_secret' => env('MYXL_X_API_BASE_SECRET', ''),
        'circle_msisdn_key' => env('MYXL_CIRCLE_MSISDN_KEY', ''),
    ],

];
