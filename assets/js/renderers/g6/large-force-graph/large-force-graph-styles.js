(function () {
  const root = window.__TEKG_LARGE_FORCE_GRAPH_STYLES || {};

  function nodeLevel(datum) {
    return Math.max(0, Number(datum?.level ?? datum?.data?.treeDepth ?? 0) || 0);
  }

  function nodeLegendKeys(datum) {
    if (Array.isArray(datum?.legendKeys)) return datum.legendKeys;
    if (Array.isArray(datum?.data?.legendKeys)) return datum.data.legendKeys;
    const key = datum?.data?.taxonomyLevelKey;
    return key ? [String(key)] : [];
  }

  function isVisibleByLegend(datum, state = {}) {
    const keys = nodeLegendKeys(datum);
    if (!keys.length) return true;
    return keys.every((key) => state[key] !== false);
  }

  function matchesFocus(datum, focusKey) {
    if (!focusKey) return true;
    return nodeLegendKeys(datum).includes(String(focusKey));
  }

  function shouldShowLabel(datum, state = {}) {
    if (!isVisibleByLegend(datum, state.legendState)) return false;
    const id = String(datum?.id || '');
    if (state.hoverNodeId && id === state.hoverNodeId) return true;
    if (state.selectedNodeId && id === state.selectedNodeId) return true;
    if (state.legendFocus && matchesFocus(datum, state.legendFocus)) return true;
    const depth = nodeLevel(datum);
    if (state.dragging && depth >= 4) return false;
    return datum?.pinnedLabel === true || datum?.data?.pinnedLabel === true || depth <= 2;
  }

  function displayLabel(datum, state = {}) {
    if (!shouldShowLabel(datum, state)) return '';
    const label = String(datum?.displayLabel || datum?.label || datum?.data?.displayLabel || datum?.data?.rawLabel || datum?.id || '');
    if (state.hoverNodeId === String(datum?.id || '') || state.selectedNodeId === String(datum?.id || '')) return label;
    const depth = nodeLevel(datum);
    const limit = depth <= 2 ? 18 : depth === 3 ? 16 : depth === 4 ? 12 : 10;
    return label.length > limit ? `${label.slice(0, Math.max(4, limit - 1))}...` : label;
  }

  function nodeOpacity(datum, state = {}) {
    if (!isVisibleByLegend(datum, state.legendState)) return 0;
    return matchesFocus(datum, state.legendFocus) ? 1 : 0.16;
  }

  function edgeOpacity(edge, state = {}) {
    if (!state || !state.nodeById) return state.legendFocus ? 0.08 : 0.64;
    const endpointId = window.__TEKG_LARGE_FORCE_GRAPH_CONTRACT?.endpointId || ((value) => String(value || ''));
    const source = state.nodeById.get(endpointId(edge?.source));
    const target = state.nodeById.get(endpointId(edge?.target));
    if (!source || !target) return 0;
    if (!isVisibleByLegend(source, state.legendState) || !isVisibleByLegend(target, state.legendState)) return 0;
    if (!state.legendFocus) return 0.64;
    return matchesFocus(source, state.legendFocus) || matchesFocus(target, state.legendFocus) ? 0.72 : 0.08;
  }

  function labelFontSize(datum) {
    return Math.max(6, 13 - nodeLevel(datum) * 0.7);
  }

  root.displayLabel = displayLabel;
  root.nodeOpacity = nodeOpacity;
  root.edgeOpacity = edgeOpacity;
  root.labelFontSize = labelFontSize;
  root.matchesFocus = matchesFocus;
  root.isVisibleByLegend = isVisibleByLegend;
  window.__TEKG_LARGE_FORCE_GRAPH_STYLES = root;
}());
