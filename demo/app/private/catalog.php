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
            // an eduID, the first (optional) step of the presentation-during-issuance flow. The claims come from the
            // offer. The issuer allows one eduID identifier ('sub') per holder key, so the identifier is generated once per
            // visitor session (and can be changed by the visitor to get a second eduID in the same wallet).
            'eduid' => [
                'title' => 'eduID',
                'issuer' => 'sandbox',
                'credentialId' => 'eduID',
                'grant' => 'preauth',
                'deferred' => false,
                'fields' => [
                    'sub' => ['label' => 'eduID identifier', 'default' => '', 'generate' => 'demo-', 'maxLength' => 100],
                    'name' => ['label' => 'Full name', 'default' => 'Demo User', 'maxLength' => 100],
                    'given_name' => ['label' => 'Given name', 'default' => 'Demo', 'maxLength' => 100],
                    'family_name' => ['label' => 'Family name', 'default' => 'User', 'maxLength' => 100],
                    'email' => ['label' => 'Email', 'default' => 'demouser@example.com', 'maxLength' => 100],
                    'schac_home_organization' => ['label' => 'Organisation', 'default' => 'example.edu', 'maxLength' => 100],
                    'eduperson_scoped_affiliation' => ['label' => 'Affiliation', 'default' => 'student@example.edu', 'maxLength' => 100],
                    'eduperson_assurance' => ['label' => 'Assurance', 'default' => 'https://refeds.org/assurance/IAP/low', 'maxLength' => 100],
                ],
            ],
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
        // issuance with a presentation: the wallet presents the credentials of the DCQL query before it gets the
        // credential, and the issuer fills the credential from the presented claims. The front-end only sends the key.
        'presentations' => [
            'eduid' => [
                'title' => 'Generic credential from an eduID',
                'issuer' => 'sandbox',
                'credentialId' => 'GenericCredentialPDI',
                // the catalog credential that the visitor can issue first, if the wallet has no eduID yet
                'prerequisite' => 'eduid',
                // credential claim <- claim of the presented credential (the DCQL query id is 'eduID')
                'mapping' => [
                    'firstName' => 'given_name',
                    'lastName' => 'family_name',
                    'eMail' => 'email',
                ],
                // an eduID: the sandbox eduID refers to the vct of the eduID issuer, so both have the same vct
                'dcql' => [
                    'credentials' => [[
                        'id' => 'eduID',
                        'format' => 'dc+sd-jwt',
                        'multiple' => false,
                        'require_cryptographic_holder_binding' => true,
                        'meta' => ['vct_values' => [
                            rtrim($env['ISSUER_EPI_URL'] ?? 'https://epi.issuer.dev.eduwallet.nl', '/') . '/vct/eduid',
                        ]],
                        'claims' => [
                            ['path' => ['given_name']],
                            ['path' => ['family_name']],
                            ['path' => ['email']],
                        ],
                    ]],
                ],
            ],
        ],
    ];
};
