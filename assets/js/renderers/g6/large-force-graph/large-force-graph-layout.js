(function () {
  const root = window.__TEKG_LARGE_FORCE_GRAPH_LAYOUT || {};

  function createTaxonomyLargeLayout(options = {}) {
    const nodeById = options.nodeById instanceof Map ? options.nodeById : new Map();
    const endpointId = window.__TEKG_LARGE_FORCE_GRAPH_CONTRACT?.endpointId || ((value) => String(value || ''));
    return {
      type: 'd3-force',
      link: {
        distance: (edge) => {
          const source = nodeById.get(endpointId(edge?.source));
          const target = nodeById.get(endpointId(edge?.target));
          const sourceDepth = Number(source?.level ?? source?.data?.treeDepth ?? 0);
          const targetDepth = Number(target?.level ?? target?.data?.treeDepth ?? 0);
          if (sourceDepth <= 0 || targetDepth <= 1) return 76;
          if (targetDepth >= 5) return 12;
          if (targetDepth >= 4) return 18;
          return 34;
        },
        strength: (edge) => {
          const target = nodeById.get(endpointId(edge?.target));
          const depth = Number(target?.level ?? target?.data?.treeDepth ?? 0);
          if (depth >= 5) return 0.88;
          if (depth >= 4) return 0.78;
          return 0.58;
        },
      },
      manyBody: {
        strength: (node) => {
          const depth = Number(node?.data?.treeDepth ?? node?.level ?? 0);
          const size = Number(node?.style?.size || node?.size || 16);
          if (depth <= 0) return -72;
          if (depth <= 1) return -42;
          if (depth <= 2) return -18;
          return -(1.2 + size * 0.12);
        },
      },
      x: {
        x: (node) => Number(node?.style?.clusterX || node?.style?.x || node?.x || 0),
        strength: (node) => {
          const depth = Number(node?.data?.treeDepth ?? node?.level ?? 0);
          if (depth <= 1) return 0.24;
          if (depth >= 5) return 0.12;
          return 0.16;
        },
      },
      y: {
        y: (node) => Number(node?.style?.clusterY || node?.style?.y || node?.y || 0),
        strength: (node) => {
          const depth = Number(node?.data?.treeDepth ?? node?.level ?? 0);
          if (depth <= 1) return 0.24;
          if (depth >= 5) return 0.12;
          return 0.16;
        },
      },
      collide: {
        radius: (node) => {
          const depth = Number(node?.data?.treeDepth ?? node?.level ?? 0);
          const size = Number(node?.style?.size || node?.size || 16);
          return size / 2 + (depth >= 5 ? 1 : depth >= 4 ? 2 : 6);
        },
        strength: 0.72,
        iterations: 3,
      },
    };
  }

  function createLayout(profile, options = {}) {
    if (profile === 'taxonomy-large') return createTaxonomyLargeLayout(options);
    return createTaxonomyLargeLayout(options);
  }

  root.createLayout = createLayout;
  root.createTaxonomyLargeLayout = createTaxonomyLargeLayout;
  window.__TEKG_LARGE_FORCE_GRAPH_LAYOUT = root;
}());
