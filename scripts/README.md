# Scripts Layout

本目录保存项目侧的 Python、JavaScript 和辅助脚本，用于构建数据资产、标准化实体、导入或检查本地数据库。

## 当前活跃规则

- 当前运行时 Neo4j 目标库是 `tekg3`。
- 当前 TE taxonomy 构建和标准化入口是 `scripts/normalize/build_tekg3_from_tekg21.py`。
- `tekg2`、`0413`、旧 lineage、旧 `graph_demo_data.js` 生成链路已经不再作为活跃构建入口。
- 旧的 `tekg2` seed、旧 disease classification import、旧 tree lineage 和旧 demo data 生成脚本已从 `scripts/` 中删除。

## 当前分组

- `build/`
  - 当前保留 parser、asset preparer、JBrowse/expression/dfam 等仍有用的构建脚本。
- `normalize/`
  - 当前保留 `tekg3` 构建、语义标准化、疾病分类、中文术语回填等维护脚本。
- `export/`
  - 当前保留仍可用的实体描述翻译导出脚本。
- `import/`
  - 当前保留仍可用的 Cypher 生成脚本。
- `checks/`
  - 当前运行时一致性检查脚本。
- `plot/`
  - 可视化和结构图生成脚本。
- root support modules
  - `path_helpers.py`
  - `semantic_aliases.py`
  - `disease_top_class.py`

## 常用检查

从项目根目录运行：

```powershell
python scripts\checks\check_runtime_db_config.py
python scripts\checks\check_taxonomy_runtime_consistency.py
python scripts\checks\check_expression_paths.py
```

## 当前关键构建入口

```powershell
python scripts\normalize\build_tekg3_from_tekg21.py
python scripts\build\prepare_expression_assets.py
python scripts\build\prepare_jbrowse_assets.py
python scripts\build\parse_dfam_embl.py
python scripts\build\parse_te_repbase.py
```

## 路径规则

- Python 脚本应优先使用 `scripts/path_helpers.py`。
- PHP 运行时路径应优先使用 `path_config.php`。
- 浏览器运行时路径应优先使用 `assets/js/tekg_paths.php`。

## 已移除的旧链路

以下类别不再作为活跃脚本保留：

- `tekg2` seed 构建脚本
- `tekg2` import bundle 生成脚本
- `0413` disease classification import 脚本
- 旧 `tree_te_lineage` 生成脚本
- 旧 `assets/data/graph_demo_data.js` 生成脚本
- 旧 `tekg2` unresolved relation 修复脚本

如需查看过去的处理历史，应查阅 Git 历史或历史文档，而不是重新把这些脚本作为运行时构建入口。
