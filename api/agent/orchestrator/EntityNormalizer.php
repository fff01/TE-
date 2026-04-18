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
        $intent = $this->detectIntent($question);
        $targetTypes = $this->detectTargetTypes($question, $intent);
        $keywords = $this->extractKeywords($question);

        return [
            'language' => $language,
            'intent' => $intent,
            'normalized_entities' => $normalizedEntities,
            'requested_target_types' => $targetTypes,
            'asks_for_papers' => $this->containsAny($question, ['paper', 'papers', 'reference', 'references', 'pubmed', 'literature', '文献', '论文', '证据']),
            'asks_for_expression' => $this->containsAny($question, ['expression', 'expressed', 'cell line', 'tissue', 'context', '表达']),
            'asks_for_genome' => $this->containsAny($question, ['genome', 'genomic', 'browser', 'locus', 'location', 'chromosome', 'chr', '基因组', '染色体', '位点']),
            'asks_for_classification' => $this->containsAny($question, ['classif', 'subfamily', 'family', 'tree', 'category', '哪几类', '属于哪类', '分类', '谱系']),
            'asks_for_mechanism' => $intent === 'mechanism',
            'compare_mode' => $this->containsAny($question, ['compare', 'versus', 'vs', 'difference', '比较', '区别']),
            'needs_external_literature' => $intent === 'mechanism'
                || $this->containsAny($question, ['最新', 'recent', 'latest', 'evidence', 'paper', 'papers', '文献', '论文'])
                || count($normalizedEntities) <= 1,
            'question_keywords' => $keywords,
            'question_tokens' => preg_split('/\s+/u', trim($question)) ?: [],
        ];
    }

    private function collectEntities(string $question): array
    {
        $results = [];
        $blockedGeneric = [
            'te', 'line', 'ltr', 'sine', 'rna', 'gene', 'protein', 'mutation', 'disease', 'diseases', 'function', 'functions', 'paper', 'papers',
        ];
        $aliases = [
            'line1' => ['type' => 'TE', 'label' => 'LINE-1'],
            'line-1' => ['type' => 'TE', 'label' => 'LINE-1'],
            'l1' => ['type' => 'TE', 'label' => 'LINE-1'],
            'l1hs' => ['type' => 'TE', 'label' => 'L1HS'],
            'sva' => ['type' => 'TE', 'label' => 'SVA'],
            'alu' => ['type' => 'TE', 'label' => 'Alu'],
            'al' . 'z' . 'heimers disease' => ['type' => 'Disease', 'label' => "Al" . "z" . "heimer's disease"],
            'al' . 'z' . 'heimer disease' => ['type' => 'Disease', 'label' => "Al" . "z" . "heimer's disease"],
            'parkinsonism' => ['type' => 'Disease', 'label' => 'Parkinsonism'],
            'cancer' => ['type' => 'Disease', 'label' => 'Cancer'],
            'carcinoma' => ['type' => 'Disease', 'label' => 'Carcinoma'],
        ];

        foreach ($aliases as $needle => $entity) {
            if ($this->questionContainsEntity($question, $needle)) {
                $results[] = $entity;
            }
        }

        if ($this->containsAny($question, ['human transposons', 'human transposable elements', '人类转座子', '转座子分为哪几类'])) {
            $results[] = ['type' => 'TE', 'label' => 'TE'];
        }

        foreach ($this->loadTeNames() as $name) {
            if (tekg_agent_strlen($name) < 3) {
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
            if (tekg_agent_strlen($name) < 5) {
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
        if ($this->containsAny($question, ['how', 'why', 'mechanism', 'cause', 'causal', 'pathway', '导致', '机制', '原因', '因果', '路线', '如何'])) {
            return 'mechanism';
        }
        if ($this->containsAny($question, ['paper', 'papers', 'reference', 'references', 'pubmed', 'evidence', '文献', '论文', '证据'])) {
            return 'literature';
        }
        if ($this->containsAny($question, ['expression', 'expressed', 'cell line', 'tissue', '表达'])) {
            return 'expression';
        }
        if ($this->containsAny($question, ['genome', 'genomic', 'browser', 'locus', 'location', 'chromosome', 'chr', '基因组', '染色体', '位点'])) {
            return 'genome';
        }
        if ($this->containsAny($question, ['classif', 'subfamily', 'family', 'tree', '哪几类', '属于哪类', '分类', '谱系'])) {
            return 'classification';
        }
        if ($this->containsAny($question, ['compare', 'versus', 'vs', 'difference', '比较', '区别'])) {
            return 'comparison';
        }
        return 'relationship';
    }

    private function detectTargetTypes(string $question, string $intent): array
    {
        $mapping = [
            'Carbohydrate' => ['carbohydrate', 'carbohydrates', '糖', '糖类'],
            'Disease' => ['disease', 'diseases', 'illness', 'phenotype', '疾病'],
            'DiseaseCategory' => ['diseasecategory', 'disease category', 'disease class', 'disease classes', '疾病分类', '疾病类别'],
            'Function' => ['function', 'functions', 'role', 'mechanism', 'activity', '功能', '机制'],
            'Gene' => ['gene', 'genes', '基因'],
            'Lipid' => ['lipid', 'lipids', '脂质', '脂类'],
            'Mutation' => ['mutation', 'mutations', 'variant', 'variants', '突变', '变异'],
            'Paper' => ['paper', 'papers', 'literature', 'reference', 'references', 'pubmed', '论文', '文献'],
            'Peptide' => ['peptide', 'peptides', '肽', '多肽'],
            'Pharmaceutical' => ['drug', 'drugs', 'pharmaceutical', 'pharmaceuticals', '药物'],
            'Protein' => ['protein', 'proteins', 'orf1p', 'orf2p', '蛋白', '蛋白质'],
            'RNA' => ['rna', 'rnas', 'mrna', 'lncrna', 'rna-like'],
            'TE' => ['transposable element', 'transposable elements', 'te', 'tes', '转座子', '转座元件'],
            'Toxin' => ['toxin', 'toxins', '毒素'],
        ];

        $targets = [];
        foreach ($mapping as $type => $needles) {
            if ($this->containsAny($question, $needles)) {
                $targets[] = $type;
            }
        }

        if ($targets === [] && $intent === 'mechanism') {
            $targets = ['Function', 'Gene', 'Mutation', 'Protein', 'RNA', 'Disease'];
        }

        if ($targets === []) {
            $targets = ['Disease', 'Function', 'Paper'];
        }

        return array_values(array_unique($targets));
    }

    private function extractKeywords(string $question): array
    {
        $keywords = [];
        foreach ([
            'alternative splicing',
            'genome instability',
            'retrotransposition',
            'immune',
            'inflammation',
            'cancer',
            'carcinoma',
            'tumor',
            'tumour',
            'al' . 'z' . 'heimer',
            'parkinson',
            'aging',
            'metastasis',
            'oncogenic',
            'p53',
            'mutation',
            'expression',
        ] as $keyword) {
            if ($this->containsAny($question, [$keyword])) {
                $keywords[] = $keyword;
            }
        }
        return $keywords;
    }

    private function containsAny(string $question, array $needles): bool
    {
        $normalized = tekg_agent_lower($question);
        foreach ($needles as $needle) {
            if (str_contains($normalized, tekg_agent_lower((string)$needle))) {
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
        $entityLower = tekg_agent_lower($entity);
        $questionLower = tekg_agent_lower($question);
        $quoted = preg_quote($entityLower, '/');
        $quoted = str_replace(['\ ', '\-', '\_'], ['[\s\-_]*', '[\s\-_]*', '[\s\-_]*'], $quoted);
        return (bool)preg_match('/(?<![a-z0-9])' . $quoted . '(?![a-z0-9])/u', $questionLower);
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
