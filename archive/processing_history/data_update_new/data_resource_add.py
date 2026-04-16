import time
import json
import logging
import requests
from Bio import Entrez
from concurrent.futures import ThreadPoolExecutor, as_completed
import threading
import os
import re
import socket
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
import pandas as pd

# ==========================================
# 配置参数
Entrez.email = "fongisek@gmail.com"
TE_NAMES_FILE = r"C:\Users\fongi\Desktop\TE\data\TE_names.txt"

API_KEY = "sk-e079c0460f4544339534eda3a8b133c8"
API_URL = "https://api.deepseek.com/v1/chat/completions"
OUTPUT_FILE = r"C:\Users\fongi\Desktop\TE\data_update\te_kg2_update.jsonl"
FAILED_LOG = r"C:\Users\fongi\Desktop\TE\data_update\failed_pmids_update.txt"
SKIPPED_LOG = r"C:\Users\fongi\Desktop\TE\data_update\skipped_pmids_add.txt"
MISSING_LOG = r"C:\Users\fongi\Desktop\TE\data_update\missing_tes_update.txt"
PROGRESS_LOG = r"C:\Users\fongi\Desktop\TE\data_update\progress_update.log"

MAX_WORKERS = 25
NCBI_BATCH_SIZE = 200
NCBI_DELAY = 0.34
REQUEST_TIMEOUT = 60
MAX_RETRIES = 3
NCBI_FETCH_RETRIES = 2
NCBI_SOCKET_TIMEOUT = 60

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(PROGRESS_LOG, encoding='utf-8'),
        logging.StreamHandler()
    ]
)

write_lock = threading.Lock()
ncbi_lock = threading.Lock()

# ==========================================
# 新增：用于统计前10篇白名单拦截的计数器
first_n_counter = 0
first_n_lock = threading.Lock()
FIRST_N = 100

# ==========================================
session = requests.Session()
retries = Retry(total=MAX_RETRIES, backoff_factor=1, status_forcelist=[429, 500, 502, 503, 504])
adapter = HTTPAdapter(pool_connections=50, pool_maxsize=50, max_retries=retries)
session.mount('https://', adapter)

# ==========================================
def load_te_names(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read().strip()
        names = [name.strip() for name in content.split(';') if name.strip()]
        names_lower = [name.lower() for name in names]
        regexes = [re.compile(r'\b' + re.escape(name) + r'\b', re.IGNORECASE) for name in names]
        logging.info(f"成功加载 {len(names)} 个转座子名称")
        return names, names_lower, regexes
    except Exception as e:
        logging.error(f"读取转座子名称文件失败: {e}")
        return [], [], []

TE_NAMES_RAW, TE_NAMES_LOWER, TE_NAME_REGEXES = load_te_names(TE_NAMES_FILE)

def contains_te_name(text):
    if not TE_NAME_REGEXES:
        return True
    for regex in TE_NAME_REGEXES:
        if regex.search(text):
            return True
    return False

# ==========================================
# 修改 filter_by_whitelist，增加可选返回值：被过滤掉的转座子名称列表
def filter_by_whitelist(result, whitelist_raw, whitelist_lower, return_filtered=False):
    """
    过滤结果中的转座子实体，只保留白名单中的。
    如果 return_filtered=True，则返回 (filtered_result, filtered_names)
    """
    if "entities" not in result or "transposons" not in result["entities"]:
        return (result, []) if return_filtered else result
    transposons = result["entities"]["transposons"]
    kept_indices = []
    kept_names_raw = []
    filtered_names = []   # 被过滤掉的名称
    for i, te in enumerate(transposons):
        name_raw = te.get("name", "")
        name_lower = name_raw.lower()
        if name_lower in whitelist_lower:
            kept_indices.append(i)
            kept_names_raw.append(name_raw)
        else:
            filtered_names.append(name_raw)
    result["entities"]["transposons"] = [transposons[i] for i in kept_indices]
    if not result["entities"]["transposons"]:
        if return_filtered:
            return (None, filtered_names)
        else:
            return None
    if "relations" in result:
        all_te_names_raw = [te["name"] for te in transposons]
        kept_names_set = set(kept_names_raw)
        filtered_relations = []
        for rel in result["relations"]:
            source = rel.get("source", "")
            target = rel.get("target", "")
            source_is_te = source in all_te_names_raw
            target_is_te = target in all_te_names_raw
            if source_is_te and source not in kept_names_set:
                continue
            if target_is_te and target not in kept_names_set:
                continue
            filtered_relations.append(rel)
        result["relations"] = filtered_relations
    if return_filtered:
        return (result, filtered_names)
    else:
        return result

# ==========================================
def check_missing_tes(pmid, title, abstract, extracted_names_raw, whitelist_lower, name_regexes):
    combined = f"{title} {abstract}"
    found_names = set()
    for name_raw, regex in zip(TE_NAMES_RAW, TE_NAME_REGEXES):
        if regex.search(combined):
            found_names.add(name_raw.lower())
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
def fetch_batch_details(pmid_list, retries=NCBI_FETCH_RETRIES):
    if not pmid_list:
        return {}
    original_timeout = socket.getdefaulttimeout()
    socket.setdefaulttimeout(NCBI_SOCKET_TIMEOUT)
    for attempt in range(retries + 1):
        try:
            with ncbi_lock:
                time.sleep(NCBI_DELAY)
                handle = Entrez.efetch(db="pubmed", id=','.join(pmid_list), retmode="xml")
                records = Entrez.read(handle)
                handle.close()
            break
        except Exception as e:
            logging.error(f"批量获取失败 (尝试 {attempt+1}/{retries+1}): {e}")
            if attempt == retries:
                socket.setdefaulttimeout(original_timeout)
                return {pmid: (None, None) for pmid in pmid_list}
            time.sleep(5 * (attempt + 1))
    else:
        socket.setdefaulttimeout(original_timeout)
        return {pmid: (None, None) for pmid in pmid_list}
    result = {}
    articles = records.get('PubmedArticle', [])
    for article in articles:
        medline = article.get("MedlineCitation", {})
        pmid = str(medline.get("PMID", ""))
        if not pmid:
            continue
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
        result[pmid] = (title, abstract_text)
    for pmid in pmid_list:
        if pmid not in result:
            result[pmid] = (None, None)
    socket.setdefaulttimeout(original_timeout)
    return result

# ==========================================
def call_deepseek_api(pmid, title, abstract):
    """调用DeepSeek API处理单篇论文，返回解析后的JSON对象，带重试"""
    # 转义标题和摘要中的特殊字符
    safe_title = title.replace('%', '%%')
    safe_abstract = abstract.replace('%', '%%')
    safe_title = safe_title.replace('{', '{{').replace('}', '}}')
    safe_abstract = safe_abstract.replace('{', '{{').replace('}', '}}')
    
    PROMPT_TEMPLATE = """
You are a biomedical IE assistant for human transposon knowledge graph from PubMed abstracts.

Paper: PMID %(pmid)s, Title: %(title)s, Abstract: %(abstract)s

【Rules】
1. Extract only HUMAN transposons (per Repbase). Ignore transposons from other species.
2. Focus on activity, pathogenicity, expression abnormality, insertional mutation, DNA damage.
3. Output ONE JSON object. No extra text.

【Entity Types】(standardize name, add one short description per entity)
- transposon: subfamily > family > superfamily. e.g. L1HS.(如果是L1 retrotranspon就只提取L1,hat transposon只提取hat,其他同理，只提取转座子名字)
- disease
- function: biological process or mechanism.
- paper: use title as description.
- gene: non-TE genes (protein-coding, ncRNA).
- rna: mRNA, lncRNA, miRNA, etc. (not from TE).
- protein
- carbohydrate
- lipid
- peptide (<50 aa, not toxin/drug)
- pharmaceutical: drug, compound, candidate.
- toxin

【Relation Types】(source -relation-> target)
Use only these predefined types. If none fits, use generic `associate with`, `cooccur with`, `correlate with`.

A. Transposon-related:
  cause|induce|promote|increaser isk|associate with|inhibit|involve in → Disease
  participate in|mediate|perform|drive|affect|utilize → Function
  regulate → Gene,RNA,Protein,Function
  insert into|disrupt → Gene
  activate|silence → Gene
  express → RNA,Protein

B. Function-related:
  lead to|trigger|promote|involve in|suppress|modulate|associate with → Disease

C. Gene/RNA/Protein/Peptide/Carbohydrate/Lipid:
  encode (Gene→RNA,Protein)
  express (Gene,Transposon→RNA,Protein)
  regulate (any→any except Paper)
  bind (Protein,RNA,Peptide,Carbohydrate,Lipid→Protein,RNA,DNA,Transposon)
  catalyze (Protein,RNA→Function,Carbohydrate,Lipid,Peptide)
  cleave (Protein→RNA,Protein,DNA)
  modify (Protein→Gene,RNA,Protein,Carbohydrate,Lipid)
  transport (Protein→Small molecule,Lipid,Ion)
  interact with (Protein,RNA,Peptide→Protein,RNA,Gene,Transposon)
  localize to (Protein,RNA→Cellular component)

D. Mutation:
  cause→Disease; lead to→Function; affect→Transposon,Gene,RNA,Protein; create→Transposon,Gene; abolish→Gene,Protein; confer→Pharmaceutical,Protein

E. Pharmaceutical/Toxin:
  inhibit→Transposon,Protein,RNA,Enzyme,Function; treat→Disease; induce→Disease,Mutation; activate→Gene,Protein,Function; cleave→Protein,RNA,DNA

F. Paper: report → any entity (except Paper)

【Output Format】
{
  "pmid": "31234567",
  "entities": {
    "transposons": [{"name": "L1HS", "description": "L1HS is an active human LINE-1 subfamily."}],
    "diseases": [{"name": "Colorectal cancer", "description": "Colorectal cancer is a malignant tumor of the colon or rectum."}],
    "functions": [{"name": "Insertional mutagenesis", "description": "Insertional mutagenesis disrupts tumor suppressor genes."}],
    "genes": [{"name": "APC", "description": "APC is a tumor suppressor gene involved in Wnt signaling."}],
    "proteins": [{"name": "beta-catenin", "description": "Beta-catenin is a transcriptional co-activator."}],
    "rnas": [],
    "carbohydrates": [],
    "lipids": [],
    "peptides": [],
    "pharmaceuticals": [{"name": "5-fluorouracil", "description": "5-FU is a chemotherapeutic agent."}],
    "toxins": []
  },
  "relations": [
    {"source": "L1HS", "relation": "insert into", "target": "APC", "description": "L1HS inserted into the APC gene."},
    {"source": "L1 insertion in APC", "relation": "cause", "target": "Colorectal cancer", "description": "The insertion disrupts APC, leading to cancer."},
    {"source": "L1HS", "relation": "mediate", "target": "Insertional mutagenesis", "description": "L1HS retrotransposition mediates insertional mutagenesis."},
    {"source": "5-fluorouracil", "relation": "treat", "target": "Colorectal cancer", "description": "5-FU is used to treat colorectal cancer."}

【Important】
- Same entity per paper: once.
- Description: concise, from abstract.
- Output language: English.
"""
    prompt = PROMPT_TEMPLATE % {
        "pmid": pmid,
        "title": safe_title,
        "abstract": safe_abstract
    }
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

    for attempt in range(1, MAX_RETRIES + 1):
        try:
            response = session.post(API_URL, headers=headers, json=payload, timeout=REQUEST_TIMEOUT)
            if response.status_code == 429:
                wait = 5 * attempt
                logging.warning(f"API限流 (PMID {pmid})，等待 {wait} 秒后重试...")
                time.sleep(wait)
                continue
            response.raise_for_status()
            # 关键：定义 result 变量
            resp_data = response.json()
            content = resp_data['choices'][0]['message']['content']
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
            parsed = json.loads(content)

            # 处理列表类型返回值
            if isinstance(parsed, list):
                if len(parsed) == 1 and isinstance(parsed[0], dict):
                    parsed = parsed[0]
                else:
                    logging.warning(f"PMID {pmid} API 返回了列表且长度不为1或元素非字典: {parsed[:2] if len(parsed) > 2 else parsed}")
                    return None
            if not isinstance(parsed, dict):
                logging.warning(f"PMID {pmid} API 返回非字典类型: {type(parsed)}")
                return None

            return parsed

        except requests.exceptions.ConnectionError as e:
            logging.warning(f"连接错误 (PMID {pmid}, 尝试 {attempt}/{MAX_RETRIES}): {e}")
            if attempt == MAX_RETRIES:
                return None
            time.sleep(2 ** attempt)
        except requests.exceptions.Timeout as e:
            logging.warning(f"超时错误 (PMID {pmid}, 尝试 {attempt}/{MAX_RETRIES}): {e}")
            if attempt == MAX_RETRIES:
                return None
            time.sleep(2 ** attempt)
        except Exception as e:
            logging.warning(f"API调用错误 (PMID {pmid}, 尝试 {attempt}/{MAX_RETRIES}): {e}")
            if attempt == MAX_RETRIES:
                return None
            time.sleep(2 ** attempt)
    return None

# ==========================================
# 修改处理函数，捕获前10篇被白名单拦截的转座子名称
def process_one_pmid_with_details(pmid, title, abstract):
    global first_n_counter
    if title is None:
        return {"pmid": pmid, "skip_reason": "fetch_failed"}
    combined_text = f"{title} {abstract}"
    if not contains_te_name(combined_text):
        return {"pmid": pmid, "skip_reason": "no_te_name"}
    result = call_deepseek_api(pmid, title, abstract)
    if result is None or not isinstance(result, dict):
        return {"pmid": pmid, "skip_reason": "api_failed"}   # 统一归为 api_failed

    # 以下保持不变 ...
    
    # 在过滤前保存原始转座子名称
    original_transposons = []
    if "entities" in result and "transposons" in result["entities"]:
        original_transposons = [te.get("name", "") for te in result["entities"]["transposons"]]
    
    # 使用带返回过滤名称的过滤函数
    filtered_result, filtered_names = filter_by_whitelist(result, TE_NAMES_RAW, TE_NAMES_LOWER, return_filtered=True)
    
    if filtered_result is None:
        # 白名单过滤后为空，记录被拦截的转座子名称
        # 检查是否需要输出到前十篇
        with first_n_lock:
            if first_n_counter < FIRST_N:
                first_n_counter += 1
                # 输出被拦截的名称（即原始名称中不在白名单的部分）
                blocked_names = filtered_names if filtered_names else original_transposons
                logging.info(f"[前十篇拦截] PMID {pmid} 被白名单过滤掉的转座子: {blocked_names}")
        return {"pmid": pmid, "skip_reason": "no_transposon_after_whitelist"}
    
    # 漏提检查
    extracted_names = [te["name"] for te in filtered_result["entities"].get("transposons", [])]
    check_missing_tes(pmid, title, abstract, extracted_names, TE_NAMES_LOWER, TE_NAME_REGEXES)
    
    # 更新计数器（成功处理也算一个处理过的文献，但不属于拦截情况）
    with first_n_lock:
        if first_n_counter < FIRST_N:
            first_n_counter += 1
            # 成功处理的不输出拦截信息
    return filtered_result

# ==========================================
def load_processed_pmids():
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
    logging.info(f"已从输出文件中找到 {len(processed)} 篇已成功处理的文献")
    return processed

def append_to_file(filepath, pmid, reason=None):
    with write_lock:
        with open(filepath, 'a', encoding='utf-8') as f:
            if reason:
                f.write(f"{pmid}\t{reason}\n")
            else:
                f.write(pmid + '\n')

def load_pmids_from_excel(excel_path, sheet_name):
    try:
        df = pd.read_excel(excel_path, sheet_name=sheet_name, dtype=str)
        if df.empty:
            logging.error(f"工作表 {sheet_name} 为空")
            return []
        first_col = df.columns[0]
        pmid_list = df[first_col].tolist()
        if pmid_list and str(pmid_list[0]).lower() == 'pmid':
            pmid_list = pmid_list[1:]
        pmid_list = [str(p).strip() for p in pmid_list if pd.notna(p) and str(p).strip()]
        logging.info(f"从 {excel_path} 工作表 {sheet_name} 读取到 {len(pmid_list)} 个 PMID")
        return pmid_list
    except Exception as e:
        logging.error(f"读取 Excel 文件失败: {e}")
        return []

# ==========================================
if __name__ == "__main__":
    EXCEL_FILE = r"C:\Users\fongi\Desktop\TE\unmatched_pmids.xlsx"
    SHEET_NAME = "only_original"
    pmid_list = load_pmids_from_excel(EXCEL_FILE, SHEET_NAME)
    if not pmid_list:
        logging.error("未获取到 PMID，程序结束。")
        exit()
    processed = load_processed_pmids()
    to_process = [p for p in pmid_list if p not in processed]
    logging.info(f"总共获取 {len(pmid_list)} 个 PMID，已成功处理 {len(processed)} 个，本次将处理 {len(to_process)} 个")
    if not to_process:
        logging.info("所有 PMID 已处理完毕，程序退出。")
        exit()
    batches = [to_process[i:i+NCBI_BATCH_SIZE] for i in range(0, len(to_process), NCBI_BATCH_SIZE)]
    logging.info(f"共分为 {len(batches)} 个批次，每批最多 {NCBI_BATCH_SIZE} 篇")
    with open(OUTPUT_FILE, 'a', encoding='utf-8') as out_f:
        for batch_idx, batch in enumerate(batches, 1):
            logging.info(f"开始处理第 {batch_idx}/{len(batches)} 批次，包含 {len(batch)} 篇文献")
            details = fetch_batch_details(batch)
            with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
                future_to_pmid = {
                    executor.submit(process_one_pmid_with_details, pmid, details[pmid][0], details[pmid][1]): pmid
                    for pmid in batch
                }
                completed = 0
                total_in_batch = len(batch)
                skip_stats = {"no_te_name": 0, "api_failed": 0, "no_transposon_after_whitelist": 0, "fetch_failed": 0}
                for future in as_completed(future_to_pmid):
                    pmid = future_to_pmid[future]
                    try:
                        result = future.result()
                        if isinstance(result, dict) and "skip_reason" in result:
                            reason = result["skip_reason"]
                            skip_stats[reason] = skip_stats.get(reason, 0) + 1
                            if reason in ("api_failed", "fetch_failed"):
                                append_to_file(FAILED_LOG, pmid, reason)
                            else:
                                append_to_file(SKIPPED_LOG, pmid, reason)
                            logging.info(f"跳过: PMID {pmid} 原因:{reason} (批次 {batch_idx}, {completed+1}/{total_in_batch})")
                        else:
                            with write_lock:
                                out_f.write(json.dumps(result, ensure_ascii=False) + '\n')
                                out_f.flush()
                            logging.info(f"成功: PMID {pmid} (批次 {batch_idx}, {completed+1}/{total_in_batch})")
                    except Exception as e:
                        logging.error(f"处理 PMID {pmid} 时发生异常: {e}")
                        append_to_file(FAILED_LOG, pmid, "exception")
                    completed += 1
                logging.info(f"批次 {batch_idx} 完成，统计: {skip_stats}")
    logging.info(f"\n处理完成！结果已保存至: {OUTPUT_FILE}")
    logging.info(f"失败的PMID已记录在: {FAILED_LOG}")
    logging.info(f"跳过的PMID（无转座子名称或无实体）已记录在: {SKIPPED_LOG}")
    logging.info(f"漏提检查记录在: {MISSING_LOG}")