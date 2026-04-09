import json
from pathlib import Path
from collections import OrderedDict

# 尝试导入 openpyxl，若未安装则给出提示
try:
    from openpyxl import Workbook
except ImportError:
    raise ImportError("请安装 openpyxl 库：pip install openpyxl")

def extract_unique_diseases(jsonl_path):
    """
    从 JSONL 文件中提取 diseases 实体，按 name 去重。
    返回一个列表，每个元素为 (name, description) 元组。
    """
    disease_dict = OrderedDict()  # 保持插入顺序，按 name 去重

    with open(jsonl_path, 'r', encoding='utf-8') as f:
        for line_num, line in enumerate(f, 1):
            line = line.strip()
            if not line:
                continue
            try:
                data = json.loads(line)
            except json.JSONDecodeError as e:
                print(f"警告：第 {line_num} 行 JSON 解析失败，已跳过。错误：{e}")
                continue

            # 获取 entities.diseases 列表
            entities = data.get('entities')
            if not isinstance(entities, dict):
                continue
            diseases = entities.get('diseases')
            if not isinstance(diseases, list):
                continue

            for disease in diseases:
                if not isinstance(disease, dict):
                    continue
                name = disease.get('name')
                if not name:  # 名称是必须字段
                    continue
                description = disease.get('description', '')
                # 去重：如果 name 未出现过则添加，否则忽略
                if name not in disease_dict:
                    disease_dict[name] = description

    # 转换为列表形式便于写入 Excel
    return [(name, desc) for name, desc in disease_dict.items()]

def write_to_excel(data, output_path):
    """将去重后的疾病数据写入 Excel 文件"""
    wb = Workbook()
    ws = wb.active
    ws.title = "Diseases"

    # 写入表头
    ws.append(["Disease Name", "Description"])

    # 写入数据
    for name, desc in data:
        ws.append([name, desc])

    # 调整列宽（可选）
    ws.column_dimensions['A'].width = 30
    ws.column_dimensions['B'].width = 50

    wb.save(output_path)
    print(f"成功写入 {len(data)} 条疾病实体到 {output_path}")

def main():
    # 输入文件路径
    input_file = Path(r"C:\Users\fongi\Desktop\TE\data_update_fix\te_kg2_final_standardized_new_standardized_fix.jsonl")
    if not input_file.is_file():
        print(f"错误：找不到文件 {input_file}")
        return

    # 输出文件路径（同目录下，名称加 _diseases）
    output_file = r"C:\Users\fongi\Desktop\TE\disease_update_new\diseases3.xlsx"

    print(f"正在读取 {input_file} ...")
    diseases_data = extract_unique_diseases(input_file)

    if not diseases_data:
        print("警告：未找到任何疾病实体。")
        return

    print(f"提取到 {len(diseases_data)} 个唯一疾病实体。")
    write_to_excel(diseases_data, output_file)

if __name__ == "__main__":
    main()