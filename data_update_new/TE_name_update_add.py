import os

def process_te_names(rmsk_path, output_path):
    """
    从 rmsk.txt 提取转座子名称，生成包含变体的词汇表，写入 TE_names.txt
    """
    # 存储所有词汇（去重）
    te_set = set()

    # 1. 读取 rmsk.txt，提取第 11,12,13 列（索引 10,11,12）
    print(f"正在读取 {rmsk_path} ...")
    with open(rmsk_path, 'r', encoding='utf-8') as f:
        for line_no, line in enumerate(f, 1):
            line = line.strip()
            if not line:
                continue
            parts = line.split('\t')
            if len(parts) < 13:
                print(f"警告：第 {line_no} 行列数不足 13，跳过")
                continue
            # 提取三列
            col11 = parts[10].strip()
            col12 = parts[11].strip()
            col13 = parts[12].strip()
            # 添加到集合
            if col11:
                te_set.add(col11)
            if col12:
                te_set.add(col12)
            if col13:
                te_set.add(col13)

    # 2. 读取现有 TE_names.txt 中的词汇
    if os.path.exists(output_path):
        print(f"读取现有文件 {output_path} ...")
        with open(output_path, 'r', encoding='utf-8') as f:
            content = f.read().strip()
            if content:
                existing_terms = content.split(';')
                for term in existing_terms:
                    term = term.strip()
                    if term:
                        te_set.add(term)

    # 3. 为包含下划线的词汇生成变体
    new_variants = set()
    for term in te_set:
        if '_' in term:
            # 下划线替换为连字符
            variant1 = term.replace('_', '-')
            # 下划线替换为空格
            variant2 = term.replace('_', ' ')
            new_variants.add(variant1)
            new_variants.add(variant2)

    # 将变体合并到主集合
    te_set.update(new_variants)

    # 4. 排序（可选，便于阅读）
    sorted_terms = sorted(te_set)

    # 5. 写入输出文件（覆盖写，因为已合并原有内容）
    print(f"写入 {output_path}，共 {len(sorted_terms)} 个唯一词汇...")
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write(';'.join(sorted_terms))

    print("完成！")

if __name__ == "__main__":
    # 请根据实际路径修改
    input_file = r"C:\Users\fongi\Desktop\TE\data\rmsk.txt"
    output_file = r"C:\Users\fongi\Desktop\TE\data\TE_names.txt"
    process_te_names(input_file, output_file)