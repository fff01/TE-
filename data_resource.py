import csv
import time
import json
import logging
import requests
from Bio import Entrez
from concurrent.futures import ThreadPoolExecutor, as_completed
import threading
import os
import re
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

# ==========================================
# 配置参数（请根据实际情况修改）
Entrez.email = "fongisek@gmail.com"          # 替换为有效邮箱
TE_NAMES_FILE = r"C:\Users\fongi\Desktop\TE\data\TE_names.txt"   # 转座子名称列表文件

# PubMed检索：人类转座子
SEARCH_QUERY = '("DNA Transposable Elements"[MeSH] OR "retrotransposon" OR "transposon" OR "retrotransposons" OR "transposons" OR "Retrotransposition" OR "transposition") AND ("humans"[MeSH Terms] OR "human" OR "homo sapiens")'
MAX_RESULTS = 100000                            # 最大处理文献数量，测试时可改小

API_KEY = "sk-be0d751270034a0d86d189729f8e4252"                                # 替换为你的API密钥
API_URL = "https://api.deepseek.com/v1/chat/completions"
OUTPUT_FILE = "te_kg2.jsonl"                     # 输出文件（JSONL格式）
FAILED_LOG = "failed_pmids.txt"                    # 失败记录文件
SKIPPED_LOG = "skipped_pmids.txt"                  # 跳过记录文件（无转座子名称或无实体）
MISSING_LOG = "missing_tes.txt"                     # 漏提记录文件（白名单出现但API未提取）
PROGRESS_LOG = "progress.log"                       # 日志文件

MAX_WORKERS = 25                                   # 并发线程数
NCBI_DELAY = 0.5                                   # 两次NCBI请求之间的最小间隔（秒）
API_DELAY = 0.5                                    # 两次API调用之间的最小间隔（秒）——每个线程内请求后sleep
REQUEST_TIMEOUT = 60                                # API请求超时时间
MAX_RETRIES = 3                                     # 最大重试次数

# 日志配置
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(PROGRESS_LOG, encoding='utf-8'),
        logging.StreamHandler()
    ]
)

# 线程锁
write_lock = threading.Lock()
ncbi_lock = threading.Lock()                         # 用于控制NCBI请求间隔

# ==========================================
# 初始化requests会话，支持重试
session = requests.Session()
retries = Retry(total=MAX_RETRIES, backoff_factor=1, status_forcelist=[429, 500, 502, 503, 504])

adapter = HTTPAdapter(
    pool_connections=50,        # 缓存连接数，建议大于 MAX_WORKERS
    pool_maxsize=50,            # 连接池最大大小
    max_retries=retries
)
session.mount('https://', adapter)

# ==========================================
# 读取转座子名称列表，并预编译正则表达式（单词边界，忽略大小写）
def load_te_names(filepath):
    """从分号分隔的文件中读取所有转座子名称，返回原始名称列表、小写名称列表、预编译正则列表"""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read().strip()
        # 按分号分割，去除每个名称两端的空格
        names = [name.strip() for name in content.split(';') if name.strip()]
        names_lower = [name.lower() for name in names]
        # 预编译正则：\b转义后的名称\b，忽略大小写
        regexes = [re.compile(r'\b' + re.escape(name) + r'\b', re.IGNORECASE) for name in names]
        logging.info(f"成功加载 {len(names)} 个转座子名称，已预编译正则表达式")
        return names, names_lower, regexes
    except Exception as e:
        logging.error(f"读取转座子名称文件失败: {e}")
        return [], [], []

TE_NAMES_RAW, TE_NAMES_LOWER, TE_NAME_REGEXES = load_te_names(TE_NAMES_FILE)

def contains_te_name(text):
    """检查文本中是否包含任意转座子名称（使用单词边界正则匹配）"""
    if not TE_NAME_REGEXES:
        # 若名称列表为空，则默认通过（保底策略）
        return True
    for regex in TE_NAME_REGEXES:
        if regex.search(text):
            return True
    return False

# ==========================================
# 后处理过滤函数：仅保留在白名单中的转座子实体，并剔除相关关系
def filter_by_whitelist(result, whitelist_raw, whitelist_lower):
    """
    过滤转座子实体，仅保留在白名单中的名称，并删除相关关系。
    如果过滤后转座子列表为空，返回None；否则返回过滤后的result。
    """
    if "entities" not in result or "transposons" not in result["entities"]:
        return result  # 无转座子实体，保持原样（后续会被跳过）
    
    transposons = result["entities"]["transposons"]
    # 获取每个转座子的原始名称和小写名称
    kept_indices = []
    kept_names_raw = []   # 保留的原始名称（用于关系过滤）
    for i, te in enumerate(transposons):
        name_raw = te.get("name", "")
        name_lower = name_raw.lower()
        if name_lower in whitelist_lower:
            kept_indices.append(i)
            kept_names_raw.append(name_raw)
    
    # 删除不在白名单的转座子实体
    result["entities"]["transposons"] = [transposons[i] for i in kept_indices]
    
    # 如果没有保留任何转座子，则返回None（表示应跳过该文献）
    if not result["entities"]["transposons"]:
        return None
    
    # 过滤关系：保留 source 和 target 都在 kept_names_raw 中（或非转座子实体）的关系
    if "relations" in result:
        # 获取所有转座子原始名称（用于判断一个实体是否是转座子）
        all_te_names_raw = [te["name"] for te in transposons]
        kept_names_set = set(kept_names_raw)
        
        filtered_relations = []
        for rel in result["relations"]:
            source = rel.get("source", "")
            target = rel.get("target", "")
            
            # 判断source是否为转座子（即是否在all_te_names_raw中）
            source_is_te = source in all_te_names_raw
            target_is_te = target in all_te_names_raw
            
            # 如果source是转座子但不在保留集中，则跳过该关系
            if source_is_te and source not in kept_names_set:
                continue
            # 如果target是转座子但不在保留集中，则跳过该关系
            if target_is_te and target not in kept_names_set:
                continue
            # 其他情况保留（source/target都不是转座子，或者都是保留的转座子）
            filtered_relations.append(rel)
        
        result["relations"] = filtered_relations
    
    return result

# ==========================================
# 漏提检查：记录摘要中出现的白名单名称（使用单词边界正则）但API未提取的情况
def check_missing_tes(pmid, title, abstract, extracted_names_raw, whitelist_lower, name_regexes):
    """
    检查标题和摘要中出现的白名单名称是否都被API提取。
    将漏提的名称记录到 MISSING_LOG 文件。
    """
    combined = f"{title} {abstract}"
    found_names = set()
    for name_raw, regex in zip(TE_NAMES_RAW, TE_NAME_REGEXES):
        if regex.search(combined):
            found_names.add(name_raw.lower())  # 存小写便于比较
    extracted_lower = {name.lower() for name in extracted_names_raw if name}
    missing = found_names - extracted_lower
    if missing:
        missing_str = ", ".join(sorted(missing))
        log_line = f"{pmid}\tMissing in API: {missing_str}\n"
        with write_lock:
            with open(MISSING_LOG, 'a', encoding='utf-8') as f:
                f.write(log_line)
        logging.info(f"漏提检查 PMID {pmid}: 缺失 {missing_str}")
    return missing

# ==========================================
# 新版 get_pmid_list：按十年区间分割查询，突破10000限制
def get_pmid_list(query, retmax):
    """
    按十年区间分割查询，获取PMID列表，并限制总数不超过retmax。
    
    """
    logging.info("正在按年份分割检索PubMed...")
    all_pmids = []
    start_year = 1940
    end_year = 2026
    intervals = []
    for year in range(start_year, end_year, 5):
        interval_start = year
        interval_end = min(year + 4, end_year)
        intervals.append((interval_start, interval_end))
    
    total_db_count = 0
    for s_year, e_year in intervals:
        # 构建子查询，使用年份范围 [PDAT]
        sub_query = f"({query}) AND ({s_year}[PDAT] : {e_year}[PDAT])"
        try:
            with ncbi_lock:
                time.sleep(NCBI_DELAY)
                handle = Entrez.esearch(
                    db="pubmed",
                    term=sub_query,
                    retmax=10000  # 每个区间最多取10000，确保不超NCBI限制
                )
                record = Entrez.read(handle)
                handle.close()
            pmids = record.get("IdList", [])
            count = int(record.get("Count", 0))
            total_db_count += count
            all_pmids.extend(pmids)
            logging.info(f"区间 {s_year}-{e_year}: 获取 {len(pmids)} 篇 (数据库中共 {count} 篇)")
            
            # 如果已超过retmax，截断并停止
            if len(all_pmids) >= retmax:
                all_pmids = all_pmids[:retmax]
                logging.info(f"已达到总限制 {retmax} 篇，停止获取后续区间")
                break
        except Exception as e:
            logging.error(f"获取区间 {s_year}-{e_year} 失败: {e}")
            continue

    logging.info(f"检索完成，共获取 {len(all_pmids)} 篇文献（目标 {retmax} 篇，数据库总 {total_db_count} 篇）")
    return all_pmids

def fetch_paper_details(pmid):
    """
    根据单个PMID获取文章的标题和摘要，带重试
    返回 (title, abstract)，若失败则返回 (None, None)
    """
    for attempt in range(1, MAX_RETRIES+1):
        try:
            with ncbi_lock:
                time.sleep(NCBI_DELAY)
                handle = Entrez.efetch(db="pubmed", id=pmid, retmode="xml")
                records = Entrez.read(handle)
                handle.close()
            articles = records.get('PubmedArticle', [])
            if not articles:
                return None, None
            article = articles[0]
            medline = article.get("MedlineCitation", {})
            article_info = medline.get("Article", {})

            title = article_info.get("ArticleTitle", "")

            abstract_text = ""
            abstract = article_info.get("Abstract", {})
            if abstract and "AbstractText" in abstract:
                abstract_parts = abstract["AbstractText"]
                if isinstance(abstract_parts, list):
                    abstract_text = " ".join(str(part) for part in abstract_parts)
                else:
                    abstract_text = str(abstract_parts)
            if not abstract_text:
                abstract_text = "No abstract available."

            return title, abstract_text
        except Exception as e:
            logging.warning(f"获取PMID {pmid} 详细信息失败 (尝试 {attempt}/{MAX_RETRIES}): {e}")
            if attempt == MAX_RETRIES:
                return None, None
            time.sleep(2 ** attempt)  # 指数退避

def call_deepseek_api(pmid, title, abstract):
    """调用DeepSeek API处理单篇论文，返回解析后的JSON对象，带重试"""
    # 提示词模板
    PROMPT_TEMPLATE = """
你是一个专业的生物医学信息提取助手，专门处理PubMed论文摘要，用于构建人类转座子知识图谱。

我将提供一篇论文的信息：
- PMID: {pmid}
- 标题: {title}
- 摘要: {abstract}

【任务目标】
请仔细阅读摘要，提取与**人类转座子**相关的实体和关系。重点关注**活性、致病机制、表达异常、插入突变**等与功能直接相关的描述，对于推测性的描述可忽略。
【重要】由于用于构建人类转座子知识图谱，只提取人类基因组中的转座子，以Repbase库为准。如果转座子来自其他物种（如细菌、果蝇、小鼠等），则**不要**提取该实体和相关关系实体。

请严格按照以下步骤和格式要求，以**JSON格式**输出一个对象（注意：只输出一个JSON对象，不要添加额外解释）。

#### 步骤1：实体识别
从摘要中，识别并提取以下四类实体。请确保实体名称尽量标准化，并为每个实体生成一句简洁的描述。

- **转座子实体**：识别属于转座子的成员。
  - **优先级别**：如果摘要明确提到亚家族（如 L1HS, L1PA1, L1-Ta, L1.3, L1RP 等），则提取为具体亚家族。
  - 如果未提及亚家族，仅提到家族，则提取为具体家族名，如"LINE-1"；如果连家族名都没有提及，则提取为超家族名，如"LINE"。
  - *实体描述示例*：L1HS is the only active LINE-1 subfamily in the human genome.
- **疾病实体 (Disease)**：摘要中提到的与转座子相关的任何疾病名称。
  - *实体描述示例*：Hemophilia A is an inherited bleeding disorder caused by a deficiency of coagulation factor VIII.
- **生物学功能/机制实体 (Function)**：转座子参与的生物学过程或分子机制。
  - *实体描述示例*：Retrotransposition is the process by which LINE-1 elements move within the genome through a "copy-and-paste" mechanism.
- **论文实体 (Paper)**：代表当前正在处理的文献。
  - *实体描述*：直接使用论文标题。

#### 步骤2：关系抽取
对于步骤1中识别的实体，提取它们之间的语义关系。每条关系应表达为一个完整的"实体-关系-实体"三元组。

关系类型仅从下列选择一个，除非下列实在不存在合适关系时可自行编写，否则只能选择下列关系其一：
- 转座子 — cause(转座子直接导致疾病发生)/induce(转座子诱发疾病（强调起始作用）)/promote(转座子促进疾病进展或恶化)/increase risk(转座子增加患病的风险)/associate with(转座子与疾病存在关联（不确定因果）)/inhibit(转座子抑制疾病（如保护性作用）)/involve in(转座子参与疾病过程（非直接因果）) — 疾病
- 转座子 — participate in(转座子参与某一生物学过程)/mediate(转座子介导某种机制（如插入、逆转录）)/regulate(转座子调控基因表达或通路)/perform(转座子执行特定功能（如逆转录转座）)/drive(转座子驱动某种机制（如基因组不稳定）)/affect(转座子影响功能/机制（较宽泛）)/utilize(转座子利用宿主机制完成自身过程) — 功能/机制
- 功能/机制 — lead to(功能/机制直接导致疾病)/trigger(功能/机制引发疾病（强调触发事件)）/promote(功能/机制促进疾病发展)/involve in(功能/机制参与疾病过程)/suppress(功能/机制抑制疾病（如保护性机制）)/modulate(功能/机制调节疾病相关通路)/associate with(功能/机制与疾病存在关联) — 疾病
- 论文 — report — 论文外实体

#### 步骤3：输出格式
请输出一个JSON对象，包含以下字段：
- `pmid`: 论文的PMID编号（字符串类型）
- `entities`: 包含本论文中识别出的所有实体，按类型分组，每个实体包含 `name` 和 `description`。
- `relations`: 包含本论文中提取出的所有关系，每条关系包含 `source`, `relation`, `target`, `description`。

**输出示例：**
{{
  "pmid": "12345678",
  "entities": {{
    "transposons": [{{"name": "L1HS", "description": "L1HS is the only active LINE-1 subfamily in the human genome."}}],
    "diseases": [{{"name": "Hepatocellular carcinoma", "description": "Hepatocellular carcinoma is the most common type of primary liver cancer."}}],
    "functions": [{{"name": "Insertional mutation", "description": "Insertional mutation refers to the process where a transposon inserts into the genome and disrupts gene function."}}]
  }},
  "relations": [
    {{"source": "L1HS", "relation": "mediate", "target": "Insertional mutation", "description": "L1HS leads to insertional mutations through retrotransposition."}},
    {{"source": "Insertional mutation", "relation": "trigger", "target": "Hepatocellular carcinoma", "description": "Insertional mutations may lead to the inactivation of tumor suppressor genes, thereby promoting hepatocellular carcinoma."}}
  ]
}}

【重要注意事项】
1. 优先活性与功能：关注描述活性、致病机制、表达异常、DNA损伤、插入多态性等。
2. 实体唯一性：同一篇论文内，相同的实体只记录一次。
3. 描述的准确性：基于摘要内容概括，保持简洁。
4. 关系完整性：尽量提取明确的关系。
5. 语言：英文。
"""
    prompt = PROMPT_TEMPLATE.format(pmid=pmid, title=title, abstract=abstract)
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }
    payload = {
        "model": "deepseek-chat",
        "messages": [
            {"role": "system", "content": "你是一个专业的生物医学信息提取助手。"},
            {"role": "user", "content": prompt}
        ],
        "temperature": 0.3,
        "max_tokens": 4096
    }

    for attempt in range(1, MAX_RETRIES+1):
        try:
            time.sleep(API_DELAY)   # 控制请求频率
            response = session.post(API_URL, headers=headers, json=payload, timeout=REQUEST_TIMEOUT)
            # 检查HTTP错误
            if response.status_code == 429:
                logging.warning(f"API限流 (PMID {pmid})，等待后重试...")
                time.sleep(5 * attempt)
                continue
            response.raise_for_status()
            result = response.json()
            content = result['choices'][0]['message']['content']

            # 清理可能的Markdown代码块标记
            content = content.strip()
            if content.startswith('```json'):
                content = content[7:]
                if content.endswith('```'):
                    content = content[:-3]
            elif content.startswith('```'):
                content = content[3:]
                if content.endswith('```'):
                    content = content[:-3]
            content = content.strip()

            data = json.loads(content)
            return data
        
        except requests.exceptions.ConnectionError as e:
            # 新增：处理连接错误（包括 ConnectionResetError）
            logging.warning(f"连接错误 (PMID {pmid}, 尝试 {attempt}/{MAX_RETRIES}): {e}")
            if attempt == MAX_RETRIES:
                return None
            time.sleep(2 ** attempt)   # 指数退避
            continue
        except requests.exceptions.Timeout as e:
            # 新增：处理超时
            logging.warning(f"超时错误 (PMID {pmid}, 尝试 {attempt}/{MAX_RETRIES}): {e}")
            if attempt == MAX_RETRIES:
                return None
            time.sleep(2 ** attempt)
            continue
        except Exception as e:
            # 其他异常（如 JSON 解析错误等）
            logging.warning(f"API调用错误 (PMID {pmid}, 尝试 {attempt}/{MAX_RETRIES}): {e}")
            if attempt == MAX_RETRIES:
                return None
            time.sleep(2 ** attempt)

        except Exception as e:
            logging.warning(f"API调用错误 (PMID {pmid}, 尝试 {attempt}/{MAX_RETRIES}): {e}")
            if attempt == MAX_RETRIES:
                return None
            time.sleep(2 ** attempt)

def process_one_pmid(pmid):
    """处理单个PMID的完整流程：获取文献、过滤、调用API、后处理"""
    # 1. 获取标题和摘要
    title, abstract = fetch_paper_details(pmid)
    if title is None:
        return {"pmid": pmid, "skip_reason": "fetch_failed"}

    # 2. 预处理过滤：检查是否包含任何转座子名称（使用单词边界正则，避免无效API调用）
    combined_text = f"{title} {abstract}"
    if not contains_te_name(combined_text):
        return {"pmid": pmid, "skip_reason": "no_te_name"}

    # 3. 调用API
    result = call_deepseek_api(pmid, title, abstract)
    if result is None:
        return {"pmid": pmid, "skip_reason": "api_failed"}
    
    # 4. 后处理：白名单过滤
    result = filter_by_whitelist(result, TE_NAMES_RAW, TE_NAMES_LOWER)
    if result is None:
        # 过滤后没有转座子实体，视为跳过
        return {"pmid": pmid, "skip_reason": "no_transposon_after_whitelist"}

    # 5. 漏提检查：记录摘要中出现但API未提取的转座子名称（使用单词边界正则）
    extracted_names = [te["name"] for te in result["entities"].get("transposons", [])]
    check_missing_tes(pmid, title, abstract, extracted_names, TE_NAMES_LOWER, TE_NAME_REGEXES)

    return result

def load_processed_pmids():
    """从输出文件和跳过日志中读取已经处理过的PMID（去重）"""
    processed = set()
    if os.path.exists(OUTPUT_FILE):
        with open(OUTPUT_FILE, 'r', encoding='utf-8') as f:
            for line in f:
                try:
                    data = json.loads(line)
                    pmid = data.get('pmid')
                    if pmid:
                        processed.add(pmid)
                except:
                    continue
    logging.info(f"已从输出文件中找到 {len(processed)} 篇已处理的文献")
    
    # 从跳过日志读取（格式：pmid\t原因 或 纯pmid）
    for log_file in [SKIPPED_LOG]:
        if os.path.exists(log_file):
            with open(log_file, 'r', encoding='utf-8') as f:
                for line in f:
                    parts = line.strip().split('\t')
                    if parts:
                        processed.add(parts[0])  # 第一个字段是pmid
    
    logging.info(f"已从各类日志中找到 {len(processed)} 篇已尝试过的文献")
    return processed

def append_to_file(filepath, pmid, reason=None):
    """记录失败或跳过的PMID到文件"""
    with write_lock:
        with open(filepath, 'a', encoding='utf-8') as f:
            if reason:
                f.write(f"{pmid}\t{reason}\n")
            else:
                f.write(pmid + '\n')

# ==========================================
if __name__ == "__main__":
    # 1. 获取PMID列表（按年份分割，突破10000限制）
    pmid_list = get_pmid_list(SEARCH_QUERY, MAX_RESULTS)
    if not pmid_list:
        logging.error("未获取到PMID，程序结束。")
        exit()

    # 2. 加载已处理的PMID，避免重复
    processed = load_processed_pmids()
    to_process = [p for p in pmid_list if p not in processed]
    logging.info(f"总共获取 {len(pmid_list)} 篇，已处理/尝试 {len(processed)} 篇，本次将处理 {len(to_process)} 篇")

    if not to_process:
        logging.info("所有文献已处理完毕，程序退出。")
        exit()

    # 3. 打开输出文件（追加模式）
    with open(OUTPUT_FILE, 'a', encoding='utf-8') as out_f:
        # 使用线程池并发处理
        with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
            # 提交所有任务
            future_to_pmid = {executor.submit(process_one_pmid, pmid): pmid for pmid in to_process}

            completed = 0
            total = len(to_process)
            skip_stats = {"no_te_name": 0, "api_failed": 0, "no_transposon_after_whitelist": 0, "fetch_failed": 0}
            for future in as_completed(future_to_pmid):
                pmid = future_to_pmid[future]
                try:
                    result = future.result()
                    if isinstance(result, dict) and "skip_reason" in result:
                        reason = result["skip_reason"]
                        skip_stats[reason] = skip_stats.get(reason, 0) + 1
                        if reason in ("api_failed", "fetch_failed"):
                            # 记录到失败文件
                            append_to_file(FAILED_LOG, pmid, reason)
                        else:
                            # 记录到跳过文件（无转座子名称或无实体）
                            append_to_file(SKIPPED_LOG, pmid, reason)
                        logging.info(f"跳过: PMID {pmid} 原因:{reason} ({completed+1}/{total})")
                    else:
                        # 成功结果
                        with write_lock:
                            out_f.write(json.dumps(result, ensure_ascii=False) + '\n')
                            out_f.flush()
                        logging.info(f"成功: PMID {pmid} ({completed+1}/{total})")
                except Exception as e:
                    logging.error(f"处理 PMID {pmid} 时发生异常: {e}")
                    append_to_file(FAILED_LOG, pmid, "exception")
                completed += 1

    logging.info(f"\n处理完成！结果已保存至: {OUTPUT_FILE}")
    logging.info(f"失败的PMID已记录在: {FAILED_LOG}")
    logging.info(f"跳过的PMID（无转座子名称或无实体）已记录在: {SKIPPED_LOG}")
    logging.info(f"漏提检查记录在: {MISSING_LOG}")
    logging.info(f"跳过统计: {skip_stats}")