<?php
declare(strict_types=1);

function tekg_agent_entity_candidate_groups(array $entity): array
{
    $strict = [];
    $broad = [];

    foreach ([
        (string)($entity['matched_alias'] ?? ''),
        (string)($entity['canonical_label'] ?? $entity['label'] ?? ''),
        (string)($entity['label'] ?? ''),
    ] as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '') {
            $strict[] = $candidate;
        }
    }

    foreach ((array)($entity['aliases'] ?? []) as $alias) {
        $value = trim((string)$alias);
        if ($value !== '') {
            $strict[] = $value;
        }
    }

    foreach ((array)($entity['broad_aliases'] ?? []) as $alias) {
        $value = trim((string)$alias);
        if ($value !== '') {
            $broad[] = $value;
        }
    }

    $strict = array_values(array_unique($strict));
    $broad = array_values(array_filter(array_unique($broad), static fn(string $value): bool => !in_array($value, $strict, true)));

    return [
        'strict' => $strict,
        'broad' => $broad,
    ];
}

function tekg_agent_support_strength(string $level): string
{
    $value = strtolower(trim($level));
    return in_array($value, ['high', 'medium', 'low'], true) ? $value : 'medium';
}

function tekg_agent_make_evidence_item(
    string $sourcePlugin,
    string $claim,
    string $entityScope = '',
    string $supportStrength = 'medium',
    array $rawSourceRef = [],
    array $display = []
): array {
    $title = trim((string)($display['title'] ?? ''));
    $meta = trim((string)($display['meta'] ?? ''));
    $body = trim((string)($display['body'] ?? ''));

    if ($title === '') {
        $title = $entityScope !== '' ? $entityScope : $sourcePlugin;
    }
    if ($body === '') {
        $body = $claim;
    }

    return [
        'source_plugin' => $sourcePlugin,
        'entity_scope' => trim($entityScope),
        'claim' => trim($claim),
        'support_strength' => tekg_agent_support_strength($supportStrength),
        'raw_source_ref' => $rawSourceRef,
        'title' => $title,
        'meta' => $meta,
        'body' => $body,
    ];
}

function tekg_agent_normalize_evidence_item(mixed $item, string $defaultPlugin = 'Unknown'): ?array
{
    if (is_string($item)) {
        $value = trim($item);
        if ($value === '') {
            return null;
        }
        return tekg_agent_make_evidence_item($defaultPlugin, $value);
    }

    if (!is_array($item)) {
        return null;
    }

    if (isset($item['claim']) || isset($item['source_plugin'])) {
        return tekg_agent_make_evidence_item(
            (string)($item['source_plugin'] ?? $defaultPlugin),
            (string)($item['claim'] ?? $item['body'] ?? $item['title'] ?? ''),
            (string)($item['entity_scope'] ?? ''),
            (string)($item['support_strength'] ?? 'medium'),
            (array)($item['raw_source_ref'] ?? []),
            [
                'title' => (string)($item['title'] ?? ''),
                'meta' => (string)($item['meta'] ?? ''),
                'body' => (string)($item['body'] ?? ''),
            ]
        );
    }

    $title = trim((string)($item['title'] ?? $item['label'] ?? $item['name'] ?? ''));
    $body = trim((string)($item['body'] ?? $item['summary'] ?? $item['text'] ?? ''));
    if ($title === '' && $body === '') {
        return null;
    }

    return tekg_agent_make_evidence_item(
        $defaultPlugin,
        $body !== '' ? $body : $title,
        '',
        'medium',
        [],
        [
            'title' => $title,
            'meta' => (string)($item['meta'] ?? ''),
            'body' => $body,
        ]
    );
}

function tekg_agent_json_safe(mixed $value): mixed
{
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = tekg_agent_json_safe($item);
        }
        return $normalized;
    }

    if (is_object($value)) {
        return tekg_agent_json_safe(get_object_vars($value));
    }

    if (is_scalar($value) || $value === null) {
        return $value;
    }

    return (string)$value;
}

function tekg_agent_node_contracts(): array
{
    return [
        'Question Understanding Node' => [
            'input' => ['question'],
            'output' => ['analysis', 'entity_resolution'],
        ],
        'Planning Node' => [
            'input' => ['question', 'analysis', 'entity_resolution', 'session_context'],
            'output' => ['planning'],
        ],
        'Evidence Collection Node' => [
            'input' => ['question', 'analysis', 'planning', 'graph_result', 'analytics_result', 'cypher_result', 'literature_result', 'literature_synthesis', 'tree_result', 'expression_result', 'genome_result', 'sequence_result', 'citation_result', 'collected_results', 'evidence_bundle', 'citation_bundle'],
            'output' => ['collection_state', 'active_expert', 'sufficiency_decision', 'graph_result', 'analytics_result', 'cypher_result', 'literature_result', 'literature_synthesis', 'tree_result', 'expression_result', 'genome_result', 'sequence_result', 'citation_result', 'collected_results', 'evidence_bundle', 'citation_bundle', 'compressed_result'],
        ],
        'Evidence Synthesis Node' => [
            'input' => ['question', 'analysis', 'planning', 'graph_result', 'analytics_result', 'cypher_result', 'literature_result', 'literature_synthesis', 'tree_result', 'expression_result', 'genome_result', 'sequence_result', 'citation_result', 'collected_results', 'evidence_bundle', 'citation_bundle', 'compressed_result'],
            'output' => ['supported_claims', 'conflicting_claims', 'missing_evidence', 'claim_clusters'],
        ],
        'Answer Structuring Node' => [
            'input' => ['question', 'analysis', 'planning', 'collected_results', 'compressed_result', 'graph_result', 'analytics_result', 'cypher_result', 'literature_result', 'literature_synthesis', 'tree_result', 'expression_result', 'genome_result', 'sequence_result', 'citation_result', 'supported_claims', 'conflicting_claims', 'missing_evidence', 'claim_clusters'],
            'output' => ['answer_structure'],
        ],
        'Answer Writer Node' => [
            'input' => ['question', 'analysis', 'answer_structure', 'supported_claims', 'conflicting_claims', 'missing_evidence', 'citation_bundle'],
            'output' => ['answer'],
        ],
        'Process Narrator Node' => [
            'input' => ['event_stream', 'analysis', 'entity_resolution', 'planning', 'collection_state', 'active_expert', 'sufficiency_decision', 'graph_result', 'analytics_result', 'cypher_result', 'literature_result', 'literature_synthesis', 'tree_result', 'expression_result', 'genome_result', 'sequence_result', 'citation_result', 'supported_claims', 'conflicting_claims', 'missing_evidence', 'claim_clusters', 'answer_structure', 'answer'],
            'output' => ['trace_event'],
        ],
    ];
}

interface TekgAgentPluginInterface
{
    public function getName(): string;
    public function run(array $context): array;
}
