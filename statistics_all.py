import pandas as pd
import os

def parse_tree(filepath):
    """
    解析树形文件，返回节点列表。
    每个节点为 {'name': str, 'depth': int, 'parent': int or None}
    深度通过 '── ' 的位置计算：depth = idx // 4 + 1（根节点 depth=0）
    """
    nodes = []
    with open(filepath, 'r', encoding='utf-8') as f:
        lines = [line.rstrip('\n') for line in f.readlines()]

    # 寻找根节点（第一个不含 '── ' 的非空行）
    root_idx = None
    start_idx = 0
    for i, line in enumerate(lines):
        if '── ' in line:
            continue
        stripped = line.strip()
        if stripped:
            root_name = stripped
            nodes.append({'name': root_name, 'depth': 0, 'parent': None})
            root_idx = 0
            start_idx = i + 1
            break
    if root_idx is None:
        raise ValueError("未找到根节点")

    stack = [(0, root_idx)]  # (depth, node_index)

    for line in lines[start_idx:]:
        if '── ' not in line:
            continue
        # 提取节点名
        _, name = line.split('── ', 1)
        name = name.strip()
        if not name:
            continue

        # 计算深度
        idx = line.find('── ')
        depth = idx // 4 + 1   # 根子节点 depth=1，根节点 depth=0

        # 维护栈：弹出深度不小于当前深度的节点
        while stack and stack[-1][0] >= depth:
            stack.pop()
        # 栈顶为父节点
        parent_idx = stack[-1][1]

        node_idx = len(nodes)
        nodes.append({'name': name, 'depth': depth, 'parent': parent_idx})
        stack.append((depth, node_idx))

    return nodes


def build_children(nodes):
    children = {i: [] for i in range(len(nodes))}
    for i, node in enumerate(nodes):
        p = node['parent']
        if p is not None:
            children[p].append(i)
    return children


def compute_leaf_counts(nodes, children):
    """返回每个节点子树的叶子数"""
    leaf_counts = {}
    def dfs(u):
        if u in leaf_counts:
            return leaf_counts[u]
        if not children[u]:
            leaf_counts[u] = 1
        else:
            leaf_counts[u] = sum(dfs(v) for v in children[u])
        return leaf_counts[u]
    dfs(0)
    return leaf_counts


def get_ancestor_class(nodes, idx):
    """向上寻找第一个 class 级别的祖先（'Class ' 开头或名为 'others'）"""
    p = nodes[idx]['parent']
    while p is not None:
        name = nodes[p]['name']
        if name.startswith('Class ') or name == 'others':
            return name
        p = nodes[p]['parent']
    return 'Unknown'


def get_ancestor_superfamily(nodes, idx):
    """向上寻找第一个以 'Superfamily:' 开头的祖先"""
    p = nodes[idx]['parent']
    while p is not None:
        if nodes[p]['name'].startswith('Superfamily:'):
            return nodes[p]['name']
        p = nodes[p]['parent']
    return 'Unknown'


def get_ancestor_family(nodes, idx):
    """向上寻找第一个以 'Family:' 开头的祖先"""
    p = nodes[idx]['parent']
    while p is not None:
        if nodes[p]['name'].startswith('Family:'):
            return nodes[p]['name']
        p = nodes[p]['parent']
    return 'Unknown'


def main():
    input_path = r"C:\Users\fongi\Desktop\TE\transposon_tree\tree_all_4.18_2.txt"
    output_dir = os.path.dirname(input_path)
    output_path = os.path.join(output_dir, "TE_tree_output_all.xlsx")

    # 1. 解析
    nodes = parse_tree(input_path)
    children = build_children(nodes)

    # 2. 收集叶子节点
    leaf_indices = [i for i in range(len(nodes)) if not children[i]]

    # 3. 生成每个叶子的路径（自底向上）
    paths = []
    for leaf in leaf_indices:
        path = [nodes[leaf]['name']]
        p = nodes[leaf]['parent']
        while p is not None:
            path.append(nodes[p]['name'])
            p = nodes[p]['parent']
        paths.append(path)

    max_len = max(len(p) for p in paths) if paths else 0
    columns = ["Element"] + [f"Ancestor_{i}" for i in range(1, max_len)]
    data = [p + [''] * (max_len - len(p)) for p in paths]
    df_paths = pd.DataFrame(data, columns=columns)

    # 4. 统计叶子数量
    leaf_counts = compute_leaf_counts(nodes, children)

    # 5. 汇总表：Class / Superfamily / Family
    classes = []
    superfamilies = []
    families = []
    for i, node in enumerate(nodes):
        name = node['name']
        # Class 级别：以 "Class " 开头，或节点名为 "others"
        if name.startswith('Class ') or name == 'others':
            classes.append((name, leaf_counts[i]))
        elif name.startswith('Superfamily:'):
            class_anc = get_ancestor_class(nodes, i)
            superfamilies.append((class_anc, name, leaf_counts[i]))
        elif name.startswith('Family:'):
            class_anc = get_ancestor_class(nodes, i)
            super_anc = get_ancestor_superfamily(nodes, i)
            families.append((class_anc, super_anc, name, leaf_counts[i]))

    df_classes = pd.DataFrame(classes, columns=['Class', 'Leaf Count'])
    df_super = pd.DataFrame(superfamilies, columns=['Class', 'Superfamily', 'Leaf Count'])
    df_families = pd.DataFrame(families, columns=['Class', 'Superfamily', 'Family', 'Leaf Count'])

    # 6. 写入 Excel
    with pd.ExcelWriter(output_path, engine='openpyxl') as writer:
        df_paths.to_excel(writer, sheet_name='Leaf Paths', index=False)

        ws_name = 'Summary Stats'
        writer.book.create_sheet(ws_name)
        ws = writer.book[ws_name]

        # Class 统计
        ws.cell(row=1, column=1, value="Class Statistics")
        for r_idx, row in enumerate([df_classes.columns.tolist()] + df_classes.values.tolist()):
            for c_idx, val in enumerate(row):
                ws.cell(row=r_idx+2, column=c_idx+1, value=val)
        class_end = len(df_classes) + 3

        # Superfamily 统计
        super_title_row = class_end
        ws.cell(row=super_title_row, column=1, value="Superfamily Statistics")
        for r_idx, row in enumerate([df_super.columns.tolist()] + df_super.values.tolist()):
            for c_idx, val in enumerate(row):
                ws.cell(row=super_title_row+1+r_idx, column=c_idx+1, value=val)
        super_end = super_title_row + 1 + len(df_super) + 1

        # Family 统计
        family_title_row = super_end
        ws.cell(row=family_title_row, column=1, value="Family Statistics")
        for r_idx, row in enumerate([df_families.columns.tolist()] + df_families.values.tolist()):
            for c_idx, val in enumerate(row):
                ws.cell(row=family_title_row+1+r_idx, column=c_idx+1, value=val)

    print(f"Excel 文件已保存至：{output_path}")


if __name__ == '__main__':
    main()