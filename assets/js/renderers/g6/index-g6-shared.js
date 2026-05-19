(function () {
  const G6Lib = window.G6;
  if (!G6Lib || !G6Lib.Graph) {
    window.__TEKG_G6_SHARED = null;
    return;
  }

  const { Graph } = G6Lib;

  const TYPE_META = window.__TEKG_G6_TYPE_META && typeof window.__TEKG_G6_TYPE_META === 'object'
    ? window.__TEKG_G6_TYPE_META
    : {};
  const TYPE_COLORS = TYPE_META.colors || {};
  const TYPE_STROKES = TYPE_META.strokes || {};

  const RELATION_LABELS_EN = {
    SUBFAMILY_OF: 'contains',
    EVIDENCE_RELATION: 'literature support',
    BIO_RELATION: 'related to',
    associated_with: 'associated with',
    related_to: 'related to',
    promotes: 'promotes',
    mediates: 'mediates',
    reports: 'reports',
    affects: 'affects',
    executes: 'executes',
    participates_in: 'participates in',
    regulates: 'regulates',
    leads_to: 'leads to',
    uses: 'uses',
    inhibits: 'inhibits',
    triggers: 'triggers',
    induces: 'induces',
    increases_risk_of: 'increases risk of',
    modulates: 'modulates',
    facilitates: 'facilitates',
    occurs_in: 'occurs in',
    activates: 'activates',
    disrupts: 'disrupts',
    produces: 'produces',
    acts_as: 'acts as',
    enables: 'enables',
    explains: 'explains',
    provides: 'provides',
    predisposes_to: 'predisposes to',
    is_regulated_by: 'is regulated by',
    alters: 'alters',
    lacks: 'lacks',
    manifests_as: 'manifests as',
    characterizes: 'characterizes',
    '相关': 'associated with',
    '促进': 'promotes',
    '介导': 'mediates',
    '报道': 'reports',
    '影响': 'affects',
    '执行': 'executes',
    '参与': 'participates in',
    '调控': 'regulates',
    '导致': 'leads to',
    '利用': 'uses',
    '抑制': 'inhibits',
    '触发': 'triggers',
    '诱导': 'induces',
    '增加风险': 'increases risk of',
    '调节': 'modulates',
    '促成': 'facilitates',
    '发生': 'occurs in',
    '激活': 'activates',
    '破坏': 'disrupts',
    '产生': 'produces',
    '充当': 'acts as',
    '使能': 'enables',
    '解释': 'explains',
    '提供': 'provides',
    '易感': 'predisposes to',
    '被调控': 'is regulated by',
    '改变': 'alters',
    '缺失': 'lacks',
    '表现为': 'manifests as',
    '表征': 'characterizes',
  };

  const TE_MIN_RADIUS = 12.5;
  const AGGREGATE_TE_CHILD_SIZE_RATIO = 1.5;

  function noop() {}

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function degreeToSize(degree) {
    const safeDegree = Math.max(1, Number(degree) || 1);
    return Math.max(10, Math.min(64, 10 + Math.log2(safeDegree + 1) * 7.5));
  }

  function canonicalTeLineageName(name) {
    let raw = String(name || '').trim();
    if (!raw) return raw;
    raw = raw.replace(/^(Class I|Class II|Subclass|Order|Superfamily|Family):\s*/i, '').trim();
    if (raw === 'LINE1' || raw === 'LINE-1' || raw === 'L1 (LINE-1)') return 'L1';
    return raw;
  }

  function getDisplayNameOverride(name) {
    const raw = String(name || '').trim();
    if (!raw) return raw;
    if (raw === 'L1' || raw === 'LINE-1') return 'LINE1';
    return raw;
  }

  function interpolateHexColor(startHex, endHex, t) {
    const start = hexToRgb(startHex);
    const end = hexToRgb(endHex);
    const clamped = Math.max(0, Math.min(1, Number(t) || 0));
    const mix = (from, to) => Math.round(from + (to - from) * clamped);
    return `rgb(${mix(start.r, end.r)}, ${mix(start.g, end.g)}, ${mix(start.b, end.b)})`;
  }

  function darkenHexColor(hex, amount) {
    return interpolateHexColor(hex, '#0f172a', amount);
  }

  function hexToRgb(hex) {
    const raw = String(hex || '').trim();
    const rgbMatch = raw.match(/^rgb\(\s*(\d+),\s*(\d+),\s*(\d+)\s*\)$/i);
    if (rgbMatch) {
      return { r: Number(rgbMatch[1]), g: Number(rgbMatch[2]), b: Number(rgbMatch[3]) };
    }
    const value = raw.replace('#', '');
    if (value.length !== 6) return { r: 148, g: 163, b: 184 };
    return {
      r: parseInt(value.slice(0, 2), 16),
      g: parseInt(value.slice(2, 4), 16),
      b: parseInt(value.slice(4, 6), 16),
    };
  }

  function mixEdgeColor(sourceType, targetType, alpha) {
    const source = hexToRgb(TYPE_COLORS[sourceType] || '#94a3b8');
    const target = hexToRgb(TYPE_COLORS[targetType] || '#94a3b8');
    const mixed = {
      r: Math.round((source.r + target.r) / 2),
      g: Math.round((source.g + target.g) / 2),
      b: Math.round((source.b + target.b) / 2),
    };
    return `rgba(${mixed.r}, ${mixed.g}, ${mixed.b}, ${alpha})`;
  }

  function containsChinese(text) {
    return /[\u4e00-\u9fff]/.test(String(text || ''));
  }

  function normalizeQueryType(value) {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === 'disease_class' || normalized === 'diseaseclass') return 'disease_class';
    return '';
  }

  function normalizeTranslationMap(raw) {
    return raw && typeof raw === 'object' ? raw : {};
  }

  function buildTeam(node) {
    if (node.type === 'Disease' || node.type === 'DiseaseCategory' || node.type === 'DiseaseClass') {
      const diseaseClass = String(node.disease_class || '').trim() || 'Disease';
      return `Disease::${diseaseClass}`;
    }
    if (node.type === 'TE') return 'TE';
    if (node.type === 'Function') return 'Function';
    return `${node.type || 'Node'}::${node.id}`;
  }

  function diseaseCategoryFillColor(level, minLevel, maxLevel) {
    const safeMin = Number.isFinite(minLevel) ? minLevel : 1;
    const safeMax = Number.isFinite(maxLevel) ? maxLevel : safeMin;
    const safeLevel = Number.isFinite(level) ? level : safeMax;
    const span = Math.max(1, safeMax - safeMin);
    const normalized = Math.max(0, Math.min(1, (safeLevel - safeMin) / span));
    return interpolateHexColor('#cf2238', '#f6d4d9', normalized);
  }

  function fitLabelToCircle(text, diameter) {
    const raw = String(text || '').trim();
    if (!raw) return '';
    const maxChars = Math.max(2, Math.floor((Math.max(0, Number(diameter) || 0) - 14) / 8.5));
    if (raw.length <= maxChars) return raw;
    if (maxChars <= 3) return raw.slice(0, maxChars);
    return `${raw.slice(0, maxChars - 1)}...`;
  }

  function resolveNode(edgeSide, nodes) {
    if (edgeSide && typeof edgeSide === 'object') return edgeSide;
    return nodes.find((node) => node.id === edgeSide) || null;
  }

  function createRunner(options) {
    const container = options?.container;
    if (!container) {
      throw new Error('G6 shared runner requires a container.');
    }

    let graph = null;
    let fixedView = !!options.initialFixedView;
    let currentKeyNodeLevel = Math.max(1, Math.min(10, Number(options.initialKeyNodeLevel) || 1));
    let currentQuery = String(options.initialQuery || '').trim();
    let currentQueryType = normalizeQueryType(options.initialQueryType);
    let currentClassQuery = String(options.initialClassQuery || '').trim();
    let currentLang = 'en';
    let currentShowAllLabels = options.initialShowAllLabels === true;

    if (currentQueryType === 'disease_class') {
      if (!currentClassQuery) currentClassQuery = currentQuery;
      if (!currentQuery) currentQuery = currentClassQuery;
    } else {
      currentClassQuery = '';
    }

    let nameTranslations = {};
    let teDescriptions = { en: {} };
    let entityDescriptions = { en: { Disease: {}, Function: {} } };
    let teLineageDepths = new Map();
    let teLineageChildren = new Map();
    let teLineageDescendants = new Map();
    let teDatabaseDegrees = new Map();
    let teFixedRadii = new Map();
    let resourcesPromise = null;

    const hooks = {
      setStatus: typeof options.setStatus === 'function' ? options.setStatus : noop,
      setDetail: typeof options.setDetail === 'function' ? options.setDetail : noop,
      setDetailHtml: typeof options.setDetailHtml === 'function' ? options.setDetailHtml : noop,
      setMode: typeof options.setMode === 'function' ? options.setMode : noop,
      onSelection: typeof options.onSelection === 'function' ? options.onSelection : noop,
      onDiseaseClassClick: typeof options.onDiseaseClassClick === 'function' ? options.onDiseaseClassClick : noop,
      onNodeExpand: typeof options.onNodeExpand === 'function' ? options.onNodeExpand : noop,
      onReady: typeof options.onReady === 'function' ? options.onReady : noop,
      setQueryUi: typeof options.setQueryUi === 'function' ? options.setQueryUi : noop,
      syncRouteState: typeof options.syncRouteState === 'function' ? options.syncRouteState : noop,
    };

    function buildCurrentRequest() {
      if (currentQueryType === 'disease_class') {
        const classQuery = String(currentClassQuery || currentQuery || '').trim();
        return {
          query: classQuery,
          queryType: 'disease_class',
          classQuery,
        };
      }
      return {
        query: String(currentQuery || '').trim(),
        queryType: '',
        classQuery: '',
      };
    }

    function normalizeGraphRequest(requestLike) {
      if (requestLike && typeof requestLike === 'object' && !Array.isArray(requestLike)) {
        const queryType = normalizeQueryType(requestLike.type || requestLike.queryType);
        const classQuery = String(requestLike.classQuery || requestLike.class || '').trim();
        const query = String(requestLike.query || requestLike.q || classQuery || '').trim();
        if (queryType === 'disease_class') {
          const normalizedClassQuery = classQuery || query;
          return {
            query: normalizedClassQuery,
            queryType,
            classQuery: normalizedClassQuery,
          };
        }
        return {
          query,
          queryType: '',
          classQuery: '',
        };
      }

      if (typeof requestLike === 'string') {
        return {
          query: String(requestLike || '').trim(),
          queryType: '',
          classQuery: '',
        };
      }

      const uiQuery = typeof options.getQuery === 'function' ? String(options.getQuery() || '').trim() : '';
      if (currentQueryType === 'disease_class') {
        const preserveClassGraph = !uiQuery || uiQuery === currentQuery || uiQuery === currentClassQuery;
        if (preserveClassGraph) return buildCurrentRequest();
      }

      const query = String(uiQuery || currentQuery || 'LINE1').trim() || 'LINE1';
      return {
        query,
        queryType: '',
        classQuery: '',
      };
    }

    function computeTeVisualMetrics() {
      teFixedRadii = new Map();
      if (!teLineageDepths.size) return;

      const lineageNames = new Set([...teLineageDepths.keys(), ...teDatabaseDegrees.keys()]);
      const baseScores = new Map();

      for (const name of lineageNames) {
        const descendantCount = teLineageDescendants.get(name) || 0;
        const directChildrenCount = (teLineageChildren.get(name) || []).length;
        const databaseDegree = Math.max(0, Number(teDatabaseDegrees.get(name)) || 0);
        const nonChildEdgeCount = Math.max(0, databaseDegree - directChildrenCount);
        const weightedSignal = Math.max(1, descendantCount * 0.35 + nonChildEdgeCount * 0.65);
        baseScores.set(name, Math.sqrt(weightedSignal));
      }

      const adjustedScores = new Map(
        [...lineageNames].map((name) => [name, Math.max(1, Number(baseScores.get(name)) || 1)]),
      );

      const namesByDepthDesc = [...lineageNames].sort(
        (left, right) => (teLineageDepths.get(right) || 0) - (teLineageDepths.get(left) || 0),
      );

      for (const name of namesByDepthDesc) {
        const children = teLineageChildren.get(name) || [];
        if (!children.length) continue;
        const maxChildScore = Math.max(...children.map((child) => Math.max(0, Number(adjustedScores.get(child)) || 0)));
        if (maxChildScore <= 0) continue;
        const currentScore = Math.max(1, Number(adjustedScores.get(name)) || 1);
        const descendantCount = teLineageDescendants.get(name) || 0;
        const structuralBonus = 2.6 + Math.min(7.0, children.length * 0.4 + Math.log2(descendantCount + 1) * 0.84);
        const inverseSizeBoost = Math.max(1.2, 5.6 / Math.max(1, currentScore));
        const additiveBonus = structuralBonus * inverseSizeBoost;
        adjustedScores.set(name, Math.max(currentScore, maxChildScore + additiveBonus));
      }

      const line1TargetRadius = degreeToSize(teDatabaseDegrees.get('L1') || teDatabaseDegrees.get('LINE1') || 1) / 2;
      const line1AdjustedScore = Math.max(1, Number(adjustedScores.get('L1')) || 1);
      const scaleCoefficient = line1TargetRadius / line1AdjustedScore;

      for (const name of lineageNames) {
        teFixedRadii.set(name, Math.max(TE_MIN_RADIUS, (adjustedScores.get(name) || 1) * scaleCoefficient));
      }

      capAggregateTeRadiiByChildren();
    }

    function capAggregateTeRadiiByChildren() {
      const namesByDepthDesc = [...teLineageDepths.keys()].sort(
        (left, right) => (teLineageDepths.get(right) || 0) - (teLineageDepths.get(left) || 0),
      );

      for (const name of namesByDepthDesc) {
        const children = getAggregateTeChildNames(name);
        if (!children.length) continue;

        const childRadii = children
          .map((child) => Number(teFixedRadii.get(child)) || 0)
          .filter((radius) => radius > 0);
        if (!childRadii.length) continue;

        const currentRadius = Number(teFixedRadii.get(name)) || 0;
        if (currentRadius <= 0) continue;

        const maxChildRadius = Math.max(...childRadii);
        const cappedRadius = Math.max(
          TE_MIN_RADIUS,
          Math.min(currentRadius, maxChildRadius * AGGREGATE_TE_CHILD_SIZE_RATIO),
        );
        teFixedRadii.set(name, cappedRadius);
      }
    }

    function getAggregateTeChildNames(name) {
      const canonicalName = canonicalTeLineageName(name);
      const taxonomyChildren = (teLineageChildren.get(canonicalName) || [])
        .map((child) => canonicalTeLineageName(child))
        .filter((child) => child && child !== canonicalName);
      if (taxonomyChildren.length) return taxonomyChildren;

      const knownNames = [...teFixedRadii.keys()].map((candidate) => canonicalTeLineageName(candidate));
      if (canonicalName === 'SINE1/7SL (Alu)') {
        return knownNames.filter((candidate) => /^Alu/i.test(candidate));
      }
      if (canonicalName === 'L1') {
        return knownNames.filter((candidate) => /^L1/i.test(candidate) && candidate !== 'L1');
      }
      return [];
    }

    function teFillColorForName(name) {
      const canonicalName = canonicalTeLineageName(name);
      const depth = teLineageDepths.get(canonicalName);
      const depths = [...teLineageDepths.values()].filter((value) => Number.isFinite(value));
      const minDepth = depths.length ? Math.min(...depths) : 0;
      const maxDepth = depths.length ? Math.max(...depths) : 1;
      const span = Math.max(1, maxDepth - minDepth);
      const normalizedDepth = Math.max(0, Math.min(1, (((Number.isFinite(depth) ? depth : maxDepth) - minDepth) / span)));
      return interpolateHexColor('#18357d', '#8fb0ff', normalizedDepth);
    }

    function teRadiusForName(name, fallbackDegree) {
      const canonicalName = canonicalTeLineageName(name);
      return teFixedRadii.get(canonicalName) || degreeToSize(fallbackDegree) / 2;
    }

    function teShouldShowLabel(node) {
      if (node?.alwaysShowLabel) return true;
      const canonicalName = canonicalTeLineageName(node?.rawLabel || node?.displayLabel);
      const depth = teLineageDepths.get(canonicalName);
      return (Number.isFinite(depth) && depth <= 2) || (Math.max(0, Number(node?.databaseDegree) || 0) > 10);
    }

    function teLabelFontSize(node) {
      const text = String(node?.displayLabel || node?.rawLabel || '').trim();
      const diameter = Math.max(16, Number(node?.size) || 16);
      if (!text) return 12;
      const estimated = (diameter - 10) / Math.max(3, text.length * 0.62);
      return Math.max(8, Math.min(16, estimated));
    }

    function secondaryShouldShowLabel(node) {
      const nodeType = String(node?.nodeType || '');
      if (nodeType === 'DiseaseClass') return true;
      if (nodeType === 'DiseaseCategory') return true;
      if (nodeType === 'Paper') return !!node?.alwaysShowLabel;

      const labelThresholds = {
        Disease: 34,
        Function: 34,
        Gene: 30,
        Protein: 30,
        RNA: 30,
        Mutation: 30,
        Pharmaceutical: 32,
        Toxin: 30,
        Lipid: 30,
        Peptide: 30,
        Carbohydrate: 30,
      };

      return Object.prototype.hasOwnProperty.call(labelThresholds, nodeType)
        && Math.max(0, Number(node?.size) || 0) >= labelThresholds[nodeType];
    }

    function secondaryLabelText(node) {
      const raw = String(node?.displayLabel || node?.rawLabel || '').trim();
      if (!raw) return '';
      return fitLabelToCircle(raw, Math.max(16, Number(node?.size) || 16));
    }

    function secondaryLabelFontSize(node) {
      const text = secondaryLabelText(node);
      const diameter = Math.max(16, Number(node?.size) || 16);
      if (!text) return 10;
      const estimated = (diameter - 8) / Math.max(3, text.length * 0.7);
      return Math.max(7, Math.min(12, estimated));
    }

    function showAllLabelText(node) {
      const raw = String(node?.displayLabel || node?.rawLabel || '').trim();
      if (!raw) return '';
      return fitLabelToCircle(raw, Math.max(16, Number(node?.size) || 16));
    }

    function showAllLabelFontSize(node) {
      const text = showAllLabelText(node);
      const diameter = Math.max(16, Number(node?.size) || 16);
      if (!text) return 10;
      const estimated = (diameter - 8) / Math.max(3, text.length * 0.68);
      const upper = node?.nodeType === 'TE' ? 14 : 12;
      return Math.max(7, Math.min(upper, estimated));
    }

    async function loadEnglishResources() {
      const [nameRes, teDescRes, entityDescRes, teLineageRes, teMetricsRes] = await Promise.allSettled([
        fetch(window.__TEKG_PATHS.dataUrl('processed/entity_description_key_translation_cache.json'), { credentials: 'same-origin' }),
        fetch(window.__TEKG_PATHS.dataUrl('processed/te_descriptions.json'), { credentials: 'same-origin' }),
        fetch(window.__TEKG_PATHS.dataUrl('processed/entity_descriptions.json'), { credentials: 'same-origin' }),
        fetch(window.__TEKG_PATHS.apiUrl('taxonomy.php?view=tree'), { credentials: 'same-origin' }),
        fetch(window.__TEKG_PATHS.apiUrl('te_metrics.php'), { credentials: 'same-origin' }),
      ]);

      if (nameRes.status === 'fulfilled' && nameRes.value.ok) {
        nameTranslations = normalizeTranslationMap(await nameRes.value.json());
      }

      if (teDescRes.status === 'fulfilled' && teDescRes.value.ok) {
        const payload = await teDescRes.value.json();
        teDescriptions = { en: payload?.en || {} };
      }

      if (entityDescRes.status === 'fulfilled' && entityDescRes.value.ok) {
        const payload = await entityDescRes.value.json();
        entityDescriptions = {
          en: {
            Disease: payload?.en?.Disease || {},
            Function: payload?.en?.Function || {},
          },
        };
      }

      if (teLineageRes.status === 'fulfilled' && teLineageRes.value.ok) {
        const payload = await teLineageRes.value.json();
        teLineageDepths = new Map();
        for (const node of payload?.nodes || []) {
          const name = canonicalTeLineageName(node?.name);
          if (!name) continue;
          const depth = Math.max(0, Number(node?.depth) || 0);
          if (!teLineageDepths.has(name) || depth < teLineageDepths.get(name)) {
            teLineageDepths.set(name, depth);
          }
        }

        teLineageChildren = new Map();
        for (const edge of payload?.edges || []) {
          const parent = canonicalTeLineageName(edge?.parent);
          const child = canonicalTeLineageName(edge?.child);
          if (!parent || !child || parent === child) continue;
          if (!teLineageChildren.has(parent)) teLineageChildren.set(parent, []);
          teLineageChildren.get(parent).push(child);
        }

        teLineageDescendants = new Map();
        const countDescendants = (name) => {
          if (teLineageDescendants.has(name)) return teLineageDescendants.get(name);
          const children = teLineageChildren.get(name) || [];
          let total = 0;
          for (const child of children) total += 1 + countDescendants(child);
          teLineageDescendants.set(name, total);
          return total;
        };

        for (const name of teLineageDepths.keys()) {
          countDescendants(name);
        }
      }

      if (teMetricsRes.status === 'fulfilled' && teMetricsRes.value.ok) {
        const payload = await teMetricsRes.value.json();
        teDatabaseDegrees = new Map(
          Object.entries(payload?.metrics || {}).map(([name, degree]) => [canonicalTeLineageName(name), Math.max(0, Number(degree) || 0)]),
        );
      }

      computeTeVisualMetrics();
    }

    function ensureResources() {
      if (!resourcesPromise) {
        resourcesPromise = loadEnglishResources().catch((error) => {
          console.warn('Failed to load English resources for G6 graph:', error);
        });
      }
      return resourcesPromise;
    }

    function translateName(rawLabel) {
      const raw = String(rawLabel || '').trim();
      if (!raw) return '';
      if (currentLang !== 'en') return raw;
      if (!containsChinese(raw)) return getDisplayNameOverride(raw);
      return nameTranslations[raw] || raw;
    }

    function translateDescription(nodeType, rawLabel, rawDescription) {
      const label = String(rawLabel || '').trim();
      const description = String(rawDescription || '').trim();
      if (currentLang !== 'en') {
        return description || '';
      }
      if (nodeType === 'DiseaseClass') return description || 'Disease class node in the current graph.';
      if (nodeType === 'DiseaseCategory') return description || 'Disease classification category in the current graph.';
      if (nodeType === 'TE') {
        const key = translateName(label) || label;
        const mapped = String(teDescriptions?.en?.[key] || '').trim();
        if (mapped) return mapped;
      }
      if (nodeType === 'Disease' || nodeType === 'Function') {
        const key = translateName(label) || label;
        const mapped = String(entityDescriptions?.en?.[nodeType]?.[key] || '').trim();
        if (mapped) return mapped;
      }
      return description || '';
    }

    function relationLabelForEdge(edge) {
      if (edge?.synthetic) return 'classified as';
      const rawRelation = String(edge?.relation || '').trim();
      const rawType = String(edge?.relationType || '').trim();
      const raw = rawRelation || rawType;
      if (!raw) return 'related to';
      if (currentLang === 'en') {
        return RELATION_LABELS_EN[raw] || raw;
      }
      return raw;
    }

    function relationLegendKeyForEdge(edge) {
      const relation = String(edge?.relation || '').trim();
      const relationType = String(edge?.relationType || '').trim();
      if (relation) return relation;
      if (relationType) return relationType;
      return 'RELATION';
    }

    function formatEvidence(edge) {
      const evidence = String(edge?.evidence || '').trim();
      const pmids = Array.isArray(edge?.pmids)
        ? edge.pmids.map((pmid) => String(pmid || '').trim()).filter(Boolean)
        : [];

      if (pmids.length) {
        return `PMID: ${pmids.join(', ')}`;
      }
      if (evidence) {
        return evidence;
      }
      return 'Not listed.';
    }

    function hashString(input) {
      let hash = 0;
      const text = String(input || '');
      for (let index = 0; index < text.length; index += 1) {
        hash = ((hash << 5) - hash + text.charCodeAt(index)) | 0;
      }
      return Math.abs(hash);
    }

    const RELATION_STYLE_COLORS = [
      '#2563eb',
      '#dc2626',
      '#059669',
      '#7c3aed',
      '#ea580c',
      '#0891b2',
      '#be123c',
      '#4f46e5',
      '#65a30d',
      '#9333ea',
    ];

    function relationStyleForType(relationType) {
      const type = String(relationType || 'RELATION').trim() || 'RELATION';
      const index = hashString(type) % RELATION_STYLE_COLORS.length;
      const dashed = /CLASSIFICATION|CATEGORY|TAXONOMY|SYNTHETIC/i.test(type);
      return {
        color: RELATION_STYLE_COLORS[index],
        dashed,
        lineDash: dashed ? [8, 6] : [],
      };
    }

    function isClassificationRelation(relationType) {
      return /CLASSIFIED_AS|HAS_SUBCATEGORY|TOP_CLASS_RELATION|DISEASE_CLASSIFICATION/i.test(String(relationType || ''));
    }

    function buildEdgeDetailHtml(edge, nodes) {
      const source = resolveNode(edge?.source, nodes);
      const target = resolveNode(edge?.target, nodes);
      const sourceLabel = source?.displayLabel || source?.rawLabel || String(edge?.source || '');
      const targetLabel = target?.displayLabel || target?.rawLabel || String(edge?.target || '');
      const relation = relationLabelForEdge(edge);
      const evidence = formatEvidence(edge);

      return [
        `<strong>${escapeHtml(sourceLabel)}</strong>`,
        `&nbsp;&rarr;&nbsp;${escapeHtml(relation)}&nbsp;&rarr;&nbsp;`,
        `<strong>${escapeHtml(targetLabel)}</strong>`,
        `<div style="margin-top:8px;color:#475569;line-height:1.6;"><strong>Evidence:</strong> ${escapeHtml(evidence)}</div>`,
      ].join('');
    }

    function buildGraphData(elements, options = {}) {
      const includePaperNodes = options.includePaperNodes === true;
      const restrictToAnchorComponent = options.restrictToAnchorComponent !== false;
      const forceAnchorLabel = options.forceAnchorLabel === true;
      const showAllLabels = options.showAllLabels === true;
      const visibleTypes = options.visibleTypes && typeof options.visibleTypes === 'object'
        ? options.visibleTypes
        : null;
      const visibleRelations = options.visibleRelations && typeof options.visibleRelations === 'object'
        ? options.visibleRelations
        : null;
      const minRelationPmids = Math.max(0, Number(options.minRelationPmids) || 0);
      const nodes = [];
      const edges = [];
      const allowedNodeIds = new Set();
      let anchorNodeId = '';

      for (const item of elements || []) {
        const data = item && item.data ? item.data : null;
        if (!data) continue;
        if (data.source && data.target) continue;
        const nodeType = data.type || 'TE';
        if (!includePaperNodes && nodeType === 'Paper') continue;
        if (visibleTypes && visibleTypes[nodeType] === false) continue;
        if (!anchorNodeId) anchorNodeId = String(data.id || '');

        const node = {
          id: data.id,
          size: nodeType === 'Paper'
            ? Math.max(28, degreeToSize(data.degree))
            : degreeToSize(data.degree),
          nodeType,
          rawLabel: data.rawLabel || data.label || data.id,
          displayLabel: translateName(data.label || data.rawLabel || data.id),
          databaseDegree: Math.max(0, Number(data.degree) || 0),
          description: translateDescription(data.type || 'TE', data.label || data.rawLabel || data.id, data.description || ''),
          diseaseClass: String(data.disease_class || ''),
          categoryLevel: Math.max(0, Number(data.category_level) || 0),
          team: buildTeam(data),
          queryLabel: nodeType === 'DiseaseCategory' ? '' : String(data.rawLabel || data.label || data.id),
          queryType: nodeType === 'DiseaseClass' ? 'disease_class' : '',
          classQuery: nodeType === 'DiseaseClass' ? String(data.rawLabel || data.label || data.id) : '',
          fillColor: TYPE_COLORS[nodeType] || '#94a3b8',
          strokeColor: TYPE_STROKES[nodeType] || '#111111',
          showAllLabels,
          alwaysShowLabel:
            (includePaperNodes && (data.type || 'TE') === 'Paper') ||
            (forceAnchorLabel && String(data.id || '') === anchorNodeId),
        };

        nodes.push(node);
        allowedNodeIds.add(node.id);
      }

      const teNodes = nodes.filter((node) => node.nodeType === 'TE');
      for (const node of teNodes) {
        const canonicalName = canonicalTeLineageName(node.rawLabel || node.displayLabel);
        const fixedRadius = teRadiusForName(canonicalName, node.databaseDegree);
        node.size = fixedRadius * 2;
        node.fillColor = teFillColorForName(canonicalName);
        node.strokeColor = darkenHexColor(node.fillColor, 0.28);
      }

      const diseaseCategoryNodes = nodes.filter((node) => node.nodeType === 'DiseaseCategory');
      if (diseaseCategoryNodes.length) {
        const levels = diseaseCategoryNodes
          .map((node) => Math.max(1, Number(node.categoryLevel) || 1))
          .filter((level) => Number.isFinite(level));
        const minLevel = levels.length ? Math.min(...levels) : 1;
        const maxLevel = levels.length ? Math.max(...levels) : minLevel;
        for (const node of diseaseCategoryNodes) {
          const fillColor = diseaseCategoryFillColor(Math.max(1, Number(node.categoryLevel) || 1), minLevel, maxLevel);
          node.fillColor = fillColor;
          node.strokeColor = darkenHexColor(fillColor, 0.24);
        }
      }

      const baseEdges = [];
      for (const item of elements || []) {
        const data = item && item.data ? item.data : null;
        if (!data || !data.source || !data.target) continue;
        if (!allowedNodeIds.has(data.source) || !allowedNodeIds.has(data.target)) continue;
        const relationType = String(data.relationType || data.relation || 'RELATION').trim() || 'RELATION';
        const relationKey = relationLegendKeyForEdge(data);
        const pmids = Array.isArray(data.pmids) ? data.pmids : [];
        if (visibleRelations && visibleRelations[relationKey] === false) continue;
        if (!isClassificationRelation(relationType) && pmids.length < minRelationPmids) continue;
        baseEdges.push({
          id: String(data.id || `${data.source}__${relationType}__${data.target}`),
          source: data.source,
          target: data.target,
          relation: String(data.relation || '').trim(),
          relationType,
          relationKey,
          evidence: String(data.evidence || '').trim(),
          pmids,
        });
      }

      const connectedNodeIds = new Set();
      for (const edge of baseEdges) {
        connectedNodeIds.add(edge.source);
        connectedNodeIds.add(edge.target);
      }

      const nonIsolatedNodes = nodes.filter((node) => connectedNodeIds.has(node.id));
      const candidateNodes = [...nodes];
      const adjacency = new Map();
      for (const node of candidateNodes) adjacency.set(node.id, []);
      for (const edge of baseEdges) {
        if (!adjacency.has(edge.source) || !adjacency.has(edge.target)) continue;
        adjacency.get(edge.source).push(edge.target);
        adjacency.get(edge.target).push(edge.source);
      }

      const mainComponentNodeIds = new Set();
      const traversalStartId = adjacency.has(anchorNodeId) ? anchorNodeId : (candidateNodes[0]?.id || '');
      if (traversalStartId) {
        const queue = [traversalStartId];
        mainComponentNodeIds.add(traversalStartId);
        while (queue.length) {
          const currentId = queue.shift();
          for (const neighborId of adjacency.get(currentId) || []) {
            if (mainComponentNodeIds.has(neighborId)) continue;
            mainComponentNodeIds.add(neighborId);
            queue.push(neighborId);
          }
        }
      }

      const visibleNodes = (!restrictToAnchorComponent || baseEdges.length === 0 || nonIsolatedNodes.length === 0)
        ? [...candidateNodes]
        : candidateNodes.filter((node) => mainComponentNodeIds.has(node.id));
      const visibleNodeIds = new Set(visibleNodes.map((node) => node.id));

      if (showAllLabels) {
        for (const node of visibleNodes) {
          const baseSize = Math.max(18, Number(node.size) || 18);
          node.size = Math.round(baseSize * 1.22 + 4);
          node.showAllLabels = true;
        }
      }

      for (const edge of baseEdges) {
        if (!visibleNodeIds.has(edge.source) || !visibleNodeIds.has(edge.target)) continue;
        const relationStyle = relationStyleForType(edge.relationKey);
        edges.push({
          id: edge.id,
          source: edge.source,
          target: edge.target,
          relation: edge.relation,
          relationType: edge.relationType,
          relationKey: edge.relationKey,
          evidence: edge.evidence,
          pmids: edge.pmids,
          strokeColor: relationStyle.color,
          lineDash: relationStyle.lineDash,
        });
      }

      const relationLegendMeta = [...new Set(edges.map((edge) => edge.relationKey || edge.relationType).filter(Boolean))]
        .sort((left, right) => left.localeCompare(right))
        .map((relationKey) => ({
          relationType: relationKey,
          ...relationStyleForType(relationKey),
        }));

      return { nodes: visibleNodes, edges, relationLegendMeta };
    }

    function getContainerMetrics() {
      const docEl = document.documentElement;
      return {
        width: Math.max(container.clientWidth || 0, docEl ? docEl.clientWidth || 0 : 0, window.innerWidth || 0),
        height: Math.max(container.clientHeight || 0, docEl ? docEl.clientHeight || 0 : 0, window.innerHeight || 0),
      };
    }

    function waitForContainerSize(maxAttempts = 60, delayMs = 50) {
      return new Promise((resolve, reject) => {
        let attempts = 0;
        const check = () => {
          attempts += 1;
          const { width, height } = getContainerMetrics();
          if (width > 24 && height > 24) {
            resolve({ width, height });
            return;
          }
          if (attempts >= maxAttempts) {
            reject(new Error('G6 container has no size yet.'));
            return;
          }
          window.setTimeout(check, delayMs);
        };
        check();
      });
    }

    async function renderElements(elements, requestLike, options = {}) {
      const request = normalizeGraphRequest(requestLike);
      const query = String(request.query || currentQuery || '').trim() || 'LINE1';
      const sourceLabel = options.sourceLabel === 'qa' ? 'qa' : 'query';
      currentQuery = query;
      currentQueryType = request.queryType || '';
      currentClassQuery = currentQueryType === 'disease_class' ? String(request.classQuery || query).trim() : '';
      if (sourceLabel !== 'qa') {
        hooks.setQueryUi(query);
        hooks.syncRouteState({
          query,
          queryType: currentQueryType,
          classQuery: currentClassQuery,
          keyNodeLevel: currentKeyNodeLevel,
          fixedView,
          showLabels: currentShowAllLabels,
          lang: 'en',
        });
      }
      hooks.setMode('dynamic', {
        query,
        queryType: currentQueryType,
        classQuery: currentClassQuery,
        source: sourceLabel,
      });
      if (!options.skipInitialStatus) {
        hooks.setStatus(
          sourceLabel === 'qa'
            ? `Synchronizing answer graph for ${query} ...`
            : `Rendering graph for ${query} (key-node level ${currentKeyNodeLevel}) ...`,
        );
      }

      try {
        await ensureResources();
        const metrics = await waitForContainerSize();
        if ((container.clientWidth || 0) < 25 && metrics.width > 0) {
          container.style.width = `${metrics.width}px`;
        }
        if ((container.clientHeight || 0) < 25 && metrics.height > 0) {
          container.style.height = `${metrics.height}px`;
        }

        const payloadElements = Array.isArray(elements) ? elements : [];
        const graphDataOptions = { ...(options.graphDataOptions || {}) };
        if (Object.prototype.hasOwnProperty.call(graphDataOptions, 'showAllLabels')) {
          currentShowAllLabels = graphDataOptions.showAllLabels === true;
        }
        graphDataOptions.showAllLabels = currentShowAllLabels;
        const showEdgeLabels = graphDataOptions.showEdgeLabels === true;
        const data = buildGraphData(payloadElements, graphDataOptions);

        if (!Array.isArray(data.nodes) || data.nodes.length === 0) {
          if (graph && typeof graph.destroy === 'function') {
            graph.destroy();
            graph = null;
          }
          container.innerHTML = '';
          hooks.onSelection?.(null);
          hooks.setDetail(
            'No visible graph nodes',
            'The current legend filters hide all nodes for this graph. Re-enable at least one node type to render the subgraph.',
          );
          hooks.setStatus(
            sourceLabel === 'qa'
              ? 'The current QA graph has no visible nodes under the active legend filters.'
              : `No visible nodes remain for ${query} under the active legend filters.`,
          );
          return { elements: payloadElements };
        }

        if (graph && typeof graph.destroy === 'function') {
          graph.destroy();
        }

        graph = new Graph({
          container,
          autoFit: false,
          data,
          node: {
            style: {
              size: (d) => d.size,
              fill: (d) => d.fillColor || TYPE_COLORS[d.nodeType] || '#94a3b8',
              stroke: (d) => d.strokeColor || TYPE_STROKES[d.nodeType] || '#111111',
              lineWidth: 2,
              labelText: (d) => {
                if (d.showAllLabels) return showAllLabelText(d);
                if (d.nodeType === 'TE' && teShouldShowLabel(d)) return d.displayLabel || d.rawLabel || '';
                if (secondaryShouldShowLabel(d)) return secondaryLabelText(d);
                return '';
              },
              labelPlacement: 'center',
              labelFill: '#111111',
              labelFontSize: (d) => {
                if (d.showAllLabels) return showAllLabelFontSize(d);
                if (d.nodeType === 'TE' && teShouldShowLabel(d)) return teLabelFontSize(d);
                if (secondaryShouldShowLabel(d)) return secondaryLabelFontSize(d);
                return 10;
              },
              labelFontWeight: 700,
              labelStroke: '#ffffff',
              labelLineWidth: 3,
              labelLineJoin: 'round',
            },
          },
          edge: {
            style: {
              stroke: (edge) => {
                if (edge.strokeColor) return edge.strokeColor;
                const source = resolveNode(edge.source, data.nodes);
                const target = resolveNode(edge.target, data.nodes);
                const alpha = edge.synthetic ? 0.62 : 0.5;
                return mixEdgeColor(source?.nodeType, target?.nodeType, alpha);
              },
              lineWidth: (edge) => (edge.synthetic ? 1.9 : 1.5),
              lineDash: (edge) => edge.lineDash || [],
              labelText: (edge) => {
                if (!showEdgeLabels) return '';
                const relationType = String(edge.relationKey || edge.relation || edge.relationType || '').trim();
                const pmidCount = Array.isArray(edge.pmids) ? edge.pmids.length : 0;
                if (!relationType && pmidCount <= 0) return '';
                return pmidCount > 0 ? `${relationType} (${pmidCount})` : relationType;
              },
              labelFontSize: 9,
              labelFill: '#334155',
              labelBackground: true,
              labelBackgroundFill: 'rgba(255,255,255,0.82)',
              labelBackgroundRadius: 4,
            },
          },
          layout: {
            type: 'd3-force',
            link: {
              distance: (edge) => {
                const source = resolveNode(edge.source, data.nodes);
                const target = resolveNode(edge.target, data.nodes);
                if (!source || !target) return 80;
                if (source.nodeType === 'DiseaseClass' || target.nodeType === 'DiseaseClass') return 120;
                if (source.team === target.team) return 96;
                if (source.nodeType === 'Disease' || target.nodeType === 'Disease') return 300;
                return 220;
              },
              strength: (edge) => {
                const source = resolveNode(edge.source, data.nodes);
                const target = resolveNode(edge.target, data.nodes);
                if (!source || !target) return 0.1;
                if (source.nodeType === 'DiseaseClass' || target.nodeType === 'DiseaseClass') return 0.92;
                if (source.team === target.team) return 0.5;
                if (source.nodeType === 'Disease' || target.nodeType === 'Disease') return 0.06;
                return 0.12;
              },
            },
            manyBody: {
              strength: (node) => {
                const size = typeof node.size === 'number' ? node.size : 16;
                if (node.nodeType === 'DiseaseClass') return -(240 + size * 4.6);
                return -(170 + size * 3.8);
              },
            },
            collide: {
              radius: (node) => {
                if (node.nodeType === 'DiseaseClass') return node.size / 2 + 46;
                if (node.nodeType === 'TE') return node.size / 2 + 38;
                if (node.nodeType === 'Disease') return node.size / 2 + 34;
                return node.size / 2 + 30;
              },
              strength: 1,
              iterations: 16,
            },
          },
          behaviors: [
            {
              type: 'drag-element-force',
              trigger: [],
              enable: (event) => event.targetType === 'node',
            },
            'zoom-canvas',
            'drag-canvas',
          ],
        });

        await graph.render();
        graph.off?.('node:click');
        graph.off?.('edge:click');
        graph.off?.('canvas:click');
        graph.on('node:click', async (event) => {
          const nodeId = event?.target?.id;
          const node = data.nodes.find((item) => item.id === nodeId);
          if (!node) return;
          hooks.onSelection(node);
          hooks.setDetail(node.displayLabel || node.rawLabel, node.description);
          const expanded = await Promise.resolve(hooks.onNodeExpand(node));
          if (expanded || expanded === false) return;
          if (!fixedView && (node.nodeType === 'DiseaseClass' || node.nodeType === 'DiseaseCategory')) {
            const classQuery = String(node.classQuery || node.diseaseClass || node.queryLabel || node.displayLabel || node.rawLabel || '').trim();
            if (classQuery) {
              const handled = await Promise.resolve(
                hooks.onDiseaseClassClick(node, {
                  query: classQuery,
                  queryType: 'disease_class',
                  classQuery,
                })
              );
              if (handled) return;
              loadGraph({
                query: classQuery,
                queryType: 'disease_class',
                classQuery,
              });
            }
            return;
          }
          if (!fixedView && node.queryLabel) {
            loadGraph(node.queryLabel);
          }
        });
        graph.on('edge:click', (event) => {
          const edgeId = event?.target?.id;
          const edge = data.edges.find((item) => item.id === edgeId);
          if (!edge) return;
          hooks.onSelection(null);
          hooks.setDetailHtml(buildEdgeDetailHtml(edge, data.nodes));
        });
        graph.on('canvas:click', () => {
          hooks.onSelection(null);
          hooks.setDetail('No node selected', 'Click a node or edge to inspect graph details.');
        });

        hooks.onSelection(null);
        hooks.setDetail('No node selected', 'Click a node or edge to inspect graph details.');
        hooks.setStatus(
          sourceLabel === 'qa'
            ? `Loaded ${data.nodes.length} nodes and ${data.edges.length} edges from the current QA answer.`
            : `Loaded ${data.nodes.length} nodes and ${data.edges.length} edges for ${query}.`,
        );
        return { elements: payloadElements, relationLegendMeta: data.relationLegendMeta };
      } catch (error) {
        hooks.setStatus(`Failed: ${error && error.message ? error.message : 'unknown error'}`);
        console.error('G6 graph failed:', error);
        throw error;
      }
    }

    async function loadGraph(requestLike, options = {}) {
      const request = normalizeGraphRequest(requestLike);
      const query = String(request.query || '').trim() || 'LINE1';
      hooks.setStatus(`Loading graph for ${query} (key-node level ${currentKeyNodeLevel}) ...`);

      try {
        const endpoint = new URL(window.__TEKG_PATHS.apiUrl('graph.php'), window.location.origin);
        endpoint.searchParams.set('q', query);
        endpoint.searchParams.set('key_level', String(currentKeyNodeLevel));
        if (request.queryType === 'disease_class') {
          endpoint.searchParams.set('type', 'disease_class');
          endpoint.searchParams.set('class', request.classQuery || query);
        }

        const response = await fetch(endpoint.toString(), {
          credentials: 'same-origin',
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();
        const graphDataOptions = { ...(options.graphDataOptions || {}) };
        if (!Object.prototype.hasOwnProperty.call(graphDataOptions, 'visibleTypes')) {
          const currentBridge = window.__TEKG_G6_BRIDGE;
          if (currentBridge && typeof currentBridge.getVisibleTypes === 'function') {
            graphDataOptions.visibleTypes = currentBridge.getVisibleTypes();
          } else if (window.parent && window.parent !== window) {
            const parentBridge = window.parent.__TEKG_G6_BRIDGE;
            if (parentBridge && typeof parentBridge.getVisibleTypes === 'function') {
              graphDataOptions.visibleTypes = parentBridge.getVisibleTypes();
            }
          }
        }
        if (!Object.prototype.hasOwnProperty.call(graphDataOptions, 'showAllLabels')) {
          const currentBridge = window.__TEKG_G6_BRIDGE;
          if (currentBridge && typeof currentBridge.getShowLabels === 'function') {
            graphDataOptions.showAllLabels = currentBridge.getShowLabels();
          } else if (window.parent && window.parent !== window) {
            const parentBridge = window.parent.__TEKG_G6_BRIDGE;
            if (parentBridge && typeof parentBridge.getShowLabels === 'function') {
              graphDataOptions.showAllLabels = parentBridge.getShowLabels();
            }
          }
        }
        if (!Object.prototype.hasOwnProperty.call(graphDataOptions, 'showEdgeLabels')) {
          const currentBridge = window.__TEKG_G6_BRIDGE;
          if (currentBridge && typeof currentBridge.getShowEdgeLabels === 'function') {
            graphDataOptions.showEdgeLabels = currentBridge.getShowEdgeLabels();
          } else if (window.parent && window.parent !== window) {
            const parentBridge = window.parent.__TEKG_G6_BRIDGE;
            if (parentBridge && typeof parentBridge.getShowEdgeLabels === 'function') {
              graphDataOptions.showEdgeLabels = parentBridge.getShowEdgeLabels();
            }
          }
        }
        const rendered = await renderElements(payload.elements || [], request, {
          sourceLabel: 'query',
          skipInitialStatus: true,
          graphDataOptions,
        });
        return { ...payload, elements: rendered.elements, relationLegendMeta: rendered.relationLegendMeta };
      } catch (error) {
        hooks.setStatus(`Failed: ${error && error.message ? error.message : 'unknown error'}`);
        console.error('G6 graph failed:', error);
        throw error;
      }
    }

    function resize() {
      const metrics = getContainerMetrics();
      if ((container.clientWidth || 0) < 25 && metrics.width > 0) {
        container.style.width = `${metrics.width}px`;
      }
      if ((container.clientHeight || 0) < 25 && metrics.height > 0) {
        container.style.height = `${metrics.height}px`;
      }
      if (graph && typeof graph.resize === 'function') {
        graph.resize();
      }
    }

    function setFixedView(next) {
      fixedView = !!next;
      hooks.syncRouteState({
        query: currentQuery,
        queryType: currentQueryType,
        classQuery: currentClassQuery,
        keyNodeLevel: currentKeyNodeLevel,
        fixedView,
        showLabels: currentShowAllLabels,
          lang: 'en',
      });
      return Promise.resolve(fixedView);
    }

    function setKeyNodeLevel(level) {
      currentKeyNodeLevel = Math.max(1, Math.min(10, Number(level) || 1));
      hooks.syncRouteState({
        query: currentQuery,
        queryType: currentQueryType,
        classQuery: currentClassQuery,
        keyNodeLevel: currentKeyNodeLevel,
        fixedView,
        showLabels: currentShowAllLabels,
        lang: currentLang,
      });
      if (!currentQuery) return Promise.resolve();
      return loadGraph(buildCurrentRequest());
    }

    function setLanguage(_lang) {
      currentLang = 'en';
      hooks.syncRouteState({
        query: currentQuery,
        queryType: currentQueryType,
        classQuery: currentClassQuery,
        keyNodeLevel: currentKeyNodeLevel,
        fixedView,
        showLabels: currentShowAllLabels,
        lang: 'en',
      });
      if (!currentQuery) return Promise.resolve();
      return loadGraph(buildCurrentRequest());
    }

    function init() {
      window.addEventListener('resize', resize);
      return ensureResources()
        .catch((error) => {
          console.warn('Failed to load shared English resources for G6 graph:', error);
        })
        .finally(() => {
          hooks.onReady();
        });
    }

    return {
      init,
      ensureResources,
      loadGraph,
      renderElements,
      resize,
      setFixedView,
      setKeyNodeLevel,
      setLanguage,
      getCurrentQuery: () => currentQuery,
      getCurrentRequest: () => buildCurrentRequest(),
      getFixedView: () => fixedView,
      getKeyNodeLevel: () => currentKeyNodeLevel,
      getShowAllLabels: () => currentShowAllLabels,
      escapeHtml,
    };
  }

  window.__TEKG_G6_SHARED = {
    createRunner,
    escapeHtml,
  };
}());
