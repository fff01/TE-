import json
import time
import re
import os
import pandas as pd
import requests
import urllib3
from pathlib import Path

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# ===================== 配置 =====================
ICD11_SEARCH_URL = "https://id.who.int/icd/release/11/2024-01/mms/search"
ENTITY_BASE = "https://id.who.int/icd/entity"
RELEASE_PARAM = "11/2024-01/mms"

CLIENT_ID = "e4fc5caa-346b-40c8-aceb-2645e95a85e2_93d3ab60-84d6-4dd5-a295-4ec0283c9166"
CLIENT_SECRET = "ou96wzC0jy7mSmHQ0eL/zoOSgn3Ot3uyLc9Yd62vIig="

ROOT = Path(__file__).resolve().parents[2]
DISEASE_UPDATE_DIR = ROOT / "archive" / "processing_history" / "disease_update_new"

INPUT_CSV = str(DISEASE_UPDATE_DIR / "diseases3.csv")
OUTPUT_EXCEL = str(DISEASE_UPDATE_DIR / "disease_classify_all_update.xlsx")
SLEEP_INTERVAL = 0.5
DEBUG_SEARCH = False

# ===================== 获取 token =====================
def get_token():
    token_endpoint = "https://icdaccessmanagement.who.int/connect/token"
    payload = {
        "client_id": CLIENT_ID,
        "client_secret": CLIENT_SECRET,
        "scope": "icdapi_access",
        "grant_type": "client_credentials"
    }
    try:
        response = requests.post(token_endpoint, data=payload, verify=False)
        response.raise_for_status()
        return response.json()["access_token"]
    except Exception as e:
        print(f"获取 token 失败: {e}")
        raise

# ===================== 搜索疾病 =====================
def search_entity(token, disease_name):
    headers = {
        "Authorization": f"Bearer {token}",
        "Accept": "application/json",
        "Accept-Language": "en",
        "API-Version": "v2"
    }
    params = {"q": disease_name, "limit": 1}
    try:
        response = requests.get(ICD11_SEARCH_URL, headers=headers, params=params, verify=False)
        if response.status_code != 200:
            return None
        data = response.json()
        if DEBUG_SEARCH:
            print(f"Search response for '{disease_name}':")
            print(json.dumps(data, indent=2, ensure_ascii=False)[:2000])
        if data.get("destinationEntities"):
            ent = data["destinationEntities"][0]
            full_uri = ent.get("id")
            if full_uri:
                return full_uri
            stem_uri = ent.get("stemId")
            if stem_uri:
                return stem_uri
        return None
    except Exception as e:
        print(f"搜索疾病 '{disease_name}' 时出错: {e}")
        return None

# ===================== 通过 URI 获取实体详情 =====================
def get_mms_entity_by_uri(token, entity_uri):
    if entity_uri.startswith("http://"):
        entity_uri = entity_uri.replace("http://", "https://", 1)
    headers = {
        "Authorization": f"Bearer {token}",
        "Accept": "application/json",
        "Accept-Language": "en",
        "API-Version": "v2"
    }
    try:
        response = requests.get(entity_uri, headers=headers, verify=False)
        response.raise_for_status()
        return response.json()
    except Exception as e:
        print(f"获取 MMS 实体 URI {entity_uri} 时出错: {e}")
        return None

# ===================== 获取实体的完整标题 =====================
def get_entity_full_title(entity):
    title = entity.get("title", {}).get("@value", "")
    if not title:
        return "Unknown"
    code = entity.get("code")
    if code and isinstance(code, str) and code.strip():
        return f"{code} {title}".strip()
    match = re.match(r'^([A-Z0-9.]+)\s+(.+)', title)
    if match:
        code_part = match.group(1)
        name_part = match.group(2)
        return f"{code_part} {name_part}".strip()
    return title

# ===================== 获取完整分类路径 =====================
def get_parents(token, entity_uri, debug=False):
    current_entity = get_mms_entity_by_uri(token, entity_uri)
    if not current_entity:
        return None
    titles = []
    depth = 0
    while depth < 20:
        depth += 1
        full_title = get_entity_full_title(current_entity)
        titles.append(full_title)
        if debug:
            print(f"  Depth {depth}: title = {full_title}")
        parent_uri = current_entity.get("parent")
        if not parent_uri:
            break
        if isinstance(parent_uri, list):
            parent_uri = parent_uri[0]
        parent_entity = get_mms_entity_by_uri(token, parent_uri)
        if not parent_entity:
            break
        parent_full_title = get_entity_full_title(parent_entity)
        if parent_full_title in ["ICD-11 for Mortality and Morbidity Statistics", "ICD Category", "ICD Entity"]:
            break
        current_entity = parent_entity
    return titles

# ===================== 从 CSV 读取疾病 =====================
def read_diseases_from_csv(csv_path):
    df = pd.read_csv(csv_path, encoding='utf-8')
    df_unique = df.drop_duplicates(subset=['name'], keep='first')
    diseases = df_unique['name'].tolist()
    desc_dict = dict(zip(df_unique['name'], df_unique['description']))
    return diseases, desc_dict

# ===================== 加载已有结果（支持断点重续）=====================
def load_existing_results(excel_path):
    """
    返回:
        found_entries: list of (disease, categories_list, description)
        not_found_entries: list of (disease, description)
        processed_set: set of disease names
    """
    found_entries = []
    not_found_entries = []
    processed_set = set()

    if not os.path.exists(excel_path):
        return found_entries, not_found_entries, processed_set

    try:
        # 读取 Found 工作表
        df_found = pd.read_excel(excel_path, sheet_name="Found")
        if not df_found.empty:
            # 获取所有 Category 列（以 "Category_" 开头的列）
            cat_cols = [col for col in df_found.columns if col.startswith("Category_")]
            cat_cols.sort(key=lambda x: int(x.split('_')[1]))
            # 确保 Description 列存在
            has_desc = "Description" in df_found.columns
            for _, row in df_found.iterrows():
                disease = row["Disease"]
                if pd.isna(disease):
                    continue
                # 提取分类路径（按顺序）
                cats = [str(row[col]) for col in cat_cols if pd.notna(row[col])]
                description = row["Description"] if has_desc and pd.notna(row["Description"]) else ""
                found_entries.append((disease, cats, description))
                processed_set.add(disease)
    except Exception as e:
        print(f"读取 Found 表时出错: {e}")

    try:
        # 读取 Not Found 工作表
        df_not_found = pd.read_excel(excel_path, sheet_name="Not Found")
        if not df_not_found.empty:
            has_desc = "Description" in df_not_found.columns
            for _, row in df_not_found.iterrows():
                disease = row["Disease"]
                if pd.isna(disease):
                    continue
                description = row["Description"] if has_desc and pd.notna(row["Description"]) else ""
                not_found_entries.append((disease, description))
                processed_set.add(disease)
    except Exception as e:
        print(f"读取 Not Found 表时出错: {e}")

    return found_entries, not_found_entries, processed_set

# ===================== 写入完整结果到 Excel =====================
def write_results(excel_path, found_entries, not_found_entries):
    """
    found_entries: list of (disease, categories_list, description)
    not_found_entries: list of (disease, description)
    """
    with pd.ExcelWriter(excel_path, engine="openpyxl") as writer:
        # ----- Found 工作表 -----
        if found_entries:
            # 计算最大分类层级数
            max_levels = max(len(cats) for _, cats, _ in found_entries)
            data = []
            for disease, cats, desc in found_entries:
                row = {"Disease": disease}
                for i, cat in enumerate(cats, start=1):
                    row[f"Category_{i}"] = cat
                row["Description"] = desc
                data.append(row)
            df_found = pd.DataFrame(data)
            # 构建列顺序：Disease, Category_1..Category_N, Description
            columns = ["Disease"] + [f"Category_{i}" for i in range(1, max_levels + 1)] + ["Description"]
            for col in columns:
                if col not in df_found.columns:
                    df_found[col] = None
            df_found = df_found[columns]
        else:
            df_found = pd.DataFrame(columns=["Disease", "Description"])
        df_found.to_excel(writer, sheet_name="Found", index=False)

        # ----- Not Found 工作表 -----
        if not_found_entries:
            empty_cols = [f"Empty_{i}" for i in range(1, 7)]
            data = []
            for disease, desc in not_found_entries:
                row = {"Disease": disease}
                for col in empty_cols:
                    row[col] = None
                row["Description"] = desc
                data.append(row)
            df_not_found = pd.DataFrame(data)
            columns = ["Disease"] + empty_cols + ["Description"]
            df_not_found = df_not_found[columns]
        else:
            df_not_found = pd.DataFrame(columns=["Disease"] + [f"Empty_{i}" for i in range(1,7)] + ["Description"])
        df_not_found.to_excel(writer, sheet_name="Not Found", index=False)

# ===================== 主流程 =====================
def main():
    # 读取 CSV 中的所有疾病
    if not os.path.exists(INPUT_CSV):
        print(f"错误：找不到 CSV 文件 {INPUT_CSV}")
        return
    all_diseases, desc_dict = read_diseases_from_csv(INPUT_CSV)
    print(f"从 CSV 读取到 {len(all_diseases)} 种疾病（去重后）")

    # 加载已有结果（断点重续）
    found_entries, not_found_entries, processed_set = load_existing_results(OUTPUT_EXCEL)
    print(f"已处理疾病数: {len(processed_set)} (成功: {len(found_entries)}, 失败: {len(not_found_entries)})")

    # 待处理疾病列表
    pending_diseases = [d for d in all_diseases if d not in processed_set]
    if not pending_diseases:
        print("所有疾病均已处理完毕，无需继续。")
        return

    print(f"剩余待处理疾病数: {len(pending_diseases)}")

    # 获取 token
    token = get_token()
    print("Token 获取成功")

    # 处理每个待处理疾病
    for i, disease in enumerate(pending_diseases, start=1):
        description = desc_dict.get(disease, "")
        print(f"处理 {i}/{len(pending_diseases)}: {disease}")

        entity_uri = search_entity(token, disease)
        if entity_uri:
            categories = get_parents(token, entity_uri, debug=False)
            if categories:
                found_entries.append((disease, categories, description))
                print(f"  -> 成功，分类层级数: {len(categories)}")
            else:
                not_found_entries.append((disease, description))
                print("  -> 无法获取分类路径")
        else:
            not_found_entries.append((disease, description))
            print("  -> 未找到实体")

        # 每处理一条就立即保存（保证断点重续）
        write_results(OUTPUT_EXCEL, found_entries, not_found_entries)
        time.sleep(SLEEP_INTERVAL)

    print(f"\n全部处理完成。最终结果已保存至 {OUTPUT_EXCEL}")
    print(f"成功分类: {len(found_entries)} 条，未找到: {len(not_found_entries)} 条")

if __name__ == "__main__":
    main()