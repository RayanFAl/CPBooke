<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        // Automated transactional mail only (OTP, bookings, tickets, payments, security).
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@booke.ly'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Booke')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Booke mailbox roles (role-based addresses)
    |--------------------------------------------------------------------------
    |
    | Defaults below are used only until Admin → Settings → Contact is saved.
    | Runtime values come from system_settings (noreply_email, support_email,
    | info_email, feedback_email) and override these env defaults on boot.
    |
    | noreply  — system automation From: Booke <no-reply@booke.ly>
    | support  — customer help Reply-To / contact: Booke Support <support@booke.ly>
    | feedback — product feedback inbox (inbound; app does not send as this)
    | info     — general / official inquiries (inbound; app does not send as this)
    |
    | Do not use noreply for human conversations. Always surface support@ in
    | transactional footers so customers know where to get help.
    |
    | Ops: create mailboxes/aliases on booke.ly and configure SPF + DKIM + DMARC
    | before relying on these addresses in production.
    |
    */

    'addresses' => [
        'noreply' => env('MAIL_FROM_ADDRESS', 'no-reply@booke.ly'),
        'support' => env('MAIL_SUPPORT_ADDRESS', 'support@booke.ly'),
        'info' => env('MAIL_INFO_ADDRESS', 'info@booke.ly'),
        'feedback' => env('MAIL_FEEDBACK_ADDRESS', 'feedback@booke.ly'),
    ],

    'names' => [
        'noreply' => env('MAIL_FROM_NAME', env('APP_NAME', 'Booke')),
        'support' => env('MAIL_SUPPORT_NAME', 'Booke Support'),
        'feedback' => env('MAIL_FEEDBACK_NAME', 'Booke Feedback'),
        'info' => env('MAIL_INFO_NAME', env('APP_NAME', 'Booke')),
    ],

];
