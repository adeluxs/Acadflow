<?php

return [
    'orcid' => ['base_url' => env('ORCID_PUBLIC_API_URL', 'https://pub.orcid.org/v3.0')],
    'providers' => [
        'openalex' => ['base_url' => env('OPENALEX_BASE_URL', 'https://api.openalex.org'), 'mailto' => env('SCHOLARLY_CONTACT_EMAIL')],
        'crossref' => ['base_url' => env('CROSSREF_BASE_URL', 'https://api.crossref.org'), 'mailto' => env('SCHOLARLY_CONTACT_EMAIL')],
        'semantic_scholar' => ['base_url' => env('SEMANTIC_SCHOLAR_BASE_URL', 'https://api.semanticscholar.org/graph/v1'), 'api_key' => env('SEMANTIC_SCHOLAR_API_KEY')],
        'core' => ['base_url' => env('CORE_BASE_URL', 'https://api.core.ac.uk/v3'), 'api_key' => env('CORE_API_KEY')],
        'doaj' => ['base_url' => env('DOAJ_BASE_URL', 'https://doaj.org/api')],
        'pubmed' => ['base_url' => env('PUBMED_BASE_URL', 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils')],
        'arxiv' => ['base_url' => env('ARXIV_BASE_URL', 'https://export.arxiv.org')],
    ],
];
