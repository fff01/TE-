<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'PHP cURL extension is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$question = trim((string)($payload['question'] ?? ''));
$language = trim((string)($payload['language'] ?? ''));
$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

if ($question === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Question is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = [
    'dashscope_key' => $localConfig['dashscope_key'] ?? env_value(['DASHSCOPE_API_KEY_BIOLOGY', 'DASHSCOPE_API_KEY']),
    'dashscope_model' => $localConfig['dashscope_model'] ?? env_value(['DASHSCOPE_MODEL_BIOLOGY', 'DASHSCOPE_MODEL'], 'qwen-plus'),
    'dashscope_url' => $localConfig['dashscope_url'] ?? env_value(['DASHSCOPE_API_URL_BIOLOGY', 'DASHSCOPE_API_URL'], 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions'),
    'ssl_verify' => isset($localConfig['ssl_verify'])
        ? (bool)$localConfig['ssl_verify']
        : filter_var(env_value(['DASHSCOPE_SSL_VERIFY_BIOLOGY', 'DASHSCOPE_SSL_VERIFY'], '0'), FILTER_VALIDATE_BOOLEAN),
    'llm_relay_url' => $localConfig['llm_relay_url'] ?? env_value(['BIOLOGY_LLM_RELAY_URL', 'LLM_RELAY_URL'], ''),
    'neo4j_url' => $localConfig['neo4j_url'] ?? env_value(['NEO4J_HTTP_URL_BIOLOGY', 'NEO4J_HTTP_URL'], 'http://127.0.0.1:7474/db/tekg/tx/commit'),
    'neo4j_user' => $localConfig['neo4j_user'] ?? env_value(['NEO4J_USER_BIOLOGY', 'NEO4J_USER'], 'neo4j'),
    'neo4j_password' => $localConfig['neo4j_password'] ?? env_value(['NEO4J_PASSWORD_BIOLOGY', 'NEO4J_PASSWORD'], ''),
];

if ($config['neo4j_password'] === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'NEO4J_PASSWORD_BIOLOGY or NEO4J_PASSWORD is not set'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new QaService($config);
    $result = $service->answer($question, $language);
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function env_value(array $names, ?string $default = null): ?string
{
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }
    }
    return $default;
}

final class QaService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function answer(string $question, string $language = ''): array
    {
        $language = $language !== '' ? $language : $this->detectLanguage($question);
        $smallTalkAnswer = $this->getSmallTalkAnswer($question, $language);
        if ($smallTalkAnswer !== null) {
            return [
                'language' => $language,
                'entity' => null,
                'intent' => 'smalltalk',
                'mode' => 'smalltalk',
                'cypher' => '',
                'records' => [],
                'references' => [],
                'answer' => $smallTalkAnswer,
            ];
        }
        if ($this->isSmallTalk($question)) {
            return [
                'language' => $language,
                'entity' => null,
                'intent' => 'smalltalk',
                'mode' => 'smalltalk',
                'cypher' => '',
                'records' => [],
                'references' => [],
                'answer' => $language === 'zh'
                    ? "你好。我现在可以回答基于本地 TE 图数据库的问题。你可以直接问：\n1. LINE-1 相关疾病\n2. LINE-1 相关功能\n3. L1HS 有哪些文献证据"
                    : "Hello. I can answer questions grounded in the local TE knowledge graph. You can ask:\n1. LINE-1 related diseases\n2. LINE-1 related functions\n3. What literature supports L1HS?",
            ];
        }
        $entity = $this->normalizeEntity($question);
        $disease = $this->normalizeDisease($question);
        $intent = $this->detectIntent($question);
        $mode = 'template_rag';
        $cypher = '';
        $rows = [];

        if ($entity !== null && $disease !== null && $this->containsAny(mb_strtolower($question), ['文献', '论文', '证据', 'paper', 'evidence', 'reference'])) {
            $intent = 'te_disease_evidence';
            [$cypher, $params] = $this->buildPairQuery($intent, $entity, $disease);
            $rows = $this->runNeo4j($cypher, $params);
        } elseif ($entity !== null && $disease !== null) {
            $intent = 'te_disease_relation';
            [$cypher, $params] = $this->buildPairQuery($intent, $entity, $disease);
            $rows = $this->runNeo4j($cypher, $params);
        } elseif ($entity !== null && $intent !== null) {
            [$cypher, $params] = $this->buildTemplateQuery($intent, $entity);
            $rows = $this->runNeo4j($cypher, $params);
        }

        if (empty($rows) && $this->config['dashscope_key']) {
            $plan = $this->planQuestion($question, $language);
            $entity = $entity ?? ($plan['entity'] ?? null);
            $intent = $intent ?? ($plan['intent'] ?? null);
            if ($entity !== null && $intent !== null && $this->isSupportedIntent($intent)) {
                [$cypher, $params] = $this->buildTemplateQuery($intent, $entity);
                $rows = $this->runNeo4j($cypher, $params);
                $mode = 'planned_template_rag';
            }
        }

        if (empty($rows) && $this->config['dashscope_key']) {
            $candidateCypher = $this->generateCypher($question, $language);
            $validatedCypher = $this->validateReadonlyCypher($candidateCypher);
            $cypher = $validatedCypher;
            $rows = $this->runNeo4j($cypher, []);
            $mode = 'text2cypher_rag';
        }

        if (empty($rows) && $entity !== null) {
            $cypher = "MATCH (n {name: \$entity})-[r]-(m) RETURN type(r) AS relation_type, coalesce(r.predicate, '') AS predicate, labels(m) AS target_labels, m.name AS target LIMIT 15";
            $rows = $this->runNeo4j($cypher, ['entity' => $entity]);
            $mode = 'neighbor_fallback';
        }

        $rows = $this->prepareRowsForAnswer($rows, $intent);
        $references = $this->extractReferences($rows);
        $references = $this->prepareReferencesForAnswer($references, $intent);
        if ($this->config['dashscope_key']) {
            try {
                $answer = $this->generateAnswer($question, $language, $rows, $references, $intent, $entity);
            } catch (Throwable $e) {
                $mode .= '+fallback';
                $answer = $this->fallbackAnswer($question, $language, $rows, $references, $intent, $entity);
            }
        } else {
            $answer = $this->fallbackAnswer($question, $language, $rows, $references, $intent, $entity);
        }
        $answer = $this->polishAnswer($answer, $language);

        return [
            'language' => $language,
            'entity' => $entity,
            'intent' => $intent,
            'mode' => $mode,
            'cypher' => $cypher,
            'records' => $rows,
            'references' => $references,
            'answer' => $answer,
        ];
    }

    private function detectLanguage(string $question): string
    {
        return preg_match('/[\x{4e00}-\x{9fff}]/u', $question) ? 'zh' : 'en';
    }

    private function normalizeEntity(string $question): ?string
    {
        $lower = mb_strtolower($question);
        $aliases = [
            'l1hs' => 'L1HS',
            'line-1' => 'LINE-1',
            'line1' => 'LINE-1',
            'l1 ' => 'LINE-1',
            ' l1' => 'LINE-1',
            'alu' => 'Alu',
            'sva' => 'SVA',
        ];
        foreach ($aliases as $alias => $entity) {
            if (str_contains($lower, $alias)) {
                return $entity;
            }
        }
        return null;
    }

    private function normalizeDisease(string $question): ?string
    {
        $lower = mb_strtolower($question);
        $aliases = [
            '阿尔茨海默' => "Alzheimer's disease",
            'alzheimer' => "Alzheimer's disease",
            '亨廷顿' => "Huntington's Disease",
            'huntington' => "Huntington's Disease",
            'rett' => 'Rett syndrome',
            '唐氏' => 'Down syndrome',
            'down syndrome' => 'Down syndrome',
            '自闭症' => 'autism spectrum disorder',
            'autism' => 'autism spectrum disorder',
            '乳腺癌' => 'breast cancer',
            'breast cancer' => 'breast cancer',
            '共济失调毛细血管扩张症' => 'ataxia telangiectasia',
            'ataxia telangiectasia' => 'ataxia telangiectasia',
            '口腔鳞状细胞癌' => 'Oral Squamous Cell Carcinoma',
            'oral squamous cell carcinoma' => 'Oral Squamous Cell Carcinoma',
        ];
        foreach ($aliases as $alias => $disease) {
            if (str_contains($lower, $alias)) {
                return $disease;
            }
        }
        return null;
    }

    private function detectIntent(string $question): ?string
    {
        $lower = mb_strtolower($question);
        if ($this->containsAny($lower, ['亚家族', '谱系', '关系', 'subfamily', 'lineage', 'relationship'])) {
            return 'subfamily';
        }
        if ($this->containsAny($lower, ['文献', '论文', '证据', 'paper', 'evidence', 'reference'])) {
            return 'entity_to_paper';
        }
        if ($this->containsAny($lower, ['功能', '机制', '作用', 'function', 'mechanism', 'role'])) {
            return 'te_to_function';
        }
        if ($this->containsAny($lower, ['疾病', '癌', '病', 'disease', 'cancer', 'disorder'])) {
            return 'te_to_disease';
        }
        return null;
    }

    private function containsAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function isSmallTalk(string $question): bool
    {
        $lower = mb_strtolower(trim($question));
        $smallTalk = [
            'hi', 'hello', 'hey', '你好', '您好', '在吗', '嗨',
            '你是谁', '你能做什么', 'help', '帮助'
        ];
        return in_array($lower, $smallTalk, true);
    }

    private function getSmallTalkAnswer(string $question, string $language): ?string
    {
        $normalized = preg_replace('/\s+/u', '', mb_strtolower(trim($question)));
        $zhSmallTalk = [
            '你好', '您好', '在吗', '嗨', '帮助',
            '你是谁', '你是什么', '你是什么模型', '你能做什么',
        ];
        $enSmallTalk = [
            'hi', 'hello', 'hey', 'help', 'whoareyou', 'whatmodelareyou', 'whatcanyoudo',
        ];

        if (in_array($normalized, $zhSmallTalk, true)) {
            return "你好。我现在可以回答基于本地 TE 图数据库的问题。你可以直接问：\n\n1. LINE-1 相关疾病\n2. LINE-1 相关功能\n3. L1HS 和 LINE-1 是什么关系\n4. 哪些文献支持 LINE-1 与阿尔茨海默病相关";
        }

        if (in_array($normalized, $enSmallTalk, true)) {
            return "Hello. I can answer questions grounded in the local TE knowledge graph. You can ask:\n\n1. LINE-1 related diseases\n2. LINE-1 related functions\n3. What is the relationship between L1HS and LINE-1?\n4. What papers support the association between LINE-1 and Alzheimer's disease?";
        }

        return null;
    }

    private function isSupportedIntent(string $intent): bool
    {
        return in_array($intent, ['subfamily', 'entity_to_paper', 'te_to_function', 'te_to_disease', 'te_disease_relation', 'te_disease_evidence'], true);
    }

    private function buildTemplateQuery(string $intent, string $entity): array
    {
        return match ($intent) {
            'te_to_function' => [
                "MATCH (t:TE {name: \$entity})-[r:BIO_RELATION]->(f:Function)
                 OPTIONAL MATCH (p:Paper)-[er:EVIDENCE_RELATION]->(f)
                 WITH f, r, collect(DISTINCT CASE WHEN p IS NULL THEN NULL ELSE {title:p.name, pmids:coalesce(er.pmids, [])} END)[0..5] AS refs
                 RETURN f.name AS target, r.predicate AS predicate, coalesce(r.pmids, []) AS pmids, [x IN refs WHERE x IS NOT NULL] AS evidence
                 ORDER BY target LIMIT 15",
                ['entity' => $entity]
            ],
            'te_to_disease' => [
                "MATCH (t:TE {name: \$entity})-[r:BIO_RELATION]->(d:Disease)
                 OPTIONAL MATCH (p:Paper)-[er:EVIDENCE_RELATION]->(d)
                 WITH d, r, collect(DISTINCT CASE WHEN p IS NULL THEN NULL ELSE {title:p.name, pmids:coalesce(er.pmids, [])} END)[0..5] AS refs
                 RETURN d.name AS target, r.predicate AS predicate, coalesce(r.pmids, []) AS pmids, [x IN refs WHERE x IS NOT NULL] AS evidence
                 ORDER BY target LIMIT 15",
                ['entity' => $entity]
            ],
            'entity_to_paper' => [
                "MATCH (p:Paper)-[r:EVIDENCE_RELATION]->(n {name: \$entity})
                 RETURN p.name AS title, r.predicate AS predicate, coalesce(r.pmids, []) AS pmids
                 ORDER BY title LIMIT 15",
                ['entity' => $entity]
            ],
            'subfamily' => $entity === 'LINE-1'
                ? [
                    "MATCH (child:TE)-[r:SUBFAMILY_OF]->(parent:TE {name: \$entity})
                     RETURN child.name AS subfamily, coalesce(r.copies, 0) AS copies
                     ORDER BY subfamily LIMIT 30",
                    ['entity' => $entity]
                ]
                : [
                    "MATCH (child:TE {name: \$entity})-[r:SUBFAMILY_OF]->(parent:TE)
                     RETURN parent.name AS parent, coalesce(r.copies, 0) AS copies LIMIT 10",
                    ['entity' => $entity]
                ],
            default => throw new RuntimeException('Unsupported intent')
        };
    }

    private function buildPairQuery(string $intent, string $entity, string $disease): array
    {
        return match ($intent) {
            'te_disease_relation' => [
                "MATCH (t:TE {name: \$entity})-[r:BIO_RELATION]->(d:Disease {name: \$disease})
                 OPTIONAL MATCH (p:Paper)-[er:EVIDENCE_RELATION]->(d)
                 WITH d, r, collect(DISTINCT CASE WHEN p IS NULL THEN NULL ELSE {title:p.name, pmids:coalesce(er.pmids, [])} END)[0..8] AS refs
                 RETURN d.name AS disease, r.predicate AS predicate, coalesce(r.pmids, []) AS pmids, [x IN refs WHERE x IS NOT NULL] AS evidence
                 LIMIT 10",
                ['entity' => $entity, 'disease' => $disease]
            ],
            'te_disease_evidence' => [
                "MATCH (t:TE {name: \$entity})-[r:BIO_RELATION]->(d:Disease {name: \$disease})
                 OPTIONAL MATCH (p:Paper)-[er:EVIDENCE_RELATION]->(d)
                 WITH d, r, [x IN collect(DISTINCT CASE WHEN p IS NULL THEN NULL ELSE {title:p.name, pmids:coalesce(er.pmids, [])} END) WHERE x IS NOT NULL][0..10] AS refs
                 RETURN d.name AS disease, r.predicate AS predicate, coalesce(r.pmids, []) AS pmids, refs AS evidence
                 LIMIT 10",
                ['entity' => $entity, 'disease' => $disease]
            ],
            default => throw new RuntimeException('Unsupported pair intent')
        };
    }

    private function runNeo4j(string $cypher, array $params): array
    {
        $payload = [
            'statements' => [[
                'statement' => $cypher,
                'parameters' => $params,
                'resultDataContents' => ['row'],
            ]],
        ];

        $response = $this->httpJson(
            $this->config['neo4j_url'],
            $payload,
            ['Content-Type: application/json'],
            $this->config['neo4j_user'],
            $this->config['neo4j_password']
        );

        if (!empty($response['errors'])) {
            $first = $response['errors'][0];
            throw new RuntimeException('Neo4j error: ' . ($first['message'] ?? 'Unknown error'));
        }

        $rows = [];
        foreach (($response['results'][0]['data'] ?? []) as $entry) {
            $rows[] = $entry['row'] ?? [];
        }
        return $rows;
    }

    private function planQuestion(string $question, string $language): array
    {
        $prompt = "你是 TE 图谱问答系统的规划器。只返回 JSON，不要附加说明。\n" .
            "支持的 intent 只有：te_to_disease, te_to_function, entity_to_paper, subfamily, unknown。\n" .
            "如果问题中出现 LINE-1/L1/LINE1 统一规范为 LINE-1；L1Hs 统一为 L1HS。\n" .
            "返回格式：{\"intent\":\"...\",\"entity\":\"...\",\"language\":\"zh|en\"}\n" .
            "问题：" . $question;

        $content = $this->dashscopeChat([
            ['role' => 'system', 'content' => 'You convert user questions into strict JSON plans.'],
            ['role' => 'user', 'content' => $prompt],
        ], 0.0);

        $decoded = json_decode($this->extractJson($content), true);
        if (!is_array($decoded)) {
            return ['intent' => 'unknown', 'entity' => null, 'language' => $language];
        }
        return $decoded;
    }

    private function generateCypher(string $question, string $language): string
    {
        $schema = "Nodes: (:TE), (:Disease), (:Function), (:Paper).\n" .
            "Relationships: (:TE)-[:BIO_RELATION {predicate, pmids}]->(:Disease|:Function);\n" .
            "(:Paper)-[:EVIDENCE_RELATION {predicate, pmids}]->(:TE|:Disease|:Function);\n" .
            "(:TE)-[:SUBFAMILY_OF {copies}]->(:TE).\n";

        $prompt = "You are generating read-only Cypher for a TE knowledge graph.\n" .
            "Rules:\n" .
            "1. Only output one Cypher query.\n" .
            "2. Read-only only. Never use CREATE, MERGE, SET, DELETE, REMOVE, CALL, LOAD.\n" .
            "3. Always include LIMIT 20 or less.\n" .
            "4. Prefer exact name matches for LINE-1 and L1HS.\n\n" .
            "Schema:\n{$schema}\nQuestion ({$language}): {$question}";

        return trim($this->dashscopeChat([
            ['role' => 'system', 'content' => 'You write safe Cypher queries only.'],
            ['role' => 'user', 'content' => $prompt],
        ], 0.0));
    }

    private function validateReadonlyCypher(string $cypher): string
    {
        $trimmed = trim(preg_replace('/```(?:cypher)?|```/i', '', $cypher));
        $upper = strtoupper($trimmed);
        foreach (['CREATE', 'MERGE', 'SET ', 'DELETE', 'REMOVE', 'LOAD ', 'DROP ', 'CALL ', 'APOC', 'FOREACH'] as $blocked) {
            if (str_contains($upper, $blocked)) {
                throw new RuntimeException('Unsafe Cypher generated and blocked');
            }
        }
        if (!preg_match('/^\s*(MATCH|OPTIONAL MATCH|WITH)\b/i', $trimmed)) {
            throw new RuntimeException('Generated Cypher is not a valid read-only query');
        }
        if (!preg_match('/\bLIMIT\s+\d+\b/i', $trimmed)) {
            $trimmed .= ' LIMIT 20';
        }
        return $trimmed;
    }

    private function generateAnswer(string $question, string $language, array $rows, array $references, ?string $intent, ?string $entity): string
    {
        $context = json_encode([
            'intent' => $intent,
            'entity' => $entity,
            'rows' => $rows,
            'references' => $references,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $langInstruction = $language === 'zh'
            ? '请用中文作答，要求准确、学术、简洁。必须优先依据给定上下文，不能编造。请先直接回答问题，再用1到3点总结关键机制或关系。若证据不足，请明确写“本地知识库暂无直接证据”。最后单列“参考文献”，列出标题和 PMID。'
            : 'Answer in English in a concise academic style. Use only the provided context and do not invent facts. Give a direct answer first, then summarize key mechanisms or relations in 1-3 points. If evidence is insufficient, explicitly state that the local knowledge graph lacks direct evidence. End with a "References" section with title and PMID.';

        $langInstruction = $language === 'zh'
            ? "请用中文作答，风格要求准确、学术、简洁，适合课程答辩展示。\n"
                . "必须严格基于给定上下文，不能编造，也不要补充上下文以外的事实。\n"
                . "输出结构固定为：\n"
                . "## 结论\n"
                . "## 关键点\n"
                . "## 参考文献\n"
                . "先用1段话直接回答问题；再列2到4条关键点；最后只列最关键的5到8条参考文献。\n"
                . "参考文献格式统一为“标题（PMID: xxxx）”。\n"
                . "如果上下文中有明显不像疾病、功能或文献标题的条目，不要主动写入答案。\n"
                . "如果上下文中存在中英文混杂实体，请优先用中文；无法可靠翻译时保留原文。\n"
                . "如果证据不足，请明确写“本地知识库暂无直接证据”。"
            : "Answer in concise academic English suitable for a project demo.\n"
                . "Use only the provided context and do not invent facts.\n"
                . "Use exactly this structure:\n"
                . "## Conclusion\n"
                . "## Key Points\n"
                . "## References\n"
                . "First answer directly in one short paragraph, then give 2 to 4 key points, then list only the most important 5 to 8 references as “Title (PMID: xxxx)”.\n"
                . "Ignore records that do not look like valid diseases, functions, or paper titles.\n"
                . "If evidence is insufficient, explicitly state that the local knowledge graph lacks direct evidence.";

        return $this->dashscopeChat([
            ['role' => 'system', 'content' => 'You are a bioinformatics knowledge-graph QA assistant.'],
            ['role' => 'user', 'content' => $langInstruction . "\n\nUser question:\n{$question}\n\nContext:\n{$context}"],
        ], 0.1);
    }

    private function fallbackAnswer(string $question, string $language, array $rows, array $references, ?string $intent, ?string $entity): string
    {
        if (empty($rows)) {
            return $language === 'zh'
                ? '本地知识库暂无直接证据，建议换一个更具体的实体或问题类型。'
                : 'The local knowledge graph currently lacks direct evidence. Try a more specific entity or question type.';
        }

        if ($language === 'zh' && $intent === 'subfamily' && isset($rows[0][0])) {
            $parent = (string)$rows[0][0];
            $copies = isset($rows[0][1]) ? (string)$rows[0][1] : '';
            return "## 结论\n该问题对应的是谱系/分类关系。根据本地知识库，当前实体属于 {$parent} 谱系。\n\n## 关键点\n"
                . "- 当前查询结果显示该实体与 {$parent} 之间存在 `SUBFAMILY_OF` 关系。\n"
                . ($copies !== '' ? "- 该关系记录中还包含拷贝数信息：{$copies}。\n" : '')
                . "\n## 参考文献\n本地知识库暂无直接文献证据。";
        }

        if ($language === 'zh') {
            $items = [];
            foreach (array_slice($rows, 0, 5) as $row) {
                if (is_array($row) && isset($row[0])) {
                    $target = (string)$row[0];
                    $predicate = isset($row[1]) ? (string)$row[1] : '相关';
                    $items[] = "- {$predicate} {$target}";
                }
            }
            $refs = [];
            foreach (array_slice($references, 0, 6) as $ref) {
                $title = (string)($ref['title'] ?? '未命名文献');
                $pmid = implode(',', $ref['pmids'] ?? []);
                $refs[] = "- {$title}" . ($pmid !== '' ? "（PMID: {$pmid}）" : '');
            }
            return "## 结论\n基于本地知识库，可以检索到与该问题直接相关的结构化记录。\n\n## 关键点\n"
                . implode("\n", $items)
                . "\n\n## 参考文献\n"
                . implode("\n", $refs);
        }

        $items = [];
        foreach (array_slice($rows, 0, 5) as $row) {
            if (is_array($row) && isset($row[0])) {
                $target = (string)$row[0];
                $predicate = isset($row[1]) ? (string)$row[1] : 'related to';
                $items[] = "- {$predicate} {$target}";
            }
        }
        $refs = [];
        foreach (array_slice($references, 0, 6) as $ref) {
            $title = (string)($ref['title'] ?? 'Untitled reference');
            $pmid = implode(',', $ref['pmids'] ?? []);
            $refs[] = "- {$title}" . ($pmid !== '' ? " (PMID: {$pmid})" : '');
        }
        return "## Conclusion\nRelevant structured records were retrieved from the local knowledge graph.\n\n## Key Points\n"
            . implode("\n", $items)
            . "\n\n## References\n"
            . implode("\n", $refs);
    }

    private function extractReferences(array $rows): array
    {
        $references = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                if (is_array($value)) {
                    if (isset($value['title'])) {
                        $key = $value['title'] . '|' . implode(',', $value['pmids'] ?? []);
                        $references[$key] = ['title' => $value['title'], 'pmids' => $value['pmids'] ?? []];
                    } elseif (isset($value[0]) && is_array($value[0]) && isset($value[0]['title'])) {
                        foreach ($value as $item) {
                            if (is_array($item) && isset($item['title'])) {
                                $key = $item['title'] . '|' . implode(',', $item['pmids'] ?? []);
                                $references[$key] = ['title' => $item['title'], 'pmids' => $item['pmids'] ?? []];
                            }
                        }
                    }
                }
            }
            if (isset($row[0]) && isset($row[2]) && is_array($row[2])) {
                foreach ($row[2] as $item) {
                    if (is_array($item) && isset($item['title'])) {
                        $key = $item['title'] . '|' . implode(',', $item['pmids'] ?? []);
                        $references[$key] = ['title' => $item['title'], 'pmids' => $item['pmids'] ?? []];
                    }
                }
            }
        }
        return array_values($references);
    }

    private function prepareRowsForAnswer(array $rows, ?string $intent): array
    {
        if ($intent === 'te_to_disease' || $intent === 'te_disease_relation' || $intent === 'te_disease_evidence') {
            $rows = array_values(array_filter($rows, function ($row): bool {
                $target = is_array($row) && isset($row[0]) ? (string)$row[0] : '';
                return $this->looksLikeDiseaseName($target);
            }));
        }

        if ($intent === 'te_to_function') {
            $rows = array_values(array_filter($rows, function ($row): bool {
                $target = is_array($row) && isset($row[0]) ? (string)$row[0] : '';
                return $this->looksLikeFunctionName($target);
            }));
        }

        $rows = $this->normalizeRows($rows, $intent);

        return array_slice($rows, 0, 12);
    }

    private function prepareReferencesForAnswer(array $references, ?string $intent): array
    {
        $filtered = array_values(array_filter($references, function ($ref): bool {
            $title = (string)($ref['title'] ?? '');
            return $title !== '' && $this->looksLikePaperTitle($title);
        }));

        usort($filtered, function ($a, $b): int {
            return count($b['pmids'] ?? []) <=> count($a['pmids'] ?? []);
        });

        return array_slice($filtered, 0, 8);
    }

    private function looksLikePaperTitle(string $title): bool
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            return false;
        }
        if (mb_strlen($trimmed) < 12) {
            return false;
        }
        if (!preg_match('/[\s:,.()\-]/u', $trimmed)) {
            return false;
        }
        return true;
    }

    private function looksLikeDiseaseName(string $name): bool
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return false;
        }
        $lower = mb_strtolower($trimmed);
        $positive = [
            'disease', 'syndrome', 'cancer', 'carcinoma', 'disorder', 'tumor', 'tumour',
            'leukemia', 'leukaemia', 'lymphoma', 'thalassemia', 'alzheimer', 'huntington',
            'autism', 'rett', 'rotor', 'ataxia', 'hemophilia', '贫血', '癌', '病', '综合征', '瘤'
        ];
        foreach ($positive as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }
        $negative = ['transduction', 'retrotransposition', 'junction', 'integration', 'utr', 'a-tail', 'chromothripsis'];
        foreach ($negative as $keyword) {
            if (str_contains($lower, $keyword)) {
                return false;
            }
        }
        return false;
    }

    private function looksLikeFunctionName(string $name): bool
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return false;
        }
        $lower = mb_strtolower($trimmed);
        $negative = ['disease', 'syndrome', 'cancer', 'carcinoma', 'disorder', '贫血', '癌', '综合征'];
        foreach ($negative as $keyword) {
            if (str_contains($lower, $keyword)) {
                return false;
            }
        }
        return true;
    }

    private function normalizeRows(array $rows, ?string $intent): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row[0])) {
                continue;
            }

            $row[0] = $this->normalizeDisplayLabel((string)$row[0], $intent);
            $key = mb_strtolower((string)$row[0]) . '|' . ((string)($row[1] ?? ''));

            if (!isset($normalized[$key])) {
                $normalized[$key] = $row;
                continue;
            }

            if (isset($row[2]) && is_array($row[2])) {
                $existingPmids = isset($normalized[$key][2]) && is_array($normalized[$key][2]) ? $normalized[$key][2] : [];
                $normalized[$key][2] = array_values(array_unique(array_merge($existingPmids, $row[2])));
            }

            if (isset($row[3]) && is_array($row[3])) {
                $existingEvidence = isset($normalized[$key][3]) && is_array($normalized[$key][3]) ? $normalized[$key][3] : [];
                $normalized[$key][3] = $this->mergeEvidenceItems($existingEvidence, $row[3]);
            }
        }

        return array_values($normalized);
    }

    private function mergeEvidenceItems(array $left, array $right): array
    {
        $merged = [];
        foreach (array_merge($left, $right) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = (string)($item['title'] ?? '');
            $pmids = $item['pmids'] ?? [];
            if (!is_array($pmids)) {
                $pmids = [$pmids];
            }
            $key = $title . '|' . implode(',', $pmids);
            $merged[$key] = [
                'title' => $title,
                'pmids' => array_values(array_unique(array_map('strval', $pmids))),
            ];
        }
        return array_values($merged);
    }

    private function normalizeDisplayLabel(string $label, ?string $intent): string
    {
        $trimmed = trim($label);
        if ($trimmed === '') {
            return $trimmed;
        }

        $map = [
            "Alzheimer's disease" => '阿尔茨海默病',
            'Huntington\'s Disease' => '亨廷顿病',
            'Down syndrome' => '唐氏综合征',
            'Rett syndrome' => 'Rett 综合征',
            'Rett综合征' => 'Rett 综合征',
            'Rotor syndrome' => 'Rotor 综合征',
            'ataxia telangiectasia' => '共济失调毛细血管扩张症',
            'autism spectrum disorder' => '自闭症谱系障碍',
            'autism spectrum disorders' => '自闭症谱系障碍',
            'Oral Squamous Cell Carcinoma' => '口腔鳞状细胞癌',
            'breast cancer' => '乳腺癌',
            'beta-thalassemia' => 'β-地中海贫血',
            'Mendelian disease' => '孟德尔遗传病',
            'IFN-based autoimmune diseases' => '干扰素相关自身免疫性疾病',
            '3´ transduction' => "3' 转导",
            "3' transduction" => "3' 转导",
            "5' and 3' transduction" => "5' 和 3' 转导",
            "5' UTR activity" => "5' UTR 活性",
            "A-tail extension" => 'A-tail 扩展',
        ];

        if (isset($map[$trimmed])) {
            return $map[$trimmed];
        }

        if ($intent === 'te_to_disease' || $intent === 'te_disease_relation' || $intent === 'te_disease_evidence') {
            return str_replace('disease', '病', $trimmed);
        }

        return $trimmed;
    }

    private function polishAnswer(string $answer, string $language): string
    {
        if ($language !== 'zh') {
            return $answer;
        }

        $parts = preg_split('/\n## 参考文献\n/u', $answer, 2);
        $body = $parts[0] ?? $answer;
        $references = $parts[1] ?? null;

        $replacements = [
            "Alzheimer's disease" => '阿尔茨海默病',
            "Huntington's Disease" => '亨廷顿病',
            'Down syndrome' => '唐氏综合征',
            'Rett syndrome' => 'Rett 综合征',
            'Rett综合征' => 'Rett 综合征',
            'autism spectrum disorder' => '自闭症谱系障碍',
            'autism spectrum disorders' => '自闭症谱系障碍',
            'ataxia telangiectasia' => '共济失调毛细血管扩张症',
            'breast cancer' => '乳腺癌',
            'Oral Squamous Cell Carcinoma' => '口腔鳞状细胞癌',
            'Rotor syndrome' => 'Rotor 综合征',
            'Mendelian disease' => '孟德尔遗传病',
            'IFN-based autoimmune diseases' => '干扰素相关自身免疫性疾病',
            'beta-thalassemia' => 'β-地中海贫血',
            ' robust ' => ' 高水平 ',
            'LINE1' => 'LINE-1',
        ];

        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
        $body = preg_replace('/\n{3,}/u', "\n\n", $body);

        if ($references !== null) {
            $references = preg_replace("/([（(]PMID:\s*\d+[)）])\n(?=[^\n])/u", "$1\n", $references);
            $references = preg_replace('/\n{3,}/u', "\n\n", $references);
            return trim($body) . "\n\n## 参考文献\n" . trim($references);
        }

        return trim($body);
    }

    private function dashscopeChat(array $messages, float $temperature = 0.2): string
    {
        $payload = [
            'model' => $this->config['dashscope_model'],
            'messages' => $messages,
            'temperature' => $temperature,
        ];

        if (!empty($this->config['llm_relay_url'])) {
            $relayResponse = $this->postLocalJson($this->config['llm_relay_url'], $payload);
            if (empty($relayResponse['ok']) || !isset($relayResponse['response'])) {
                throw new RuntimeException('LLM relay returned an invalid response');
            }
            $response = $relayResponse['response'];
        } else {
            $response = $this->httpJson(
                $this->config['dashscope_url'],
                $payload,
                [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->config['dashscope_key'],
                ]
            );
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        if (!is_string($content) || $content === '') {
            throw new RuntimeException('DashScope returned empty content');
        }
        return $content;
    }

    private function extractJson(string $text): string
    {
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return $matches[0];
        }
        return $text;
    }

    private function postLocalJson(string $url, array $payload): array
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '127.0.0.1';
        $port = (int)($parts['port'] ?? 80);
        $path = ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        if (!$socket) {
            throw new RuntimeException("Local relay socket failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, 90);
        $request =
            "POST {$path} HTTP/1.1\r\n" .
            "Host: {$host}:{$port}\r\n" .
            "Content-Type: application/json\r\n" .
            "Content-Length: " . strlen($body) . "\r\n" .
            "Connection: close\r\n\r\n" .
            $body;

        fwrite($socket, $request);
        $raw = stream_get_contents($socket);
        $meta = stream_get_meta_data($socket);
        fclose($socket);

        if (($meta['timed_out'] ?? false) === true) {
            throw new RuntimeException('Local relay request failed: socket timed out');
        }
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Local relay request failed: empty socket response');
        }

        [$headers, $responseBody] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
        if (!preg_match('#^HTTP/\d+\.\d+\s+(\d{3})#', $headers, $matches)) {
            throw new RuntimeException('Local relay returned malformed HTTP response');
        }
        $httpCode = (int)$matches[1];
        if ($httpCode >= 400) {
            throw new RuntimeException('Local relay returned HTTP ' . $httpCode . ': ' . $responseBody);
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid relay JSON response');
        }
        return $decoded;
    }

    private function httpJson(string $url, array $payload, array $headers, ?string $user = null, ?string $password = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge($headers, ['Expect:', 'Connection: close']),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        if (str_starts_with($url, 'https://') && !$this->config['ssl_verify']) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if ($user !== null && $password !== null) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $password);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if ($status >= 400) {
            $message = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $raw;
            throw new RuntimeException("HTTP {$status}: {$message}");
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response');
        }
        return $decoded;
    }
}
