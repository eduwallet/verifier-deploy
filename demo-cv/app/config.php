<?php
// this is an example OBC credential from the educredentials examples github
$obccredentialdata = json_decode('{
    "credential_id": "OpenBadgeCredential",
    "type": "vc2",
    "credential": {
        "@context": [
          "https://www.w3.org/ns/credentials/v2",
          "https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json",
          "https://raw.githubusercontent.com/educredentials/obv3-examples/refs/heads/main/contexts/educredential.json"
        ],
        "id": "http://example.com/credentials/crd-A1B2C3",
        "type": [
          "VerifiableCredential",
          "OpenBadgeCredential"
        ],
        "issuer": {
          "id": "https://example.com/issuers/iss-9Z8Y7X",
          "type": [
            "Profile"
          ],
          "name": "Naboo Theed University",
          "otherIdentifier": [
            {
              "type": "IdentifierEntry",
              "identifier": "42NB",
              "identifierType": "ext:BRIN"
            },
            {
              "type": "IdentifierEntry",
              "identifier": "university.naboo",
              "identifierType": "name"
            }
          ],
          "address": {
            "type": [
              "Address"
            ],
            "addressCountry": "Naboo",
            "addressCountryCode": "XX",
            "addressLocality": "Theed",
            "streetAddress": "Jedi Temple 1",
            "postalCode": "1337"
          }
        },
        "validFrom": "2024-08-30T00:00:00Z",
        "validUntil": "2029-08-30T00:00:00Z",
        "credentialSubject": {
          "id": "https://example.com/credentials/stu-1A2B3C",
          "type": [
            "AchievementSubject"
          ],
          "achievement": {
            "id": "https://example.com/achievements/ach-33LML",
            "type": [
              "Achievement",
              "EducredentialAchievement"
            ],
            "name": "Lightsaber Dueling Techniques",
            "image": {
              "id": "https://raw.githubusercontent.com/educredentials/obv3-examples/refs/heads/main/images/lightsaber.jpg",
              "type": "Image"
            },
            "description": "# Lightsaber Dueling Techniques\n This badge is awarded for demonstrating proficiency in lightsaber dueling techniques.\n\n\n      Below text is dummy text to show all the markdown features.\n\n      ## Koptexten\n\n      # h1 Koptekst\n\n      ## h2 Koptekst\n\n      ### h3 Koptekst\n\n      #### h4 Koptekst\n\n      ##### h5 Koptekst\n\n      ###### h6 Koptekst\n\n      ## Regelafbrekingen\n\n      Hier is een regel voor ons om mee te beginnen.\n      >\n\n      Deze regel wordt gescheiden van de vorige door een \">\" \n      (groter dan teken, zonder de aanhalingstekens) \n      zodat het een *aparte paragraaf* wordt.\n      >\n      >\n      >\n      Het intikken van meer > tekens levert echter niet meer lege regels op.\n      \n      ## Nadruk\n\n      **Dit is vetgedrukte tekst**\n\n      *Dit is cursieve tekst*\n\n      ~~Dit is doorgehaalde tekst~~\n\n      ## Blockquotes\n\n      > Blockquotes kunnen ook genest worden...\n      >> ...by using additional greater-than signs right next to each other...\n      > > > ...or with spaces between the arrows.\n\n      ## Ongeordende lijsten\n\n      + Maak een lijst door een regel te beginnen met `+`, `-`, of `*`\n      + Sub-lijsten worden gemaakt door 2 spaties in te laten springen:\n        - Verandering van markeringsteken forceert start van nieuwe lijst:\n          * Ac tristique libero volutpat at\n          + Facilisis in pretium nisl aliquet\n          - Nulla volutpat aliquam velit\n      + Zeer gemakkelijk!\n\n      ## Geordende lijsten\n\n      1. Maak een lijst door een regel te beginnen met `1.\'\n      2. Tweede item\n      3. Derde item\n      4. Zeer gemakkelijk!\n\n      ## Code, Links, Images en Tabellen zijn niet ondersteund",
            "criteria": {
              "narrative": "To earn this badge, you must demonstrate proficiency in lightsaber dueling techniques."
            },
            "inLanguage": "en-US",
            "timeInvestment": 13,
            "participationType": "onsite or blended",
            "assessmentType": "application of a skill",
            "supervisionType": "onsite with identity verification",
            "identityChecked": false,
            "alignment": [
              {
                "type": [
                  "Alignment"
                ],
                "targetType": "ext:QualityAssurance",
                "targetName": "M Performance, Sport and Health",
                "targetDescription": "Toets nieuwe opleiding\n**HBO-master**\npresteren, sport en gezondheid",
                "targetCode": "AV-1337",
                "targetUrl": "https://data.example.com/decisions/AV-1337"
              },
              {
                "type": [
                  "Alignment"
                ],
                "targetType": "ext:EQF",
                "targetName": "EQF level 3",
                "targetCode": "3",
                "targetUrl": "https://content.example.com/description-eqf-levels"
              }
            ],
            "resultDescription": [
              {
                "id": "https://example.com/results/ects-nl-NL-A1B2C3",
                "type": [
                  "ResultDescription"
                ],
                "valueMax": "10",
                "valueMin": "1",
                "name": "Final Project Grade",
                "requiredValue": "6",
                "resultType": "ext:ECTSGradeScore"
              }
            ]
          },
          "result": [
            {
              "type": [
                "Result"
              ],
              "resultDescription": "https://example.com/results/ects-nl-NL-A1B2C3",
              "value": "7.5"
            }
          ]
        },
        "credentialSchema": [
          {
            "id": "https://purl.imsglobal.org/spec/ob/v3p0/schema/json/ob_v3p0_achievementcredential_schema.json",
            "type": "1EdTechJsonSchemaValidator2019"
          },
          {
            "id": "https://raw.githubusercontent.com/educredentials/obv3-examples/refs/heads/main/schemas/extracurricular.json",
            "type": "1EdTechJsonSchemaValidator2019"
          }
        ]
    }    
}');

$abc = [
    "name" => "Academic Base Credential",
    "short" => "abc",
    "credentialId" => "AcademicBaseCredential",
    "presentation" => "ABC",
    "flow" => "preauth",
    "data" => [
        "given_name" => "Martin",
        "family_name" => "Jorgensen",
        "sub" => "mjorgensen",
        "eduperson_unique_id" => "d29632ca63d10461fa3ddc400cb7f5089806a635",
        "email" => "mjorgensen@example.net"
    ]
];

$aec = [
    "name" => "Academic Enrollment Credential",
    "short" => "aec",
    "credentialId" => "AcademicEnrollmentCredential",
    "presentation" => "AEC",
    "flow" => "preauth",
    "data" => [
        "crohoCreboCode" => "AK0092#3",
        "name" => "Linkse Denkbeelden in Rechtse Media",
        "phase" => "doctoraal",
        "modeOfStudy" => "voltijds",
        "institutionBRINCode" => "AK0092",
        "startDate" => "2026-06-05",
        "endDate" => "2027-08-31"
    ]
];

$sc = [
    "name" => "Support Credential",
    "short" => "sc",
    "credentialId" => "SupportCredential",
    "presentation" => "SC",
    "flow" => "preauth",
    "data" => [
        "issuer" => "Universiteit van Harderwijk",
        "title" => "Ondersteuning",
        "name" => "Extra Tijd",
        "institution_shortcode" => "uvh",
        "valid_from" => "2026-06-05",
        "valid_until" => "2027-08-31"
    ]
];

$scc = [
    "name" => "Student Card Credential",
    "short" => "scc",
    "credentialId" => "StudentCardCredential",
    "presentation" => "SCC",
    "flow" => "preauth",
    "data" => [
        "name" => "Pietje Puk",
        "email" =>"pietje@example.com",
        "institution" => "Universiteit van Harderwijk",
        "student_number" => "A12302991"
    ]
];

$sdc = [
    "name" => "Study Data Credential",
    "short" => "sdc",
    "credentialId" => "StudyDataCredential",
    "presentation" => "SDC",
    "flow" => "preauth",
    "data" => [
        "academic_year" => 2026,
        "program" => "Linkse Denkbeelden in Rechtse Media",
        "role" => "Student",
        "mode" => "voltijds",
        "student_number" => "A12302991"
    ]
];

$eec = [
    "name" => "Exam Enrollment Credential",
    "short" => "eec",
    "credentialId" => "ExamEnrollmentCredential",
    "presentation" => "EEC",
    "flow" => "preauth",
    "data" => [
        "title" => "Linkse Denkbeelden in Rechtse Media",
        "code" => "A33123",
        "eduId" => "2878466189919923",
        "support" => "extra_time"
    ]
];

$obc = [
    "name" => "OpenBadgeCredential",
    "short" => "obc",
    "credentialId" => "OpenBadgeCredential",
    "presentation" => "OBC",
    "flow" => "preauth",
    "credential" => $obccredentialdata
];

$obcauth = [
    "name" => "OpenBadgeCredential",
    "short" => "obc",
    "credentialId" => "OpenBadgeCredential",
    "flow" => "auth",
    "callback" => "https://democv.dev.eduwallet.nl/backendcall.php?action=datacallback"
];

$pid = [
    "name" => "Personal ID",
    "short" => "pid",
    "credentialId" => "PID",
    "flow" => "preauth",
    "data" => [
        "personal_administrative_number" => "999782771",      
        "document_number" => "N27P328I12",
        "given_name" => "Erica",
        "family_name" => "Tester",
        "nationality" => "Nederlandse",
        "birth_date" => "1970-01-01",
        "birth_city" => "Dresden",
        "birth_country" => "Deutschland",
        "birth_place" => "Dresden",
        "given_name_birth" => "Erika",
        "family_name_birth" => "Versucher",
        "resident_address" => "Puttenseweg 18b-12",
        "resident_street" => "Puttenseweg",
        "resident_house_number" => "18b-12",
        "resident_postal_code" => "1111 AA",
        "resident_city" => "Harderwijk",
        "resident_country" => "Netherlands",
        "expiry_date" => "2030-02-03",
        "issuance_date" => "2026-04-03",
        "issuing_authority" => "Ministerie van Buitenlandse Zaken",
        "issuing_jurisdiction" => "Netherlands",
        "issuing_country" => "Netherlands",
        "portrait" => "data=image/png;base64;sjkdajsajkadsndsjndkadnkjs",
        "age_birth_year" => 2006,
        "age_in_years" => 17,
        "age_over_13" => 1,
        "age_over_18" => 0,
        "sex" => 1        
    ]
];

$eduid = [
    "name" => "eduID",
    "short" => "eduid",
    "credentialId" => "eduid",
    "flow" => "auth",
    "data" => []
];
$entitlement = [
    "name" => "Entitlement",
    "short" => "entitlement",
    "credentialId" => "entitlement",
    "flow" => "auth",
    "data" => []
];

$proeftuin = [
    "mbob" => [
        "name" => "MBO Beek",
        "short" => "mbob",
        "type" => "issuer",
        "credentials" => [$abc, $aec, $sc, $scc, $sdc, $eec, $obc]
    ],
    "uvh" => [
        "name" => "Universiteit van Harderwijk",
        "short" => "uvh",
        "type" => "issuer",
        "credentials" => [$abc, $aec, $sc, $scc, $sdc, $eec, $obc]
    ],
    "hbot" => [
        "name" => "HBO Texel",
        "short" => "hbot",
        "type" => "issuer",
        "credentials" => [$abc, $aec, $sc, $scc, $sdc, $eec, $obc]
    ],
    "tun" => [
        "name" => "Theed University of Naboo",
        "short" => "tun",
        "type" => "issuer",
        "credentials" => [$abc]
    ],
    "epi" => [
        "name" => "eduID Proeftuin Issuer",
        "short" => "epi",
        "type" => "issuer",
        "credentials" => [$abc]
    ],
    "nlgov" => [
        "name" => "NL Government",
        "short" => "nlgov",
        "type" => "issuer",
        "credentials" => [$pid]
    ],
    "nbgov" => [
        "name" => "Naboo Government",
        "short" => "nbgov",
        "type" => "issuer",
        "credentials" => [$pid]
    ],
    "sandbox" => [
        "name" => "Sandbox",
        "short" => "sandbox",
        "type" => "issuer",
        "credentials" => [
            ["name" => "Academic Base Credential as VCDM2", "short" => "abc_jwt"],
            ["name" => "Academic Base Credential as LDP", "short" => "abc_ld"],
            ["name" => "Academic Base Credential as SD-JWT", "short" => "abc_sd"],
            ["name" => "Personal ID as VCDM2", "short" => "pid_jwt"],
            ["name" => "Personal ID as SD-JWT", "short" => "pid_sd"],
            ["name" => "Generic Credential as VCDM2", "short" => "gc_jwt"],
            ["name" => "Generic Credential as VCDM1.1", "short" => "gc_jwt1"],
            ["name" => "Generic Credential as LDP", "short" => "gc_ld"]
        ]
    ],
    "sandboxver" => [
        "name" => "Sandbox Verifier",
        "short" => "sandboxver",
        "type" => "verifier",
        "credentials" => [$abc, $aec, $sc, $scc, $sdc, $eec, $obc, $pid,
            ["name" => "Generic Credential (VCDM)", "short" => "gc", "presentation" => "GC"],
            ["name" => "Generic Credential (LDP)", "short" => "gcld", "presentation" => "GCLDP"]
        ]
    ]
];

$pilots = [
    "eduid" => [
        "name" => "eduID",
        "short" => "eduid",
        "type" => "issuer",
        "credentials" => [$eduid, $entitlement]
    ],
    "eduidver" => [
        "name" => "eduID Verifier",
        "short" => "eduidver",
        "type" => "verifier",
        "credentials" => [$eduid]
    ],
];
$edubadges = [
    "edubadges" => [
        "name" => "EDUBadges",
        "short" => "edubadges",
        "type" => "issuer",
        "credentials" => [$obcauth]
    ],
];

$groups = [
    "ptdev" => [
        "name" => "Proeftuin Development",
        "env" => "dev",
        "issuers" => $proeftuin,
        "url" => "agent.dev.eduwallet.nl",
        "tenantDomain" => false
    ],
    "pttest" => [
        "name" => "Proeftuin Playground",
        "env" => "playground",
        "issuers" => $proeftuin,
        "url" => "agent.playground.eduwallet.nl",
        "tenantDomain" => true
    ],
    "ptstage" => [
        "name" => "Proeftuin Staging",
        "env" => "staging",
        "issuers" => $proeftuin,
        "url" => "agent.staging.eduwallet.nl",
        "tenantDomain" => true
    ],
    "ptprod" => [
        "name" => "Proeftuin Productie",
        "env" => "prod",
        "issuers" => $proeftuin,
        "url" => "agent.eduwallet.nl",
        "tenantDomain" => true
    ],
    "pdev" => [
        "name" => "Pilots Development",
        "env" => "dev",
        "issuers" => $pilots,
        "url" => "issuer.dev.eduid.nl",
        "tenantDomain" => false
    ],
    "ptest" => [
        "name" => "Pilots Playground",
        "env" => "playground",
        "issuers" => $pilots,
        "url" => "issuer.playground.pilots.eduid.nl",
        "tenantDomain" => false
    ],
    "pstage" => [
        "name" => "Pilots Staging",
        "env" => "staging",
        "issuers" => $pilots,
        "url" => "issuer.staging.pilots.eduid.nl",
        "tenantDomain" => false
    ],
    "pprod" => [
        "name" => "Pilots Productie",
        "env" => "prod",
        "issuers" => $pilots,
        "url" => "issuer.pilots.eduid.nl",
        "tenantDomain" => false
    ],
    "edev" => [
        "name" => "EduBadges Development",
        "env" => "dev",
        "issuers" => $edubadges,
        "url" => "issuer.dev.edubadges.nl",
        "tenantDomain" => false
    ],
    "etest" => [
        "name" => "EduBadges Playground",
        "env" => "playground",
        "issuers" => $edubadges,
        "url" => "issuer.playground.pilots.edubadges.nl",
        "tenantDomain" => false
    ],
    "estage" => [
        "name" => "EduBadges Staging",
        "env" => "staging",
        "issuers" => $edubadges,
        "url" => "issuer.staging.pilots.edubadges.nl",
        "tenantDomain" => false
    ],
    "eprod" => [
        "name" => "EduBadges Productie",
        "env" => "prod",
        "issuers" => $edubadges,
        "url" => "issuer.pilots.edubadges.nl",
        "tenantDomain" => false
    ],
];