<?php
declare(strict_types=1);

final class TekgAgentEntityNormalizer
{
    private ?array $teNames = null;
    private ?array $treeNames = null;
    private ?array $diseaseNames = null;

    public function analyze(string $question, string $language = ''): array
    {
        $language = tekg_agent_detect_language($question, $language);
        $normalizedEntities = $this->collectEntities($question);
        return [
            'language' => $language,
            'intent' => $this->detectIntent($question),
            'normalized_entities' => $normalizedEntities,
            'requested_target_types' => $this->detectTargetTypes($question),
            'asks_for_papers' => $this->containsAny($question, ['paper', 'papers', 'reference', 'references', 'pubmed', '文献', '论文', '证据']),
            'asks_for_expression' => $this->containsAny($question, ['expression', 'expressed', 'context', 'cell line', 'tissue', '表达']),
            'asks_for_genome' => $this->containsAny($question, ['genome', 'genomic', 'locus', 'location', 'browser', 'chromosome', 'chr', '基因组', '染色体']),
            'asks_for_classification' => $this->containsAny($question, ['classif', 'subfamily', 'family', 'tree', 'category', '哪几类', '属于哪类', '分类']),
            'compare_mode' => $this->containsAny($question, ['compare', 'versus', 'vs', 'difference', '区别', '比较']),
            'question_keywords' => $this->extractKeywords($question),
            'question_tokens' => preg_split('/\s+/u', trim($question)) ?: [],
        ];
    }

    private function collectEntities(string $question): array
    {
        $results = [];
        $blockedGeneric = [
            'te', 'line', 'ltr', 'sine', 'rna', 'disease', 'diseases', 'function', 'functions', 'paper', 'papers',
        ];
        $aliases = [
            'line1' => ['type' => 'TE', 'label' => 'LINE-1'],
            'line-1' => ['type' => 'TE', 'label' => 'LINE-1'],
            'l1' => ['type' => 'TE', 'label' => 'LINE-1'],
            'l1hs' => ['type' => 'TE', 'label' => 'L1HS'],
            'sva' => ['type' => 'TE', 'label' => 'SVA'],
            'alu' => ['type' => 'TE', 'label' => 'Alu'],
            'alzheimersdisease' => ['type' => 'Disease', 'label' => "Alzheimer's disease"],
            'alzheimersdisease' => ['type' => 'Disease', 'label' => "Alzheimer's disease"],
            'parkinsonism' => ['type' => 'Disease', 'label' => 'Parkinsonism'],
        ];
        foreach ($aliases as $needle => $entity) {
            if ($this->questionContainsEntity($question, $needle)) {
                $results[] = $entity;
            }
        }
        if ($this->containsAny($question, ['human transposons', 'human transposable elements', '人类转座子'])) {
            $results[] = ['type' => 'TE', 'label' => 'TE'];
        }
        foreach ($this->loadTeNames() as $name) {
            if (mb_strlen($name, 'UTF-8') < 3) {
                continue;
            }
            if (in_array(tekg_agent_normalize_lookup_token($name), $blockedGeneric, true)) {
                continue;
            }
            if ($this->questionContainsEntity($question, $name)) {
                $results[] = ['type' => 'TE', 'label' => $name];
            }
        }
        foreach ($this->loadDiseaseNames() as $name) {
            if (mb_strlen($name, 'UTF-8') < 6) {
                continue;
            }
            if (in_array(tekg_agent_normalize_lookup_token($name), $blockedGeneric, true)) {
                continue;
            }
            if ($this->questionContainsEntity($question, $name)) {
                $results[] = ['type' => 'Disease', 'label' => $name];
            }
        }
        $seen = [];
        $unique = [];
        foreach ($results as $entity) {
            $key = ($entity['type'] ?? '') . '::' . tekg_agent_normalize_lookup_token((string)($entity['label'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $entity;
        }
        return $unique;
    }

    private function detectIntent(string $question): string
    {
        if ($this->containsAny($question, ['paper', 'papers', 'reference', 'references', 'pubmed', 'evidence', '文献'])) {
            return 'literature';
        }
        if ($this->containsAny($question, ['expression', 'expressed', 'cell line', 'tissue', '表达'])) {
            return 'expression';
        }
        if ($this->containsAny($question, ['genome', 'genomic', 'browser', 'locus', 'location', 'chromosome', 'chr', '基因组'])) {
            return 'genome';
        }
        if ($this->containsAny($question, ['classif', 'subfamily', 'family', 'tree', '哪几类', '属于哪类', '分类'])) {
            return 'classification';
        }
        if ($this->containsAny($question, ['compare', 'versus', 'vs', 'difference', '区别', '比较'])) {
            return 'comparison';
        }
        return 'relationship';
    }

    private function detectTargetTypes(string $question): array
    {
        $mapping = [
            'Disease' => ['disease', 'diseases', 'illness', 'phenotype', '疾病'],
            'Function' => ['function', 'functions', 'role', 'mechanism', 'activity', '功能'],
            'Paper' => ['paper', 'papers', 'literature', 'reference', 'references', 'pubmed', '文献'],
            'Protein' => ['protein', 'proteins', 'orf2p', 'orf1p', '蛋白'],
            'Gene' => ['gene', 'genes', '基因'],
            'RNA' => ['rna', 'rnas'],
            'Mutation' => ['mutation', 'mutations', 'variant', 'variants', '突变'],
        ];
        $targets = [];
        foreach ($mapping as $type => $needles) {
            if ($this->containsAny($question, $needles)) {
                $targets[] = $type;
            }
        }
        return $targets;
    }

    private function extractKeywords(string $question): array
    {
        $keywords = [];
        foreach (['alternative splicing', 'genome instability', 'cancer', 'alzheimer', 'parkinson', 'retrotransposition'] as $keyword) {
            if ($this->containsAny($question, [$keyword])) {
                $keywords[] = $keyword;
            }
        }
        return $keywords;
    }

    private function containsAny(string $question, array $needles): bool
    {
        $normalized = mb_strtolower($question, 'UTF-8');
        foreach ($needles as $needle) {
            if (str_contains($normalized, mb_strtolower($needle, 'UTF-8'))) {
                return true;
            }
        }
        return false;
    }

    private function questionContainsEntity(string $question, string $entity): bool
    {
        $entity = trim($entity);
        if ($entity === '') {
            return false;
        }
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $entity)) {
            return str_contains($question, $entity);
        }
        $entityLower = mb_strtolower($entity, 'UTF-8');
        $questionLower = mb_strtolower($question, 'UTF-8');
        $quoted = preg_quote($entityLower, '/');
        $quoted = str_replace(['\ ', '\-', '\_'], ['[\s\-_]*', '[\s\-_]*', '[\s\-_]*'], $quoted);
        if (preg_match('/(?<![a-z0-9])' . $quoted . '(?![a-z0-9])/u', $questionLower)) {
            return true;
        }
        return false;
    }

    private function loadTeNames(): array
    {
        if (is_array($this->teNames)) {
            return $this->teNames;
        }
        $names = [];
        $dbNamesPath = TEKG_DATA_FS_DIR . '/processed/_db_te_names_current.json';
        if (is_file($dbNamesPath)) {
            $decoded = json_decode((string)file_get_contents($dbNamesPath), true);
            if (is_array($decoded)) {
                foreach ($decoded as $name) {
                    if (is_string($name) && trim($name) !== '') {
                        $names[] = trim($name);
                    }
                }
            }
        }
        foreach ($this->loadTreeNames() as $name) {
            $names[] = $name;
        }
        $this->teNames = array_values(array_unique($names));
        return $this->teNames;
    }

    private function loadTreeNames(): array
    {
        if (is_array($this->treeNames)) {
            return $this->treeNames;
        }
        $path = TEKG_DATA_FS_DIR . '/processed/tree_te_lineage.json';
        $names = [];
        if (is_file($path)) {
            $decoded = json_decode((string)file_get_contents($path), true);
            foreach (($decoded['nodes'] ?? []) as $node) {
                $name = trim((string)($node['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }
        $this->treeNames = array_values(array_unique($names));
        return $this->treeNames;
    }

    private function loadDiseaseNames(): array
    {
        if (is_array($this->diseaseNames)) {
            return $this->diseaseNames;
        }
        $path = TEKG_DATA_FS_DIR . '/processed/disease_top_class_map.json';
        $names = [];
        if (is_file($path)) {
            $decoded = json_decode((string)file_get_contents($path), true);
            if (is_array($decoded)) {
                foreach (array_keys($decoded) as $name) {
                    if (is_string($name) && trim($name) !== '') {
                        $names[] = trim($name);
                    }
                }
            }
        }
        $this->diseaseNames = array_values(array_unique($names));
        return $this->diseaseNames;
    }
}
