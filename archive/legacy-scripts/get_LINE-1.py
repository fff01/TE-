import pandas as pd
import requests
import json
import time

# ====== 配置区域 ======
API_KEY = "sk-***"          # DeepSeek API密钥
API_URL = "https://api.deepseek.com/v1/chat/completions"  # DeepSeek API端点
CSV_FILE = r"C:\Users\fongi\Desktop\TE\LINE1_pubmed_data.csv"  # 输入文件路径
OUTPUT_FILE = "output.jsonl"            # 输出文件
NUM_ROWS = None                             # 测试
# =====================

# 读取CSV文件，仅读取前NUM_ROWS行
df = pd.read_csv(CSV_FILE, nrows=NUM_ROWS)
print(f"成功读取 {len(df)} 条论文数据。")

# 提示词
PROMPT_TEMPLATE = """
你是一个专业的生物医学信息提取助手，专门处理PubMed论文摘要，用于构建转座子知识图谱。

我将提供一篇论文的信息：
- PMID: {pmid}
- 标题: {title}
- 摘要: {abstract}

【任务目标】
请仔细阅读摘要，提取与**LINE-1转座子**相关的实体和关系。重点关注**活性、致病机制、表达异常、插入突变**等与功能直接相关的描述，对于推测性或进化历史的描述可忽略。

请严格按照以下步骤和格式要求，以**JSON格式**输出一个对象（注意：只输出一个JSON对象，不要添加额外解释）。

#### 步骤1：实体识别
从摘要中，识别并提取以下四类实体。请确保实体名称尽量标准化，并为每个实体生成一句简洁的描述。

- **转座子实体 (Transposon)**：识别属于LINE-1超家族的成员。
  - **优先级别**：如果摘要明确提到亚家族（如 L1HS, L1PA1, L1-Ta, L1.3, L1RP 等），则提取为具体亚家族。
  - 如果未提及亚家族，仅提到LINE-1/L1，则提取为"LINE-1"。
  - *实体描述示例*：L1HS是人类基因组中唯一活跃的LINE-1亚家族。
- **疾病实体 (Disease)**：摘要中提到的与转座子相关的任何疾病名称。
  - *实体描述示例*：血友病A是一种因凝血因子VIII缺乏导致的遗传性出血疾病。
- **生物学功能/机制实体 (Function)**：转座子参与的生物学过程或分子机制。
  - *实体描述示例*：逆转录转座是LINE-1通过"复制-粘贴"机制在基因组中移动的过程。
- **论文实体 (Paper)**：代表当前正在处理的文献。
  - *实体描述*：直接使用论文标题的中文翻译。

#### 步骤2：关系抽取
对于步骤1中识别的实体，提取它们之间的语义关系。每条关系应表达为一个完整的"实体-关系-实体"三元组。

常用关系类型参考：
- 转座子 — **导致/诱发** — 疾病
- 转座子 — **促进/增加** — 疾病风险
- 转座子 — **参与/介导** — 功能/机制
- 功能/机制 — **引发** — 疾病
- 论文 — **报道/描述** — 任何实体

#### 步骤3：输出格式
请输出一个JSON对象，包含以下字段：
- `pmid`: 论文的PMID编号（字符串类型）
- `entities`: 包含本论文中识别出的所有实体，按类型分组，每个实体包含 `name` 和 `description`。
- `relations`: 包含本论文中提取出的所有关系，每条关系包含 `source`, `relation`, `target`, `description`。

**输出示例：**
{{
  "pmid": "12345678",
  "entities": {{
    "transposons": [{{"name": "L1HS", "description": "L1HS是人类基因组中唯一活跃的LINE-1亚家族。"}}],
    "diseases": [{{"name": "肝细胞癌", "description": "肝细胞癌是最常见的原发性肝癌类型。"}}],
    "functions": [{{"name": "插入突变", "description": "插入突变是指转座子插入基因组导致基因功能破坏的过程。"}}]
  }},
  "relations": [
    {{"source": "L1HS", "relation": "导致", "target": "插入突变", "description": "L1HS通过逆转录转座导致插入突变的发生。"}},
    {{"source": "插入突变", "relation": "引发", "target": "肝细胞癌", "description": "插入突变可能引发肿瘤抑制基因失活，从而促进肝细胞癌。"}}
  ]
}}

【重要注意事项】
1. 优先活性与功能：关注描述活性、致病机制、表达异常、DNA损伤、插入多态性等。
2. 实体唯一性：同一篇论文内，相同的实体只记录一次。
3. 描述的准确性：基于摘要内容概括，保持简洁。
4. 关系完整性：尽量提取明确的关系。
5. 语言：实体名称可用英文，实体描述和关系描述必须用中文。
"""

def call_deepseek_api(pmid, title, abstract):
    #调用DeepSeek API处理单篇论文
    prompt = PROMPT_TEMPLATE.format(pmid=pmid, title=title, abstract=abstract)
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }
    payload = {
        "model": "deepseek-chat",          
        "messages": [
            {"role": "system", "content": "你是一个专业的生物医学信息提取助手。"},
            {"role": "user", "content": prompt}
        ],
        "temperature": 0.3,                 # 较低温度保证输出稳定性
        "max_tokens": 4096
    }

    try:
        response = requests.post(API_URL, headers=headers, json=payload)
        response.raise_for_status()
        result = response.json()
        content = result['choices'][0]['message']['content']

        # 清理可能出现的Markdown代码块标记
        content = content.strip()
        if content.startswith('```json'):
            content = content[7:]
            if content.endswith('```'):
                content = content[:-3]
        elif content.startswith('```'):
            content = content[3:]
            if content.endswith('```'):
                content = content[:-3]
        content = content.strip()

        # 解析JSON
        data = json.loads(content)
        return data
    except Exception as e:
        print(f"  错误 (PMID {pmid}): {e}")
        if 'content' in locals():
            print(f"  返回内容片段: {content[:200]}...")
        return None

# 主循环
with open(OUTPUT_FILE, 'w', encoding='utf-8') as out_f:
    for idx, row in df.iterrows():
        pmid = str(row['PMID'])
        title = row['Title']
        abstract = row['Abstract']
        print(f"处理中 ({idx+1}/{len(df)}): PMID {pmid}")
        result = call_deepseek_api(pmid, title, abstract)
        if result:
            out_f.write(json.dumps(result, ensure_ascii=False) + '\n')
            out_f.flush()
            print(f"  成功")
        else:
            print(f"  跳过")
        time.sleep(0.5)   # 简单限流，避免超过API速率限制

print(f"\n处理完成！结果已保存至: {OUTPUT_FILE}")

