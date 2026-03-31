import re

# 定义要排除的宽泛术语（全小写，不含末尾句点）
EXCLUDE_TERMS = {
    'transposable element',
    'retrotransposable element',
    'transposon',
    'retrotransposon',
    'transposons',
    'retrotransposons'
}

def extract_te_names(input_file, output_file):
    """
    从转座子数据库文件中提取所有人类转座子名称，并排除过于宽泛的术语。
    - 提取每个条目 ID 行的名称（第二个字段）。
    - 提取每个条目 KW 行中以分号分隔的所有关键词（去除首尾空格和末尾句点）。
    将所有唯一名称写入输出文件，用分号分隔。
    """
    names = set()
    
    with open(input_file, 'r', encoding='utf-8') as f:
        lines = f.readlines()
    
    i = 0
    while i < len(lines):
        line = lines[i].strip()
        
        # 处理 ID 行
        if line.startswith('ID'):
            parts = line.split()
            if len(parts) >= 2:
                id_name = parts[1].strip().rstrip('.')
                if id_name.lower() not in EXCLUDE_TERMS:
                    names.add(id_name)
        
        # 处理 KW 行
        if line.startswith('KW'):
            kw_text = line[5:].strip()  # 去掉 'KW   '
            # 收集后续可能续行的 KW 内容（以 5 个空格开头）
            while i + 1 < len(lines) and lines[i+1].startswith(' ' * 5):
                i += 1
                kw_text += ' ' + lines[i].strip()
            # 按分号分割关键词
            for item in kw_text.split(';'):
                clean_item = item.strip().rstrip('.')  # 去除空格和末尾句点
                if clean_item and clean_item.lower() not in EXCLUDE_TERMS:
                    names.add(clean_item)
        
        i += 1
    
    # 排序以便输出稳定
    sorted_names = sorted(names)
    output_str = ';'.join(sorted_names)
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(output_str)
    
    print(f"共提取 {len(sorted_names)} 个唯一名称，已写入 {output_file}")

if __name__ == "__main__":
    input_path = r"C:\Users\fongi\Desktop\TE\data\TE_Repbase.txt"
    output_path = r"C:\Users\fongi\Desktop\TE\data\TE_names.txt"
    extract_te_names(input_path, output_path)