<?php

// Versioned project snapshots keep future catalogue updates from rewriting existing work.
// Guidance is authored by AcadSyntra; linked frameworks remain the reporting authorities.
$families = [
    'quantitative' => [
        'sections' => ['Introduction', 'Literature review', 'Research design and measures', 'Sampling and analysis plan', 'Results', 'Discussion', 'Conclusions'],
        'stages' => ['Define questions and variables', 'Specify sampling and analysis', 'Review ethics and pilot instruments', 'Collect and validate data', 'Analyse and report uncertainty', 'Interpret and prepare submission'],
        'instruments' => ['Questionnaire', 'Measurement codebook', 'Data collection log'],
        'analysis' => ['Descriptive statistics', 'Effect sizes and confidence intervals', 'Assumption checks and justified inferential tests'],
        'requirements' => ['Operational definitions and measurement quality', 'Sampling frame, power or precision justification', 'Missing-data and bias assessment'],
        'frameworks' => [],
    ],
    'qualitative' => [
        'sections' => ['Research context and questions', 'Literature and conceptual position', 'Methodology and reflexivity', 'Recruitment and data generation', 'Findings and themes', 'Interpretation and trustworthiness', 'Conclusions'],
        'stages' => ['Frame the inquiry', 'Choose epistemology and sampling', 'Review ethics and develop guides', 'Generate and document evidence', 'Code, compare and develop interpretations', 'Review reflexivity and report'],
        'instruments' => ['Interview guide', 'Observation field notes', 'Reflexive journal', 'Coding and theme record'],
        'analysis' => ['Qualitative coding', 'Thematic interpretation', 'Negative cases and reflexive memos'],
        'requirements' => ['Researcher positionality and relationships', 'Recruitment rationale and information sufficiency', 'Evidence trail linking excerpts to interpretations'],
        'frameworks' => ['srqr'],
    ],
    'mixed' => [
        'sections' => ['Introduction and integrated questions', 'Literature and mixed-methods rationale', 'Design and integration plan', 'Quantitative strand', 'Qualitative strand', 'Joint display and integrated findings', 'Discussion and conclusions'],
        'stages' => ['Justify mixing and sequence', 'Plan both strands and integration', 'Review ethics and instruments', 'Collect both evidence streams', 'Analyse strands and construct joint displays', 'Interpret convergence and divergence'],
        'instruments' => ['Questionnaire', 'Interview guide', 'Integration matrix'],
        'analysis' => ['Descriptive statistics', 'Qualitative coding', 'Joint displays and meta-inferences'],
        'requirements' => ['Timing, priority and sampling relationship of strands', 'Explicit integration points', 'Explain contradictory findings'],
        'frameworks' => ['srqr'],
    ],
    'review' => [
        'sections' => ['Review question and rationale', 'Protocol and eligibility criteria', 'Search strategy', 'Screening and selection', 'Extraction and quality appraisal', 'Synthesis of evidence', 'Discussion and limitations'],
        'stages' => ['Specify review question and protocol', 'Document reproducible search', 'Screen and record exclusion reasons', 'Extract and appraise evidence', 'Synthesize included studies', 'Report search dates and selection flow'],
        'instruments' => ['Search strategy log', 'Screening and exclusion log', 'Evidence extraction matrix'],
        'analysis' => ['Evidence synthesis', 'Risk of bias assessment', 'Heterogeneity and certainty assessment'],
        'requirements' => ['Eligibility criteria and dated database searches', 'Traceable screening decisions', 'Source provenance and duplicate handling'],
        'frameworks' => ['prisma'],
    ],
    'design' => [
        'sections' => ['Problem and requirements', 'Related work and design constraints', 'Design methodology', 'Architecture and implementation', 'Testing and evaluation', 'Discussion and limitations', 'Conclusions and future work'],
        'stages' => ['Identify problem and stakeholders', 'Specify acceptance criteria', 'Design and prototype', 'Implement with versioned artefacts', 'Evaluate against requirements', 'Document reproducibility and handover'],
        'instruments' => ['Requirements matrix', 'Design decision log', 'Test and evaluation protocol'],
        'analysis' => ['Requirements traceability', 'Benchmark comparison', 'Usability and performance evaluation'],
        'requirements' => ['Explicit design constraints and alternatives', 'Reproducible build and evaluation procedure', 'Evidence of testing, failures and limitations'],
        'frameworks' => [],
    ],
    'humanities' => [
        'sections' => ['Research question and context', 'Scholarship and conceptual framework', 'Corpus and interpretive method', 'Analysis and argument', 'Counterarguments and interpretation', 'Conclusions'],
        'stages' => ['Define question and corpus', 'Establish provenance and context', 'Select interpretive approach', 'Analyse primary sources', 'Evaluate competing interpretations', 'Develop and defend argument'],
        'instruments' => ['Source and provenance register', 'Analytical memo', 'Argument and counterargument map'],
        'analysis' => ['Source criticism', 'Interpretive analysis', 'Comparative argumentation'],
        'requirements' => ['Primary versus secondary sources distinguished', 'Transparent selection and interpretive assumptions', 'Counter-evidence and rival explanations'],
        'frameworks' => [],
    ],
    'practice' => [
        'sections' => ['Creative inquiry and context', 'Critical and theoretical influences', 'Practice methodology', 'Process and artefact portfolio', 'Critical reflection and evaluation', 'Contribution and conclusions'],
        'stages' => ['Frame the creative question', 'Situate practice and influences', 'Plan ethical documentation', 'Develop and document iterations', 'Reflect and evaluate artefacts', 'Present portfolio and contribution'],
        'instruments' => ['Practice journal', 'Portfolio register', 'Critical reflection memo'],
        'analysis' => ['Reflective analysis', 'Artefact critique', 'Audience or peer evaluation'],
        'requirements' => ['Document iterations and creative decisions', 'Copyright, consent and attribution', 'Explain how practice contributes knowledge'],
        'frameworks' => [],
    ],
];
// Each design adds its own decisions, instruments and analysis to its family.
$designs = [
    'quantitative' => ['Quantitative research', 'quantitative', 'Specify constructs, variables and a testable analysis plan.'],
    'qualitative' => ['Qualitative research', 'qualitative', 'Justify the interpretive approach, reflexivity and evidence trail.'],
    'mixed_methods' => ['Mixed-methods research', 'mixed', 'Choose convergent, explanatory sequential or exploratory sequential integration.'],
    'experimental' => ['Experimental research', 'quantitative', 'Document allocation, controls, blinding, intervention fidelity and causal assumptions.', ['Allocation and intervention protocol'], ['Between-group effects and uncertainty']],
    'quasi_experimental' => ['Quasi-experimental research', 'quantitative', 'Address non-random assignment, baseline imbalance and confounding.', ['Group comparability and confounder register'], ['Adjusted comparisons or difference-in-differences where justified']],
    'descriptive' => ['Descriptive research', 'quantitative', 'Define the population and estimation targets without causal claims.', ['Descriptive variable codebook'], ['Frequency distributions and precision']],
    'exploratory' => ['Exploratory research', 'mixed', 'Document evolving questions and clearly label exploratory analyses.', ['Exploratory decision journal'], ['Pattern exploration and triangulation']],
    'explanatory' => ['Explanatory research', 'mixed', 'Specify mechanisms and evaluate alternative explanations.', ['Mechanism and rival explanation map'], ['Explanatory integration']],
    'correlational' => ['Correlational research', 'quantitative', 'Assess measurement scale, association, outliers and non-causal interpretation.', ['Variable-pair and confounder plan'], ['Pearson or rank correlation as justified']],
    'survey' => ['Survey research', 'quantitative', 'Document recruitment, non-response, questionnaire pilot and sampling weights.', ['Questionnaire and pilot feedback'], ['Survey estimates and non-response assessment']],
    'interviews' => ['Interview study', 'qualitative', 'Record interviewer relationships, guide development and transcription decisions.', ['Semi-structured interview guide'], ['Case comparison and thematic interpretation'], ['coreq', 'srqr']],
    'focus_groups' => ['Focus groups', 'qualitative', 'Address group dynamics, moderation, confidentiality and dissenting views.', ['Moderator guide and group context log'], ['Interaction-sensitive thematic analysis'], ['coreq', 'srqr']],
    'observation' => ['Observational study', 'qualitative', 'Specify observer role, field access, context and recording procedures.', ['Observation schedule and field diary']],
    'case_study' => ['Case study', 'qualitative', 'Define case boundaries, units of analysis and multiple evidence sources.', ['Case boundary and evidence matrix'], ['Within-case and cross-case analysis']],
    'ethnography' => ['Ethnography', 'qualitative', 'Explain immersion, field relationships, positionality and representation.', ['Ethnographic field diary'], ['Cultural interpretation and thick description']],
    'phenomenology' => ['Phenomenology', 'qualitative', 'Choose a phenomenological tradition and document lived-experience interpretation.', ['Lived-experience interview and reflexive memo'], ['Meaning units and experiential interpretation']],
    'grounded_theory' => ['Grounded theory', 'qualitative', 'Document theoretical sampling, constant comparison and theory development.', ['Theoretical sampling and category memo'], ['Constant comparison and category development']],
    'narrative' => ['Narrative inquiry', 'qualitative', 'Preserve sequence, voice, context and co-construction of accounts.', ['Narrative chronology'], ['Narrative structure and positioning']],
    'action_research' => ['Action research', 'mixed', 'Document iterative plan, act, observe and reflect cycles with participants.', ['Action cycle and reflection log'], ['Cycle evaluation and practitioner reflection']],
    'participatory' => ['Participatory research', 'mixed', 'Record shared governance, power relationships and community ownership.', ['Participation and shared decision agreement'], ['Co-analysis and community validation']],
    'longitudinal' => ['Longitudinal study', 'quantitative', 'Specify waves, retention, attrition and within-person dependence.', ['Wave schedule and retention plan'], ['Repeated-measure or longitudinal modelling'], ['strobe']],
    'cross_sectional' => ['Cross-sectional study', 'quantitative', 'Define the observation window, selection bias and temporal limitations.', ['Observation-window and recruitment log'], ['Prevalence and association estimates'], ['strobe']],
    'systematic_review' => ['Systematic review', 'review', 'Pre-specify the protocol, screening process and risk-of-bias approach.'],
    'scoping_review' => ['Scoping review', 'review', 'Map concepts and evidence types using a PCC question and charting process.', ['PCC eligibility and charting form'], ['Evidence mapping and descriptive charting'], ['prisma_scr']],
    'narrative_review' => ['Narrative literature review', 'review', 'Explain scope and source selection without claiming systematic completeness.', ['Narrative review selection rationale'], ['Critical thematic synthesis'], []],
    'meta_analysis' => ['Meta-analysis', 'review', 'Pre-specify effect metrics, pooling model, dependence and sensitivity analyses.', ['Effect size and variance extraction form'], ['Effect-size pooling, heterogeneity and sensitivity analysis']],
    'bibliometric' => ['Bibliometric research', 'review', 'Record database coverage, query, deduplication and author disambiguation.', ['Bibliographic cleaning and disambiguation log'], ['Co-citation, co-authorship and keyword networks'], []],
    'historical' => ['Historical research', 'humanities', 'Assess archival provenance, chronology and source reliability.', ['Archival source criticism register'], ['Chronological and contextual interpretation']],
    'philosophical' => ['Philosophical research', 'humanities', 'Define concepts, reconstruct arguments and address counterexamples.', ['Argument reconstruction worksheet'], ['Conceptual analysis and logical critique']],
    'doctrinal_legal' => ['Doctrinal legal research', 'humanities', 'Specify jurisdiction, authority hierarchy and currency of law.', ['Legislation, cases and authority table'], ['Doctrinal synthesis and comparative legal reasoning']],
    'computational' => ['Computational research', 'design', 'Version algorithms, environments, dependencies and reproducibility seeds.', ['Algorithm and environment specification'], ['Complexity and reproducibility evaluation']],
    'simulation' => ['Simulation study', 'design', 'Specify model assumptions, calibration, verification and sensitivity.', ['Simulation scenario and parameter register'], ['Sensitivity and model validation']],
    'data_science_ai' => ['Data science and AI research', 'design', 'Track data provenance, train/test separation, leakage, fairness and model limitations.', ['Dataset/model card and evaluation protocol'], ['Held-out evaluation, calibration and error analysis']],
    'engineering' => ['Engineering design', 'design', 'Specify safety constraints, design calculations and physical validation.', ['Design specification and safety review'], ['Verification against engineering requirements']],
    'prototyping' => ['Prototyping project', 'design', 'Record prototypes, acceptance criteria and feedback-driven iterations.', ['Prototype iteration and test log'], ['Prototype comparison and feasibility evaluation']],
    'software' => ['Software development research', 'design', 'Connect requirements to architecture, implementation and test evidence.', ['Software requirements and test traceability'], ['Functional, performance and usability evaluation']],
    'clinical' => ['Clinical research', 'quantitative', 'Select a specific study design. Ethics committee approval and any trial registration must be obtained independently.', ['Clinical protocol and safety reporting plan'], ['Prespecified clinical outcome analysis'], ['consort', 'spirit']],
    'laboratory' => ['Laboratory research', 'quantitative', 'Record SOPs, calibration, replicates, controls and batch effects.', ['Laboratory protocol and calibration record'], ['Replicate variability and quality control']],
    'environmental' => ['Environmental research', 'mixed', 'Document spatial and temporal sampling, permits and measurement calibration.', ['Site sampling and quality-assurance plan'], ['Spatial/temporal patterns and uncertainty']],
    'field' => ['Field research', 'mixed', 'Document site access, field safety, permits and context-dependent bias.', ['Field protocol and site log'], ['Site comparison and triangulation']],
    'creative_practice' => ['Creative practice-based research', 'practice', 'Link documented creative practice to an explicit research contribution.'],
    'interdisciplinary' => ['Interdisciplinary research', 'mixed', 'Make disciplinary assumptions, integration and shared quality criteria explicit.', ['Disciplinary integration agreement'], ['Integrated interpretation across disciplines']],
];

$methodologies = [];
foreach ($designs as $key => $row) {
    [$label, $family, $decision] = $row;
    $base = $families[$family];
    $methodologies[$key] = array_merge($base, [
        'label' => $label, 'family' => $family, 'decision' => $decision,
        'instruments' => array_values(array_unique(array_merge($base['instruments'], $row[3] ?? []))),
        'analysis' => array_values(array_unique(array_merge($base['analysis'], $row[4] ?? []))),
        'requirements' => array_merge($base['requirements'], [$decision]),
        'frameworks' => $row[5] ?? $base['frameworks'],
    ]);
}

$reportingChecks = [
    'prisma' => ['Protocol, registration status and changes disclosed', 'Search strategies, databases and dates documented', 'Eligibility and screening decisions traceable', 'Selection flow and exclusion reasons accounted for', 'Appraisal, synthesis and uncertainty explained', 'Availability of extraction data and limitations stated'],
    'prisma_scr' => ['Review purpose and scope justified', 'Population, concept and context criteria explicit', 'Search and selection process reproducible', 'Charting fields and evidence map described', 'Limitations and implications supported'],
    'consort' => ['Trial registration and protocol availability documented', 'Allocation and concealment procedures described', 'Outcomes and analysis population prespecified', 'Participant flow and missing outcomes explained', 'Harms and uncertainty reported alongside effects', 'Funding, conflicts and data-sharing arrangements disclosed'],
    'spirit' => ['Trial rationale, objectives and design specified', 'Interventions, outcomes and visit schedule defined', 'Allocation and sample-size rationale documented', 'Analysis, missingness and monitoring plans explicit', 'Ethics, consent, registration and dissemination arrangements documented'],
    'strobe' => ['Design, setting and observation dates explicit', 'Recruitment and selection criteria documented', 'Variables, measurements and confounding addressed', 'Missingness, bias and analysis decisions explained', 'Estimates, uncertainty and limitations reported'],
    'coreq' => ['Interviewer background and participant relationships disclosed', 'Recruitment, setting and guide development explained', 'Recording, transcription and analytic process described', 'Quotes linked to anonymised source identifiers', 'Themes and differing views supported by evidence'],
    'srqr' => ['Research orientation and context explicit', 'Researcher position and reflexivity documented', 'Sampling and evidence-generation rationale explained', 'Analysis and trustworthiness procedures transparent', 'Interpretations linked to actual evidence and limitations'],
];

$catalogue = [
    'version' => 1,
    'levels' => ['nd' => 'National Diploma (ND)', 'hnd' => 'Higher National Diploma (HND)', 'undergraduate' => 'Undergraduate', 'postgraduate' => 'Postgraduate diploma', 'masters' => "Master’s", 'doctoral' => 'Doctoral', 'professional' => 'Independent / professional'],
    'styles' => ['apa' => 'APA', 'ieee' => 'IEEE', 'mla' => 'MLA', 'chicago' => 'Chicago', 'harvard' => 'Harvard', 'vancouver' => 'Vancouver'],
    'frameworks' => [
        'prisma' => ['label' => 'PRISMA 2020', 'url' => 'https://www.prisma-statement.org/prisma-2020', 'scope' => 'Systematic reviews; choose relevant extensions.'],
        'prisma_scr' => ['label' => 'PRISMA-ScR', 'url' => 'https://www.prisma-statement.org/scoping', 'scope' => 'Scoping reviews.'],
        'consort' => ['label' => 'CONSORT 2025', 'url' => 'https://www.consort-spirit.org/', 'scope' => 'Randomised trial results only; not every clinical study.'],
        'spirit' => ['label' => 'SPIRIT 2025', 'url' => 'https://www.consort-spirit.org/', 'scope' => 'Randomised trial protocols.'],
        'strobe' => ['label' => 'STROBE', 'url' => 'https://www.strobe-statement.org/', 'scope' => 'Epidemiological cohort, case-control and cross-sectional studies.'],
        'coreq' => ['label' => 'COREQ', 'url' => 'https://www.equator-network.org/reporting-guidelines/coreq/', 'scope' => 'Qualitative interviews and focus groups.'],
        'srqr' => ['label' => 'SRQR', 'url' => 'https://www.equator-network.org/reporting-guidelines/srqr/', 'scope' => 'Qualitative research reporting.'],
    ],
    'methodologies' => $methodologies,
];

foreach ($catalogue['frameworks'] as $key => &$framework) $framework['checks'] = $reportingChecks[$key] ?? [];
unset($framework);
return $catalogue;
