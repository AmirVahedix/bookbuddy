<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FTP Deployment Host
    |--------------------------------------------------------------------------
    |
    | The FTP server host address (e.g. ftp.example.com or IP address).
    |
    */
    'host' => env('FTP_DEPLOY_HOST'),

    /*
    |--------------------------------------------------------------------------
    | FTP Authentication Credentials
    |--------------------------------------------------------------------------
    |
    | Username and password used to connect and upload files to the FTP server.
    |
    */
    'username' => env('FTP_DEPLOY_USERNAME'),
    'password' => env('FTP_DEPLOY_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | FTP Port & SSL Settings
    |--------------------------------------------------------------------------
    |
    | Port number (defaults to 21) and whether to use SSL/TLS connection (FTPS).
    |
    */
    'port' => env('FTP_DEPLOY_PORT', 21),
    'ssl' => env('FTP_DEPLOY_SSL', false),

    /*
    |--------------------------------------------------------------------------
    | FTP Remote Root Directory
    |--------------------------------------------------------------------------
    |
    | Target root directory path on the FTP server (e.g. /public_html or /).
    |
    */
    'root' => env('FTP_DEPLOY_ROOT', '/'),

    /*
    |--------------------------------------------------------------------------
    | Git Tag Tracking
    |--------------------------------------------------------------------------
    |
    | Default Git tag name used to track the latest deployed commit.
    |
    */
    'tag' => env('FTP_DEPLOY_TAG', 'deployed-latest'),

    /*
    |--------------------------------------------------------------------------
    | Default Excluded Paths & Files
    |--------------------------------------------------------------------------
    |
    | Additional files or directory paths to exclude from deployment.
    |
    */
    'excludes' => [
        // 'custom-file.txt',
        // 'docs/',
    ],
];
