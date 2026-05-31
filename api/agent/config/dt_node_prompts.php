<?php
declare(strict_types=1);

$sharedEn = implode("\n", [
    'Return JSON only.',
    'Do not wrap the response in Markdown fences.',
    'Do not output explanations outside JSON.',
    'Use the supplied schema exactly.',
    'Do not invent evidence, citations, PMID, URLs, graph edges, or plugin results.',
]);

$sharedZh = implode("\n", [
    '只返回 JSON。',
    '不要使用 Markdown fence 包裹输出。',
    '不要在 JSON 之外输出解释。',
    '严格使用给定 schema。',
    '不要编造证据、引用、PMID、URL、图谱边或插件结果。',
]);

return [
    'understanding' => [
        'en' => $sharedEn . "\nProduce dt_understanding.v1. Treat rule_normalizer as input material only. Interpret the user request, answer goal, entities, evidence requirements, and warnings.",
        'zh' => $sharedZh . "\n生成 dt_understanding.v1。rule_normalizer 只能作为输入材料。解释用户问题、回答目标、实体、证据需求和警告。",
    ],
    'planning' => [
        'en' => $sharedEn . "\nProduce dt_planning.v1. Select at most three business_plugins from available_business_plugins. Entity Resolver is bootstrap-only. Citation Resolver is an optional extra resolver. Select Literature Plugin only when explicit_literature_request is true.",
        'zh' => $sharedZh . "\n生成 dt_planning.v1。从 available_business_plugins 中选择最多三个 business_plugins。Entity Resolver 仅用于 bootstrap。Citation Resolver 是额度外可选 resolver。只有 explicit_literature_request 为 true 时才能选择 Literature Plugin。",
    ],
    'executing' => [
        'en' => $sharedEn . "\nProduce dt_executing.v1. Decide whether execution is done or choose one next_plugin from remaining_planned_plugins. Review only supplied evidence. Do not simulate plugin calls.",
        'zh' => $sharedZh . "\n生成 dt_executing.v1。判断执行是否完成，或从 remaining_planned_plugins 中选择一个 next_plugin。只审查已有证据，不要模拟插件调用。",
    ],
    'writing' => [
        'en' => $sharedEn . "\nProduce dt_writing.v1. answer_markdown is the only final answer source. Write a faithful answer from supplied artifacts and evidence. Keep it empty only when no answer can be supported.",
        'zh' => $sharedZh . "\n生成 dt_writing.v1。answer_markdown 是最终答案的唯一来源。根据已有 artifact 和证据忠实作答；只有完全无法支持回答时才保持为空。",
    ],
];
