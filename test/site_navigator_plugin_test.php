<?php
declare(strict_types=1);

require_once __DIR__ . '/../path_config.php';
require_once __DIR__ . '/../site_i18n.php';
require_once __DIR__ . '/../api/agent/bootstrap.php';

$pluginPath = __DIR__ . '/../api/agent/plugins/SiteNavigatorPlugin.php';
if (!is_file($pluginPath)) {
    fwrite(STDERR, "SiteNavigatorPlugin.php is missing.\n");
    exit(1);
}
require_once $pluginPath;

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

function navigator_context(string $question): array
{
    return [
        'question' => $question,
        'source_page' => 'index',
        'current_url' => 'http://localhost/TE-/index.php',
        'analysis' => [
            'intent' => 'genome',
            'normalized_entities' => [[
                'type' => 'TE',
                'label' => 'L1HS',
                'canonical_label' => 'L1HS',
                'display_label' => 'L1HS',
                'matched_alias' => 'L1HS',
                'aliases' => ['L1HS'],
            ]],
            'alias_chains' => [[
                'type' => 'TE',
                'canonical_label' => 'L1HS',
                'display_label' => 'L1HS',
                'matched_alias' => 'L1HS',
                'aliases' => ['L1HS'],
            ]],
        ],
    ];
}

$plugin = new TekgAgentSiteNavigatorPlugin();

$genome = $plugin->run(navigator_context('L1HS的Genome Annotation Distribution情况'));
assert_same('Site Navigator Plugin', $genome['plugin_name'] ?? '', 'plugin name');
assert_same('ok', $genome['status'] ?? '', 'genome navigation status');
assert_same('search-karyotype-panel', $genome['results']['primary_route']['fragment'] ?? '', 'genome distribution fragment');
assert_same('http://localhost/TE-/search.php?q=L1HS&type=TE#search-karyotype-panel', $genome['results']['primary_route']['url'] ?? '', 'absolute genome distribution URL');
assert_true(str_contains((string)($genome['results']['answer_markdown'] ?? ''), '[Genome Annotation Distribution]('), 'genome markdown link is clickable');

$sequence = $plugin->run(navigator_context('L1HS的完整序列在哪里看'));
assert_same('search-sequence-panel', $sequence['results']['primary_route']['fragment'] ?? '', 'sequence fragment');
assert_same('http://localhost/TE-/search.php?q=L1HS&type=TE#search-sequence-panel', $sequence['results']['primary_route']['url'] ?? '', 'absolute sequence URL');

$expression = $plugin->run(navigator_context('L1HS在哪些组织表达，Expression页面在哪里'));
assert_same('expression-detail-summary', $expression['results']['primary_route']['fragment'] ?? '', 'expression detail fragment');
assert_same('http://localhost/TE-/expression_detail.php?te=L1HS#expression-detail-summary', $expression['results']['primary_route']['url'] ?? '', 'absolute expression URL');

$browser = $plugin->run(navigator_context('L1HS的Genome Browser入口'));
assert_same('search-jbrowse-panel', $browser['results']['primary_route']['fragment'] ?? '', 'search JBrowse panel fragment');
assert_same('http://localhost/TE-/search.php?q=L1HS&type=TE#search-jbrowse-panel', $browser['results']['primary_route']['url'] ?? '', 'absolute JBrowse panel URL');

echo "Site Navigator Plugin tests passed.\n";
