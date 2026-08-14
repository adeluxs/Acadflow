<?php

return [
    'remote_sync' => env('ACADEMIC_CATALOG_REMOTE_SYNC', true),
    'seed_templates' => env('ACADEMIC_CATALOG_SEED_TEMPLATES', true),
    'http_timeout' => (int) env('ACADEMIC_CATALOG_HTTP_TIMEOUT', 20),

    // Official NUC registries. The parser intentionally reads only the institution
    // name, website and year columns and never imports vice-chancellor personal data.
    'nuc' => [
        'federal' => env('NUC_FEDERAL_UNIVERSITIES_URL', 'https://www.nuc.edu.ng/nigerian-univerisities/federal-univeristies/'),
        'state' => env('NUC_STATE_UNIVERSITIES_URL', 'https://www.nuc.edu.ng/nigerian-univerisities/state-univeristies/'),
        'private' => env('NUC_PRIVATE_UNIVERSITIES_URL', 'https://www.nuc.edu.ng/nigerian-univerisities/private-univeristies/'),
    ],

    // NBTE currently links its approved polytechnic registry to Digital NBTE.
    // Keep the endpoint configurable so the source can change without a code release.
    'nbte' => [
        'polytechnics' => env('NBTE_POLYTECHNIC_SOURCE_URL', 'https://www.digitalnbte.nbte.gov.ng/Public/PUCPolytechnics'),
        'approved_index' => env('NBTE_APPROVED_INDEX_URL', 'https://www.web.nbte.gov.ng/tvet%20institutions'),
    ],

    // Community snapshot is only a resilience fallback when the regulator endpoint
    // is temporarily unreachable. A successful official sync always wins and marks
    // its rows with NUC/NBTE provenance.
    'fallback' => [
        'institutions_csv' => env('NIGERIA_INSTITUTION_FALLBACK_URL', 'https://raw.githubusercontent.com/awesomegoodman/higher-institutions-ng/main/resources/NARR/NARR%20institution.csv'),
    ],
];
