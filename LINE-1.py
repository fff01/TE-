import csv
import time
from Bio import Entrez

# ==========================================
# 配置与参数设置
Entrez.email = "sample@.com" #输入有效邮箱

# 检索式
search_query = '("L1" OR "LINE-1" OR "LINE1" OR "L1HS") AND ("retrotransposition") AND ("human" OR "homo sapiens")'

# 设置要获取的最大文献数量
MAX_RESULTS = 10000
OUTPUT_FILE = "LINE1_pubmed_data.csv"

def get_pmid_list(query, retmax):
    """
    使用 esearch 搜索 Pubmed 并获取相关的 PMID 列表
    """
    print(f"正在检索 Pubmed 数据库...")
    try:
        handle = Entrez.esearch(db="pubmed", term=query, retmax=retmax)
        record = Entrez.read(handle)
        handle.close()
        pmid_list = record.get("IdList", [])
        print(f"检索成功，共找到 {record.get('Count')} 篇相关文献，本次将提取前 {len(pmid_list)} 篇。")
        return pmid_list
    except Exception as e:
        print(f"检索失败: {e}")
        return []

def fetch_paper_details(pmid_list):
    """
    使用 efetch 批量获取文献的详细 XML 数据
    """
    print("正在下载文献详细信息...")
    try:
        # 将 PMID 列表用逗号拼接成字符串
        ids = ",".join(pmid_list)
        handle = Entrez.efetch(db="pubmed", id=ids, retmode="xml")
        records = Entrez.read(handle)
        handle.close()
        return records['PubmedArticle']
    except Exception as e:
        print(f"获取详细信息失败: {e}")
        return []

def extract_and_save_data(articles, filename):
    """
    解析 XML 数据，提取 PMID、标题和摘要，并保存为 CSV
    """
    print(f"正在解析数据并保存至 {filename} ...")
    
    with open(filename, mode="w", newline="", encoding="utf-8") as csvfile:
        writer = csv.writer(csvfile)
        # 写入表头
        writer.writerow(["PMID", "Title", "Abstract"])
        
        count = 0
        for article in articles:
            try:
                # 获取 MedlineCitation
                medline = article.get("MedlineCitation", {})
                article_info = medline.get("Article", {})
                
                # 1. 提取 PMID (登录号)
                pmid = str(medline.get("PMID", ""))
                
                # 2. 提取文章标题
                title = article_info.get("ArticleTitle", "")
                
                # 3. 提取摘要
                abstract_text = ""
                abstract = article_info.get("Abstract", {})
                if abstract and "AbstractText" in abstract:
                    # AbstractText 可能是一个列表（尤其是结构化摘要如 Background, Methods 等）
                    abstract_parts = abstract["AbstractText"]
                    if isinstance(abstract_parts, list):
                        abstract_text = " ".join(str(part) for part in abstract_parts)
                    else:
                        abstract_text = str(abstract_parts)
                
                # 如果没有摘要，可以选择跳过或者记录为空。这里选择记录，以防丢失仅有标题的重要文献
                if not abstract_text:
                    abstract_text = "No abstract available."

                # 写入 CSV
                writer.writerow([pmid, title, abstract_text])
                count += 1
                
            except Exception as e:
                print(f"解析 PMID 为 {medline.get('PMID', 'Unknown')} 的文章时出错跳过: {e}")
                continue
                
    print(f"处理完成！成功保存 {count} 条记录。")

if __name__ == "__main__":
    # 执行主流程
    pmids = get_pmid_list(search_query, MAX_RESULTS)
    
    if pmids:
        # 如超过上千篇，在每次 fetch 之间加入 time.sleep(1)
        articles_data = fetch_paper_details(pmids)
        if articles_data:
            extract_and_save_data(articles_data, OUTPUT_FILE)
    else:
        print("未获取到 PMID，程序结束。")

