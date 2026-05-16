<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/path_config.php';

function tekg_taxonomy_env_value(array $names, ?string $default = null): ?string
{
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }
    }
    return $default;
}

function tekg_taxonomy_config(): array
{
    $localConfig = [];
    $localConfigPath = __DIR__ . '/config.local.php';
    if (is_file($localConfigPath)) {
        $loaded = require $localConfigPath;
        if (is_array($loaded)) {
            $localConfig = $loaded;
        }
    }

    return [
        'neo4j_url' => $localConfig['neo4j_url'] ?? tekg_taxonomy_env_value(['NEO4J_HTTP_URL_BIOLOGY', 'NEO4J_HTTP_URL'], 'http://127.0.0.1:7474/db/tekg3/tx/commit'),
        'neo4j_user' => $localConfig['neo4j_user'] ?? tekg_taxonomy_env_value(['NEO4J_USER_BIOLOGY', 'NEO4J_USER'], 'neo4j'),
        'neo4j_password' => $localConfig['neo4j_password'] ?? tekg_taxonomy_env_value(['NEO4J_PASSWORD_BIOLOGY', 'NEO4J_PASSWORD'], ''),
    ];
}

function tekg_taxonomy_database_name(array $config): string
{
    $url = (string)($config['neo4j_url'] ?? '');
    if (preg_match('#/db/([^/]+)/tx/commit#', $url, $matches) === 1) {
        return (string)$matches[1];
    }
    return '';
}

function tekg_taxonomy_run_neo4j(array $config, string $statement, array $parameters = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required');
    }
    if (trim((string)($config['neo4j_password'] ?? '')) === '') {
        throw new RuntimeException('Neo4j password is not configured');
    }

    $payload = json_encode([
        'statements' => [[
            'statement' => $statement,
            'parameters' => $parameters === [] ? new stdClass() : $parameters,
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init((string)$config['neo4j_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => (string)$config['neo4j_user'] . ':' . (string)$config['neo4j_password'],
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Neo4j request failed: ' . $error);
    }
    if ($status >= 400) {
        throw new RuntimeException('Neo4j HTTP ' . $status);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Neo4j response is not valid JSON');
    }
    if (!empty($decoded['errors'])) {
        $message = (string)($decoded['errors'][0]['message'] ?? 'Neo4j query failed');
        throw new RuntimeException($message);
    }

    $result = $decoded['results'][0] ?? null;
    if (!is_array($result)) {
        return [];
    }

    $columns = $result['columns'] ?? [];
    $rows = [];
    foreach (($result['data'] ?? []) as $entry) {
        $values = $entry['row'] ?? [];
        $row = [];
        foreach ($columns as $index => $column) {
            $row[(string)$column] = $values[$index] ?? null;
        }
        $rows[] = $row;
    }

    return $rows;
}

function tekg_taxonomy_parse_names(string $raw): array
{
    $names = [];
    foreach (preg_split('/[,;\n\r\t]+/', $raw) ?: [] as $part) {
        $name = trim((string)$part);
        if ($name !== '') {
            $names[] = $name;
        }
    }
    return array_values(array_unique($names));
}

function tekg_taxonomy_normalize_item(array $row): array
{
    $path = [
        'class' => $row['taxonomy_class'] ?? null,
        'subclass' => $row['taxonomy_subclass'] ?? null,
        'order' => $row['taxonomy_order'] ?? null,
        'superfamily' => $row['taxonomy_superfamily'] ?? null,
        'family' => $row['taxonomy_family'] ?? null,
        'subclade' => $row['taxonomy_subclade'] ?? null,
    ];
    $name = trim((string)($row['name'] ?? ''));
    $pathLabels = array_values(array_filter(
        array_map(static fn($value): string => trim((string)$value), array_values($path)),
        static fn(string $value): bool => $value !== ''
    ));
    if ($name !== '' && (empty($pathLabels) || end($pathLabels) !== $name)) {
        $pathLabels[] = $name;
    }

    return [
        'name' => $name,
        'taxonomy_group' => $row['taxonomy_group'] ?? null,
        'taxonomy_status' => $row['taxonomy_status'] ?? null,
        'taxonomy_source' => $row['taxonomy_source'] ?? null,
        'taxonomy_canonical_name' => $row['taxonomy_canonical_name'] ?? $name,
        'path' => $path,
        'path_labels' => $pathLabels,
        'display_path' => implode(' --- ', array_merge(['TE'], $pathLabels)),
        'is_leaf_standard' => (bool)($row['is_leaf_standard'] ?? false),
        'homepage_chart_included' => (bool)($row['homepage_chart_included'] ?? false),
    ];
}

function tekg_taxonomy_fetch_items(?array $names = null, ?array $config = null): array
{
    $config ??= tekg_taxonomy_config();
    $names = array_values(array_filter(array_map('strval', $names ?? []), static fn(string $name): bool => trim($name) !== ''));
    $rows = tekg_taxonomy_run_neo4j(
        $config,
        <<<'CYPHER'
MATCH (t:TE)
WHERE size($names) = 0 OR t.name IN $names OR t.taxonomy_canonical_name IN $names
RETURN t.name AS name,
       t.taxonomy_group AS taxonomy_group,
       t.taxonomy_status AS taxonomy_status,
       t.taxonomy_source AS taxonomy_source,
       t.taxonomy_canonical_name AS taxonomy_canonical_name,
       t.taxonomy_class AS taxonomy_class,
       t.taxonomy_subclass AS taxonomy_subclass,
       t.taxonomy_order AS taxonomy_order,
       t.taxonomy_superfamily AS taxonomy_superfamily,
       t.taxonomy_family AS taxonomy_family,
       t.taxonomy_subclade AS taxonomy_subclade,
       t.is_leaf_standard AS is_leaf_standard,
       t.homepage_chart_included AS homepage_chart_included
ORDER BY toLower(t.name)
CYPHER,
        ['names' => $names]
    );

    return array_map('tekg_taxonomy_normalize_item', $rows);
}

function tekg_taxonomy_index_items(array $items): array
{
    $index = [];
    foreach ($items as $item) {
        $name = trim((string)($item['name'] ?? ''));
        if ($name !== '') {
            $index[$name] = $item;
            $index[tekg_taxonomy_canonical_key($name)] = $item;
        }
        $canonicalName = trim((string)($item['taxonomy_canonical_name'] ?? ''));
        if ($canonicalName !== '') {
            $index[$canonicalName] = $item;
            $index[tekg_taxonomy_canonical_key($canonicalName)] = $item;
        }
    }
    return $index;
}

function tekg_taxonomy_canonical_key(string $value): string
{
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\\s_\\-]+/', '', $value) ?? $value;
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function tekg_taxonomy_find_item(string $query, array $items): ?array
{
    $index = tekg_taxonomy_index_items($items);
    return $index[$query] ?? $index[tekg_taxonomy_canonical_key($query)] ?? null;
}

function tekg_taxonomy_tree_payload(array $items): array
{
    $nodes = [
        'TE' => [
            'name' => 'TE',
            'original_label' => 'TE',
            'depth' => 0,
            'description' => 'TE root synthesized from Neo4j taxonomy properties.',
        ],
    ];
    $edges = [];

    foreach ($items as $item) {
        $labels = array_values(array_filter(
            array_map(static fn($value): string => trim((string)$value), (array)($item['path_labels'] ?? [])),
            static fn(string $value): bool => $value !== ''
        ));
        $parent = 'TE';
        foreach ($labels as $index => $label) {
            $depth = $index + 1;
            $nodes[$label] ??= [
                'name' => $label,
                'original_label' => $label,
                'depth' => $depth,
                'description' => $label . ' taxonomy node synthesized from Neo4j tekg3.',
            ];
            $edgeKey = $label . "\0" . $parent;
            $edges[$edgeKey] = [
                'child' => $label,
                'parent' => $parent,
                'relation' => 'SUBFAMILY_OF',
            ];
            $parent = $label;
        }
    }

    $nodeList = array_values($nodes);
    usort($nodeList, static function (array $left, array $right): int {
        $depthCompare = ((int)($left['depth'] ?? 0)) <=> ((int)($right['depth'] ?? 0));
        return $depthCompare !== 0 ? $depthCompare : strcasecmp((string)$left['name'], (string)$right['name']);
    });
    $edgeList = array_values($edges);
    usort($edgeList, static fn(array $left, array $right): int => strcasecmp((string)$left['parent'] . (string)$left['child'], (string)$right['parent'] . (string)$right['child']));

    return [
        'root' => 'TE',
        'root_label' => 'TE',
        'node_count' => count($nodeList),
        'edge_count' => count($edgeList),
        'nodes' => $nodeList,
        'edges' => $edgeList,
    ];
}

function tekg_taxonomy_summary_payload(array $items): array
{
    $summary = [
        'total_te_nodes' => count($items),
        'with_taxonomy_class' => 0,
        'homepage_chart_included' => 0,
        'is_leaf_standard' => 0,
        'taxonomy_groups' => [],
        'taxonomy_sources' => [],
    ];
    foreach ($items as $item) {
        if (trim((string)($item['path']['class'] ?? '')) !== '') {
            $summary['with_taxonomy_class']++;
        }
        if (!empty($item['homepage_chart_included'])) {
            $summary['homepage_chart_included']++;
        }
        if (!empty($item['is_leaf_standard'])) {
            $summary['is_leaf_standard']++;
        }
        $group = (string)($item['taxonomy_group'] ?? '');
        if ($group !== '') {
            $summary['taxonomy_groups'][$group] = ($summary['taxonomy_groups'][$group] ?? 0) + 1;
        }
        $source = (string)($item['taxonomy_source'] ?? '');
        if ($source !== '') {
            $summary['taxonomy_sources'][$source] = ($summary['taxonomy_sources'][$source] ?? 0) + 1;
        }
    }
    ksort($summary['taxonomy_groups']);
    ksort($summary['taxonomy_sources']);
    return $summary;
}
