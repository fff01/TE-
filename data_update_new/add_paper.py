import json
import requests
import time
import os
import shutil

# ================== 配置区域 ==================
# 你可以根据需要修改以下变量
INPUT_FILE = r"C:\Users\fongi\Desktop\TE\data_update\te_kg2_final_standardized.jsonl"
BATCH_SIZE = 200      # 每批请求处理的论文数量，推荐200
RATE_LIMIT = 0.34     # 两次API请求之间的最小间隔（秒），避免请求过于频繁
# ============================================

def backup_file(file_path):
    """备份原始文件"""
    backup_path = file_path + ".backup"
    try:
        shutil.copy2(file_path, backup_path)
        print(f"已备份文件至: {backup_path}")
    except Exception as e:
        print(f"备份文件时出错: {e}")
        raise e

def fetch_titles_batch(pmids):
    """
    批量获取一组PMID对应的论文标题。
    使用NCBI的ESummary API。
    """
    if not pmids:
        return {}
    
    # 将PMID列表转换为逗号分隔的字符串
    pmids_str = ",".join(pmids)
    
    # API基础URL
    base_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi"
    
    # 请求参数
    params = {
        "db": "pubmed",
        "id": pmids_str,
        "retmode": "json",  # 要求返回JSON格式，方便处理
        "retmax": BATCH_SIZE,
    }
    
    # 添加一个简单的User-Agent，让服务器知道请求的来源
    headers = {
        "User-Agent": "MyPaperUpdateScript/1.0 (your_email@example.com)"  # 建议替换为真实邮箱
    }
    
    try:
        response = requests.get(base_url, params=params, headers=headers)
        response.raise_for_status()  # 如果请求出错，会抛出异常
        data = response.json()
        
        # 从返回的JSON中提取标题
        titles = {}
        # 结果存储在 data['result'] 中，key是PMID字符串
        for pmid, info in data['result'].items():
            # 跳过 'uids' 这个元数据字段
            if pmid != 'uids':
                titles[pmid] = info.get('title', 'Title not found')
        
        return titles
        
    except requests.exceptions.RequestException as e:
        print(f"API请求出错: {e}")
        return {}
    except json.JSONDecodeError as e:
        print(f"解析API返回的JSON时出错: {e}")
        return {}

def collect_all_pmids(file_path):
    """从JSONL文件中收集所有唯一的PMID"""
    pmids = set()
    entries = []
    
    print(f"正在读取文件: {file_path}")
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            for line_num, line in enumerate(f, 1):
                line = line.strip()
                if not line:
                    continue
                try:
                    entry = json.loads(line)
                    if 'pmid' in entry and entry['pmid']:
                        pmids.add(entry['pmid'])
                        entries.append(entry)
                    else:
                        print(f"第{line_num}行缺少'pmid'字段，已跳过。")
                except json.JSONDecodeError:
                    print(f"第{line_num}行JSON解析失败，已跳过。")
    except FileNotFoundError:
        print(f"错误：文件未找到 - {file_path}")
        raise
    except Exception as e:
        print(f"读取文件时出错: {e}")
        raise
    
    print(f"共找到 {len(pmids)} 个唯一的PMID，总条目 {len(entries)} 条。")
    return list(pmids), entries

def update_entries_with_titles(entries, all_titles):
    """为每个条目添加'paper'实体"""
    updated_entries = []
    for entry in entries:
        pmid = entry['pmid']
        paper_title = all_titles.get(pmid, "Title not found")
        
        # 确保 'entities' 字段存在
        if 'entities' not in entry:
            entry['entities'] = {}
        
        # 添加 'paper' 实体
        entry['entities']['paper'] = [{
            "name": paper_title,
            "description": paper_title
        }]
        
        updated_entries.append(entry)
    
    return updated_entries

def save_entries(file_path, entries):
    """将更新后的条目保存回原文件"""
    try:
        with open(file_path, 'w', encoding='utf-8') as f:
            for entry in entries:
                f.write(json.dumps(entry, ensure_ascii=False) + '\n')
        print(f"成功保存更新后的文件至: {file_path}")
    except Exception as e:
        print(f"保存文件时出错: {e}")
        raise

def main():
    print("脚本开始运行...")
    
    # 1. 备份原始文件
    #backup_file(INPUT_FILE)
    
    # 2. 收集所有PMID和原始数据
    all_pmids, original_entries = collect_all_pmids(INPUT_FILE)
    
    from collections import Counter
    pmid_counts = Counter(entry['pmid'] for entry in original_entries)
    duplicates = {pmid: count for pmid, count in pmid_counts.items() if count > 1}
    print(f"重复的PMID: {duplicates}")
    # 3. 分批获取论文标题
    all_titles = {}
    total_batches = (len(all_pmids) + BATCH_SIZE - 1) // BATCH_SIZE
    
    for i in range(0, len(all_pmids), BATCH_SIZE):
        batch = all_pmids[i:i + BATCH_SIZE]
        batch_num = i // BATCH_SIZE + 1
        print(f"📡 正在处理第 {batch_num}/{total_batches} 批 (PMID: {batch[0]} ...)")
        
        titles_batch = fetch_titles_batch(batch)
        all_titles.update(titles_batch)
        
        # 遵守速率限制，避免请求过于频繁
        if i + BATCH_SIZE < len(all_pmids):
            time.sleep(RATE_LIMIT)
    
    print(f"成功获取了 {len(all_titles)} 篇论文的标题。")
    
    missing_pmids = [pmid for pmid in all_pmids if pmid not in all_titles]
    if missing_pmids:
        print(f"⚠️ 未能获取标题的 PMID 共 {len(missing_pmids)} 个：")
        print(missing_pmids)
    else:
        print("✅ 所有 PMID 均成功获取标题。")

    # 4. 更新数据条目
    updated_entries = update_entries_with_titles(original_entries, all_titles)
    
    # 5. 保存最终结果
    save_entries(INPUT_FILE, updated_entries)
    
    print("所有操作完成！")

if __name__ == "__main__":
    main()
# 在获取完 all_titles 后添加
