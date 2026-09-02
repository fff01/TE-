from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]
preview = (ROOT / 'preview.php').read_text(encoding='utf-8')
workspace = (ROOT / 'templates/preview/coexpression_workspace.php').read_text(encoding='utf-8')
mode = (ROOT / 'assets/js/pages/preview/coexpression-mode.js').read_text(encoding='utf-8')
coordinator = (ROOT / 'assets/js/pages/preview/preview-workspace-mode.js').read_text(encoding='utf-8')
adapter = (ROOT / 'assets/js/renderers/g6/coexpression/coexpression-dynamic-adapter.js').read_text(encoding='utf-8')
renderer = (ROOT / 'assets/js/renderers/g6/coexpression/coexpression-renderer.js').read_text(encoding='utf-8')

checks = {
    'workspace label': 'TE-Gene Graph' in preview and 'TE-Gene Graph workspace' in workspace,
    'scope control': 'te-gene-scope-select' in workspace and 'populateScopeOptions' in mode,
    'new endpoint': "te_gene.php" in mode,
    'route scope': "searchParams.set('scope'" in coordinator and "mode', 'te_gene'" in coordinator,
    'evidence labels': all(label in adapter for label in ['edgeLabel', 'coexpressionEvidence', 'eqtlEvidence']),
    'detail evidence': 'renderTeGeneEdgeInspectCard' in renderer and 'supportingTissues' in renderer,
    'no evidence colour mode': 'TE_GENE_EVIDENCE' in adapter,
}
missing = [name for name, passed in checks.items() if not passed]
if missing:
    print('FAIL:', ', '.join(missing))
    sys.exit(1)
print('TE-Gene Graph static contract: PASS')
