<?php
declare(strict_types=1);

final class TekgAgentEntityNormalizer
{
    private ?array $teNames = null;
    private ?array $treeNames = null;
    private ?array $diseaseNames = null;
    private ?array $teAliasIndex = null;
    private ?array $diseaseAliasIndex = null;

    public function analyze(string $question, string $language = ''): array
    {
        $language = tekg_agent_detect_language($question, $language);
        $normalizedEntities = $this->collectEntities($question);
        $intent = $this->detectIntent($question);
        $targetTypes = $this->detectTargetTypes($question, $intent);
        $keywords = $this->extractKeywords($question);
        $aliasChains = $this->buildAliasChains($normalizedEntities);

        return [
            'language' => $language,
            'intent' => $intent,
            'normalized_entities' => $normalizedEntities,
            'alias_chains' => $aliasChains,
            'requested_target_types' => $targetTypes,
            'asks_for_papers' => $this->containsAny($question, ['paper', 'papers', 'reference', 'references', 'pubmed', 'literature', 'citation', 'citations']),
            'asks_for_expression' => $this->containsAny($question, ['expression', 'expressed', 'cell line', 'cell lines', 'tissue', 'context', 'transcriptome']),
            'asks_for_genome' => $this->containsAny($question, ['genome', 'genomic', 'browser', 'locus', 'location', 'chromosome', 'chr', 'coordinate']),
            'asks_for_classification' => $this->containsAny($question, ['classif', 'subfamily', 'family', 'tree', 'category', 'lineage', 'taxonomy']),
            'asks_for_sequence' => $this->containsAny($question, ['sequence', 'consensus', 'repbase', 'length', 'orf', 'orfs', 'utr', 'promoter', 'motif', 'structure', 'annotation']),
            'asks_for_mechanism' => $intent === 'mechanism',
            'compare_mode' => $this->containsAny($question, ['compare', 'versus', 'vs', 'difference']),
            'needs_external_literature' => $intent === 'mechanism'
                || $this->containsAny($question, ['recent', 'latest', 'evidence', 'paper', 'papers', 'pubmed', 'literature'])
                || count($normalizedEntities) <= 1,
            'question_keywords' => $keywords,
            'question_tokens' => preg_split('/\s+/u', trim($question)) ?: [],
        ];
    }

    private function collectEntities(string $question): array
    {
        $results = [];
        $results = array_merge($results, $this->matchFromAliasIndex($question, $this->loadTeAliasIndex(), 'TE'));
        $results = array_merge($results, $this->matchFromAliasIndex($question, $this->loadDiseaseAliasIndex(), 'Disease'));

        if ($this->containsAny($question, ['human transposons', 'human transposable elements', 'transposable elements'])) {
            $results[] = $this->makeEntity('TE', 'TE', 'Transposable elements', ['TE', 'Transposable elements', 'Human transposons'], 'Transposable elements');
        }

        $seen = [];
        $unique = [];
        foreach ($results as $entity) {
            $key = ($entity['type'] ?? '') . '::' . tekg_agent_normalize_lookup_token((string)($entity['canonical_label'] ?? $entity['label'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $entity;
        }

        usort($unique, static function (array $left, array $right): int {
            $leftLabel = (string)($left['canonical_label'] ?? $left['label'] ?? '');
            $rightLabel = (string)($right['canonical_label'] ?? $right['label'] ?? '');
            return strcasecmp($leftLabel, $rightLabel);
        });

        return $unique;
    }

    private function buildAliasChains(array $entities): array
    {
        $chains = [];
        foreach ($entities as $entity) {
            if (!is_array($entity)) {
                continue;
            }
            $chains[] = [
                'type' => (string)($entity['type'] ?? ''),
                'canonical_label' => (string)($entity['canonical_label'] ?? $entity['label'] ?? ''),
                'display_label' => (string)($entity['display_label'] ?? $entity['label'] ?? ''),
                'matched_alias' => (string)($entity['matched_alias'] ?? ''),
                'aliases' => array_values(array_unique(array_filter(array_map(
                    static fn($value): string => trim((string)$value),
                    is_array($entity['aliases'] ?? null) ? $entity['aliases'] : []
                )))),
            ];
        }
        return $chains;
    }

    private function matchFromAliasIndex(string $question, array $index, string $type): array
    {
        $results = [];
        $blockedGeneric = [
            'te', 'tes', 'line', 'lines', 'ltr', 'sine', 'dna', 'rna', 'protein', 'proteins', 'gene', 'genes',
            'disease', 'diseases', 'function', 'functions', 'paper', 'papers', 'mutation', 'mutations',
        ];
        foreach ($index as $canonical => $aliases) {
            if (in_array(tekg_agent_normalize_lookup_token($canonical), $blockedGeneric, true)) {
                continue;
            }
            foreach ($aliases as $alias) {
                if (in_array(tekg_agent_normalize_lookup_token((string)$alias), $blockedGeneric, true)) {
                    continue;
                }
                if (!$this->questionContainsEntity($question, $alias)) {
                    continue;
                }
                $results[] = $this->makeEntity($type, $canonical, $canonical, $aliases, $alias);
                break;
            }
        }
        return $results;
    }

    private function makeEntity(string $type, string $canonical, string $displayLabel, array $aliases, string $matchedAlias): array
    {
        return [
            'type' => $type,
            'label' => $canonical,
            'canonical_label' => $canonical,
            'display_label' => $displayLabel,
            'aliases' => array_values(array_unique(array_filter(array_map(
                static fn($value): string => trim((string)$value),
                $aliases
            )))),
            'matched_alias' => $matchedAlias,
        ];
    }

    private function detectIntent(string $question): string
    {
        if ($this->containsAny($question, ['how', 'why', 'mechanism', 'cause', 'causal', 'pathway', 'drive', 'lead to'])) {
            return 'mechanism';
        }
        if ($this->containsAny($question, ['paper', 'papers', 'reference', 'references', 'pubmed', 'evidence', 'literature', 'citation'])) {
            return 'literature';
        }
        if ($this->containsAny($question, ['expression', 'expressed', 'cell line', 'tissue', 'transcriptome'])) {
            return 'expression';
        }
        if ($this->containsAny($question, ['genome', 'genomic', 'browser', 'locus', 'location', 'chromosome', 'chr', 'coordinate'])) {
            return 'genome';
        }
        if ($this->containsAny($question, ['sequence', 'consensus', 'repbase', 'length', 'orf', 'utr', 'motif', 'structure', 'annotation'])) {
            return 'sequence';
        }
        if ($this->containsAny($question, ['classif', 'subfamily', 'family', 'tree', 'category', 'taxonomy', 'lineage'])) {
            return 'classification';
        }
        if ($this->containsAny($question, ['compare', 'versus', 'vs', 'difference'])) {
            return 'comparison';
        }
        return 'relationship';
    }

    private function detectTargetTypes(string $question, string $intent): array
    {
        $mapping = [
            'Carbohydrate' => ['carbohydrate', 'carbohydrates'],
            'Disease' => ['disease', 'diseases', 'illness', 'phenotype', 'cancer', 'carcinoma'],
            'DiseaseCategory' => ['diseasecategory', 'disease category', 'disease class', 'disease classes'],
            'Function' => ['function', 'functions', 'role', 'mechanism', 'activity', 'retrotransposition'],
            'Gene' => ['gene', 'genes'],
            'Lipid' => ['lipid', 'lipids'],
            'Mutation' => ['mutation', 'mutations', 'variant', 'variants', 'insertion'],
            'Paper' => ['paper', 'papers', 'literature', 'reference', 'references', 'pubmed', 'citation'],
            'Peptide' => ['peptide', 'peptides'],
            'Pharmaceutical' => ['drug', 'drugs', 'pharmaceutical', 'pharmaceuticals'],
            'Protein' => ['protein', 'proteins', 'orf1p', 'orf2p', 'reverse transcriptase'],
            'RNA' => ['rna', 'rnas', 'mrna', 'lncrna'],
            'TE' => ['transposable element', 'transposable elements', 'te', 'tes', 'retrotransposon'],
            'Toxin' => ['toxin', 'toxins'],
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
        if ($targets === [] && $intent === 'sequence') {
            $targets = ['TE'];
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
            'alzheimer',
            'parkinson',
            'aging',
            'metastasis',
            'oncogenic',
            'p53',
            'mutation',
            'expression',
            'sequence',
            'consensus',
            'orf1p',
            'orf2p',
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

    private function loadTeAliasIndex(): array
    {
        if (is_array($this->teAliasIndex)) {
            return $this->teAliasIndex;
        }

        $index = [];
        foreach ($this->loadTeNames() as $name) {
            $index[$name] = $this->generateAliases($name);
        }

        foreach (tekg_agent_entity_alias_map() as $rule) {
            if (!is_array($rule) || (string)($rule['entity_type'] ?? '') !== 'TE') {
                continue;
            }
            $canonical = trim((string)($rule['canonical_label'] ?? ''));
            if ($canonical === '') {
                continue;
            }
            $aliases = array_merge(
                is_array($rule['aliases'] ?? null) ? $rule['aliases'] : [],
                is_array($rule['broad_aliases'] ?? null) ? $rule['broad_aliases'] : []
            );
            $index[$canonical] = array_values(array_unique(array_merge($index[$canonical] ?? [$canonical], array_map('strval', $aliases))));
        }

        $this->teAliasIndex = $index;
        return $this->teAliasIndex;
    }

    private function loadDiseaseAliasIndex(): array
    {
        if (is_array($this->diseaseAliasIndex)) {
            return $this->diseaseAliasIndex;
        }

        $index = [];
        foreach ($this->loadDiseaseNames() as $name) {
            $index[$name] = $this->generateAliases($name);
        }

        foreach (tekg_agent_entity_alias_map() as $rule) {
            if (!is_array($rule) || (string)($rule['entity_type'] ?? '') !== 'Disease') {
                continue;
            }
            $canonical = trim((string)($rule['canonical_label'] ?? ''));
            if ($canonical === '') {
                continue;
            }
            $aliases = array_merge(
                is_array($rule['aliases'] ?? null) ? $rule['aliases'] : [],
                is_array($rule['broad_aliases'] ?? null) ? $rule['broad_aliases'] : []
            );
            $index[$canonical] = array_values(array_unique(array_merge($index[$canonical] ?? [$canonical], array_map('strval', $aliases))));
        }

        $this->diseaseAliasIndex = $index;
        return $this->diseaseAliasIndex;
    }

    private function generateAliases(string $label): array
    {
        $label = trim($label);
        if ($label === '') {
            return [];
        }
        $aliases = [$label];
        if (str_contains($label, '_')) {
            $aliases[] = str_replace('_', '-', $label);
            $aliases[] = str_replace('_', ' ', $label);
        }
        if (str_contains($label, '-')) {
            $aliases[] = str_replace('-', '', $label);
            $aliases[] = str_replace('-', ' ', $label);
        }
        if (str_contains($label, ' ')) {
            $aliases[] = str_replace(' ', '', $label);
            $aliases[] = str_replace(' ', '-', $label);
        }
        return array_values(array_unique(array_filter($aliases)));
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
        $paths = [
            TEKG_DATA_FS_DIR . '/processed/tree_te_lineage.json',
            TEKG_DATA_FS_DIR . '/processed/tekg2_0413_tree_rmsk_repbase_lineage.json',
            TEKG_DATA_FS_DIR . '/processed/tekg2_0413_tree_all_lineage.json',
        ];
        $names = [];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            $decoded = json_decode((string)file_get_contents($path), true);
            foreach ((array)($decoded['nodes'] ?? []) as $node) {
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
