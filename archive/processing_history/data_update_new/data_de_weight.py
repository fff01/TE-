import json
import sys
from difflib import SequenceMatcher
from collections import defaultdict
import pandas as pd
from pathlib import Path

def similar(a: str, b: str, threshold: float = 0.8) -> bool:
    """返回两个字符串的相似度是否超过阈值（使用 SequenceMatcher）"""
    return SequenceMatcher(None, a, b).ratio() > threshold

def cluster_names(names: list, threshold: float = 0.8) -> list:
    """
    将名称列表按相似度 > threshold 进行聚类（传递闭包）。
    返回列表的列表，每个子列表是一组相似名称。
    """
    if not names:
        return []
    # 为避免重复比较，先排序（可选）
    names = sorted(set(names))
    n = len(names)
    visited = [False] * n
    clusters = []

    for i in range(n):
        if visited[i]:
            continue
        # 开始新簇
        cluster = [names[i]]
        visited[i] = True
        # 使用队列收集所有与当前簇中任一元素相似的名字
        queue = [i]
        while queue:
            cur = queue.pop()
            for j in range(n):
                if not visited[j] and similar(names[cur], names[j], threshold):
                    visited[j] = True
                    cluster.append(names[j])
                    queue.append(j)
        clusters.append(sorted(cluster))
    return clusters

def main(jsonl_path: str, output_excel: str = "entity_clusters.xlsx"):
    # 需要处理的实体类型（排除 diseases 和 paper）
    target_types = [
        "transposons", "functions", "genes", "rnas",
        "proteins", "carbohydrates", "lipids", "peptides",
        "pharmaceuticals", "toxins"
    ]
    # 存储每种类型的名称集合（去重）
    entity_sets = {t: set() for t in target_types}

    # 读取 JSONL 文件
    with open(jsonl_path, 'r', encoding='utf-8') as f:
        for line_num, line in enumerate(f, 1):
            line = line.strip()
            if not line:
                continue
            try:
                data = json.loads(line)
            except json.JSONDecodeError as e:
                print(f"警告：第 {line_num} 行 JSON 解析失败，跳过。错误：{e}")
                continue
            entities = data.get("entities", {})
            for t in target_types:
                if t in entities:
                    for ent in entities[t]:
                        name = ent.get("name")
                        if name:
                            entity_sets[t].add(name)

    # 准备写入 Excel
    with pd.ExcelWriter(output_excel, engine='openpyxl') as writer:
        for t in target_types:
            names = list(entity_sets[t])
            if not names:
                # 空工作表也写入提示
                df = pd.DataFrame({"信息": ["无数据"]})
                df.to_excel(writer, sheet_name=t, index=False)
                continue

            if t == "functions":
                # functions 类型：每个名称单独一行一列
                df = pd.DataFrame({"name": sorted(names)})
                df.to_excel(writer, sheet_name=t, index=False)
            else:
                # 其他类型：相似度聚类，每组一行，组内名称放在不同列
                clusters = cluster_names(names, threshold=0.8)
                # 确定最大列数
                max_cols = max(len(cluster) for cluster in clusters) if clusters else 0
                # 构建 DataFrame，每行对应一个簇，列名为 col_1, col_2, ...
                data = []
                for cluster in clusters:
                    # 补齐到相同列数（用 None 填充）
                    row = cluster + [None] * (max_cols - len(cluster))
                    data.append(row)
                df = pd.DataFrame(data, columns=[f"col_{i+1}" for i in range(max_cols)])
                df.to_excel(writer, sheet_name=t, index=False)

    print(f"处理完成！结果已保存至：{output_excel}")

if __name__ == "__main__":
    # 默认路径（请根据实际情况修改或使用命令行参数）
    default_path = r"C:\Users\fongi\Desktop\TE\data_update\te_kg2_final_standardized.jsonl"
    if len(sys.argv) > 1:
        file_path = sys.argv[1]
    else:
        file_path = default_path
        print(f"使用默认路径：{file_path}")

    if not Path(file_path).exists():
        print(f"错误：文件不存在 - {file_path}")
        sys.exit(1)

    main(file_path)