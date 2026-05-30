# Agent 插件说明

本文档说明 `api/agent/plugins/` 下各 Agent 插件的职责、输入输出、数据源和已知风险。它面向后续维护，不是用户帮助页。

## 统一插件接口

所有插件实现 `TekgAgentPluginInterface`：

```php
public function getName(): string;
public function run(array $context): array;
```

插件由 `api/agent/plugin_registry.php` 注册。默认注册名必须与路由策略、规划队列和前端工具事件保持一致，例如 `Graph Plugin`、`Sequence Plugin`。

`run($context)` 常见输入：

- `question`：用户原始问题。
- `analysis`：`EntityNormalizer` 产出的意图、实体、别名链、需求标记。
- `planning`：Agent 规划出的 knowledge gaps、tool plan、subtasks。
- `plugin_results`：前序插件结果，用于串联证据。
- `config`：模型、relay、PubMed、Neo4j 等运行配置。

插件标准输出通常包含：

- `plugin_name`：插件注册名。
- `status`：`ok`、`partial`、`empty` 或 `error`。
- `query_summary`：本次执行意图。
- `results`：结构化原始结果，供后续节点使用。
- `display_label`、`display_summary`、`display_details`：前端 thinking 区展示材料。
- `result_counts`：计数指标。
- `evidence_items`：统一结构化证据项。旧字符串会在 envelope 层被兼容归一化，但新插件不应再输出裸字符串。
- `citations`：引用记录。
- `errors`：插件自身错误。
- `latency_ms`：插件耗时。

注意：插件输出不是最终报告。最终报告还会经过 evidence package、evidence walk、report plan、Writing/Polishing 和 integrity gate。

### `evidence_items` 结构约定

插件应优先通过 `tekg_agent_make_evidence_item()` 生成证据项。基础字段包括：

- `source_plugin`：证据来源插件名。
- `entity_scope`：该证据适用的实体范围。
- `claim`：证据支持的最小陈述；不要把长摘要或前端 narration 当 claim。
- `support_strength`：`high`、`medium`、`low`、`none`。
- `raw_source_ref`：可追溯的原始来源、行、记录、URL、计数等。
- `title`、`meta`、`body`：前端展示用摘要。

扩展字段用于让写作层理解证据类型：

- `evidence_type`：例如 `graph_relation`、`profile_summary`、`genomic_locus`、`classification_context`、`citation_normalization`、`system_error`、`empty_result`。
- `coverage_dimension`：例如 `relationship`、`expression`、`genomic_location`、`classification_context`、`citation`。
- `subject`、`object`：结构化主语/宾语。
- `provenance`：数据来源说明。
- `diagnostic`：错误、空结果、计数等诊断信息。
- `citations`：该证据项直接携带的引用。
- `quality_flags`：例如 `not_evidence`、`representative_locus`、`not_biological_claim`。

失败、空结果和引用归一化可以进入 `evidence_items`，但必须用 `support_strength=none` 或 `quality_flags` 明确标记，避免写作层把它们当作生物学事实。

## 插件总览

| 插件 | 主要用途 | 主要数据源 | 是否调用 LLM | 常见风险 |
|---|---|---|---|---|
| Entity Resolver | 固定实体和别名边界 | `EntityNormalizer` 分析结果 | 否 | 实体漏识别会影响所有下游插件 |
| Site Navigator Plugin | 返回站内页面/面板链接 | `config/site_navigation_map.php` | 否 | 只提供入口，不提供科学数据 |
| Graph Plugin | 查局部结构化关系 | Neo4j TE-KG | 否 | 图谱关系不等于因果证据 |
| Graph Analytics Plugin | 排名、计数、拓扑聚合 | Neo4j TE-KG | 否 | 固定模板覆盖有限 |
| Cypher Explorer Plugin | 生成只读 Cypher 探索 | LLM + Neo4j | 是 | 依赖 LLM 生成质量和只读校验 |
| Literature Plugin | 本地图谱文献 + PubMed 检索 | Neo4j、NCBI E-utilities、PubMed cache | 否 | PubMed 关键词可能带入弱/无关文献 |
| Literature Reading Plugin | 将文献列表综合成 claim clusters | Literature Plugin citations + LLM | 是 | 容易把标题/摘要级信息过度综合 |
| Tree Plugin | TE/疾病分类路径 | taxonomy runtime、disease map | 否 | 只提供分类背景，不是机制证据 |
| Expression Plugin | TE 表达上下文 | expression runtime / MySQL | 否 | 数据库连接失败时会 `partial` |
| Genome Plugin | 代表性基因组位点和 JBrowse 入口 | JBrowse hit JSON | 否 | 代表性 locus 不等于全部插入位点 |
| Sequence Plugin | Repbase-backed 序列记录 | `data/processed/te_repbase_db_matched.json` | 否 | structure hints 可能被写作模型误读 |
| Citation Resolver | 汇总、去重、规范化引用 | 上游插件 citations | 否 | 不判断引用是否真正支持 claim |

## Entity Resolver

文件：`EntityResolverPlugin.php`

职责：

- 将 `analysis.alias_chains` 转换为前端可展示、下游可复用的实体解析结果。
- 输出 canonical label、entity type、matched alias、strict aliases、broad aliases、confidence。
- 给后续 Graph/Literature/Genome/Sequence 层提供稳定实体名和候选别名。

输入依赖：

- `analysis.alias_chains`

主要输出：

- `results.alias_chains`
- `result_counts.resolved_entities`
- `result_counts.alias_variants`
- `evidence_items`：例如 “Resolved the TE entity L1HS with 2 strict alias variants.”

适用场景：

- 几乎所有 Agent 路径的第一步。
- 研究报告、机制综述、图谱查询、序列/表达/基因组查询都需要它。

已知风险：

- 它不查数据库，只包装 normalizer 结果。
- 如果 normalizer 没识别实体，后续插件只能靠 broader keyword 或前序图谱结果补救。

## Site Navigator Plugin

文件：`SiteNavigatorPlugin.php`

职责：

- 为“在哪看、哪个页面、打开入口、URL”类问题生成 TE-KG 站内链接。
- 根据问题 capability 选择 `search.php`、`browse.php`、`preview.php`、`expression_detail.php` 等入口。

数据源：

- `api/agent/config/site_navigation_map.php`
- 当前 URL 的 origin，用于生成绝对链接。

输入依赖：

- `question`
- `analysis.normalized_entities`
- `analysis.request_context.current_url`
- `analysis.asks_for_site_navigation`

主要输出：

- `results.primary_route`
- `results.candidate_routes`
- `results.answer_markdown`
- `result_counts.routes`

适用场景：

- 用户问“L1HS 的 sequence 页面在哪里？”
- 用户问“Genome Annotation Distribution 应该点哪里？”
- 用户需要可点击页面入口，而不是数据解释。

已知风险：

- 它只返回路线，不读取页面中的科学数据。
- 不能用它满足“研究报告”“序列内容”“表达数据”“疾病证据”等数据需求。
- 过去 `disease links` 中的 `link` 曾误触发导航；后续维护时不要用单个 `link` 这类宽泛子串判断纯导航需求。

## Graph Plugin

文件：`GraphPlugin.php`

职责：

- 查询 TE-KG 局部结构化关系。
- 支持 TE 到 Disease、Gene、Function、Protein、RNA、Mutation、Paper 等目标类型的关系。
- 支持 TE-TE lineage pair、TE-Disease pair、机制相关 cancer TE 候选。

数据源：

- Neo4j runtime，当前项目硬约束是 `tekg3`。
- Paper 节点和 relation 上的 `pmids` / `evidence` 字段。

输入依赖：

- `analysis.normalized_entities`
- `analysis.requested_target_types`
- `analysis.intent`
- `analysis.asks_for_mechanism`
- `analysis.question_keywords`

主要输出：

- `results.rows`
- `results.by_type`
- `results.graph_elements`
- `result_counts.relations`
- 按目标类型补充计数，例如 `Disease`、`Gene`
- `citations`：从 relation `pmids`、`evidence` 和 Paper 节点补齐。

适用场景：

- 疾病链接、机制链、实体关系列表。
- 研究报告里的 disease links 和 gene targets。
- 需要本地图谱证据优先时。

已知风险：

- 图谱边是结构化关联，不自动等价于强因果证据。
- `support_strength=high` 当前更多反映 strict alias/local graph provenance，不应在最终写作中直接解释成“强生物学因果”。
- 如果 final answer 不区分 association、activation、hypomethylation、insertional mutagenesis，会造成证据过度强化。

## Graph Analytics Plugin

文件：`GraphAnalyticsPlugin.php`

职责：

- 执行固定模板的图谱统计、排名和拓扑聚合。
- 例如关系类型分布、某 TE 连接最多的目标、疾病按 TE 数量排名。

数据源：

- Neo4j runtime。

输入依赖：

- `question`
- `analysis.normalized_entities`
- `analysis.requested_target_types`
- `analysis.asks_for_graph_analytics`

主要输出：

- `results.analytics_result.query_class`
- `results.analytics_result.top_k`
- `results.analytics_result.generated_cypher`
- `results.graph_elements`
- `result_counts.top_k`

适用场景：

- “哪个疾病与 TE 关联最强？”
- “关系类型分布是什么？”
- “L1HS 连接最多的疾病/基因是什么？”

已知风险：

- 它是模板型 analytics，不是自由探索。
- 排名指标是当前查询定义下的图谱计数，不一定代表生物学强度。
- 对复杂 schema 问题可能需要 Cypher Explorer。

## Cypher Explorer Plugin

文件：`CypherExplorerPlugin.php`

职责：

- 当固定图谱插件不够用时，生成并执行只读 Cypher。
- 有少量 heuristic query；否则调用 LLM 生成 JSON，里面包含 `generated_cypher` 和 `params`。

数据源：

- LLM：生成 Cypher。
- Neo4j：执行只读查询。

输入依赖：

- `question`
- `analysis`
- `planning`
- `config.deepseek_model`

主要输出：

- `results.cypher_result.generated_cypher`
- `results.cypher_result.validated_features`
- `results.cypher_result.rows`
- `results.cypher_result.column_schema`

适用场景：

- 固定 Graph/Graph Analytics 模板覆盖不了的聚合或路径问题。
- 需要临时探索 schema、路径、复杂聚合。

已知风险：

- 依赖 LLM 生成质量。
- 只允许 read-only 子集；`CREATE/MERGE/SET/DELETE/DETACH/DROP/CALL dbms/CALL apoc` 等应被阻断。
- 错误结果常见来源是 schema 假设错误或生成 Cypher 不能通过校验。

## Literature Plugin

文件：`LiteraturePlugin.php`

职责：

- 汇总本地图谱已有文献引用。
- 需要时调用 NCBI E-utilities 搜 PubMed。
- 将 local graph citations 与 PubMed citations merge/normalize。

数据源：

- Graph Plugin 上游结果里的 `citations`。
- Neo4j `Paper` 节点。
- PubMed `esearch`、`esummary`、`efetch`。
- PubMed cache：`data/cache/agent/pubmed/{md5(term)}.json`。

输入依赖：

- `question`
- `analysis.normalized_entities`
- `analysis.question_keywords`
- `analysis.needs_external_literature`
- `analysis.asks_for_papers`
- `analysis.compare_mode`
- `plugin_results.Graph Plugin`

主要输出：

- `results.query_terms`
- `results.local_citation_count`
- `results.pubmed_total_hits`
- `results.reviewed_citation_count`
- `results.citations`
- `result_counts.reviewed`
- `result_counts.pubmed_candidates`

适用场景：

- 明确要求 papers、literature、evidence、citations。
- 机制综述、证据审计、研究报告。
- 本地图谱证据不足，需要 PubMed 补充。

已知风险：

- PubMed query term 当前由实体和关键词拼接，容易带入弱相关或无关文献。
- 插件本身主要拿 title/metadata/abstract summary，不做深度阅读全文。
- `reviewed` 不等于人工级相关性审查。
- 对 L1HS 报告曾出现 murine GPCR false positive，这类应在后续过滤或 Reading 阶段剔除。

## Literature Reading Plugin

文件：`LiteratureReadingPlugin.php`

职责：

- 读取 Literature Plugin 的 citations。
- 选取前 12 条 citation，调用 LLM 合成为 claim clusters、supported claims、conflicts、missing evidence。
- 如果 LLM 不返回 JSON，会用标题/摘要级 fallback 聚类。

数据源：

- `plugin_results.Literature Plugin.citations`
- LLM JSON synthesis。

输入依赖：

- `question`
- `analysis.intent`
- `analysis.answer_language`
- `config.deepseek_model`

主要输出：

- `results.claim_clusters`
- `results.citation_groups`
- `results.supported_claims`
- `results.conflicting_claims`
- `results.missing_evidence`
- `result_counts.claim_clusters`

适用场景：

- 文献较多，需要从 citation list 变成可写作的 claim map。
- 机制综述、证据审计、研究报告。

已知风险：

- 它不真正下载全文，通常基于 title、metadata、abstract summary。
- 如果 citation 本身含 false positive，Reading 可能把弱相关文献纳入 claim cluster。
- LLM synthesis 可能把“文献题名”过度解释成事实，需要 integrity gate 或后续过滤约束。

## Tree Plugin

文件：`TreePlugin.php`

职责：

- 提供 TE 分类路径或疾病顶层分类。
- 当用户问分类、家族、谱系、tree、taxonomy 时使用。

数据源：

- `taxonomy_lib.php`
- runtime taxonomy items
- `data/processed/disease_top_class_map.json`

输入依赖：

- `analysis.normalized_entities`
- `analysis.asks_for_classification`

主要输出：

- `results.kind=te_path`
- `results.kind=disease_top_class`
- `result_counts.paths`

适用场景：

- “L1HS 属于哪个 family/subfamily？”
- “某疾病属于哪个疾病大类？”
- 研究报告中补充分类背景。

已知风险：

- 它提供 lineage/context，不提供机制或因果证据。
- taxonomy runtime truth source 不应新增第二套来源；需遵守项目硬约束。

## Expression Plugin

文件：`ExpressionPlugin.php`

职责：

- 查询 TE 表达详情 bundle。
- 汇总每个 dataset 的 top expression context，例如组织、细胞系、癌症细胞系等。

数据源：

- `tekg_expression_fetch_detail_bundle()`
- expression runtime / MySQL。

输入依赖：

- `analysis.normalized_entities`
- 如果没有 TE entity，会尝试从 `plugin_results.Graph Plugin.results.rows[].source_name` 补 TE 名。

主要输出：

- `results[].te_name`
- `results[].datasets[].dataset_key`
- `results[].datasets[].top_context`
- `results[].datasets[].median_of_median`
- `results[].datasets[].max_of_max`
- `result_counts.profiles`
- `errors`

适用场景：

- 用户问 expression、tissue、cell line、transcriptome。
- 研究报告中补充表达上下文。

已知风险：

- 如果 MySQL 或 expression runtime 不可用，会返回 `partial` 或 `empty`。
- 连接错误应在最终报告中说成“系统未能取得表达数据”，不能写成“该 TE 没有表达”。
- 当前插件只取 top contexts，不能替代表达页面完整图表。

## Genome Plugin

文件：`GenomePlugin.php`

职责：

- 读取 TE genomic hit bundle。
- 返回代表性 locus、total hits、sample hits 和 JBrowse URL。

数据源：

- `tekg_jbrowse_fs_path('repeats/te_hits/{TE}.json')`
- JBrowse URL 构造函数 `site_url_with_state()`

输入依赖：

- `analysis.normalized_entities` 中的 TE entity。

主要输出：

- `results[].te_name`
- `results[].total_hits`
- `results[].representative_locus`
- `results[].sample_hits`
- `results[].jbrowse_url`
- `result_counts.loci`

适用场景：

- 基因组位置、locus、chromosome、JBrowse、genomic context。
- 研究报告中回答 “genomic location”。

已知风险：

- `representative_locus` 是代表性位点，不是所有 L1HS copy 的完整坐标列表。
- 最终写作必须区分 “代表性 locus” 与 “全部 genomic locations”。
- 如果 Genome Plugin 已返回 locus，Writing 阶段不能再写 “No genomic coordinates were provided”。

## Sequence Plugin

文件：`SequencePlugin.php`

职责：

- 将 TE entity / alias 匹配到 Repbase-backed sequence records。
- 返回 consensus length、sequence preview/full sequence、keywords、structure hints 和 Repbase references。

数据源：

- `data/processed/te_repbase_db_matched.json`
- 字段包括 `db_to_repbase`、`entries`、`sequence`、`sequence_summary`、`keywords`、`references`。

输入依赖：

- `analysis.alias_chains`
- `question` 中是否要求 full sequence。

主要输出：

- `results.matched_records`
- `display_details.full_sequences`
- `result_counts.matched_records`
- `result_counts.strict_matches`
- `result_counts.broad_matches`
- `citations`：Repbase record references。

适用场景：

- sequence、consensus、length、ORF、structure、annotation。
- 研究报告中的 sequence 维度。

已知风险：

- `structure_hints()` 是关键词抽取，不是严格分类器。
- 如果关键词中同时出现 `LTR` 和 `LINE`，最终写作不能说 “L1HS belongs to LTR and LINE families”；应回到原始 record 判断，或只说 “record contains structure hints/keywords”。
- full sequence 只有用户明确要求完整序列时才应全文输出，避免报告过长。

## Citation Resolver

文件：`CitationResolverPlugin.php`

职责：

- 收集所有上游插件的 `citations`。
- 统一 normalize、deduplicate、summarize。
- 为最终报告和前端 reference UI 提供稳定引用表。

数据源：

- `plugin_results[*].citations`
- `TekgAgentCitationResolver`

输入依赖：

- `plugin_results`

主要输出：

- `results.citations`
- `result_counts.total`
- `result_counts.pmid`
- `result_counts.title_only`

适用场景：

- Graph/Literature/Sequence 等插件已产生 citations 后。
- Writing 前统一引用。

已知风险：

- 它只规范化引用，不判断 citation 是否真正支持某个 claim。
- false positive citation 如果上游没有剔除，这里仍会被格式化。
- 最终报告还需要 claim-citation 对齐检查。

## 插件之间的典型执行链

### 简单导航

```text
Entity Resolver -> Site Navigator Plugin
```

适合页面入口问题。不能用来回答科学数据。

### 简单序列

```text
Entity Resolver -> Sequence Plugin
```

适合直接查询 consensus length、sequence、structure hints。

### 机制/疾病证据

```text
Entity Resolver -> Graph Plugin -> Literature Plugin -> Literature Reading Plugin -> Citation Resolver
```

先取本地图谱关系，再补文献，再做文献 claim 聚类。

### 多维研究报告

```text
Entity Resolver
-> Literature Plugin
-> Literature Reading Plugin
-> Graph Plugin
-> Expression Plugin
-> Genome Plugin
-> Sequence Plugin
-> Citation Resolver
```

当前路由会把 planning 中的多维 evidence 需求并入执行队列，并要求 research synthesis 在必需插件跑完前不能提前 sufficient。

## 与六阶段 LLM 的关系

插件不是六阶段 LLM 节点。插件负责取数据；六阶段 LLM 负责理解、规划、收集决策、执行审查、整合和写作。

每个插件执行后，Agent 当前会调用 `ExecutingReview` LLM 审查该插件输出。这会提高可解释性，但会增加耗时和失败点。插件本身可能很快，慢点常常在插件后的 LLM review。

## 当前需要重点改进的插件问题

1. Literature Plugin 需要更强的 query 和 false-positive filtering，避免无关 PubMed 结果进入 evidence。
2. Literature Reading Plugin 需要明确区分 title-level、abstract-level、local graph-level evidence。
3. Graph Plugin 的 `high` support 不应被最终写作解释成强因果证据。
4. Expression Plugin 失败时，最终报告必须区分“系统未取到数据”和“生物学上没有表达”。
5. Genome Plugin 的 representative locus 必须被 Writing 阶段正确使用，且不能被误写成“没有坐标”。
6. Sequence Plugin 的 structure hints 需要更精确，避免 `LTR/LINE` 关键词被写作模型误读为分类结论。
7. Citation Resolver 应配合后续 claim-citation audit，不应单独承担“证据支持性判断”。
