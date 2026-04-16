import json
import pandas as pd
from pathlib import Path

def load_pmids(file_path: Path) -> set:
    """从 JSONL 文件中提取所有 pmid，返回集合（自动去重）"""
    pmids = set()
    with open(file_path, 'r', encoding='utf-8') as f:
        for line_num, line in enumerate(f, 1):
            line = line.strip()
            if not line:
                continue
            try:
                data = json.loads(line)
                if 'pmid' in data:
                    pmids.add(data['pmid'])
                else:
                    print(f"警告：文件 {file_path} 第 {line_num} 行缺少 'pmid' 字段")
            except json.JSONDecodeError as e:
                print(f"警告：文件 {file_path} 第 {line_num} 行 JSON 解析失败: {e}")
    return pmids

def compare_pmids(original_set: set, update_set: set):
    """计算并打印比较结果，同时将独有 pmid 写入 Excel"""
    only_original = original_set - update_set
    only_update = update_set - original_set
    common = original_set & update_set
    union = original_set | update_set

    print("=" * 50)
    print("PMID 覆盖情况统计")
    print("=" * 50)
    print(f"原始文件 (te_kg2.jsonl)       PMID 数量: {len(original_set)}")
    print(f"更新文件 (te_kg2_update.jsonl) PMID 数量: {len(update_set)}")
    print("-" * 50)
    print(f"共同 PMID 数量 (交集):         {len(common)}")
    print(f"仅原始文件 PMID 数量:          {len(only_original)}")
    print(f"仅更新文件 PMID 数量:          {len(only_update)}")
    print(f"全部 PMID 数量 (并集):         {len(union)}")
    print("=" * 50)


    # 将独有 PMID 写入 Excel 文件（两个工作表）
    output_excel = Path("unmatched_pmids.xlsx")
    with pd.ExcelWriter(output_excel, engine='openpyxl') as writer:
        # 工作表1：仅原始文件独有的 PMID
        df_original = pd.DataFrame(sorted(only_original), columns=["pmid"]) if only_original else pd.DataFrame(columns=["pmid"])
        df_original.to_excel(writer, sheet_name="only_original", index=False)
        
        # 工作表2：仅更新文件独有的 PMID
        df_update = pd.DataFrame(sorted(only_update), columns=["pmid"]) if only_update else pd.DataFrame(columns=["pmid"])
        df_update.to_excel(writer, sheet_name="only_update", index=False)
    
    print(f"\n独有 PMID 已写入 Excel 文件：{output_excel.absolute()}")
    print(f"  - 工作表 'only_original'：{len(only_original)} 条记录")
    print(f"  - 工作表 'only_update' ：{len(only_update)} 条记录")

def main():
    original_path = Path(r"C:\Users\fongi\Desktop\TE\data\te_kg2.jsonl")
    update_path = Path(r"C:\Users\fongi\Desktop\TE\data_update\te_kg2_update.jsonl")

    if not original_path.is_file():
        print(f"错误：找不到原始文件 {original_path}")
        return
    if not update_path.is_file():
        print(f"错误：找不到更新文件 {update_path}")
        return

    print("正在加载原始文件中的 pmid...")
    original_pmids = load_pmids(original_path)
    print("正在加载更新文件中的 pmid...")
    update_pmids = load_pmids(update_path)

    compare_pmids(original_pmids, update_pmids)

if __name__ == "__main__":
    main()