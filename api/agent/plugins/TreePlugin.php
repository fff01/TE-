<?php
declare(strict_types=1);

final class TekgAgentTreePlugin implements TekgAgentPluginInterface
{
    private ?array $tree = null;
    private ?array $diseaseTopMap = null;

    public function getName(): string
    {
        return 'Tree Plugin';
    }

    public function run(array $context): array
    {
        $started = microtime(true);
        $analysis = $context['analysis'] ?? [];
        $entities = is_array($analysis['normalized_entities'] ?? null) ? $analysis['normalized_entities'] : [];
        $teEntities = array_values(array_filter($entities, static fn(array $item): bool => ($item['type'] ?? '') === 'TE'));
        $diseaseEntities = array_values(array_filter($entities, static fn(array $item): bool => ($item['type'] ?? '') === 'Disease'));
        $results = [];
        $evidence = [];

        if (($analysis['asks_for_classification'] ?? false) && $teEntities === []) {
            foreach ($this->topTeClasses() as $name) {
                $results[] = ['kind' => 'te_top_class', 'label' => $name];
                $evidence[] = 'Top-level TE class: ' . $name;
            }
        }
        foreach ($teEntities as $entity) {
            $path = $this->tePath((string)$entity['label']);
            if ($path !== []) {
                $results[] = ['kind' => 'te_path', 'label' => (string)$entity['label'], 'path' => $path];
                $evidence[] = (string)$entity['label'] . ' classification path: ' . implode(' -> ', $path);
            }
        }
        foreach ($diseaseEntities as $entity) {
            $topClass = $this->diseaseTopClass((string)$entity['label']);
            if ($topClass !== null) {
                $results[] = ['kind' => 'disease_top_class', 'label' => (string)$entity['label'], 'top_class' => $topClass];
                $evidence[] = (string)$entity['label'] . ' top disease class: ' . $topClass;
            }
        }

        return [
            'plugin_name' => $this->getName(),
            'status' => $results === [] ? 'empty' : 'ok',
            'query_summary' => 'Resolved TE and disease classification tree context.',
            'results' => $results,
            'evidence_items' => $evidence,
            'citations' => [],
            'errors' => [],
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }

    private function topTeClasses(): array
    {
        $tree = $this->loadTree();
        $top = [];
        foreach ($tree['nodes'] as $node) {
            if ((int)($node['depth'] ?? -1) === 1) {
                $top[] = (string)$node['name'];
            }
        }
        return array_values(array_unique($top));
    }

    private function tePath(string $name): array
    {
        $tree = $this->loadTree();
        $parentMap = [];
        foreach ($tree['edges'] as $edge) {
            $parentMap[(string)$edge['child']] = (string)$edge['parent'];
        }
        $current = trim($name);
        if ($current === '') {
            return [];
        }
        $path = [$current];
        $guard = 0;
        while (isset($parentMap[$current]) && $guard < 20) {
            $current = $parentMap[$current];
            $path[] = $current;
            $guard++;
        }
        return array_reverse($path);
    }

    private function diseaseTopClass(string $name): ?string
    {
        $map = $this->loadDiseaseTopMap();
        return $map[$name] ?? null;
    }

    private function loadTree(): array
    {
        if (is_array($this->tree)) {
            return $this->tree;
        }
        $path = TEKG_DATA_FS_DIR . '/processed/tree_te_lineage.json';
        $decoded = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
        $this->tree = is_array($decoded) ? $decoded : ['nodes' => [], 'edges' => []];
        return $this->tree;
    }

    private function loadDiseaseTopMap(): array
    {
        if (is_array($this->diseaseTopMap)) {
            return $this->diseaseTopMap;
        }
        $path = TEKG_DATA_FS_DIR . '/processed/disease_top_class_map.json';
        $decoded = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
        $this->diseaseTopMap = is_array($decoded) ? $decoded : [];
        return $this->diseaseTopMap;
    }
}
