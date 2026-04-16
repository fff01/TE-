import json
import shutil
from pathlib import Path
from collections import defaultdict
import pandas as pd

# ---------- 辅助函数 ----------
def build_synonym_map(excel_path: Path):
    """从Excel构建同义词映射：同义词 -> 标准词（每行第一列）"""
    synonym_map = {}
    xl = pd.ExcelFile(excel_path)
    for sheet_name in xl.sheet_names:
        df = pd.read_excel(excel_path, sheet_name=sheet_name, header=None)
        for _, row in df.iterrows():
            if row.isnull().all():
                continue
            standard = str(row[0]).strip() if pd.notna(row[0]) else None
            if not standard:
                continue
            for col in range(1, len(row)):
                val = row[col]
                if pd.notna(val):
                    syn = str(val).strip()
                    if syn:
                        synonym_map[syn] = standard
    return synonym_map

def apply_synonym_map(obj_list, synonym_map):
    for obj in obj_list:
        if 'name' in obj and obj['name'] in synonym_map:
            obj['name'] = synonym_map[obj['name']]
        if 'source' in obj and obj['source'] in synonym_map:
            obj['source'] = synonym_map[obj['source']]
        if 'target' in obj and obj['target'] in synonym_map:
            obj['target'] = synonym_map[obj['target']]

# ---------- 优化后的等价分组函数（使用标准化键）----------
def group_by_key(strings, key_func):
    """根据 key_func 生成的键进行分组，返回 {key: [索引列表]}"""
    groups = defaultdict(list)
    for idx, s in enumerate(strings):
        groups[key_func(s)].append(idx)
    return groups

def apply_hyphen_rule_optimized(str_list):
    """
    连字符规则：生成两种标准化形式（空格替换 / 删除），如果任一相同则视为等价。
    等价类中所有字符串统一为第一次出现的原始字符串。
    """
    # 构建映射：标准化键 -> 第一次出现的索引
    first_index_for_key = {}
    index_to_standard = {}
    for idx, s in enumerate(str_list):
        # 生成两种键
        key1 = s.replace('-', ' ').lower()
        key2 = s.replace('-', '').lower()
        # 如果任一键已存在，则属于已有组
        found_key = None
        if key1 in first_index_for_key:
            found_key = key1
        elif key2 in first_index_for_key:
            found_key = key2
        if found_key is not None:
            # 属于已有组，记录应标准化为组内第一个字符串
            first_idx = first_index_for_key[found_key]
            index_to_standard[idx] = str_list[first_idx]
        else:
            # 新组，将自己作为第一个
            first_index_for_key[key1] = idx
            first_index_for_key[key2] = idx
            index_to_standard[idx] = s  # 自身作为标准
    # 应用标准化
    for idx, standard in index_to_standard.items():
        str_list[idx] = standard

def apply_plural_rule_optimized(str_list):
    """
    复数规则：仅当两个或更多字符串仅差末尾小写s时，才统一为单数形式。
    使用标准化键：去掉末尾s（如果有）作为键，且要求组内至少有2个不同原始字符串。
    """
    # 构建原始字符串到其“单数基”的映射
    base_to_indices = defaultdict(list)
    for idx, s in enumerate(str_list):
        s_low = s.lower()
        if s_low.endswith('s'):
            base = s_low[:-1]  # 去掉末尾s
        else:
            base = s_low
        base_to_indices[base].append(idx)
    # 处理每个基组
    for base, indices in base_to_indices.items():
        if len(indices) < 2:
            continue  # 没有配对，不处理
        # 确定标准字符串：优先选择组内不以's'结尾的原始字符串（保持原样）
        standard = None
        for idx in indices:
            s = str_list[idx]
            if not s.lower().endswith('s'):
                standard = s
                break
        if standard is None:
            # 全部以s结尾，取第一个并去掉末尾s
            first_s = str_list[indices[0]]
            standard = first_s[:-1] if first_s.lower().endswith('s') else first_s
        # 统一所有为该标准
        for idx in indices:
            str_list[idx] = standard

def apply_case_rule_optimized(str_list):
    """大小写规则：忽略大小写相同的字符串统一为第一个出现的大小写形式"""
    # 键：小写形式 -> 第一次出现的索引
    lower_to_first_idx = {}
    index_to_standard = {}
    for idx, s in enumerate(str_list):
        key = s.lower()
        if key in lower_to_first_idx:
            first_idx = lower_to_first_idx[key]
            index_to_standard[idx] = str_list[first_idx]
        else:
            lower_to_first_idx[key] = idx
            index_to_standard[idx] = s
    for idx, standard in index_to_standard.items():
        str_list[idx] = standard

# ---------- 主流程 ----------
def main():
    excel_path = Path(r"C:\Users\fongi\Desktop\TE\entity_clusters.xlsx")
    jsonl_path = Path(r"C:\Users\fongi\Desktop\TE\data_update\te_kg2_final_standardized.jsonl")

    # 1. 备份
    backup_path = jsonl_path.with_suffix(jsonl_path.suffix + '.backup')
    if not backup_path.exists():
        shutil.copy2(jsonl_path, backup_path)
        print(f"已备份至: {backup_path}")
    else:
        print(f"备份文件已存在，跳过备份: {backup_path}")

    # 2. 读取同义词映射
    print("读取同义词映射...")
    synonym_map = build_synonym_map(excel_path)
    print(f"共加载 {len(synonym_map)} 条同义词映射")

    # 3. 读取JSONL
    records = []
    with open(jsonl_path, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line:
                records.append(json.loads(line))

    # 4. 应用同义词映射
    print("应用同义词映射...")
    for rec in records:
        entities = rec.get("entities", {})
        for ent_list in entities.values():
            apply_synonym_map(ent_list, synonym_map)
        relations = rec.get("relations", [])
        apply_synonym_map(relations, synonym_map)

    # 5. 收集所有需要标准化的字符串及位置
    str_list = []
    str_locations = []  # 每个元素: (类型, record_idx, ...)
    for rec_idx, rec in enumerate(records):
        # 实体
        entities = rec.get("entities", {})
        for ent_type, ent_list in entities.items():
            for ent_idx, ent in enumerate(ent_list):
                if 'name' in ent:
                    str_list.append(ent['name'])
                    str_locations.append(('entity', rec_idx, ent_type, ent_idx, 'name'))
        # 关系
        relations = rec.get("relations", [])
        for rel_idx, rel in enumerate(relations):
            if 'source' in rel:
                str_list.append(rel['source'])
                str_locations.append(('relation', rec_idx, rel_idx, 'source'))
            if 'target' in rel:
                str_list.append(rel['target'])
                str_locations.append(('relation', rec_idx, rel_idx, 'target'))

    print(f"共收集到 {len(str_list)} 个待标准化字符串")

    # 6. 依次应用三个规则（优化版）
    apply_hyphen_rule_optimized(str_list)
    print("连字符规则应用完成")
    apply_plural_rule_optimized(str_list)
    print("复数规则应用完成")
    apply_case_rule_optimized(str_list)
    print("大小写规则应用完成")

    # 7. 写回
    for (loc, new_val) in zip(str_locations, str_list):
        if loc[0] == 'entity':
            _, rec_idx, ent_type, ent_idx, field = loc
            records[rec_idx]['entities'][ent_type][ent_idx][field] = new_val
        else:  # relation
            _, rec_idx, rel_idx, field = loc
            records[rec_idx]['relations'][rel_idx][field] = new_val

    # 8. 保存
    with open(jsonl_path, 'w', encoding='utf-8') as f:
        for rec in records:
            f.write(json.dumps(rec, ensure_ascii=False) + '\n')

    print(f"标准化完成，已保存至: {jsonl_path}")

if __name__ == "__main__":
    main()