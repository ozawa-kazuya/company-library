<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 返却・貸出場所の制限（社内ネットワーク / Wi-Fi）
    |--------------------------------------------------------------------------
    |
    | ブラウザから Wi-Fi 名（SSID）は取得できないため、
    | 社内 Wi-Fi 等に割り当てられた IP アドレス帯で判定します。
    |
    | RETURN_ALLOWED_NETWORKS の例:
    |   192.168.1.0/24        … 社内 LAN 全体
    |   203.0.113.45/32       … オフィスの固定グローバル IP
    |   192.168.1.0/24,10.0.0.0/8
    |
    */
    'return_location' => [
        'restricted' => env('RETURN_LOCATION_RESTRICTED', false),

        'allowed_networks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('RETURN_ALLOWED_NETWORKS', '')),
        ))),

        'allow_localhost' => env('RETURN_ALLOW_LOCALHOST', false),

        'name' => env('RETURN_LOCATION_NAME', '社内Wi-Fi'),

        'denial_message' => env('RETURN_LOCATION_DENIAL_MESSAGE'),
    ],

    /*
    | 新規登録・シーダーで付与する初期パスワード。
    | このパスワードのままログインした利用者は、変更画面へ誘導する。
    */
    'initial_password' => env('INITIAL_PASSWORD', 'password'),

    /*
    | 返却期限切れの Slack 通知。
    | overdue_webhook_url … 管理用チャンネル（Incoming Webhook）。空ならチャンネル投稿しない。
    | bot_token … 本人への DM（Bot Token）。空なら DM しない。
    */
    'slack' => [
        'overdue_webhook_url' => env('SLACK_OVERDUE_WEBHOOK_URL', ''),
        'bot_token' => env('SLACK_BOT_TOKEN', ''),
    ],

    /*
    | Google Books API キー（任意）。
    | 空だと無認証枠を使う。表紙が取れないときは Google Cloud で
    | Books API を有効化し、GOOGLE_BOOKS_API_KEY を設定する。
    */
    'google_books_api_key' => env('GOOGLE_BOOKS_API_KEY', ''),

    /*
    | 本物の Grafana OSS の URL。空なら管理画面はアプリ内の集計を出す。
    */
    'grafana_url' => env('GRAFANA_URL', ''),

];
