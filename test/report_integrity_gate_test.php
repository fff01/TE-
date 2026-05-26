<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$gatePath = $root . '/api/agent/contracts/ReportIntegrityGate.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assert_contains_string(string $needle, array $haystack, string $message): void
{
    foreach ($haystack as $item) {
        if (is_string($item) && str_contains($item, $needle)) {
            return;
        }
    }
    fwrite(STDERR, "Assertion failed: {$message}\nNeedle: {$needle}\nHaystack: " . var_export($haystack, true) . "\n");
    exit(1);
}

assert_true(is_file($gatePath), 'ReportIntegrityGate.php should exist');
require_once $gatePath;
assert_true(class_exists('ReportIntegrityGate'), 'ReportIntegrityGate class should be loadable');

$package = [
    'claims' => [
        [
            'id' => 'claim_1',
            'text' => 'LINE-1 activation is associated with cancer.',
            'citation_ids' => ['citation_1'],
            'route_ids' => ['route_1'],
        ],
    ],
    'citation_map' => [
        [
            'id' => 'citation_1',
            'citation' => [
                'pmid' => '12345',
                'url' => 'https://pubmed.ncbi.nlm.nih.gov/12345/',
            ],
        ],
    ],
    'route_map' => [
        [
            'id' => 'route_1',
            'route' => [
                'url' => 'http://localhost/TE-/search.php?q=L1HS&type=TE#search-literature-panel',
                'href' => '/TE-/search.php?q=L1HS&type=TE#search-literature-panel',
            ],
        ],
    ],
];

$valid = ReportIntegrityGate::check(
    "## Evidence Review\nSupported report cites PMID 12345 and [Literature](https://pubmed.ncbi.nlm.nih.gov/12345/) with citation_id: citation_1 and route_id: route_1.\nLINE-1 activation is associated with cancer.",
    $package,
    [
        'claim_nodes' => [
            ['id' => 'claim_node_1', 'claim_id' => 'claim_1', 'text' => 'LINE-1 activation is associated with cancer.'],
        ],
    ],
    [
        'sections' => [
            ['key' => 'evidence_review', 'title' => 'Evidence Review'],
        ],
    ]
);
assert_same(true, $valid['ok'], 'valid report passes');
assert_same([], $valid['errors'], 'valid report has no errors');
assert_same(['12345'], $valid['cited_pmids'], 'valid report returns cited PMIDs');
assert_same(['https://pubmed.ncbi.nlm.nih.gov/12345/'], $valid['linked_urls'], 'valid report returns linked URLs');
assert_same([], $valid['unsupported_markers'], 'valid report has no unsupported markers');

$missingPlannedSection = ReportIntegrityGate::check('LINE-1 activation is associated with cancer. PMID 12345', $package, [], [
    'sections' => [
        ['key' => 'limitations', 'title' => 'Limitations'],
    ],
]);
assert_same(true, $missingPlannedSection['ok'], 'missing planned section warns but does not fail');
assert_same([], $missingPlannedSection['errors'], 'missing planned section is not a hard error');
assert_contains_string('planned section', $missingPlannedSection['warnings'], 'missing planned section warning is reported');

$translatedSectionTitle = ReportIntegrityGate::check(
    '## 证据综述' . "\n" . 'LINE-1 activation is associated with cancer. PMID 12345',
    $package,
    [],
    [
        'sections' => [
            ['key' => 'evidence_review', 'title' => 'Evidence Review'],
        ],
    ]
);
assert_same(true, $translatedSectionTitle['ok'], 'translated section titles do not block Chinese reports');
assert_contains_string('planned section', $translatedSectionTitle['warnings'], 'translated section title warning is reported');

$missingWalkClaim = ReportIntegrityGate::check('## Evidence Review' . "\n" . 'PMID 12345 only.', $package, [
    'claim_nodes' => [
        ['id' => 'claim_node_1', 'claim_id' => 'claim_1', 'text' => 'LINE-1 activation is associated with cancer.'],
    ],
], []);
assert_same(true, $missingWalkClaim['ok'], 'missing walk claim is warning, not blocking');
assert_contains_string('walk claim', $missingWalkClaim['warnings'], 'missing walk claim warning is reported');

$badPmid = ReportIntegrityGate::check('Unsupported PMID 99999 is cited.', $package);
assert_same(false, $badPmid['ok'], 'unknown PMID fails');
assert_contains_string('PMID 99999', $badPmid['errors'], 'unknown PMID error is reported');

$badUrl = ReportIntegrityGate::check('Unsupported URL https://example.org/missing is linked.', $package);
assert_same(false, $badUrl['ok'], 'unknown URL fails');
assert_contains_string('https://example.org/missing', $badUrl['errors'], 'unknown URL error is reported');

$emptyClaimsPackage = $package;
$emptyClaimsPackage['claims'] = [];
$strongConclusion = ReportIntegrityGate::check('This report demonstrates a causal relationship.', $emptyClaimsPackage);
assert_same(false, $strongConclusion['ok'], 'strong conclusion without claims fails');
assert_contains_string('strong conclusion', $strongConclusion['errors'], 'strong conclusion error is reported');

$badMarkers = ReportIntegrityGate::check('Uses citation_id: citation_404 and route_id: route_404.', $package);
assert_same(false, $badMarkers['ok'], 'unknown citation_id and route_id markers fail');
assert_same(['citation_id: citation_404', 'route_id: route_404'], $badMarkers['unsupported_markers'], 'unsupported markers are returned');

echo "report_integrity_gate_test passed\n";
