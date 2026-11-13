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
            // the two generic credentials of the 'multiple' demo
            'generic1' => [
                'title' => 'Generic credential 1',
                'issuer' => 'sandbox',
                'credentialId' => 'GenericCredential_JWT',
                'grant' => 'preauth',
                'deferred' => false,
                'fields' => [
                    'institute' => ['label' => 'Instituut', 'default' => 'Education Inc', 'maxLength' => 100],
                    'credential' => ['label' => 'Credential', 'default' => 'Lessons Learned', 'maxLength' => 100],
                    'grade' => ['label' => 'Grade', 'default' => 'B-', 'maxLength' => 100],
                ],
            ],
            'generic2' => [
                'title' => 'Generic credential 2',
                'issuer' => 'sandbox',
                'credentialId' => 'GenericCredential_JWT',
                'grant' => 'preauth',
                'deferred' => false,
                'fields' => [
                    'institute' => ['label' => 'Instituut', 'default' => 'Education Inc', 'maxLength' => 100],
                    'credential' => ['label' => 'Credential', 'default' => 'Digital Knowledge', 'maxLength' => 100],
                    'grade' => ['label' => 'Grade', 'default' => 'A+', 'maxLength' => 100],
                ],
            ],
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
        // credentials that are issued together in one offer, in the order of the offer. The bundle
        // has exactly one deferred credential, which the demo back-office releases.
        'bundles' => [
            'multiple' => [
                'title' => 'Multiple credentials',
                'issuer' => 'sandbox',
                'credentials' => ['generic1', 'generic2', 'deferred'],
            ],
        ],
    ];
};
