    const GRAPH_TUNING = {
      focusZoomStep: 0.01,
      neighborOpacity: 0.88,
      dimmedOpacity: 0.38,
      evidenceNodeRepulsion: 900000,
      diseaseFunctionRepulsion: 700000,
      teNodeRepulsion: 600000,
      paperOuterOffset: 220,
      overlapPadding: 28,
      overlapIterations: 14
    };
    const demoData = window.GRAPH_DEMO_DATA || {elements:[],qa:{}};
    const initialElements = JSON.parse(JSON.stringify(demoData.elements || []));
    const el = id => document.getElementById(id);
    const nodeDetails = el('node-details'), searchInput = el('node-search'), nav = el('search-results-nav'), prevBtn = el('prev-result'), nextBtn = el('next-result'), resultCounter = el('result-counter'), resultName = el('result-name'), chatMessages = el('chat-messages'), userInput = el('user-question'), sendBtn = el('send-question');
    let currentLang = 'zh', currentAnswerStyle = 'simple', currentAnswerDepth = 'shallow', currentModelProvider = 'qwen', currentCustomPrompt = '', customPromptDraft = '', currentCustomDepth = {rows:12,references:8}, customDepthDraft = {rows:12,references:8}, customEditorOpen = false, customEditorMode = 'prompt', previousAnswerStyleBeforeCustom = 'simple', previousAnswerDepthBeforeCustom = 'shallow', searchResults = [], currentResultIndex = -1, focusLevel = 0, searchDebounceId = null, fixedView = false, currentGraphKind = 'default-tree';
    let terminologyNames = {zh:{}, en:{}}, terminologyNameLookup = {zh:{}, en:{}}, terminologyRelations = {zh:{}, en:{}};
    let ui = {zh:{},en:{}}; 
    const typeLabel = {zh:{TE:'转座元件',Disease:'疾病',Function:'功能/机制',Paper:'文献'},en:{TE:'Transposable Element',Disease:'Disease',Function:'Function/Mechanism',Paper:'Paper'}};
    const relLabel = {zh:{SUBFAMILY_OF:'包含','与...相关':'与…相关','不导致':'不导致','促进':'促进','介导':'介导','报道':'报道','影响':'影响','执行':'执行','参与':'参与','调控':'调控'},en:{SUBFAMILY_OF:'contains','与...相关':'associated with','不导致':'does not cause','促进':'promotes','介导':'mediates','报道':'reports','影响':'affects','执行':'executes','参与':'participates in','调控':'regulates'}};
    const nameMap = {zh:{},en:{}};
    const descMap = {zh:{},en:{}};
    let teDescMap = {zh:{}, en:{}};
    let entityDescMap = {
      zh: {Disease:{}, Function:{}},
      en: {Disease:{}, Function:{}}
    };
    let localQaTemplates = {zh:{}, en:{}};
    const DISPLAY_NAME_OVERRIDES = {
      zh: {'L1':'LINE1','LINE-1':'LINE1'},
      en: {'L1':'LINE1','LINE-1':'LINE1'}
    };
    const getTypeColor = t => ({TE:'#2563eb',Disease:'#ef4444',Function:'#10b981',Paper:'#f59e0b'}[t] || '#94a3b8');
    function containsChinese(text){return /[\u4e00-\u9fff]/.test(text || '');}
    function getType(type){return (typeLabel[currentLang] && typeLabel[currentLang][type]) || type;}
    function rebuildTerminologyIndexes(){
      terminologyNameLookup = {zh:{}, en:{}};
      ['zh','en'].forEach(lang=>{
        const source = terminologyNames[lang] || {};
        Object.keys(source).forEach(key=>{
          terminologyNameLookup[lang][key] = source[key];
          terminologyNameLookup[lang][String(key).toLowerCase()] = source[key];
        });
      });
    }
    function mergeTerminology(payload){
      if(!payload || typeof payload !== 'object') return;
      ['zh','en'].forEach(lang=>{
        terminologyNames[lang] = Object.assign({}, terminologyNames[lang] || {}, payload.names?.[lang] || {});
        terminologyRelations[lang] = Object.assign({}, terminologyRelations[lang] || {}, payload.relations?.[lang] || {});
      });
      rebuildTerminologyIndexes();
    }
    async function loadTerminology(){
      try{
        const res = await fetch(`terminology/te_terminology.json?v=${Date.now()}`, {cache:'no-store'});
        if(!res.ok) throw new Error(`HTTP ${res.status}`);
        const payload = await res.json();
        mergeTerminology(payload);
      }catch(err){
        console.warn('Failed to load terminology table:', err);
      }
      try{
        const overrideRes = await fetch(`terminology/te_terminology_overrides.json?v=${Date.now()}`, {cache:'no-store'});
        if(!overrideRes.ok) throw new Error(`HTTP ${overrideRes.status}`);
        const overridePayload = await overrideRes.json();
        mergeTerminology(overridePayload);
      }catch(err){
        console.warn('Failed to load terminology override table:', err);
      }
    }
    async function loadTeDescriptions(){
      try{
        const res = await fetch(`data/processed/te_descriptions.json?v=${Date.now()}`, {cache:'no-store'});
        if(!res.ok) throw new Error(`HTTP ${res.status}`);
        const payload = await res.json();
        teDescMap = {
          zh: Object.assign({}, payload?.zh || {}),
          en: Object.assign({}, payload?.en || {})
        };
      }catch(err){
        console.warn('Failed to load TE descriptions:', err);
        teDescMap = {zh:{}, en:{}};
      }
    }
    async function loadEntityDescriptions(){
      try{
        const res = await fetch(`data/processed/entity_descriptions.json?v=${Date.now()}`, {cache:'no-store'});
        if(!res.ok) throw new Error(`HTTP ${res.status}`);
        const payload = await res.json();
        entityDescMap = {
          zh: {
            Disease: Object.assign({}, payload?.zh?.Disease || {}),
            Function: Object.assign({}, payload?.zh?.Function || {})
          },
          en: {
            Disease: Object.assign({}, payload?.en?.Disease || {}),
            Function: Object.assign({}, payload?.en?.Function || {})
          }
        };
      }catch(err){
        console.warn('Failed to load entity descriptions:', err);
        entityDescMap = {zh:{Disease:{}, Function:{}}, en:{Disease:{}, Function:{}}};
      }
    }
    async function loadUiText(){
      try{
        const res = await fetch(`data/processed/ui_text.json?v=${Date.now()}`, {cache:'no-store'});
        if(!res.ok) throw new Error(`HTTP ${res.status}`);
        const payload = await res.json();
        ui = {
          zh: Object.assign({}, payload?.zh || {}),
          en: Object.assign({}, payload?.en || {})
        };
      }catch(err){
        console.warn('Failed to load UI text:', err);
        ui = {zh:{}, en:{}};
      }
    }
    async function loadLocalQaTemplates(){
      try{
        const res = await fetch(`data/processed/local_qa_templates.json?v=${Date.now()}`, {cache:'no-store'});
        if(!res.ok) throw new Error(`HTTP ${res.status}`);
        const payload = await res.json();
        localQaTemplates = {
          zh: Object.assign({}, payload?.zh || {}),
          en: Object.assign({}, payload?.en || {})
        };
      }catch(err){
        console.warn('Failed to load local QA templates:', err);
        localQaTemplates = {zh:{}, en:{}};
      }
    }
    function getLocalQaTemplate(key, fallback=''){
      const source = localQaTemplates[currentLang] || {};
      return source[key] ?? fallback;
    }
    function getDisplayNameKey(name=''){
      if(!name) return '';
      return DISPLAY_NAME_OVERRIDES[currentLang]?.[name] || DISPLAY_NAME_OVERRIDES.en?.[name] || name;
    }
    function lookupMappedName(name=''){
      if(!name) return '';
      const displayKey = getDisplayNameKey(name);
      return terminologyNameLookup[currentLang][displayKey] || terminologyNameLookup[currentLang][String(displayKey).toLowerCase()] || nameMap[currentLang][displayKey] || '';
    }
    function lookupMappedRelation(rel=''){
      if(!rel) return '';
      return terminologyRelations[currentLang][rel] || relLabel[currentLang][rel] || rel;
    }
    function getRel(rel){return lookupMappedRelation(rel);}
    function getPaperDisplayName(name, pmid=''){
      const mapped = lookupMappedName(name);
      if(mapped) return mapped;
      if(currentLang==='zh') return pmid ? `文献 PMID:${pmid}` : '文献节点';
      if(containsChinese(name)) return pmid ? `Paper PMID:${pmid}` : 'Paper node';
      return name;
    }
    function getName(name,type,description='',pmid=''){
      const mapped = lookupMappedName(name);
      if(mapped) return mapped;
      if(type==='Paper') return getPaperDisplayName(name, pmid);
      if(currentLang==='en' && containsChinese(name)) return `${getType(type)} node`;
      return name;
    }
    const inferType = name => {
      const current = cy.nodes().filter(n => n.data('label') === name)[0];
      if(current) return current.data('type') || 'Unknown';
      return ((demoData.elements.find(x => x.data && x.data.label === name && !x.data.source) || {}).data || {}).type || 'Unknown';
    };
    function isBrokenDescription(text=''){
      const value = String(text || '').trim();
      if(!value) return true;
      if(/^\?+$/.test(value)) return true;
      if((value.match(/\?/g)||[]).length >= 4) return true;
      if(/[\u9500\u951b\u956f\u95ba\u95c2\u95b8\u95b7\u7487\u9391]/.test(value)) return true;
      return false;
    }
    function isTreeImportDescription(text=''){
      const value = String(text || '').trim();
      if(!value) return false;
      return /imported from tree\.txt|lineage node imported|lineage reference|original label:/i.test(value);
    }
    function getTeDesc(name, description=''){
      const key = String(getDisplayNameKey(name) || '').trim();
      const mapped = teDescMap[currentLang] && teDescMap[currentLang][key] ? String(teDescMap[currentLang][key]).trim() : '';
      if(mapped && !isBrokenDescription(mapped)) return mapped;
      if(currentLang==='en'){
        const rawDesc = String(description || '').trim();
        if(rawDesc && !isBrokenDescription(rawDesc) && !isTreeImportDescription(rawDesc)) return rawDesc;
      }
      if(currentLang==='zh'){
        return `${getName(name,'TE')} 是当前知识图谱中的转座元件节点。当前页面主要展示它在 TE 谱系结构以及相关机制、疾病和文献证据中的位置。`;
      }
      return `${getName(name,'TE')} is a transposable element node in the current knowledge graph. The current page mainly shows its position within TE lineage structure and related mechanism, disease, and literature evidence.`;
    }
    function getEntityDesc(name,type,description=''){
      const key = String(name || '').trim();
      const mapped = entityDescMap[currentLang]?.[type]?.[key] ? String(entityDescMap[currentLang][type][key]).trim() : '';
      if(mapped && !isBrokenDescription(mapped)) return mapped;
      if(currentLang==='en'){
        const rawDesc = String(description || '').trim();
        if(rawDesc && !isBrokenDescription(rawDesc)) return rawDesc;
      }
      return '';
    }
    function buildDefaultDesc(name,type,pmid=''){
      if(type==='TE') return getTeDesc(name,'');
      if(type==='Function') return currentLang==='zh' ? `${getName(name,type,'',pmid)} \u662f\u5f53\u524d\u77e5\u8bc6\u56fe\u8c31\u4e2d\u7684\u529f\u80fd\u6216\u673a\u5236\u8282\u70b9\u3002` : `${getName(name,type,'',pmid)} is a function or mechanism node in the current graph.`;
      if(type==='Disease') return currentLang==='zh' ? `${getName(name,type,'',pmid)} \u662f\u5f53\u524d\u77e5\u8bc6\u56fe\u8c31\u4e2d\u7684\u75be\u75c5\u8282\u70b9\u3002` : `${getName(name,type,'',pmid)} is a disease node in the current graph.`;
      if(type==='Paper') return currentLang==='zh' ? `\u8be5\u8282\u70b9\u8868\u793a\u652f\u6491\u5f53\u524d\u56fe\u8c31\u5173\u7cfb\u7684\u6587\u732e\u8bb0\u5f55${pmid ? `\uff08PMID: ${pmid}\uff09` : ''}\u3002` : `This node represents a literature record supporting the current graph knowledge${pmid ? ` (PMID: ${pmid})` : ''}.`;
      return '';
    }
    function getDesc(name,type,description='',pmid=''){
      const rawDesc = String(description || '').trim();
      if(type==='TE') return getTeDesc(name, rawDesc);
      if(type==='Disease' || type==='Function'){
        const entityDesc = getEntityDesc(name, type, rawDesc);
        if(entityDesc) return entityDesc;
      }
      if(rawDesc && !isBrokenDescription(rawDesc)) return rawDesc;
      const mappedDesc = descMap[currentLang] && descMap[currentLang][name] ? String(descMap[currentLang][name]).trim() : '';
      if(mappedDesc && !isBrokenDescription(mappedDesc)) return mappedDesc;
      return buildDefaultDesc(name,type,pmid);
    }
    function isPureTeTreeElements(elements=[]){
      return Array.isArray(elements) && elements.length>0 && elements.every(item=>{
        const data=item&&item.data?item.data:{};
        if(data.source && data.target) return data.relation==='SUBFAMILY_OF';
        return data.type==='TE';
      });
    }
