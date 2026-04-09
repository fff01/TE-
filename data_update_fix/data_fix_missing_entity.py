import time
import json
import logging
import requests
from Bio import Entrez
from concurrent.futures import ThreadPoolExecutor, as_completed
import threading
import os
import socket
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
import shutil

# ==========================================
# 配置参数
Entrez.email = "fongisek@gmail.com"

API_KEY = "sk-9e0a68044af4429087604a3154856d44"
API_URL = "https://api.deepseek.com/v1/chat/completions"
INPUT_FILE = r"C:\Users\fongi\Desktop\TE\data_update_fix\te_kg2_final_standardized_new.jsonl"
BACKUP_FILE = INPUT_FILE + ".fix_missing_backup"
COMPLETED_LOG = r"C:\Users\fongi\Desktop\TE\data_update_fix\fixed_missing_pmids4.txt"
STATS_LOG = r"C:\Users\fongi\Desktop\TE\data_update_fix\missing_entities_stats4.txt"
DIAGNOSTIC_LOG = r"C:\Users\fongi\Desktop\TE\data_update_fix\failed_fix_details4.json"
PROGRESS_LOG = r"C:\Users\fongi\Desktop\TE\data_update_fix\fix_missing_progress4.log"

# 确保所有日志和输出文件的目录存在
for file_path in [INPUT_FILE, COMPLETED_LOG, STATS_LOG, DIAGNOSTIC_LOG, PROGRESS_LOG]:
    dir_name = os.path.dirname(file_path)
    if dir_name:
        os.makedirs(dir_name, exist_ok=True)

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
file_update_lock = threading.Lock()

# ==========================================
# 标准实体类型列表（复数形式，与entities键名一致）
STANDARD_ENTITY_TYPES = [
    'transposons', 'diseases', 'functions', 'genes', 'proteins', 'rnas',
    'carbohydrates', 'lipids', 'peptides', 'pharmaceuticals', 'toxins', 'mutations'
]

# 类型规范化映射
TYPE_NORMALIZATION = {
    'transposon': 'transposons', 'transposons': 'transposons', 'Transposons': 'transposons',
    'disease': 'diseases', 'diseases': 'diseases', 'Diseases': 'diseases',
    'function': 'functions', 'functions': 'functions', 'Functions': 'functions',
    'gene': 'genes', 'genes': 'genes', 'Genes': 'genes',
    'protein': 'proteins', 'proteins': 'proteins', 'Proteins': 'proteins',
    'rna': 'rnas', 'rnas': 'rnas', 'Rnas': 'rnas', 'RNAs': 'rnas',
    'carbohydrate': 'carbohydrates', 'carbohydrates': 'carbohydrates', 'Carbohydrates': 'carbohydrates',
    'lipid': 'lipids', 'lipids': 'lipids', 'Lipids': 'lipids',
    'peptide': 'peptides', 'peptides': 'peptides', 'Peptides': 'peptides',
    'pharmaceutical': 'pharmaceuticals', 'pharmaceuticals': 'pharmaceuticals', 'Pharmaceuticals': 'pharmaceuticals',
    'toxin': 'toxins', 'toxins': 'toxins', 'Toxins': 'toxins',
    'mutation': 'mutations', 'mutations': 'mutations', 'Mutations': 'mutations',
}

def normalize_entity_type(type_str):
    if not type_str:
        return None
    cleaned = type_str.strip().lower()
    if cleaned in TYPE_NORMALIZATION:
        return TYPE_NORMALIZATION[cleaned]
    if cleaned.endswith('s') and cleaned[:-1] in TYPE_NORMALIZATION:
        return TYPE_NORMALIZATION[cleaned[:-1]]
    logging.warning(f"未知实体类型: '{type_str}'")
    return None

# ==========================================
session = requests.Session()
retries = Retry(total=MAX_RETRIES, backoff_factor=1, status_forcelist=[429, 500, 502, 503, 504])
adapter = HTTPAdapter(pool_connections=50, pool_maxsize=50, max_retries=retries)
session.mount('https://', adapter)

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

def call_deepseek_fix(pmid, title, abstract, missing_names):
    if not missing_names:
        return []
    safe_title = title.replace('\\', '\\\\').replace('"', '\\"')
    safe_abstract = abstract.replace('\\', '\\\\').replace('"', '\\"')
    missing_list = ', '.join(missing_names)
    
    prompt = f"""
You are a biomedical knowledge graph assistant. The following PubMed article has some entity names that appear in relations but are missing from the entities section.

PMID: {pmid}
Title: {safe_title}
Abstract: {safe_abstract}

Missing entity names: {missing_list}

Your task:
- For each missing entity name, infer its correct biomedical entity type from the list below, and provide a short description (one sentence) based on the abstract.
- Output a JSON list of objects, each with fields: "name", "type", "description".
- Allowed types (use exactly these plural forms): transposons, diseases, functions, genes, proteins, rnas, carbohydrates, lipids, peptides, pharmaceuticals, toxins, mutations.
- Do NOT use singular forms (e.g., use "transposons" not "transposon").
- If a name is ambiguous, choose the most relevant type.
- Do not add any extra text outside the JSON list.

Example output:
[
  {{"name": "BRAF V600E", "type": "mutations", "description": "BRAF V600E is a somatic mutation in the BRAF gene."}},
  {{"name": "MAPK pathway", "type": "functions", "description": "MAPK pathway is a signaling cascade involved in cell proliferation."}}
]

Now output the JSON list for the missing names above:
"""
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }
    payload = {
        "model": "deepseek-chat",
        "messages": [
            {"role": "system", "content": "You are a precise biomedical information extraction assistant. Always output valid JSON."},
            {"role": "user", "content": prompt}
        ],
        "temperature": 0.2,
        "max_tokens": 2000
    }
    for attempt in range(1, MAX_RETRIES + 1):
        try:
            response = session.post(API_URL, headers=headers, json=payload, timeout=REQUEST_TIMEOUT)
            if response.status_code == 429:
                wait = 5 * attempt
                logging.warning(f"API限流 (PMID {pmid})，等待 {wait} 秒...")
                time.sleep(wait)
                continue
            response.raise_for_status()
            content = response.json()['choices'][0]['message']['content']
            content = content.strip()
            if content.startswith('```json'):
                content = content[7:]
            if content.startswith('```'):
                content = content[3:]
            if content.endswith('```'):
                content = content[:-3]
            content = content.strip()
            parsed = json.loads(content)
            if isinstance(parsed, list):
                valid_entities = []
                for item in parsed:
                    if isinstance(item, dict) and 'name' in item and 'type' in item:
                        if 'description' not in item:
                            item['description'] = ''
                        valid_entities.append(item)
                    else:
                        logging.warning(f"PMID {pmid} API返回的实体格式无效: {item}")
                return valid_entities
            else:
                logging.warning(f"PMID {pmid} API返回非列表: {type(parsed)}")
                return []
        except json.JSONDecodeError as e:
            logging.warning(f"PMID {pmid} JSON解析失败 (尝试 {attempt}/{MAX_RETRIES}): {e}")
            if attempt == MAX_RETRIES:
                return []
            time.sleep(2 ** attempt)
        except Exception as e:
            logging.warning(f"API调用失败 (PMID {pmid}, 尝试 {attempt}/{MAX_RETRIES}): {e}")
            if attempt == MAX_RETRIES:
                return []
            time.sleep(2 ** attempt)
    return []

def collect_missing_entities(entry):
    entities = entry.get('entities', {})
    relations = entry.get('relations', [])
    
    existing_names = set()
    for ent_list in entities.values():
        if isinstance(ent_list, list):
            for ent in ent_list:
                if isinstance(ent, dict) and 'name' in ent:
                    existing_names.add(ent['name'])
    
    missing = set()
    for rel in relations:
        if rel.get('relation') == 'report':
            continue
        source = rel.get('source')
        target = rel.get('target')
        if source and source not in existing_names:
            missing.add(source)
        if target and target not in existing_names:
            missing.add(target)
    return list(missing)

def merge_entities(original_entry, new_entities, missing_names):
    entities = original_entry.get('entities', {})
    for key in STANDARD_ENTITY_TYPES:
        if key not in entities:
            entities[key] = []
    
    existing_names = set()
    for ent_list in entities.values():
        for ent in ent_list:
            if isinstance(ent, dict) and 'name' in ent:
                existing_names.add(ent['name'])
    
    added = 0
    skipped_reasons = {name: [] for name in missing_names}
    
    for ent in new_entities:
        name = ent.get('name')
        if not name:
            continue
        if name in existing_names:
            skipped_reasons.setdefault(name, []).append("already_exists")
            continue
        raw_type = ent.get('type')
        normalized_type = normalize_entity_type(raw_type)
        if not normalized_type:
            skipped_reasons.setdefault(name, []).append(f"unrecognized_type: {raw_type}")
            continue
        description = ent.get('description', '')
        if normalized_type not in entities:
            entities[normalized_type] = []
        entities[normalized_type].append({"name": name, "description": description})
        existing_names.add(name)
        added += 1
        skipped_reasons[name] = ["added"]
    
    # 记录未返回的缺失名称
    for name in missing_names:
        if name not in skipped_reasons:
            skipped_reasons[name] = ["not_returned_by_api"]
    
    original_entry['entities'] = entities
    return original_entry, added, skipped_reasons

def update_jsonl_entry(pmid, new_data):
    with file_update_lock:
        if not os.path.exists(INPUT_FILE):
            logging.error(f"文件不存在: {INPUT_FILE}")
            return False
        lines = []
        with open(INPUT_FILE, 'r', encoding='utf-8') as f:
            lines = f.readlines()
        new_line = json.dumps(new_data, ensure_ascii=False) + '\n'
        found = False
        for i, line in enumerate(lines):
            line_stripped = line.strip()
            if not line_stripped:
                continue
            try:
                data = json.loads(line_stripped)
                if data.get('pmid') == pmid:
                    lines[i] = new_line
                    found = True
                    break
            except:
                continue
        if not found:
            logging.warning(f"未找到PMID {pmid}，无法更新")
            return False
        with open(INPUT_FILE, 'w', encoding='utf-8') as f:
            f.writelines(lines)
        logging.info(f"已更新文件中的 PMID {pmid}")
        return True

def mark_completed(pmid):
    with write_lock:
        with open(COMPLETED_LOG, 'a', encoding='utf-8') as f:
            f.write(pmid + '\n')

def load_completed_pmids():
    completed = set()
    if os.path.exists(COMPLETED_LOG):
        with open(COMPLETED_LOG, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if line:
                    completed.add(line)
        logging.info(f"已加载 {len(completed)} 个已完成的PMID")
    return completed

def backup_original():
    if not os.path.exists(BACKUP_FILE):
        shutil.copy2(INPUT_FILE, BACKUP_FILE)
        logging.info(f"已备份原文件至 {BACKUP_FILE}")
    else:
        logging.info(f"备份文件已存在: {BACKUP_FILE}")

# ==========================================
if __name__ == "__main__":
    backup_original()
    completed = load_completed_pmids()
    
    # 读取所有条目
    all_entries = []
    with open(INPUT_FILE, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            try:
                entry = json.loads(line)
                all_entries.append(entry)
            except:
                logging.warning("跳过无效JSON行")
    
    # 统计缺失
    missing_stats = []  # (pmid, missing_names)
    total_pmids_with_missing = 0
    for entry in all_entries:
        pmid = entry.get('pmid')
        if pmid in completed:
            continue
        missing = collect_missing_entities(entry)
        if missing:
            missing_stats.append((pmid, missing))
            total_pmids_with_missing += 1
    
    logging.info(f"总文献数: {len(all_entries)}, 存在缺失实体的文献数: {total_pmids_with_missing}")
    if not missing_stats:
        logging.info("没有需要补全的实体，程序退出。")
        exit()
    
    # 保存统计
    with open(STATS_LOG, 'w', encoding='utf-8') as f:
        f.write("PMID\tMissing_Count\tMissing_Names\n")
        for pmid, missing in missing_stats:
            f.write(f"{pmid}\t{len(missing)}\t{', '.join(missing)}\n")
    logging.info(f"缺失实体统计已保存至 {STATS_LOG}")
    
    to_process = missing_stats
    logging.info(f"待处理文献数: {len(to_process)}")
    
    pmid_list = [pmid for pmid, _ in to_process]
    batches = [pmid_list[i:i+NCBI_BATCH_SIZE] for i in range(0, len(pmid_list), NCBI_BATCH_SIZE)]
    logging.info(f"共分为 {len(batches)} 个批次，每批最多 {NCBI_BATCH_SIZE} 篇")
    
    total_pmids_fixed = 0
    total_entities_added = 0
    per_pmid_stats = []
    failed_details = {}  # 存储未成功添加的详细诊断信息
    
    pmid_to_missing = {pmid: missing for pmid, missing in to_process}
    
    for batch_idx, batch in enumerate(batches, 1):
        logging.info(f"开始处理第 {batch_idx}/{len(batches)} 批次，包含 {len(batch)} 篇文献")
        details = fetch_batch_details(batch)
        with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
            future_to_pmid = {}
            for pmid in batch:
                title, abstract = details.get(pmid, (None, None))
                if title is None:
                    logging.warning(f"PMID {pmid} 摘要获取失败，跳过")
                    # 记录失败详情
                    failed_details[pmid] = {
                        "missing_names": pmid_to_missing[pmid],
                        "error": "fetch_failed",
                        "api_response": None
                    }
                    continue
                missing_names = pmid_to_missing[pmid]
                future = executor.submit(call_deepseek_fix, pmid, title, abstract, missing_names)
                future_to_pmid[future] = pmid
            
            for future in as_completed(future_to_pmid):
                pmid = future_to_pmid[future]
                try:
                    new_entities = future.result()
                    missing_names = pmid_to_missing[pmid]
                    if not new_entities:
                        logging.warning(f"PMID {pmid} API返回空，缺失: {missing_names}")
                        failed_details[pmid] = {
                            "missing_names": missing_names,
                            "api_response": None,
                            "reason": "api_returned_empty"
                        }
                        continue
                    
                    # 找到原始entry
                    original_entry = None
                    for entry in all_entries:
                        if entry.get('pmid') == pmid:
                            original_entry = entry
                            break
                    if original_entry is None:
                        logging.error(f"未找到PMID {pmid} 的原始数据")
                        failed_details[pmid] = {
                            "missing_names": missing_names,
                            "api_response": new_entities,
                            "reason": "original_entry_not_found"
                        }
                        continue
                    
                    updated_entry, added, skipped_reasons = merge_entities(original_entry, new_entities, missing_names)
                    if added > 0:
                        success = update_jsonl_entry(pmid, updated_entry)
                        if success:
                            mark_completed(pmid)
                            total_pmids_fixed += 1
                            total_entities_added += added
                            per_pmid_stats.append((pmid, added))
                            logging.info(f"PMID {pmid} 补全成功，添加了 {added} 个新实体")
                        else:
                            logging.error(f"PMID {pmid} 文件更新失败")
                            failed_details[pmid] = {
                                "missing_names": missing_names,
                                "api_response": new_entities,
                                "skipped_reasons": skipped_reasons,
                                "reason": "file_update_failed"
                            }
                    else:
                        logging.warning(f"PMID {pmid} 无新实体可添加。缺失: {missing_names}, API返回: {new_entities}, 跳过原因: {skipped_reasons}")
                        failed_details[pmid] = {
                            "missing_names": missing_names,
                            "api_response": new_entities,
                            "skipped_reasons": skipped_reasons,
                            "reason": "no_entity_added"
                        }
                        # 对于这种确定无法补全的，可以选择标记完成以避免重复处理？暂不标记，留待人工审查
                except Exception as e:
                    logging.error(f"处理PMID {pmid} 时发生异常: {e}")
                    failed_details[pmid] = {
                        "missing_names": pmid_to_missing.get(pmid, []),
                        "error": str(e),
                        "api_response": None
                    }
    
    # 保存诊断报告
    with open(DIAGNOSTIC_LOG, 'w', encoding='utf-8') as f:
        json.dump(failed_details, f, indent=2, ensure_ascii=False)
    logging.info(f"诊断报告已保存至 {DIAGNOSTIC_LOG}")
    
    logging.info("\n========== 最终统计 ==========")
    logging.info(f"存在缺失实体的总文献数: {total_pmids_with_missing}")
    logging.info(f"成功补全的文献数: {total_pmids_fixed}")
    logging.info(f"总共添加的实体数: {total_entities_added}")
    if per_pmid_stats:
        avg = total_entities_added / len(per_pmid_stats)
        logging.info(f"平均每篇添加实体数: {avg:.2f}")
        with open(STATS_LOG, 'a', encoding='utf-8') as f:
            f.write("\n=== 补全结果详情 ===\n")
            f.write("PMID\tAdded_Entities\n")
            for pmid, added in per_pmid_stats:
                f.write(f"{pmid}\t{added}\n")
    logging.info(f"详细统计已保存至: {STATS_LOG}")
    logging.info(f"完成日志: {COMPLETED_LOG}")
    logging.info(f"失败诊断: {DIAGNOSTIC_LOG}")