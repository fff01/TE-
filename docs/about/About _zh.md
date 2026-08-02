# TE-KG About 帮助文档

本文档是 About 公共页面的中文编辑稿，既记录面向用户的文字，也记录后续静态图和动态图的具体制作要求。图片说明只规定必须展示的内容，不限定截图或录制工具。由于网站界面为英文，图中标注建议继续使用下文给出的英文文字。

## 1. 关于 TE-KG

TE-KG 是一个面向人类转座元件的综合资源，将分类体系、文献提取的关系、代表性序列和基因组记录、表达与共表达环境、可下载数据，以及受证据约束的自然语言问答连接在一起。

### TE-KG 是什么

- TE-KG 将人类转座元件的多种互补视图汇集到同一界面，而不是把每种数据当作孤立表格。
- 该资源包括 TE 目录、实体详情页、基于文献的关系图、分类视图、表达与共表达探索，以及可下载数据。
- 代表性序列和基因组记录描述的是当前支持的参考或注释记录，不代表某个 TE 在基因组中的每一个拷贝。
- TE-KG 的核心目标是在便于探索 TE 信息的同时，让来源证据与解释边界保持可见。

### 本指南包括什么

- 说明每个公共页面适合回答什么问题。
- 说明各页面的主要控件及推荐操作顺序。
- 区分 TE 检索、实体详情、图谱与路径探索、表达与共表达分析、自然语言问答和数据下载等工作流。
- 说明主要证据边界，帮助用户区分数据库观察结果与生物学结论。

### 数据访问入口

- 使用 Home 查看数据库当前的整体组成。
- 使用 Browse 浏览 TE 目录，再通过 Search 查看选中实体的详细记录。
- 使用 Graph 探索知识关系、TE 分类和共表达网络；使用 Path Finder 查看两个指定实体之间的连接。
- 使用 Expression 查看当前表达数据集中的丰度模式。
- 使用 Agent 或 DeepThink 提出有证据支撑的自然语言问题，并在当前对话中继续追问。
- 使用 Download 获取网站当前提供的文件。

### 证据解释原则

- 在有支持论文时，应根据论文核对关系层面的陈述；观察到关联并不等于建立因果关系。
- 图路径表示数据库中存储的连接，不必然等同于生物通路或作用机制。
- 共表达表示特定环境下的相关性，本身不能证明调控关系。
- PMID、标题、年份、期刊、IF 和 JCR 是描述性证据元数据，不能互相替代为置信度；缺失值应保持缺失。
- TE 目录、知识图谱、分类体系和共表达目录采用不同统计单位，不能把各页面数量当作同一总体直接比较。

**静态图说明：`about-resource-overview.png`**

制作一张清晰的功能路线图，以 `TE-KG` 为中心，周围依次放置 `Browse`、`Search`、`Path Finder`、`Graph`、`Expression`、`Agent & DeepThink`、`Download` 和 `About`。从 Browse 指向 Search；从 Agent & DeepThink 分别指向 Graph、Path Finder、Expression 和 Download，用于表示回答后的核验入口。图中使用以下英文标注：

- `Scan and filter the TE catalog`：指向 Browse。
- `Review a selected entity in detail`：指向 Search。
- `Explore knowledge, classification, and co-expression networks`：指向 Graph。
- `Ask evidence-grounded questions, then verify the sources`：指向 Agent & DeepThink。
- `Download the files currently exposed through the site`：指向 Download。

图片底部增加一句：`Associations, paths, expression, and co-expression represent different evidence types.`

## 2. Home 概览

Home 是 TE-KG 的总览入口，用于介绍资源、概括当前数据库组成，并提供进入主要公共工作流的快捷路径。

### 页面包含的内容

- Overview 区域概述 TE-KG 的目的和范围。
- Dataset Status 从当前知识图谱读取实时只读统计，而不是在页面中写入固定数量。
- 环形图分别展示实体组成、TE 分类和关系谓词组成。
- Quick Links 可直接进入主要的检索、图谱、表达、下载和帮助工作流。

### 如何阅读 Dataset Status

- Entity Composition 按存储节点统计主要知识图谱实体类别，每个节点只计一次。
- TE Classification 可以切换分类层级，因此图表能够从大类切换到更具体的分类层级。
- Relation Composition 使用详细谓词统计，能够显示常见关系类型，而不是把它们压缩为模糊标签。
- 实时统计加载失败时，页面会显示降级状态，不会猜测或编造数值。

### 推荐工作流

- 想快速了解数据库当前内容时，可以从 Home 开始。
- 想浏览 TE 记录时进入 Browse；想查看实体详情时进入 Search；想查看指定连接时进入 Path Finder。
- 想进行知识关系、分类或共表达的可视化探索时进入 Graph；更适合用自然语言描述的问题可进入 Agent。

**静态图说明：`about-home-overview.png`**

在桌面宽度下截取 Home，需同时显示页面简介、完整 Dataset Status 图表和 Quick Links。使用编号标记和以下英文说明：

- `Read the resource scope before choosing a workflow`：标注 Overview 文字。
- `Check the current knowledge-graph composition`：标注 Dataset Status。
- `Change the classification level shown in this chart`：标注 TE Classification 的层级控件。
- `Compare detailed relationship predicates`：标注 Relation Composition。
- `Open the workflow that matches your question`：标注 Quick Links。

标注不得暗示不同图表的扇区使用相同分母。

## 3. Browse

Browse 是以表格为主的 TE 目录，适合先浏览和筛选 TE 记录，再把选中的 TE 打开到 Search 详情页。

### 页面用途

- 当用户希望查看目录表格而不是直接进入图谱时使用 Browse。
- 表格并列展示 TE 名称、class、family、subtype 和 description。
- 目录支持分页，便于浏览较大的结果集。
- 确定目标 TE 后，点击表格中的 TE 名称进入 Search 详情页。

### 使用筛选器

- 使用 class、family 和 subtype 控件按 TE 分类缩小目录范围。
- 在搜索框输入 TE 名称或前缀，并在有候选项时选择数据库提供的建议。
- 可以组合文字搜索和分类筛选，以缩小较大的结果集。
- 如果没有任何记录符合当前组合条件，清除一个或多个筛选项后重试。

### 数据解释

- Browse 是 TE 目录，因此它的行数不等于其他页面中的 TE 节点数、分类叶节点数或共表达特征数。
- Class、family 和 subtype 表示每条记录对应的目录分类层级。
- Browse 主要用于发现和比较；需要详情或关系证据时，应进入 Search、Graph 或 Path Finder。

**动态图说明：`about-browse-selector.gif`**

录制 10-12 秒的桌面操作。开始时显示未筛选的 Browse 表格；打开 Class 筛选并选择 `Retrotransposons`，再在 Family 中选择 `Non LTR Retrotransposons (LINEs)`，随后在 TE 搜索框输入 `L1HS` 并选择数据库候选项，展示更新后的结果数量。点击 L1HS 行，在 Search 详情页已经清楚加载后结束。录制中保留鼠标指针，每次结果更新后停留约一秒。该动态图必须体现“从目录发现到实体详情”的交接，不得暗示 Browse 可以检索疾病或功能实体。

## 4. Search 与实体详情

Search 用于打开 TE 或其他受支持实体的详细记录。根据选中的实体，页面可能显示 Summary、Local Graph、代表性序列、基因组注释和 Genome Browser。

### 查找记录

- 可以搜索 TE、疾病、功能或 PMID，也可以从 Browse 中的 TE 链接进入。
- 当名称存在多种可能时，选择数据库提供的候选项以明确实体类型。
- Summary 用于确认当前记录，并展示该实体类型可用的元数据。
- 并非所有实体都有相同面板；页面只显示当前记录实际支持的详情视图。

### 阅读 TE 详情

- 使用 Local Graph 查看邻近实体和关系，无需离开详情页。
- Sequence 显示受支持的代表性或共识序列及现有注释，不代表每一个基因组 TE 拷贝。
- Genome Annotation Distribution 汇总当前 assembly 上支持的命中；Genome Browser 用于查看具体基因组位置。
- 对较长记录，可使用页内导航直接跳转到现有面板。

### 解释边界

- 缺少某个面板表示当前资源没有为所选实体提供对应记录。
- 序列和基因组面板可能来自不同参考或注释层，应结合页面显示的标签解释。
- Local Graph 中的关系是数据库支持的关联；把它解释为生物机制前，应继续核对来源证据。

**静态图说明：`about-search-l1hs-detail.png`**

使用 L1HS 的完整页面截图或纵向拼接图，确保 Summary、Local Graph、Sequence、Genome Annotation Distribution 和 Genome Browser 均可辨认。按以下位置使用英文标注：

- `Confirm the entity identity and available metadata`：标注 Summary。
- `Inspect nearby database relationships`：标注 Local Graph。
- `Representative or consensus sequence; not every genomic copy`：标注 Sequence。
- `Review the assembly and total annotation hits`：标注分布图上方的 assembly 与命中统计。
- `Inspect supported loci in genomic context`：标注 Genome Browser。

不要把某个只在部分实体中存在的面板标注为所有实体都会显示。

## 5. Path Finder

Path Finder 搜索两个指定实体之间已存储的连接，并以可核验形式展示每条关系的证据。它适合处理“两个实体如何连接”的问题，而不是单个实体的查询。

### 搜索结构

- 搜索框两侧都包含较窄的类别选择器和较宽的实体选择器。
- 实体下拉内容受已选类别限制。
- 开始搜索前，两端都应选择一个候选实体。
- 这种结构可避免把多个类别混在一个不受控的自动补全列表中。

### 阅读路径结果

- 路径显示连接两个端点的实体与关系顺序。
- 如果有更具体的关系谓词，应按具体谓词理解关系标签。
- 每条关系下方以表格显示证据，而不是只列出 PMID。
- 证据行可能包括 PMID、年份、期刊、IF、JCR、match type 和标题。
- 返回的图路径是一条存储关系链，不会自动成为生物通路或机制模型。

### 核对证据

- 使用 PMID 和标题确认支持论文。
- 期刊、IF 和 JCR 是描述性元数据，不能替代对论文的阅读。
- Match type 用于说明论文与期刊元数据如何匹配。
- 一条路径有多条关系时，应逐条核查，而不是假设整条路径证据强度一致。

**动态图说明：`about-pathfinder-search.gif`**

录制一次完整的双端搜索。左侧选择 `TE` 和 `L1HS`，右侧选择一个当前有结果的目标类别和实体，运行搜索并停留在返回路径。展开或滚动到其中一条关系的证据表，点击一个 PMID 链接，让 PubMed 跳转清晰可见；如果链接覆盖当前页，最后回到证据表。结尾增加小型提示：`A stored graph path is not automatically a biological pathway.`

## 6. Graph 工作区

Graph 提供三种互补的可视化工作流：基于文献的知识关系、Tree 或力导向 Graph 形式的 TE 分类，以及独立的特定环境共表达网络。

### 图谱操作

- 保持选中 Knowledge Graph，搜索实体并加载其可见关系邻域。
- 通过平移、缩放和拖动节点查看密集网络；使用节点操作查看实体选项和元数据。
- 需要核对某条可见边时，打开关系证据。
- 需要当前知识图谱视图的快照或数据表示时，使用现有 Export 功能。

### 图例与筛选

- 实体图例用于区分 TE、疾病、功能、论文及其他可见节点类型。
- 关系图例显示谓词类别，可把密集视图缩小到当前问题相关的关系。
- 点击图例项只会暂时改变当前视图的强调或显示内容，不会修改存储数据。
- 解释筛选后的图谱时，应同时查看当前图例状态。

### 分类 Tree 与 Graph

- 显示 TE 分类时，Tree 提供稳定的层级视图；Graph 提供可交互重排的力导向视图。
- 使用 All 或 RMSK + RepBase 改变两种布局所显示的分类来源范围。
- Tree 和 Graph 是同一分类数据的两种布局，不是两个独立分类来源。
- 力导向 Graph 中节点间距和占用面积反映布局行为及层级规模，不代表生物丰度或常见程度。

### Co-expression 工作区

- 切换到 Co-expression，选择环境和 TE，再搜索一个有限范围的 TE-gene 相关网络。
- 使用图例显示或隐藏 TE 和 Gene 节点、识别 module hub，并选择当前显示的边范围。
- Expression activity 是所选环境中的独立节点层，不编码相关强度或因果性。
- 共表达是环境特异的关联证据，本身不能证明调控、机制，也不等于完整离线网络。

**静态图说明：`about-graph-knowledge.png`**

截取一个已经加载的 Knowledge Graph，画面需要包含工作区切换、搜索框、实体图例、关系图例、一个已选节点和可见的证据入口。使用以下英文标注：

- `Switch between knowledge and co-expression workflows`：标注工作区页签。
- `Search for an entity and load its neighborhood`：标注搜索控件。
- `Click a legend item to change the current view temporarily`：标注图例。
- `Open source evidence for a visible relationship`：标注边的证据入口。
- `Move and filter the view without changing stored data`：标注图画布。

**动态图说明：`about-graph-classification.gif`**

从已显示的分类 Tree 开始。切换为 `Graph`，等待力导向布局稳定，拖动一个 TE 节点并展示周围节点响应；点击一个 class 图例项暂时隐藏该类，再恢复显示；随后从 `RMSK + RepBase` 切换到 `All`，最后回到 Tree。整个过程保持 Tree/Graph 和来源范围控件可见。动态图应让用户理解两种布局使用同一分类数据，并且图中面积不是丰度比例。

**动态图说明：`about-graph-coexpression.gif`**

从 Knowledge Graph 切换到 Co-expression，选择环境和 TE 后运行 Search。网络加载后，关闭再恢复 Gene 节点显示，指向 module-hub 图例，改变边范围，切换 `Expression activity`，最后打开导出菜单或导出 PNG。结束画面必须显示相关性提示，并增加：`Correlation and expression activity do not imply causation.`

## 7. Agent 与 DeepThink

Agent 是自然语言研究界面。Agent 模式通过结构化多阶段流程收集证据；DeepThink 使用更短的、受证据约束的推理流程回答较直接的问题。

### 选择模式

- 需要综合序列、基因组位置、表达、疾病关系和文献等多个数据库区域时，使用 Agent 生成研究报告。
- 问题较直接、较短的推理与写作流程已经足够时，使用 DeepThink。
- 尽量使用清晰的 TE、疾病、基因名称或 PMID；缩写或实体名称有歧义时应先要求澄清。
- 可见阶段轨迹展示请求如何从理解和规划推进到证据收集与写作。

### 提问与追问

- 可以询问 TE 分类、序列、基因组记录、表达、共表达、图关系、疾病、基因或文献证据。
- 首轮回答后，可以直接追问，无需重复完整主题。
- 上下文只在当前浏览器对话中保留；刷新页面或新建会话后不会继续保留。
- 文献证据重要时，点击答案中的 PMID 链接查看对应 PubMed 记录。

### 阅读回答

- 回答是针对当前请求所检索证据的综合，不是独立实验验证。
- 重要关系可在 Graph 或 Path Finder 核对，表达模式在 Expression 核对，可下载内容在 Download 核对。
- 数据库缺少相应证据时，回答可能合理地保持有限；检索结果中缺失不能证明生物学上不存在。
- 期刊指标是描述性元数据，不是置信度；关联表述也不应被读成因果表述。

**动态图说明：`about-agent-follow-up.gif`**

从 Agent 空白页开始，选择 Agent 模式并提问：`Summarize the evidence available for L1HS, including disease links and literature.` 展示阶段轨迹推进，以及包含至少一个可点击 PMID 的完整回答。随后在不重复 L1HS 的情况下追问：`Which of those links has the strongest direct literature support?`，展示系统保留当前对话上下文并正常完成。最后将鼠标指向一个 PMID 链接。动态图不得暴露原始插件名称、内部标记、JSON 或开发者诊断信息。

**静态图说明：`about-agent-modes.png`**

截取同时显示两个模式按钮和简短任务模板的空白页。使用两个英文标注：

- `Use Agent for multi-source research questions`：标注 Agent。
- `Use DeepThink for a shorter evidence-grounded response`：标注 Deep Think。

在输入区下方增加：`Follow-up context lasts only for the current browser conversation.`

## 8. Expression

Expression 是 TE 丰度查询界面，支持目录级筛选，以及 normal tissue、normal cell line 和 cancer cell line 数据集中的 TE 详情视图。

### 查找 TE

- 使用 Keyword 搜索 TE，也可以组合 dataset source、top-context 文字和 minimum global median 筛选表格。
- 使用 Sort 按现有汇总指标排序，再点击 TE 行进入详情页。
- 在对应数据存在时，浏览表会汇总最高的 normal tissue、normal cell line 和 cancer cell line 环境。
- 如果未出现候选或结果，应先确认该 TE 是否存在于当前表达目录，再解释这种缺失。

### 阅读表达视图

- 使用详情 Summary 确认可用数据集和当前选择的 median、mean 或 maximum 指标。
- Normal Tissue、Normal Cell Line 和 Cancer Cell Line 来自不同研究环境，不是配对队列比较。
- 使用图表在当前显示的数据集与指标内部比较不同环境。
- 表达丰度、知识图谱关系和共表达相关性回答的是不同问题，应分别解释。

### 数据说明

- 表达数值应结合当前数据集、指标和预处理环境解释。
- Normal tissue、normal cell line 和 cancer cell line 之间的差异可能同时包含生物学差异与研究设计差异。
- Download 提供网站当前公开的表达矩阵和元数据，便于独立检查。

**静态图说明：`about-expression-detail.png`**

截取一个 TE 的详情页，并通过纵向拼接让 Summary 和三个现有数据集区域都可见。使用以下英文标注：

- `Choose Median, Mean, or Max before comparing values`：标注指标控件。
- `Confirm which datasets are available for this TE`：标注 Summary。
- `Compare contexts within the Normal Tissue dataset`：标注第一张图。
- `Normal Cell Line is a separate study context`：标注第二张图。
- `Cancer Cell Line is not a matched cohort comparison`：标注第三张图。

图片底部增加：`Interpret values within the displayed dataset and preprocessing context.`

## 9. Download

Download 列出 TE-KG 当前提供的数据文件。用户可在下载前比较数据集名称、文件名、网站用途、格式和简短说明。

### 表格结构

- Dataset 使用易于理解的名称标识每个可下载资源。
- File 链接指向当前下载路径。
- Used in 说明当前哪些页面或流程使用该文件。
- Format 标识提供的文件类型。

### 筛选下载项

- 使用 Expression、Graph 或 Taxonomy 等类别按钮缩小表格。
- Search 可以匹配数据集名称、文件名、用途说明、格式和行描述。
- 下载前可展开数据行阅读简短说明。
- 想查看当前完整下载目录时，清除搜索文字或回到 All。

### 目录范围

- 表中列出的文件对应可见 TE-KG 工作流或可供独立复核的数据。
- 内部中间产物和归档工作文件默认不包含在内。
- 文件出现在本页表示它是网站当前提供的下载，不自动代表带版本的长期归档发布。
- 正式发布时，应使用随文件提供的稳定标识符、版本、校验和与许可证信息。

**动态图说明：`about-download-filter.gif`**

从 All 开始，点击 `Expression`，在 Search 中输入一个表达文件名片段，展开匹配行显示说明，再点击文件名链接但不要触发影响录制的完整下载。清除搜索，选择 `Taxonomy`，最后回到 All。全过程保持类别数量以及 Dataset、File、Used in、Format 列可见。结尾增加：`A current site download is not automatically a versioned archival release.`

## 10. About

About 是 TE-KG 公共界面的详细指南，说明每个页面的用途、操作方法以及页面之间的关系。

### 指南结构

- 使用左侧导航切换不同页面的指南。
- 每节说明页面目的、控件、数据解释和重要边界。
- 本指南面向需要判断“从哪里开始、如何核验结果”的用户。
- 正文关注公共界面行为，不介绍内部实现细节。

### 选择合适的工作流

- 使用 Browse 查询 TE 目录，使用 Search 查看选中实体的详细记录。
- 使用 Path Finder 查询两个实体之间的连接。
- 使用 Graph 探索知识关系、TE 分类、共表达和关系证据。
- 使用 Expression 查看当前表达数据集中的 TE 丰度模式。
- 使用 Download 获取网站当前提供的文件。
- 使用 Agent 或 DeepThink 进行自然语言综合，再到相关证据页面核验重要陈述。

### 证据优先的阅读方式

- 对关系层面的陈述，优先使用能够显示来源记录的视图。
- 得出生物学结论前，应先区分关联、图连接、表达丰度和共表达相关性。
- 不要把期刊 IF 理解为置信度，也不要推断缺失的期刊指标。
- 不同页面结果不一致时，先核对各页面显示的实体、数据集、环境、指标和证据类型。

**静态图说明：`about-guide-navigation.png`**

截取 About 页面，画面需包含左侧导航、Search 输入框、一个已选中的一级栏目、对应的二级标题和正文。使用以下英文标注：

- `Search across all guide text`：标注指南搜索框。
- `Choose a page guide`：标注一级导航。
- `Jump to a specific task or interpretation note`：标注二级导航。
- `Read purpose, workflow, and evidence boundaries together`：标注正文区域。

该图只用于解释 About 导航，不重复各功能页面已经使用的标注。
