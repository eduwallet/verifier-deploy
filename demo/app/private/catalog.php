<?php
// The building blocks the front-end may use. The front-end only sends the keys defined here;
// it never supplies an upstream URL, token or credential identifier.
// $env holds the settings of private/.env (and the environment variables).
return static function (array $env): array {
    return [
        'issuers' => [
            'sandbox' => [
                'name' => 'Sandbox issuer',
                'baseUrl' => $env['ISSUER_SANDBOX_URL'] ?? 'https://sandbox.issuer.dev.eduwallet.nl',
                'token' => $env['ISSUER_DEV_SANDBOX'] ?? '',
            ],
        ],
        'credentials' => [
            'deferred' => [
                'title' => 'Deferred credential',
                'issuer' => 'sandbox',
                // the credential configuration identifier at the issuer
                'credentialId' => 'DeferredCredential',
                'grant' => 'preauth',
                'deferred' => true,
                // the claims the user may supply, in offer and when authorizing
                'fields' => [
                    'name' => ['label' => 'Name', 'default' => 'Demo User', 'maxLength' => 100],
                    'email' => ['label' => 'Email', 'default' => 'demouser@example.com', 'maxLength' => 100],
                    'institute' => ['label' => 'Institute', 'default' => 'Example Inc', 'maxLength' => 100],
                ],
            ],
        ],
    ];
};
