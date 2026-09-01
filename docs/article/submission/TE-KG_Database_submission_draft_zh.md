# TE-KG：具备来源追溯能力的人类转座元件知识图谱与多层次资源

作者姓名待补充  
院系和单位待补充  
通讯作者信息待补充

中文审阅稿 v0.1，2026年8月1日

==*中文审阅稿 - 尚未补齐的作者与来源信息以红色显示*==

# 摘要

人类转座元件的信息分散在重复序列分类、参考序列、基因组注释、表达数据集和生物医学文献中。我们构建了 TE-KG，以人类 TE 为中心连接这些互补数据。当前知识图谱包含 225 个 TE 节点、2,308 个论文节点和 12,444 条有向生物学关系，每条关系均保留谓词和 PubMed 来源信息。表达数据包括正常组织、正常原代细胞和癌细胞系三类情境，共 1,158 个样本；特定情境下的共表达网络覆盖 285 个 TE 和 499 个基因。TE-KG 在同一网络资源中提供浏览、知识图谱与路径探索、表达可视化、数据下载和自然语言检索功能，为人类 TE 的分类、基因组、表达和文献证据提供可追溯的访问入口。

**数据库网址：** https://bis.zju.edu.cn/tekg/

**关键词：** 转座元件；知识图谱；生物医学文献；表达；共表达；数据库

# 引言

转座元件（transposable element，TE）是人类基因组的重要组成部分，相关研究涉及多种相互补充的数据类型。重复序列家族资源描述 TE 分类和共识序列，基因组注释记录其基因组位置，表达数据集量化不同生物学情境中的转录水平，生物医学研究则报道 TE 与其他实体之间的关系。这些来源共同提供了理解人类 TE 生物学的互补视角。

现有专业资源在特定证据类型上具有较大深度。RepBase 提供重复序列家族分类和共识序列 (1,2)。HERVd、dbRIP、euL1db 和 TranspoGene 提供内源性逆转录病毒、逆转录转座子插入和 TE 相关基因组注释等专业信息 (3–6)。HervD Atlas 整理 HERV 与疾病的关联并通过交互式知识图谱提供访问，CancerHERVdb 聚焦癌症中的 HERV 表达，TE-SCALE 则提供人类癌症单细胞水平的 TE 表达和 TE-基因共表达信息 (7–9)。这些资源共同体现了专业数据库所覆盖的 TE 数据类型之广泛。

尽管相关数据类型丰富，同一 TE 的信息仍然分散在不同资源中。研究者在分类与序列记录、基因组位置、表达谱、共表达网络和支持文献之间切换时，需要反复核对名称。通过共享的 TE 标识符连接这些数据层，可以使跨层检索更加直接，并便于访问相应的原始证据。

本文介绍 TE-KG，一个以人类 TE 为中心的资源，用于整合文献提取的生物医学关系、分类信息、代表性序列与基因组记录、表达谱以及特定情境下的共表达网络。TE-KG 提供浏览、知识图谱与路径探索、表达可视化、数据下载和自然语言访问。

# 材料与方法

## 文献收集、信息提取与标准化

PubMed 检索使用了以下 MeSH 词和自由词组合：`("DNA Transposable Elements"[MeSH] OR "retrotransposon" OR "transposon" OR "retrotransposons" OR "transposons" OR "Retrotransposition" OR "transposition") AND ("humans"[MeSH Terms] OR "human" OR "homo sapiens")`。检索共返回截至 2026 年 4 月 13 日的 34,788 篇文献。筛选白名单由 RMSK 注释的人类 TE 名称以及 RepBase 中的人类 TE 标识符和 KW 行文本共同构成。首轮标题与摘要筛选保留了 5,362 篇文献。随后对语言模型输出进行第二轮白名单检查，最终形成包含 2,308 篇论文的语料库。

保留的文本和元数据通过带约束的 DeepSeek-V3 提示词处理，用于识别以 TE 为中心的生物医学陈述及其参与实体。提取实体被映射到 11 类：碳水化合物、疾病、功能、基因、脂质、突变、肽、药物、蛋白质、RNA 和毒素。关系被划分为因果、调控、互作、关联和遗传信息流五类。每条关系记录保留标准化谓词、一个或多个 PubMed 标识符（PMID）及支持 PMID 的数量。实体名称采用 80% 的 Ratcliff-Obershelp 相似度阈值匹配；自动解析不足时，再通过人工整理建立同义词到规范名称的映射。

三轮人工审核分别检查了首轮排除、二次排除和图谱整合阶段标记的记录，每轮包含 50 条记录，对应的正确决定率分别为 100%、94% 和 96%。

## TE 分类、序列与基因组记录

TE 分类主要依据 RepBase 注释，其以 EMBL 格式获取自 Homo sapiens and ancestral (shared) repeats 集合 (1,2)。对于 RepBase 未收录或分类不完整的 TE，以 RMSK 分类作为补充参考。RepBase 衍生的本地来源还提供 TE 标识符及共识或参考序列，基因组位置则来自 RMSK 注释。这些序列和位置记录分别代表参考模型和代表性基因组位点，而非活跃、具有多态性或样本特异性插入的完整清单。

分类体系由 Neo4j 支持的 API 提供。RMSK + RepBase 视图展示依据这两个来源完成分类的 TE 层级。All 视图保留该层级，并加入未被 RMSK 或 RepBase 覆盖的 TE 名称，其中包括知识图谱中的部分非标准名称。

## 表达数据集

表达层包含三个当前使用的矩阵。正常组织矩阵来自 E-MTAB-1733 和 E-MTAB-2836，含 37,868 个特征和 205 个样本；正常原代细胞矩阵来自 SRP013565，含 37,868 个特征和 307 个样本；癌细胞系矩阵来自 PRJNA523380，含 37,868 个特征和 646 个样本。三个矩阵共计 1,158 个样本。

## 特定情境下的共表达网络

共表达分别在正常组织、正常原代细胞和癌细胞系情境中计算。丰度值按 log2(count + 1) 转换，TE-基因关联使用 Spearman 秩相关衡量。候选边在 \|r\| ≥ 0.4 且 Benjamini-Hochberg 错误发现率（FDR）不高于 0.05 时保留 (13)。保留的正相关边用于 Louvain 社区发现，随机种子为 42，分辨率为 1.8 (14)。

完整网络输出用于数据发布和数据库导入。为支持交互式查看，每个网络视图最多展示 50 个节点和 150 条边。

## 图数据库、关系型存储与网页应用

知识图谱存储在 Neo4j 中，MySQL 则存储 Browse 目录、表达矩阵和共表达数据集。PHP 应用程序接口将这些数据库连接到网页应用，浏览器端组件负责渲染表格、表达图表、分类视图以及交互式知识图谱和共表达网络。

## Agent 与 DeepThink

自然语言界面包含两种基于数据库证据的检索流程。DeepThink 采用“理解、规划、执行和写作”四个阶段处理直接问题；Agent 在此基础上增加证据收集与整合，形成适用于跨多个数据层问题的六阶段流程。两种流程都会解析问题中的实体，选择相应的 TE-KG 检索插件，并依据返回的数据库记录生成回答。追问上下文仅在当前浏览器会话中保留，检索到的文献则以 PubMed 链接呈现在最终回答中。

# 结果

## 具有明确来源信息的文献关联人类 TE 图谱

当前 Neo4j 快照包含 225 个 TE 节点、2,308 个 Paper 节点和 12,444 条连接 TE 与生物医学实体的有向 `BIO_RELATION` 关系。每条生物学关系均具有标准化谓词、至少一个 PMID 和支持 PMID 计数，用户可以据此查看与图中关系相关的文献。

生物医学模式包含 11 类实体。目前数量较多的标签包括 Function（3,683 个节点）、Gene（1,280）、Protein（1,089）、Disease（676）、RNA（588）、Mutation（377）和 Pharmaceutical（293）。数量较少的类别包括 Toxin（67）、Lipid（26）、Peptide（23）和 Carbohydrate（12）。DiseaseCategory（767）、Paper（2,308）及分类相关节点单独表示，因为它们承担组织或来源追踪作用，而不属于上述 11 类生物医学实体。

表 1. 当前 TE-KG 内容快照。

| **组成部分**       | **数量**          | **记录类型**                                                           | **数据来源**       |
|--------------------|-------------------|------------------------------------------------------------------------|--------------------|
| TE 节点            | 225               | 知识图谱中的 TE 实体                                                   | Neo4j              |
| Paper 节点         | 2,308             | 知识图谱中表示的论文                                                   | Neo4j              |
| 有向生物学关系     | 12,444            | 已存储的 `BIO_RELATION` 关系                                           | Neo4j              |
| 生物医学实体类别   | 11                | 文献提取实体所使用的标准化类别                                         | Neo4j              |
| Browse 目录条目    | 276               | 版本化 TE 目录记录                                                     | MySQL Browse 目录  |
| 已分类 TE          | 225 个中的 192 个 | 已分配分类类别的 TE 节点                                               | 分类 API           |
| 表达样本           | 1,158             | 205 个正常组织、307 个正常原代细胞和 646 个癌细胞系样本                | 三个表达矩阵       |
| 可检索共表达条目   | 784               | 285 个 TE 和 499 个基因条目                                            | 共表达展示目录     |
| 下载文件           | 10                | 6 个表达文件、2 个图文件和 2 个分类文件                                | Download 文件集合  |

## 分类视图

分类摘要包含 225 个 TE 节点，其中 192 个已分配分类类别，188 个属于标准叶节点。在这 225 个 TE 节点中，189 个归入 RMSK + RepBase 分类来源，另有 36 个归入补充的 All 来源。All 视图会合并这两组记录。

分类界面将同一套 API 分类数据表示为层级树或力导向图。交互式图例可以隐藏或恢复 TE 类别，且不改变底层结果集。

## 表达与共表达增加三类生物学情境

三个表达数据集分别支持在正常组织、正常原代细胞和癌细胞系情境中查看 TE 与基因的丰度谱。共表达目录覆盖相同的三类情境，并为 285 个 TE 和 499 个基因提供可检索网络；其中展示的边均满足材料与方法中定义的相关系数和 FDR 阈值。

## 相互连接的界面提供互补证据入口

TE-KG 针对不同问题提供多个访问入口。Browse 整合了 TE 目录筛选、内置搜索以及查看所选 TE 结构化记录的功能；Knowledge Graph 展示文献关联的生物医学关系并提供 PubMed 证据；Path 搜索所选实体之间的连接；Expression 展示特定情境下的丰度谱；Co-expression 展示 TE-基因相关邻域；Download 提供表 1 汇总的发布文件。

![alt text](<../../about/figs/TE-KG Data Architecture and Public Services.svg>)

这些相互连接的视图使用户能够沿着选定的 TE 查看其分类、基因组记录、表达谱、共表达邻域和支持文献。

# 讨论

TE-KG 以共享的 TE 标识符组织异构记录，减少了研究者在不同专业数据来源之间反复切换和核对名称的负担。其主要贡献是提供从一个 TE 记录进入互补数据，并返回文献提取关系所依据论文的连贯路径。

这种定位与专业资源形成互补。RepBase 提供更深入的重复序列家族参考记录 (1,2)。dbRIP 和 euL1db 以 TE-KG 目前无法提供的分辨率描述逆转录转座子插入记录 (4,5)。HervD Atlas 提供经人工整理的 HERV-疾病关联，TE-SCALE 则提供超越本文批量数据集的癌症单细胞表达和共表达信息 (7,9)。TE-KG 主要适用于横跨多种证据类型的问题。

谓词和 PMID 使文献提取的图关系可以被检查，并允许用户返回支持该关系的论文。当前语料库经过自动筛选、受约束的语言模型提取、标准化和人工整理等步骤生成，因此检索、缩写解析、实体标准化和关系提取仍可能产生错误。后续需要通过保留样本标识符的可复现审核量化提取准确率。

TE 表达估计对重复序列特征和定量方法较为敏感 (15)。三类表达情境来自不同研究，因此分别作为独立数据集合进行分析，而不是配对的正常与癌症实验。共表达网络概括达到阈值的两两相关，不能据此建立调控关系。网页界面展示的是完整离线网络中选定的邻域。

自然语言界面为检索 TE-KG 中的多类记录提供了便捷入口，并支持当前会话内的追问。回答质量取决于实体解析、证据检索和语言模型综合，因此可能存在信息不完整的情况。PubMed 链接和相应数据库视图使用户可以直接检查检索到的证据。

# 结论

TE-KG 为研究人类 TE 的分类、基因组、表达和文献证据提供了统一且可追溯的资源。数据库通过共享的 TE 标识符连接不同记录，并保留指向原始来源的链接，从而同时支持针对性检索和跨证据层探索。

# 数据可用性

TE-KG 通过网页界面提供图、分类和表达数据文件。==*\[待补说明：使用稳定的公开数据库网址、版本化仓库与归档标识符、文件级许可证、发布版本、更新策略和“登录号到样本”清单替换本工作声明。最终资源必须免费开放，且无需登录或注册。\]*==

# 补充数据

==*\[待补说明：提供表格计划中所述的冻结来源表、查询文件、数据清单、文献审核记录、共表达敏感性分析输出和详细验证结果。\]*==

# 经费支持

==*\[待补说明：需要作者提供，包括资助机构名称、项目编号和接受资助的作者姓名缩写。\]*==

# 致谢

==*\[待补说明：需要作者提供。\]*==

# 利益冲突

作者声明无利益冲突。

# 参考文献

1\. Bao, W., Kojima, K.K. and Kohany, O. (2015) Repbase update, a database of repetitive elements in eukaryotic genomes. *Mobile DNA*, **6**, 11. [<u>doi:10.1186/s13100-015-0041-9</u>](https://doi.org/10.1186/s13100-015-0041-9)

2\. Kojima, K.K. (2018) Human transposable elements in repbase: Genomic footprints from fish to humans. *Mobile DNA*, **9**, 2. [<u>doi:10.1186/s13100-017-0107-y</u>](https://doi.org/10.1186/s13100-017-0107-y)

3\. Pačes, J., Pavlíček, A. and Pačes, V. (2002) HERVd: Database of human endogenous retroviruses. *Nucleic Acids Research*, **30**, 205–206. [<u>doi:10.1093/nar/30.1.205</u>](https://doi.org/10.1093/nar/30.1.205)

4\. Wang, J., Song, L., Grover, D., et al. (2006) dbRIP: A highly integrated database of retrotransposon insertion polymorphisms in humans. *Human Mutation*, **27**, 323–329. [<u>doi:10.1002/humu.20307</u>](https://doi.org/10.1002/humu.20307)

5\. Mir, A.A., Philippe, C. and Cristofari, G. (2015) euL1db: The european database of L1HS retrotransposon insertions in humans. *Nucleic Acids Research*, **43**, D43–D47. [<u>doi:10.1093/nar/gku1043</u>](https://doi.org/10.1093/nar/gku1043)

6\. Levy, A., Sela, N. and Ast, G. (2008) TranspoGene and microTranspoGene: Transposed elements influence on the transcriptome of seven vertebrates and invertebrates. *Nucleic Acids Research*, **36**, D47–D52. [<u>doi:10.1093/nar/gkm949</u>](https://doi.org/10.1093/nar/gkm949)

7\. Li, C., Qian, Q., Yan, C., et al. (2024) HervD Atlas: A curated knowledgebase of associations between human endogenous retroviruses and diseases. *Nucleic Acids Research*, **52**, D1315–D1326. [<u>doi:10.1093/nar/gkad904</u>](https://doi.org/10.1093/nar/gkad904)

8\. Stricker, E., Peckham-Gregory, E.C. and Scheurer, M.E. (2023) CancerHERVdb: Human endogenous retrovirus (HERV) expression database for human cancer accelerates studies of the retrovirome and predictions for HERV-based therapies. *Journal of Virology*, **97**, e00059–23. [<u>doi:10.1128/jvi.00059-23</u>](https://doi.org/10.1128/jvi.00059-23)

9\. Meng, X., Nie, Z., Wang, Q., et al. (2026) TE-SCALE: A comprehensive database for exploring transposable element expression across human cancers at single-cell resolution. *Nucleic Acids Research*, **54**, D1658–D1671. [<u>doi:10.1093/nar/gkaf1235</u>](https://doi.org/10.1093/nar/gkaf1235)

10\. Edqvist, P.-H.D., Fagerberg, L., Hallström, B.M., et al. (2015) Expression of human skin-specific genes defined by transcriptomics and antibody-based profiling. *Journal of Histochemistry and Cytochemistry*, **63**, 129–141. [<u>doi:10.1369/0022155414562646</u>](https://doi.org/10.1369/0022155414562646)

11\. Uhlén, M., Fagerberg, L., Hallström, B.M., et al. (2015) Tissue-based map of the human proteome. *Science*, **347**, 1260419. [<u>doi:10.1126/science.1260419</u>](https://doi.org/10.1126/science.1260419)

12\. Ghandi, M., Huang, F.W., Jané-Valbuena, J., et al. (2019) Next-generation characterization of the cancer cell line encyclopedia. *Nature*, **569**, 503–508. [<u>doi:10.1038/s41586-019-1186-3</u>](https://doi.org/10.1038/s41586-019-1186-3)

13\. Benjamini, Y. and Hochberg, Y. (1995) Controlling the false discovery rate: A practical and powerful approach to multiple testing. *Journal of the Royal Statistical Society: Series B*, **57**, 289–300. [<u>doi:10.1111/j.2517-6161.1995.tb02031.x</u>](https://doi.org/10.1111/j.2517-6161.1995.tb02031.x)

14\. Blondel, V.D., Guillaume, J.-L., Lambiotte, R., et al. (2008) Fast unfolding of communities in large networks. *Journal of Statistical Mechanics: Theory and Experiment*, **2008**, P10008. [<u>doi:10.1088/1742-5468/2008/10/P10008</u>](https://doi.org/10.1088/1742-5468/2008/10/P10008)

15\. Lanciano, S. and Cristofari, G. (2020) Measuring and interpreting transposable element expression. *Nature Reviews Genetics*, **21**, 721–736. [<u>doi:10.1038/s41576-020-0251-y</u>](https://doi.org/10.1038/s41576-020-0251-y)
