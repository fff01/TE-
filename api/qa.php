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
$answerStyle = trim((string)($payload['answer_style'] ?? 'simple'));
$answerDepth = trim((string)($payload['answer_depth'] ?? ''));
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
    $result = $service->answer($question, $language, $answerStyle, $answerDepth);
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

    public function answer(string $question, string $language = '', string $answerStyle = 'simple', string $answerDepth = ''): array
    {
        $this->debug('answer:start', ['question' => $question, 'language' => $language, 'style' => $answerStyle, 'depth' => $answerDepth]);
        $answerStyle = strtolower(trim($answerStyle)) === 'detailed' ? 'detailed' : 'simple';
        $answerDepth = $this->normalizeAnswerDepth($answerDepth, $answerStyle);
        $language = $language !== '' ? $language : $this->detectLanguage($question);
        $this->debug('answer:normalized', ['language' => $language, 'style' => $answerStyle, 'depth' => $answerDepth]);
        $smallTalkAnswer = $this->getSmallTalkAnswer($question, $language);
        if ($smallTalkAnswer !== null) {
            $this->debug('answer:smalltalk');
            return [
                'language' => $language,
                'answer_style' => $answerStyle,
                'answer_depth' => $answerDepth,
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
                'answer_style' => $answerStyle,
                'answer_depth' => $answerDepth,
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
        if ($intent === null) {
            if ($this->containsAny($question, ['文献', '论文', '证据', 'paper', 'evidence', 'reference'])) {
                $intent = 'entity_to_paper';
            } elseif ($this->containsAny($question, ['功能', '机制', '作用', 'function', 'mechanism', 'role'])) {
                $intent = 'te_to_function';
            } elseif ($this->containsAny($question, ['疾病', '癌', '病', 'disease', 'cancer', 'disorder'])) {
                $intent = 'te_to_disease';
            } elseif ($this->containsAny($question, ['亚家族', '谱系', '关系', 'subfamily', 'lineage', 'relationship'])) {
                $intent = 'subfamily';
            }
        }
        $this->debug('answer:intent', ['entity' => $entity, 'disease' => $disease, 'intent' => $intent]);
        $mode = 'template_rag';
        $cypher = '';
        $rows = [];

        if ($entity !== null && $disease !== null && $this->containsAny(mb_strtolower($question), ['文献', '论文', '证据', 'paper', 'evidence', 'reference'])) {
            $intent = 'te_disease_evidence';
            [$cypher, $params] = $this->buildPairQuery($intent, $entity, $disease);
            $rows = $this->runNeo4j($cypher, $params);
            $this->debug('answer:pair_query', ['rows' => count($rows)]);
        } elseif ($entity !== null && $disease !== null) {
            $intent = 'te_disease_relation';
            [$cypher, $params] = $this->buildPairQuery($intent, $entity, $disease);
            $rows = $this->runNeo4j($cypher, $params);
            $this->debug('answer:pair_query', ['rows' => count($rows)]);
        } elseif ($entity !== null && $intent !== null) {
            [$cypher, $params] = $this->buildTemplateQuery($intent, $entity);
            $rows = $this->runNeo4j($cypher, $params);
            $this->debug('answer:template_query', ['rows' => count($rows)]);
        }

        if (empty($rows) && $entity !== null) {
            $cypher = "MATCH (n {name: \$entity})-[r]-(m) RETURN type(r) AS relation_type, coalesce(r.predicate, '') AS predicate, labels(m) AS target_labels, m.name AS target LIMIT 15";
            $rows = $this->runNeo4j($cypher, ['entity' => $entity]);
            $mode = 'neighbor_fallback';
        }

        $rows = $this->prepareRowsForAnswer($rows, $intent, $answerStyle, $answerDepth);
        $references = $this->extractReferences($rows);
        $references = $this->prepareReferencesForAnswer($references, $intent, $answerStyle, $answerDepth);
        $this->debug('answer:prepared', ['rows' => count($rows), 'refs' => count($references), 'intent' => $intent, 'depth' => $answerDepth]);
        try {
            $answer = $this->generateAnswer($question, $language, $rows, $references, $intent, $entity, $answerStyle, $answerDepth);
            $mode .= '+llm';
            $this->debug('answer:llm');
        } catch (Throwable $e) {
            $mode .= '+structured_local';
            $this->debug('answer:structured_local', ['error' => $e->getMessage()]);
            $answer = $this->fallbackAnswer($question, $language, $rows, $references, $intent, $entity, $answerStyle, $answerDepth);
        }
        $answer = $this->polishAnswer($answer, $language);
        $this->debug('answer:done', ['mode' => $mode]);

        return [
            'language' => $language,
            'answer_style' => $answerStyle,
            'answer_depth' => $answerDepth,
            'entity' => $entity,
            'intent' => $intent,
            'mode' => $mode,
            'cypher' => $cypher,
            'records' => $rows,
            'references' => $references,
            'graph_context' => $this->buildGraphContext($rows, $references, $intent, $entity, $question),
            'answer' => $answer,
        ];
    }

    private function buildGraphContext(array $rows, array $references, ?string $intent, ?string $entity, string $question): array
    {
        $elements = [];
        $nodeSeen = [];
        $edgeSeen = [];

        $anchorLabel = $entity ?? $this->guessAnchorFromQuestion($question);
        $anchorType = $this->inferAnchorType($intent, $anchorLabel);
        $anchorId = $this->graphNodeId($anchorType, $anchorLabel);
        $this->addGraphNode($elements, $nodeSeen, $anchorId, $anchorLabel, $anchorType);

        $paperCache = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            [$targetLabel, $targetType, $relation, $evidenceItems, $relationPmids] = $this->normalizeGraphRow($row, $intent);
            if ($targetLabel === '') {
                continue;
            }

            $targetId = $this->graphNodeId($targetType, $targetLabel);
            $this->addGraphNode($elements, $nodeSeen, $targetId, $targetLabel, $targetType);
            $edgeId = $this->graphEdgeId($anchorId, $targetId, $relation, $index);
            $this->addGraphEdge($elements, $edgeSeen, $edgeId, $anchorId, $targetId, $relation, '', $relationPmids);

            foreach ($this->fetchPaperNodesByPmids($relationPmids, $paperCache) as $paper) {
                $paperId = $this->graphNodeId('Paper', $paper['title'], $paper['pmid']);
                $this->addGraphNode($elements, $nodeSeen, $paperId, $paper['title'], 'Paper', '', $paper['pmid']);
                $paperEdgeId = $this->paperSupportEdgeId($paperId, $targetId, $paper['pmid'], $paper['title']);
                $this->addGraphEdge($elements, $edgeSeen, $paperEdgeId, $paperId, $targetId, 'EVIDENCE_RELATION', '', [$paper['pmid']]);
            }

            foreach ($evidenceItems as $evidenceIndex => $evidence) {
                if (!is_array($evidence)) {
                    continue;
                }
                $title = trim((string)($evidence['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $pmids = array_values(array_unique(array_map('strval', $evidence['pmids'] ?? [])));
                $paperId = $this->graphNodeId('Paper', $title, $pmids[0] ?? '');
                $this->addGraphNode($elements, $nodeSeen, $paperId, $title, 'Paper', '', $pmids[0] ?? '');
                $paperEdgeId = $this->paperSupportEdgeId($paperId, $targetId, $pmids[0] ?? '', $title);
                $this->addGraphEdge($elements, $edgeSeen, $paperEdgeId, $paperId, $targetId, 'EVIDENCE_RELATION', '', $pmids);
            }
        }

        foreach ($references as $refIndex => $ref) {
            if (!is_array($ref)) {
                continue;
            }
            $title = trim((string)($ref['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $pmids = array_values(array_unique(array_map('strval', $ref['pmids'] ?? [])));
            $paperId = $this->graphNodeId('Paper', $title, $pmids[0] ?? '');
            $this->addGraphNode($elements, $nodeSeen, $paperId, $title, 'Paper', '', $pmids[0] ?? '');
            $edgeId = $this->graphEdgeId($paperId, $anchorId, 'EVIDENCE_RELATION', 'ref_' . $refIndex);
            $this->addGraphEdge($elements, $edgeSeen, $edgeId, $paperId, $anchorId, 'EVIDENCE_RELATION', '', $pmids);
        }

        return [
            'anchor' => [
                'name' => $anchorLabel,
                'type' => $anchorType,
            ],
            'elements' => $elements,
        ];
    }

    private function fetchPaperNodesByPmids(array $pmids, array &$paperCache): array
    {
        $normalized = array_values(array_unique(array_filter(array_map('strval', $pmids), static fn ($v) => trim($v) !== '')));
        if (empty($normalized)) {
            return [];
        }

        $missing = array_values(array_filter($normalized, static fn ($pmid) => !array_key_exists($pmid, $paperCache)));
        if (!empty($missing)) {
            $rows = $this->runNeo4j(
                "MATCH (p:Paper)
                 WHERE p.pmid IN \$pmids
                 RETURN p.name AS title, p.pmid AS pmid",
                ['pmids' => $missing]
            );
            foreach ($missing as $pmid) {
                $paperCache[$pmid] = null;
            }
            foreach ($rows as $row) {
                $title = trim((string)($row[0] ?? ''));
                $pmid = trim((string)($row[1] ?? ''));
                if ($title === '' || $pmid === '') {
                    continue;
                }
                $paperCache[$pmid] = [
                    'title' => $title,
                    'pmid' => $pmid,
                ];
            }
        }

        $results = [];
        foreach ($normalized as $pmid) {
            if (is_array($paperCache[$pmid] ?? null)) {
                $results[] = $paperCache[$pmid];
            }
        }
        return $results;
    }

    private function normalizeGraphRow(array $row, ?string $intent): array
    {
        $evidenceItems = [];
        $relationPmids = [];
        if ($this->looksLikeRelationType((string)($row[0] ?? ''))) {
            $relationType = (string)($row[0] ?? '');
            $predicate = trim((string)($row[1] ?? ''));
            $targetLabels = is_array($row[2] ?? null) ? $row[2] : [];
            $targetLabel = trim((string)($row[3] ?? ''));
            $targetType = $this->normalizeGraphType((string)($targetLabels[0] ?? ''));
            $relation = $predicate !== '' ? $predicate : $relationType;
            return [$targetLabel, $targetType, $relation, $evidenceItems, $relationPmids];
        }

        $targetLabel = trim((string)($row[0] ?? ''));
        $relation = trim((string)($row[1] ?? ''));
        $relationPmids = array_values(array_unique(array_map('strval', is_array($row[2] ?? null) ? $row[2] : [])));
        $evidenceItems = is_array($row[3] ?? null) ? $row[3] : [];
        $targetType = $this->inferTargetTypeFromIntent($intent, $targetLabel);

        return [$targetLabel, $targetType, $relation !== '' ? $relation : 'BIO_RELATION', $evidenceItems, $relationPmids];
    }

    private function inferAnchorType(?string $intent, string $anchorLabel): string
    {
        if ($anchorLabel === '') {
            return 'TE';
        }
        if (str_contains($anchorLabel, 'PMID:')) {
            return 'Paper';
        }
        return match ($intent) {
            'entity_to_paper', 'te_to_disease', 'te_to_function', 'subfamily', 'te_disease_relation', 'te_disease_evidence' => 'TE',
            default => 'TE',
        };
    }

    private function inferTargetTypeFromIntent(?string $intent, string $targetLabel): string
    {
        return match ($intent) {
            'te_to_disease', 'te_disease_relation', 'te_disease_evidence' => 'Disease',
            'te_to_function' => 'Function',
            'entity_to_paper' => 'Paper',
            'subfamily' => 'TE',
            default => $this->guessTypeFromLabel($targetLabel),
        };
    }

    private function guessTypeFromLabel(string $label): string
    {
        if ($this->looksLikePaperTitle($label)) {
            return 'Paper';
        }
        if ($this->looksLikeDiseaseName($label)) {
            return 'Disease';
        }
        if ($this->looksLikeFunctionName($label)) {
            return 'Function';
        }
        return 'TE';
    }

    private function normalizeGraphType(string $type): string
    {
        return in_array($type, ['TE', 'Disease', 'Function', 'Paper'], true) ? $type : 'TE';
    }

    private function looksLikeRelationType(string $value): bool
    {
        return in_array($value, ['BIO_RELATION', 'EVIDENCE_RELATION', 'SUBFAMILY_OF'], true);
    }

    private function guessAnchorFromQuestion(string $question): string
    {
        return $this->normalizeEntity($question) ?? $this->normalizeDisease($question) ?? trim($question);
    }

    private function graphNodeId(string $type, string $label, string $pmid = ''): string
    {
        $raw = $type . '|' . $label . '|' . $pmid;
        return preg_replace('/[^A-Za-z0-9_:-]/', '_', $raw);
    }

    private function graphEdgeId(string $source, string $target, string $relation, string|int $suffix): string
    {
        $raw = $source . '|' . $relation . '|' . $target . '|' . $suffix;
        return preg_replace('/[^A-Za-z0-9_:-]/', '_', $raw);
    }

    private function paperSupportEdgeId(string $paperId, string $targetId, string $pmid = '', string $title = ''): string
    {
        $suffix = $pmid !== '' ? 'pmid_' . $pmid : 'title_' . md5($title);
        return $this->graphEdgeId($paperId, $targetId, 'EVIDENCE_RELATION', $suffix);
    }

    private function addGraphNode(array &$elements, array &$nodeSeen, string $id, string $label, string $type, string $description = '', string $pmid = ''): void
    {
        if (isset($nodeSeen[$id])) {
            return;
        }
        $nodeSeen[$id] = true;
        $elements[] = [
            'data' => [
                'id' => $id,
                'label' => $label,
                'type' => $type,
                'description' => $description,
                'pmid' => $pmid,
            ],
        ];
    }

    private function addGraphEdge(array &$elements, array &$edgeSeen, string $id, string $source, string $target, string $relation, string $evidence = '', array $pmids = []): void
    {
        if ($source === '' || $target === '' || isset($edgeSeen[$id])) {
            return;
        }
        $edgeSeen[$id] = true;
        $elements[] = [
            'data' => [
                'id' => $id,
                'source' => $source,
                'target' => $target,
                'relation' => $relation,
                'evidence' => $evidence,
                'pmids' => array_values(array_unique(array_map('strval', $pmids))),
            ],
        ];
    }

    private function detectLanguage(string $question): string
    {
        return preg_match('/[\x{4e00}-\x{9fff}]/u', $question) ? 'zh' : 'en';
    }

    private function normalizeAnswerDepth(string $answerDepth, string $answerStyle): string
    {
        $depth = strtolower(trim($answerDepth));
        if (in_array($depth, ['shallow', 'medium', 'deep'], true)) {
            return $depth;
        }
        return $answerStyle === 'detailed' ? 'deep' : 'shallow';
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
            'alzheimer' => "Alzheimer's disease",
            'huntington' => "Huntington's Disease",
            'rett' => 'Rett syndrome',
            'down syndrome' => 'Down syndrome',
            'autism' => 'autism spectrum disorder',
            'breast cancer' => 'breast cancer',
            'ataxia telangiectasia' => 'ataxia telangiectasia',
            'oral squamous cell carcinoma' => 'Oral Squamous Cell Carcinoma',
        ];
        foreach ($aliases as $alias => $disease) {
            if (str_contains($lower, $alias)) {
                return $disease;
            }
        }
        if ($this->containsAny($question, ['阿尔茨海默病', '阿尔兹海默症', '阿兹海默症'])) {
            return "Alzheimer's disease";
        }
        if ($this->containsAny($question, ['亨廷顿病'])) {
            return "Huntington's Disease";
        }
        if ($this->containsAny($question, ['Rett', '雷特综合征', 'Rett综合征'])) {
            return 'Rett syndrome';
        }
        if ($this->containsAny($question, ['唐氏综合征'])) {
            return 'Down syndrome';
        }
        if ($this->containsAny($question, ['自闭症', '自闭症谱系障碍'])) {
            return 'autism spectrum disorder';
        }
        if ($this->containsAny($question, ['乳腺癌'])) {
            return 'breast cancer';
        }
        if ($this->containsAny($question, ['共济失调毛细血管扩张症'])) {
            return 'ataxia telangiectasia';
        }
        if ($this->containsAny($question, ['口腔鳞状细胞癌'])) {
            return 'Oral Squamous Cell Carcinoma';
        }
        return null;
    }

    private function detectIntent(string $question): ?string
    {
        $lower = mb_strtolower($question);
        if ($this->containsAny($lower, ['subfamily', 'lineage', 'relationship'])) {
            return 'subfamily';
        }
        if ($this->containsAny($lower, ['paper', 'evidence', 'reference'])) {
            return 'entity_to_paper';
        }
        if ($this->containsAny($lower, ['function', 'mechanism', 'role'])) {
            return 'te_to_function';
        }
        if ($this->containsAny($lower, ['disease', 'cancer', 'disorder'])) {
            return 'te_to_disease';
        }
        if ($this->containsAny($question, ['亚家族', '谱系', '关系'])) {
            return 'subfamily';
        }
        if ($this->containsAny($question, ['文献', '论文', '证据'])) {
            return 'entity_to_paper';
        }
        if ($this->containsAny($question, ['功能', '机制', '作用'])) {
            return 'te_to_function';
        }
        if ($this->containsAny($question, ['疾病', '癌', '病'])) {
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
        $smallTalk = ['hi', 'hello', 'hey', 'help'];
        if (preg_match('/^(你好|您好|嗨|在吗|你是谁|你是什么模型|你能做什么|帮助)$/u', trim($question))) {
            return true;
        }
        return in_array($lower, $smallTalk, true);
    }

    private function getSmallTalkAnswer(string $question, string $language): ?string
    {
        $normalized = preg_replace('/\s+/u', '', mb_strtolower(trim($question)));
        $zhSmallTalk = [
            '你好', '您好', '嗨', '在吗', '帮助',
            '你是谁', '你能做什么', '你是什么模型', '你会什么',
        ];
        $enSmallTalk = [
            'hi', 'hello', 'hey', 'help', 'whoareyou', 'whatmodelareyou', 'whatcanyoudo',
        ];

        if (in_array($normalized, $zhSmallTalk, true)) {
            return "你好。我现在可以回答基于本地 TE 知识图谱的问题。你可以直接问：\n\n1. LINE-1 相关疾病\n2. LINE-1 相关功能\n3. L1HS 和 LINE-1 是什么关系\n4. 哪些文献支持 LINE-1 与阿尔茨海默病相关";
        }

        if (in_array($normalized, $enSmallTalk, true)) {
            return "Hello. I can answer questions grounded in the local TE knowledge graph. You can ask:

1. LINE-1 related diseases
2. LINE-1 related functions
3. What is the relationship between L1HS and LINE-1?
4. What papers support the association between LINE-1 and Alzheimer's disease?";
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

    private function generateAnswer(string $question, string $language, array $rows, array $references, ?string $intent, ?string $entity, string $answerStyle, string $answerDepth): string
    {
        $context = json_encode([
            'intent' => $intent,
            'entity' => $entity,
            'rows' => $rows,
            'references' => $references,
            'answer_style' => $answerStyle,
            'answer_depth' => $answerDepth,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $template = $this->loadPromptTemplate($language, $answerStyle, $answerDepth);
        $prompt = $this->renderPromptTemplate($template, [
            'question' => $question,
            'context' => $context,
            'language' => $language,
            'answer_style' => $answerStyle,
            'answer_depth' => $answerDepth,
            'intent' => $intent ?? 'unknown',
            'entity' => $entity ?? '',
        ]);

        return $this->dashscopeChat([
            ['role' => 'system', 'content' => 'You are a bioinformatics knowledge-graph QA assistant.'],
            ['role' => 'user', 'content' => $prompt],
        ], 0.1);
    }

    private function loadPromptTemplate(string $language, string $answerStyle, string $answerDepth): string
    {
        $lang = strtolower($language) === 'en' ? 'en' : 'zh';
        $style = strtolower($answerStyle) === 'detailed' ? 'detailed' : 'simple';
        $depth = in_array(strtolower($answerDepth), ['shallow', 'medium', 'deep'], true) ? strtolower($answerDepth) : ($style === 'detailed' ? 'deep' : 'shallow');
        $basePath = __DIR__ . DIRECTORY_SEPARATOR . 'prompts' . DIRECTORY_SEPARATOR . "{$lang}_{$style}.md";
        $depthPath = __DIR__ . DIRECTORY_SEPARATOR . 'prompts' . DIRECTORY_SEPARATOR . "{$lang}_depth_{$depth}.md";
        $parts = [];

        if (is_file($basePath)) {
            $content = file_get_contents($basePath);
            if (is_string($content) && trim($content) !== '') {
                $parts[] = trim($content);
            }
        }

        if (is_file($depthPath)) {
            $content = file_get_contents($depthPath);
            if (is_string($content) && trim($content) !== '') {
                $parts[] = trim($content);
            }
        }

        if (!empty($parts)) {
            return implode("\n\n", $parts);
        }

        if ($lang === 'zh') {
            return $style === 'detailed'
                ? "请用中文作答，并严格基于给定上下文生成详细回答。\n\n输出结构：\n## 结论\n## 机制与关系解释\n## 证据与文献\n## 局限与说明\n\n用户问题：\n{{question}}\n\n上下文：\n{{context}}"
                : "请用中文作答，并严格基于给定上下文生成简洁回答。\n\n输出结构：\n## 结论\n## 关键点\n## 参考文献\n\n用户问题：\n{{question}}\n\n上下文：\n{{context}}";
        }

        return $style === 'detailed'
            ? "Answer in detailed academic English using only the provided context.

Structure:
## Conclusion
## Mechanistic Interpretation
## Evidence and References
## Limitations

User question:
{{question}}

Context:
{{context}}"
            : "Answer in concise academic English using only the provided context.

Structure:
## Conclusion
## Key Points
## References

User question:
{{question}}

Context:
{{context}}";
    }

    private function renderPromptTemplate(string $template, array $vars): string
    {
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{{' . strtoupper($key) . '}}'] = (string)$value;
            $replacements['{{' . strtolower($key) . '}}'] = (string)$value;
            $replacements['{{' . $key . '}}'] = (string)$value;
        }
        return strtr($template, $replacements);
    }

    private function fallbackAnswer(string $question, string $language, array $rows, array $references, ?string $intent, ?string $entity, string $answerStyle, string $answerDepth): string
    {
        if ($language === 'zh') {
            $templateName = empty($rows)
                ? ('fallback_zh_' . $answerStyle . '_empty.md')
                : ('fallback_zh_' . $answerStyle . '.md');
            $templatePath = __DIR__ . DIRECTORY_SEPARATOR . 'prompts' . DIRECTORY_SEPARATOR . $templateName;
            $template = is_file($templatePath)
                ? (string) file_get_contents($templatePath)
                : "## 结论\n本地知识图谱暂无直接证据。";

            $items = [];
            foreach (array_slice($rows, 0, $this->fallbackRowLimit($answerStyle, $answerDepth)) as $row) {
                if (!is_array($row) || !isset($row[0])) {
                    continue;
                }
                $target = $this->extractFallbackTarget($row);
                $predicate = isset($row[1]) && trim((string)$row[1]) !== '' ? (string) $row[1] : '相关';
                if ($answerStyle === 'detailed') {
                    $items[] = '- 当前知识图谱记录显示，查询对象与“' . $target . '”之间存在“' . $predicate . '”关系，可作为进一步解释该问题的结构化证据。';
                } else {
                    $items[] = '- ' . $predicate . ' ' . $target;
                }
            }
            if (empty($items)) {
                $items[] = $answerStyle === 'detailed'
                    ? '- 当前未检索到足够的结构化记录，暂时无法对该问题做更深入展开。'
                    : '- 当前未检索到直接证据。';
            }

            $refs = [];
            foreach (array_slice($references, 0, $this->fallbackReferenceLimit($answerStyle, $answerDepth)) as $ref) {
                $title = (string) ($ref['title'] ?? '未命名文献');
                $pmid = implode(',', $ref['pmids'] ?? []);
                $refs[] = '- ' . $title . ($pmid !== '' ? '（PMID: ' . $pmid . '）' : '');
            }
            if (empty($refs)) {
                $refs[] = '- 当前未检索到可展示的参考文献。';
            }

            return strtr($template, [
                '{{items}}' => implode("
", $items),
                '{{refs}}' => implode("
", $refs),
            ]);
        }

        if (empty($rows)) {
            return $answerStyle === 'detailed'
                ? "## Conclusion
The local knowledge graph does not currently contain enough structured evidence to support a fully detailed answer.

## Mechanistic Interpretation
- No directly usable entity-relation records were retrieved for this query.
- The queried entity may need stronger normalization, or relevant graph relations may still be incomplete.
- A more specific disease, function, or TE name often improves retrieval quality.

## Evidence and References
No local evidence records were retrieved for this query, so a full evidence trail cannot be expanded.

## Limitations
- The answer is constrained by the current coverage of the local knowledge graph.
- Missing normalization or incomplete relation extraction can reduce retrieval quality."
                : "## Conclusion
The local knowledge graph currently lacks direct evidence for this question.

## Key Points
- No directly relevant structured records were retrieved.
- Try using a more specific entity or relation type.

## References
None.";
        }

        $items = [];
        foreach (array_slice($rows, 0, $this->fallbackRowLimit($answerStyle, $answerDepth)) as $row) {
            if (is_array($row) && isset($row[0])) {
                $target = $this->extractFallbackTarget($row);
                $predicate = isset($row[1]) ? (string) $row[1] : 'related to';
                $items[] = $answerStyle === 'detailed'
                    ? "- The current graph records indicate a '" . $predicate . "' relation between the queried entity and " . $target . "."
                    : '- ' . $predicate . ' ' . $target;
            }
        }
        $refs = [];
        foreach (array_slice($references, 0, $this->fallbackReferenceLimit($answerStyle, $answerDepth)) as $ref) {
            $title = (string) ($ref['title'] ?? 'Untitled reference');
            $pmid = implode(',', $ref['pmids'] ?? []);
            $refs[] = '- ' . $title . ($pmid !== '' ? ' (PMID: ' . $pmid . ')' : '');
        }
        if ($answerStyle === 'detailed') {
            return "## Conclusion
Relevant structured records were retrieved from the local knowledge graph. The answer below expands the main relations, evidence direction, and likely interpretation supported by the current graph context.

## Mechanistic Interpretation
"
                . implode("
", $items)
                . "

## Evidence and References
The following references are the main evidence items currently retained in the local graph:
"
                . implode("
", $refs)
                . "

## Limitations
- The answer is limited to structured records already imported into the graph.
- Missing normalization or incomplete relation extraction may reduce coverage.";
        }

        return "## Conclusion
Relevant structured records were retrieved from the local knowledge graph.

## Key Points
"
            . implode("
", $items)
            . "

## References
"
            . implode("
", $refs);
    }

    private function extractFallbackTarget(array $row): string
    {
        if (isset($row[0]) && is_string($row[0]) && !in_array($row[0], ['BIO_RELATION', 'EVIDENCE_RELATION', 'SUBFAMILY_OF'], true)) {
            return (string) $row[0];
        }
        if (isset($row[3]) && is_scalar($row[3])) {
            return (string) $row[3];
        }
        if (isset($row[0])) {
            return is_scalar($row[0]) ? (string) $row[0] : '未知对象';
        }
        return '未知对象';
    }

    private function prepareRowsForAnswer(array $rows, ?string $intent, string $answerStyle = 'simple', string $answerDepth = 'shallow'): array
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

        $limit = $this->rowLimit($answerStyle, $answerDepth);
        return array_slice($rows, 0, $limit);
    }

    private function extractReferences(array $rows): array
    {
        $references = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row[3]) || !is_array($row[3])) {
                continue;
            }

            foreach ($row[3] as $evidence) {
                if (!is_array($evidence)) {
                    continue;
                }

                $title = trim((string)($evidence['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $pmids = array_values(array_unique(array_map('strval', $evidence['pmids'] ?? [])));
                if (!isset($references[$title])) {
                    $references[$title] = [
                        'title' => $title,
                        'pmids' => $pmids,
                    ];
                    continue;
                }

                $references[$title]['pmids'] = array_values(array_unique(array_merge($references[$title]['pmids'], $pmids)));
            }
        }

        return array_values($references);
    }

    private function prepareReferencesForAnswer(array $references, ?string $intent, string $answerStyle = 'simple', string $answerDepth = 'shallow'): array
    {
        $filtered = array_values(array_filter($references, function ($ref): bool {
            $title = (string)($ref['title'] ?? '');
            return $title !== '' && $this->looksLikePaperTitle($title);
        }));

        usort($filtered, function ($a, $b): int {
            return count($b['pmids'] ?? []) <=> count($a['pmids'] ?? []);
        });

        $limit = $this->referenceLimit($answerStyle, $answerDepth);
        return array_slice($filtered, 0, $limit);
    }

    private function rowLimit(string $answerStyle, string $answerDepth): int
    {
        return match ($answerDepth) {
            'medium' => $answerStyle === 'detailed' ? 8 : 6,
            'deep' => $answerStyle === 'detailed' ? 12 : 8,
            default => $answerStyle === 'detailed' ? 6 : 4,
        };
    }

    private function referenceLimit(string $answerStyle, string $answerDepth): int
    {
        return match ($answerDepth) {
            'medium' => $answerStyle === 'detailed' ? 6 : 4,
            'deep' => $answerStyle === 'detailed' ? 8 : 5,
            default => $answerStyle === 'detailed' ? 4 : 3,
        };
    }

    private function fallbackRowLimit(string $answerStyle, string $answerDepth): int
    {
        return match ($answerDepth) {
            'medium' => $answerStyle === 'detailed' ? 7 : 5,
            'deep' => $answerStyle === 'detailed' ? 9 : 6,
            default => $answerStyle === 'detailed' ? 5 : 4,
        };
    }

    private function fallbackReferenceLimit(string $answerStyle, string $answerDepth): int
    {
        return match ($answerDepth) {
            'medium' => $answerStyle === 'detailed' ? 8 : 5,
            'deep' => $answerStyle === 'detailed' ? 10 : 6,
            default => $answerStyle === 'detailed' ? 6 : 4,
        };
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
            'autism', 'rett', 'rotor', 'ataxia', 'hemophilia',
            '病', '癌', '综合征', '白血病', '淋巴瘤'
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
        $negative = ['disease', 'syndrome', 'cancer', 'carcinoma', 'disorder', '病', '癌', '综合征'];
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
        return trim($label);
    }

    private function polishAnswer(string $answer, string $language): string
    {
        $answer = preg_replace('/
{3,}/u', "

", $answer);
        return trim((string) $answer);
    }

    private function dashscopeChat(array $messages, float $temperature = 0.2): string
    {
        $payload = [
            'model' => $this->config['dashscope_model'],
            'messages' => $messages,
            'temperature' => $temperature,
            'enable_thinking' => false,
        ];

        if (!empty($this->config['llm_relay_url'])) {
            $relayResponse = $this->httpJson(
                $this->config['llm_relay_url'],
                $payload,
                ['Content-Type: application/json']
            );
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

        stream_set_timeout($socket, 20);
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

    private function debug(string $stage, array $data = []): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $stage;
        if ($data !== []) {
            $line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line .= PHP_EOL;
        @file_put_contents(__DIR__ . '/qa_debug.log', $line, FILE_APPEND);
    }
}


