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
    function buildLayout(kind=currentGraphKind){
      if(kind==='default-tree'){
        return {
          name:'preset',
          fit:true,
          padding:80,
          animate:false
        };
      }
      return{name:'cose',fit:true,padding:75,randomize:true,animate:false,nodeDimensionsIncludeLabels:true,componentSpacing:180,nestingFactor:.9,gravity:45,numIter:1800,initialTemp:220,coolingFactor:.96,minTemp:1,idealEdgeLength:edge=>{const rel=edge.data('relation')||''; if(rel==='SUBFAMILY_OF') return 170; if(rel==='EVIDENCE_RELATION') return 240; return 210;},edgeElasticity:edge=>{const rel=edge.data('relation')||''; return rel==='EVIDENCE_RELATION'?40:90;},nodeRepulsion:node=>{const type=node.data('type')||''; return type==='Paper'?GRAPH_TUNING.evidenceNodeRepulsion:(type==='Disease'||type==='Function'?GRAPH_TUNING.diseaseFunctionRepulsion:GRAPH_TUNING.teNodeRepulsion);}}
    }
    const cy = cytoscape({container:el('cy'),elements:demoData.elements,style:[{selector:'node',style:{'label':e=>getName(e.data('label'),e.data('type'),e.data('description')||'',e.data('pmid')||''),'font-size':e=>{const d=e.data('tree_depth'); if(d===0) return '20px'; if(d===1) return '17px'; if(d===2) return '14px'; if(d===3) return '12px'; return '11px';},'min-zoomed-font-size':9,'text-valign':'center','text-halign':'center','background-color':e=>getTypeColor(e.data('type')),'color':'#0f172a','text-outline-width':3,'text-outline-color':'#fff','width':'label','height':'label','padding':e=>{const d=e.data('tree_depth'); if(d===0) return '20px'; if(d===1) return '17px'; if(d===2) return '14px'; if(d===3) return '11px'; return '14px';},'text-wrap':'wrap','text-max-width':150,'border-width':2,'border-color':'#fff','shape':'round-rectangle'}},{selector:'edge',style:{'width':2,'line-color':'#cbd5e1','target-arrow-color':'#64748b','target-arrow-shape':'triangle','curve-style':'bezier','control-point-step-size':55,'label':e=>{const rel=e.data('relation')||''; return rel==='EVIDENCE_RELATION'?'':getRel(rel)},'font-size':'9px','min-zoomed-font-size':8,'text-rotation':'autorotate','color':'#334155','text-background-color':'rgba(255,255,255,0.92)','text-background-opacity':1,'text-background-padding':'2px','text-outline-width':0}},{selector:'.highlight',style:{'border-width':6,'border-color':'#0f172a','font-weight':'800','overlay-color':'#2563eb','overlay-opacity':0.12,'overlay-padding':'16px','shadow-blur':18,'shadow-color':'#2563eb','shadow-opacity':0.35,'shadow-offset-x':0,'shadow-offset-y':0,'z-index':999}},{selector:'.neighbor',style:{'border-width':4,'border-color':'#60a5fa','overlay-color':'#93c5fd','overlay-opacity':0.03,'overlay-padding':'6px','opacity':GRAPH_TUNING.neighborOpacity,'shadow-blur':4,'shadow-color':'#93c5fd','shadow-opacity':0.08}},{selector:'.dimmed',style:{'opacity':GRAPH_TUNING.dimmedOpacity}},{selector:'.focus-edge',style:{'label':e=>getRel(e.data('relation')),'width':4,'line-color':'#2563eb','target-arrow-color':'#2563eb','font-size':'11px','color':'#0f172a','text-background-color':'rgba(255,255,255,0.98)','text-background-opacity':1,'text-background-padding':'4px','opacity':0.95}},{selector:'.edge-dimmed',style:{'opacity':0.28}}],layout:buildLayout(),wheelSensitivity:.2});
    function normalizeElementsForGraphKind(elements, graphKind){
      const cloned = JSON.parse(JSON.stringify(elements || []));
      if(graphKind!=='dynamic') return cloned;
      for(const item of cloned){
        const d = item && item.data ? item.data : null;
        if(!d || !d.source || !d.target) continue;
        if(d.relation==='SUBFAMILY_OF'){
          const originalSource = d.source;
          d.source = d.target;
          d.target = originalSource;
        }
      }
      return cloned;
    }
    function applyGraphElements(elements, focusLabel='', options={}){
      currentGraphKind = options.graphKind || (isPureTeTreeElements(elements) ? 'default-tree' : 'dynamic');
      cy.elements().remove();
      cy.add(normalizeElementsForGraphKind(elements, currentGraphKind));
      clearHighlights();
      searchResults=[]; currentResultIndex=-1; updateNav();
      const layout = cy.layout(buildLayout(currentGraphKind));
      layout.run();
      setTimeout(()=>{
        if(currentGraphKind!=='default-tree') refineGraphLayout();
          const target = focusLabel
            ? cy.nodes().filter(n => n.data('label') === focusLabel || n.data('query_label') === focusLabel || n.id() === focusLabel)[0]
            : cy.nodes()[0];
          if(currentGraphKind==='default-tree'){
            if(target){
              clearHighlights();
              target.addClass('highlight');
              target.neighborhood('node').addClass('neighbor');
              target.connectedEdges().addClass('focus-edge');
              showNode(target);
            } else nodeDetails.textContent=ui[currentLang].empty;
            cy.fit(undefined,55);
            return;
          }
        if(target) focus(target); else cy.fit(undefined,55);
      }, 320);
    }
    function restoreInitialGraph(){
      searchInput.value='';
      clearHighlights();
      searchResults=[]; currentResultIndex=-1;
      focusLevel=0;
      el('focus-level').value='0';
      updateFocusUi();
      applyGraphElements(initialElements, 'TE', {graphKind:'default-tree'});
    }
    async function loadDynamicGraph(query, fallbackToCurrent=false){
      const keyword=(query||'').trim();
      if(!keyword) return;
      const response = await fetch(`api/graph.php?q=${encodeURIComponent(keyword)}`);
      const payload = await response.json();
      if(!response.ok || !payload.ok) throw new Error(payload.error || 'Graph request failed');
      if(!payload.elements || payload.elements.length===0){
        throw new Error(currentLang==='zh' ? '未找到相关节点或文献子图。' : 'No matching graph fragment was found.');
      }
      applyGraphElements(payload.elements, payload.anchor && payload.anchor.name ? payload.anchor.name : keyword, {graphKind:'dynamic'});
      searchInput.value = payload.anchor && payload.anchor.name ? payload.anchor.name : keyword;
      return payload;
    }
    function renderIntro(){chatMessages.innerHTML=''; addMessage(ui[currentLang].intro,'assistant')}
    function repairModeControls(){
      const host=el('style-segmented');
      if(host){
        host.innerHTML='<button id="mode-simple" class="active" type="button">简单</button><button id="mode-detailed" type="button">详细</button><button id="mode-custom" type="button">自定义</button>';
      }
    }
    function getCustomEditorElements(){
      return {
        editor: el('custom-editor'),
        title: el('custom-editor-title'),
        help: el('custom-editor-help'),
        promptField: el('custom-prompt'),
        depthWrap: el('custom-depth-editor'),
        rowsField: el('custom-rows'),
        referencesField: el('custom-references'),
        rowsLabel: el('custom-rows-label'),
        referencesLabel: el('custom-references-label'),
        depthNote: el('custom-depth-note'),
        msgs: el('chat-messages'),
        inputWrap: document.querySelector('.chat .input')
      };
    }
    function updateCustomEditorUi(){
      const {editor, title, help, promptField, depthWrap, rowsField, referencesField, rowsLabel, referencesLabel, depthNote, msgs, inputWrap} = getCustomEditorElements();
      const isPromptMode = customEditorMode === 'prompt';
      if(title) title.textContent = isPromptMode
        ? (currentLang==='zh' ? '自定义提示词' : 'Custom prompt')
        : (currentLang==='zh' ? '自定义回答深度' : 'Custom answer depth');
      if(help) help.textContent = isPromptMode
        ? (currentLang==='zh'
          ? '在这里写下你希望智能问答遵循的语气、结构、重点或限制条件。点击确认后会暂时保存，并在后续提问时优先使用。'
          : 'Write any tone, structure, emphasis, or constraints you want the QA system to follow. Click confirm to save it temporarily for subsequent questions.')
        : (currentLang==='zh'
          ? '在这里设置回答深度的自定义参数。row 表示最多取多少条结构化关系记录，references 表示最多附上多少篇参考文献。'
          : 'Set custom answer-depth parameters here. Row means the maximum number of structured relation records, and references means the maximum number of cited references.');
      if(promptField){
        promptField.style.display = isPromptMode ? 'block' : 'none';
        promptField.placeholder = currentLang==='zh'
          ? '例如：请用学术但通俗的中文回答；先给结论，再分点说明机制；尽量引用 PMID。'
          : 'For example: answer in academic but plain English; give the conclusion first; then explain mechanisms in bullets; cite PMID whenever possible.';
        if(document.activeElement!==promptField){
          promptField.value = customPromptDraft || currentCustomPrompt || '';
        }
      }
      if(depthWrap) depthWrap.classList.toggle('active', !isPromptMode);
      if(rowsLabel) rowsLabel.textContent = currentLang==='zh' ? 'row（关系条数）' : 'row (relation limit)';
      if(referencesLabel) referencesLabel.textContent = currentLang==='zh' ? 'references（文献条数）' : 'references (reference limit)';
      if(depthNote) depthNote.textContent = currentLang==='zh'
        ? 'row 表示最多取多少条结构化关系记录，references 表示最多附上多少篇参考文献。建议上界分别不超过 12 和 8，避免回答过长。'
        : 'Row means the maximum number of structured relation records, and references means the maximum number of cited references. Recommended upper bounds are 12 and 8 to avoid overly long answers.';
      if(rowsField && document.activeElement!==rowsField) rowsField.value = String(customDepthDraft.rows ?? currentCustomDepth.rows ?? 12);
      if(referencesField && document.activeElement!==referencesField) referencesField.value = String(customDepthDraft.references ?? currentCustomDepth.references ?? 8);
      if(editor) editor.classList.toggle('active', customEditorOpen);
      if(msgs) msgs.style.display = customEditorOpen ? 'none' : 'flex';
      if(inputWrap) inputWrap.style.display = customEditorOpen ? 'none' : 'flex';
    }
    function openCustomPromptEditor(){
      previousAnswerStyleBeforeCustom = currentAnswerStyle==='custom' ? 'custom' : currentAnswerStyle;
      customPromptDraft = currentCustomPrompt || '';
      customEditorMode = 'prompt';
      customEditorOpen = true;
      updateCustomEditorUi();
    }
    function openCustomDepthEditor(){
      previousAnswerDepthBeforeCustom = currentAnswerDepth==='custom' ? 'custom' : currentAnswerDepth;
      customDepthDraft = {
        rows: currentCustomDepth.rows || 12,
        references: currentCustomDepth.references || 8
      };
      customEditorMode = 'depth';
      customEditorOpen = true;
      updateCustomEditorUi();
    }
    function closeCustomEditor({save=false}={}){
      const promptField = el('custom-prompt');
      const rowsField = el('custom-rows');
      const referencesField = el('custom-references');
      if(customEditorMode==='prompt'){
        customPromptDraft = promptField ? String(promptField.value || '').trim() : customPromptDraft;
        if(save){
          currentCustomPrompt = customPromptDraft;
          currentAnswerStyle = 'custom';
        }else if(currentAnswerStyle!=='custom'){
          currentAnswerStyle = previousAnswerStyleBeforeCustom || 'simple';
        }
      }else{
        const parsedRows = Math.max(1, Number(rowsField?.value) || currentCustomDepth.rows || 12);
        const parsedReferences = Math.max(1, Number(referencesField?.value) || currentCustomDepth.references || 8);
        customDepthDraft = {rows: parsedRows, references: parsedReferences};
        if(save){
          currentCustomDepth = {...customDepthDraft};
          currentAnswerDepth = 'custom';
        }else if(currentAnswerDepth!=='custom'){
          currentAnswerDepth = previousAnswerDepthBeforeCustom || 'shallow';
        }
      }
      customEditorOpen = false;
      updateCustomEditorUi();
      updateAnswerModeUi();
    }
    function updateAnswerModeUi(){
      const modeSimpleBtn=el('mode-simple'), modeDetailedBtn=el('mode-detailed'), modeCustomBtn=el('mode-custom');
      const depthShallowBtn=el('depth-shallow'), depthMediumBtn=el('depth-medium'), depthDeepBtn=el('depth-deep'), depthCustomBtn=el('depth-custom');
      const modelQwenBtn=el('model-qwen'), modelDeepSeekBtn=el('model-deepseek');
      el('qa-mode-label').textContent=currentLang==='zh'?'回答模式':'Answer mode';
      el('qa-depth-label').textContent=currentLang==='zh'?'回答深度':'Answer depth';
      el('qa-model-label').textContent = ui[currentLang].modelLabel;
      if(modeSimpleBtn&&modeDetailedBtn&&modeCustomBtn){
        modeSimpleBtn.textContent=currentLang==='zh'?'简单':'Brief';
        modeDetailedBtn.textContent=currentLang==='zh'?'详细':'Detailed';
        modeCustomBtn.textContent=currentLang==='zh'?'自定义':'Custom';
        modeSimpleBtn.classList.toggle('active', currentAnswerStyle==='simple');
        modeDetailedBtn.classList.toggle('active', currentAnswerStyle==='detailed');
        modeCustomBtn.classList.toggle('active', currentAnswerStyle==='custom');
      }
      if(depthShallowBtn&&depthMediumBtn&&depthDeepBtn&&depthCustomBtn){
        depthShallowBtn.textContent=currentLang==='zh'?'浅':'Shallow';
        depthMediumBtn.textContent=currentLang==='zh'?'中':'Medium';
        depthDeepBtn.textContent=currentLang==='zh'?'深':'Deep';
        depthCustomBtn.textContent=currentLang==='zh'?'自定义':'Custom';
        depthShallowBtn.classList.toggle('active', currentAnswerDepth==='shallow');
        depthMediumBtn.classList.toggle('active', currentAnswerDepth==='medium');
        depthDeepBtn.classList.toggle('active', currentAnswerDepth==='deep');
        depthCustomBtn.classList.toggle('active', currentAnswerDepth==='custom');
      }
      if(modelQwenBtn && modelDeepSeekBtn){
        modelQwenBtn.textContent = ui[currentLang].modelQwen;
        modelDeepSeekBtn.textContent = ui[currentLang].modelDeepSeek;
        modelQwenBtn.classList.toggle('active', currentModelProvider==='qwen');
        modelDeepSeekBtn.classList.toggle('active', currentModelProvider==='deepseek');
      }
      updateCustomEditorUi();
    }
    function updateFocusUi(){el('focus-label').textContent=ui[currentLang].focus; el('focus-value').textContent=String(focusLevel);}
    function setAnswerStyle(style){
      currentAnswerStyle = ['simple','detailed','custom'].includes(style) ? style : 'simple';
      if(currentAnswerStyle==='simple' && currentAnswerDepth==='deep') currentAnswerDepth='shallow';
      if(currentAnswerStyle==='detailed' && currentAnswerDepth==='shallow') currentAnswerDepth='deep';
      if(currentAnswerStyle!=='custom'){
        customEditorOpen = false;
      }
      updateAnswerModeUi();
    }
    function setAnswerDepth(depth){if(['shallow','medium','deep','custom'].includes(depth)) currentAnswerDepth=depth; customEditorOpen = false; updateAnswerModeUi();}
    function setModelProvider(provider){
      currentModelProvider = provider === 'deepseek' ? 'deepseek' : 'qwen';
      updateAnswerModeUi();
    }
    function updateFixedViewUi(){
      const btn = el('toggle-fixed-view');
      const label = el('fixed-view-text');
      if(label) label.textContent = fixedView ? ui[currentLang].fixedOn : ui[currentLang].fixedOff;
      if(btn) btn.classList.toggle('active', fixedView);
    }
    function setUi(){document.documentElement.lang=currentLang==='zh'?'zh-CN':'en'; el('page-title').textContent=ui[currentLang].pageTitle; el('page-badge').textContent=ui[currentLang].badge; el('graph-title').textContent=ui[currentLang].graphTitle; el('qa-title').textContent=ui[currentLang].qaTitle; el('page-footer').textContent=ui[currentLang].footer; el('reset-text').textContent=ui[currentLang].reset; searchInput.placeholder=ui[currentLang].search; userInput.placeholder=ui[currentLang].ph; if(!userInput.value || userInput.value===ui.zh.q || userInput.value===ui.en.q) userInput.value=ui[currentLang].q; el('lang-zh').classList.toggle('active', currentLang==='zh'); el('lang-en').classList.toggle('active', currentLang==='en'); updateAnswerModeUi(); updateFocusUi(); updateFixedViewUi(); if(searchResults.length===0) nodeDetails.textContent=ui[currentLang].empty; else showNode(searchResults[currentResultIndex]); renderIntro(); cy.style().update(); updateNav();}
    function showNode(node){const raw=node.data('label'), type=node.data('type') || 'Unknown', description=node.data('description')||'', pmid=node.data('pmid')||''; nodeDetails.innerHTML=`<strong>${getName(raw,type,description,pmid)}</strong> (${getType(type)})<br>${getDesc(raw,type,description,pmid)}<div class="meta">${ui[currentLang].orig}: ${raw}${pmid ? ` | PMID: ${pmid}` : ''} | ${ui[currentLang].deg}: ${node.degree()}</div>`}
    function showEdge(edge){
      const s=getName(edge.source().data('label'),edge.source().data('type'),edge.source().data('description')||'',edge.source().data('pmid')||'');
      const t=getName(edge.target().data('label'),edge.target().data('type'),edge.target().data('description')||'',edge.target().data('pmid')||'');
      const evidenceText=String(edge.data('evidence')||'').trim();
      const rawPmids=edge.data('pmids');
      const pmids=Array.isArray(rawPmids)?rawPmids.filter(Boolean).map(String):[];
      const ev=evidenceText || (pmids.length ? `PMID: ${pmids.join(', ')}` : ui[currentLang].none);
      nodeDetails.innerHTML=`<strong>${s}</strong> → ${getRel(edge.data('relation'))} → <strong>${t}</strong><br>${ui[currentLang].evi}: ${ev}`;
    }
    function clearHighlights(){cy.nodes().removeClass('highlight neighbor dimmed'); cy.edges().removeClass('focus-edge edge-dimmed')}
    function getTargetZoom(level){return 1 + (level/10)*GRAPH_TUNING.focusZoomStep}
    function getGraphCenter(){
      const line1=cy.nodes().filter(n=>n.data('label')==='LINE-1')[0];
      if(line1) return {...line1.position()};
      const nodes=cy.nodes();
      if(nodes.length===0) return {x:0,y:0};
      let sx=0, sy=0;
      nodes.forEach(n=>{sx+=n.position('x'); sy+=n.position('y');});
      return {x:sx/nodes.length, y:sy/nodes.length};
    }
    function pushPaperNodesOutward(){
      const center=getGraphCenter();
      const nonPaper=cy.nodes().filter(n=>n.data('type')!=='Paper');
      let maxRadius=0;
      nonPaper.forEach(n=>{
        const p=n.position();
        maxRadius=Math.max(maxRadius, Math.hypot(p.x-center.x, p.y-center.y));
      });
      const paperNodes=cy.nodes().filter(n=>n.data('type')==='Paper');
      const baseRadius=maxRadius + GRAPH_TUNING.paperOuterOffset;
      paperNodes.forEach((node,index)=>{
        const p=node.position();
        let dx=p.x-center.x, dy=p.y-center.y;
        let angle=Math.atan2(dy,dx);
        if(!Number.isFinite(angle) || (dx===0 && dy===0)) angle=(Math.PI*2*index)/Math.max(paperNodes.length,1);
        const radius=Math.max(baseRadius, Math.hypot(dx,dy) + GRAPH_TUNING.paperOuterOffset*0.6);
        node.position({
          x:center.x + Math.cos(angle)*radius,
          y:center.y + Math.sin(angle)*radius
        });
      });
    }
    function resolveNodeOverlaps(){
      const nodes=cy.nodes().toArray();
      const center=getGraphCenter();
      for(let iter=0; iter<GRAPH_TUNING.overlapIterations; iter+=1){
        let moved=false;
        for(let i=0;i<nodes.length;i+=1){
          for(let j=i+1;j<nodes.length;j+=1){
            const a=nodes[i], b=nodes[j];
            const boxA=a.boundingBox({includeLabels:true, includeOverlays:false});
            const boxB=b.boundingBox({includeLabels:true, includeOverlays:false});
            const overlapX=Math.min(boxA.x2,boxB.x2)-Math.max(boxA.x1,boxB.x1);
            const overlapY=Math.min(boxA.y2,boxB.y2)-Math.max(boxA.y1,boxB.y1);
            if(overlapX<=0 || overlapY<=0) continue;
            moved=true;
            let dx=b.position('x')-a.position('x');
            let dy=b.position('y')-a.position('y');
            if(dx===0 && dy===0){
              dx=(Math.random()-.5)||0.5;
              dy=(Math.random()-.5)||0.5;
            }
            const len=Math.hypot(dx,dy) || 1;
            const ux=dx/len, uy=dy/len;
            const shift=(Math.min(overlapX,overlapY)/2)+GRAPH_TUNING.overlapPadding;
            const aIsPaper=a.data('type')==='Paper', bIsPaper=b.data('type')==='Paper';
            const aWeight=aIsPaper?0.35:0.5;
            const bWeight=bIsPaper?0.65:0.5;
            a.position({x:a.position('x')-ux*shift*aWeight,y:a.position('y')-uy*shift*aWeight});
            b.position({x:b.position('x')+ux*shift*bWeight,y:b.position('y')+uy*shift*bWeight});
            if(aIsPaper){
              const ap=a.position();
              const adx=ap.x-center.x, ady=ap.y-center.y;
              const alen=Math.hypot(adx,ady)||1;
              a.position({x:ap.x+(adx/alen)*8,y:ap.y+(ady/alen)*8});
            }
            if(bIsPaper){
              const bp=b.position();
              const bdx=bp.x-center.x, bdy=bp.y-center.y;
              const blen=Math.hypot(bdx,bdy)||1;
              b.position({x:bp.x+(bdx/blen)*8,y:bp.y+(bdy/blen)*8});
            }
          }
        }
        if(!moved) break;
      }
    }
    function refineGraphLayout(){
      pushPaperNodesOutward();
      resolveNodeOverlaps();
    }
    function focus(node){clearHighlights(); const neighborhood=node.closedNeighborhood(); const others=cy.elements().difference(neighborhood); others.nodes().addClass('dimmed'); others.edges().addClass('edge-dimmed'); node.addClass('highlight'); node.neighborhood('node').addClass('neighbor'); node.connectedEdges().addClass('focus-edge'); showNode(node); if(focusLevel===0){cy.fit(node.closedNeighborhood(),80);} else {const zoom=Math.min(cy.maxZoom(), getTargetZoom(focusLevel)); cy.animate({center:{eles:node},zoom,duration:280});}}
    function updateNav(){if(searchResults.length===0){nav.style.display='none'; clearHighlights(); return;} const n=searchResults[currentResultIndex]; nav.style.display='flex'; resultCounter.textContent=`${currentResultIndex+1}/${searchResults.length}`; resultName.textContent=`${getName(n.data('label'),n.data('type'),n.data('description')||'',n.data('pmid')||'')} (${getType(n.data('type'))})`; prevBtn.disabled=currentResultIndex===0; nextBtn.disabled=currentResultIndex===searchResults.length-1; focus(n)}
    function handleSearch(){
      const keyword=searchInput.value.trim().toLowerCase();
      if(searchDebounceId){clearTimeout(searchDebounceId); searchDebounceId=null;}
      if(!keyword){
        searchResults=[]; currentResultIndex=-1; updateNav(); nodeDetails.textContent=ui[currentLang].empty; return;
      }
      searchResults=cy.nodes().filter(n => `${n.data('label')} ${getName(n.data('label'),n.data('type'),n.data('description')||'',n.data('pmid')||'')}`.toLowerCase().includes(keyword)).toArray();
      currentResultIndex=searchResults.length>0?0:-1;
      updateNav();
      if(searchResults.length===0 && keyword.length>=2){
        searchDebounceId = setTimeout(async()=>{
          try{
            await loadDynamicGraph(searchInput.value.trim());
          }catch(_err){}
        }, 350);
      }
    }
    function escapeHtml(text){const div=document.createElement('div'); div.textContent=text; return div.innerHTML;}
    function renderBubbleContent(text,sender){if(sender==='user') return escapeHtml(text).replace(/\n/g,'<br>'); if(window.marked){marked.setOptions({breaks:true,gfm:true}); return marked.parse(text);} return text.replace(/\n/g,'<br>');}
    function addMessage(text,sender){const m=document.createElement('div'); m.className=`msg ${sender}`; m.innerHTML=`<div class="avatar"><i class="fas ${sender==='user'?'fa-user':'fa-robot'}"></i></div><div class="bubble">${renderBubbleContent(text,sender)}</div>`; chatMessages.appendChild(m); chatMessages.scrollTop=chatMessages.scrollHeight; return m}
    function makeRef(items){if(!items || items.length===0) return `<div class="ref">${ui[currentLang].noneLocal}</div>`; return `<div class="ref"><strong>${ui[currentLang].ref}:</strong><br>${items.map(i => `${getName(i.name, inferType(i.name), '', (i.pmids||[])[0]||'')}${(i.pmids||[]).length ? ` (PMID: ${(i.pmids||[]).join(', ')})` : ''}`).join('<br>')}</div>`}
    function getCustomPromptValue(){
      const field = el('custom-prompt');
      if(customEditorOpen && field) return String(field.value || '').trim();
      return String(currentCustomPrompt || '').trim();
    }
    function buildEffectiveQuestion(question){
      const customPrompt = getCustomPromptValue();
      currentCustomPrompt = customPrompt;
      return question;
    }
    function answerLocal(q){
      const qaTpl = localQaTemplates[currentLang] || {};
      const modeLabel=currentLang==='zh'
        ? (currentAnswerStyle==='detailed'?'详细模式':currentAnswerStyle==='custom'?'自定义模式':'简单模式')
        : (currentAnswerStyle==='detailed'?'Detailed mode':currentAnswerStyle==='custom'?'Custom mode':'Brief mode');
      const depthLabel=currentAnswerDepth==='custom'
        ? (currentLang==='zh'
          ? `自定义（row=${currentCustomDepth.rows}，references=${currentCustomDepth.references}）`
          : `Custom (row=${currentCustomDepth.rows}, references=${currentCustomDepth.references})`)
        : (currentLang==='zh' ? ({shallow:'浅',medium:'中',deep:'深'}[currentAnswerDepth]||'浅') : ({shallow:'Shallow',medium:'Medium',deep:'Deep'}[currentAnswerDepth]||'Shallow'));
      const intro=`> ${modeLabel} · ${currentLang==='zh' ? '回答深度：' : 'Depth: '}${depthLabel}\n\n`;
      const providerLine = `${ui[currentLang].providerPrefix}${currentModelProvider === 'deepseek' ? ui[currentLang].modelDeepSeek : ui[currentLang].modelQwen}\n\n`;
      const lower=(q||'').toLowerCase();
      const asksLine1=lower.includes('line-1') || lower.includes('line1') || lower.includes('l1');
      const asksFunction=lower.includes('功能') || lower.includes('机制') || lower.includes('function') || lower.includes('mechanism');
      const asksDisease=lower.includes('疾病') || lower.includes('癌') || lower.includes('病') || lower.includes('disease') || lower.includes('cancer');
      const asksPaper=lower.includes('文献') || lower.includes('证据') || lower.includes('paper') || lower.includes('evidence');
      const asksRelation=(lower.includes('关系') || lower.includes('是什么关系') || lower.includes('relationship')) && lower.includes('l1hs');

      const formatRef=(i)=>`- ${getName(i.name, inferType(i.name), '', (i.pmids||[])[0]||'')}${(i.pmids||[]).length ? ` (PMID: ${(i.pmids||[]).join(', ')})` : ''}`;
      const sections = qaTpl.detailedSections || {
        conclusion: currentLang==='zh' ? '结论' : 'Conclusion',
        mechanism: currentLang==='zh' ? '机制与关系解释' : 'Mechanistic Interpretation',
        evidence: currentLang==='zh' ? '证据与文献' : 'Evidence and References',
        limitations: currentLang==='zh' ? '局限与说明' : 'Limitations'
      };
      const buildDetailed=(title, items, refs, limitItems=8, limitRefs=8)=>{ 
        const bulletLines=(items||[]).slice(0,limitItems).map(i=>`- ${getRel(i.predicate)} ${getName(i.name, inferType(i.name))}`).join('\n') || getLocalQaTemplate('detailedEmptyItems', currentLang==='zh' ? '- 当前未检索到可展开的本地结构化记录。' : '- No expandable local structured records were found.');
        const refLines=(refs||[]).slice(0,limitRefs).map(formatRef).join('\n') || getLocalQaTemplate('detailedNoRefs', currentLang==='zh' ? '当前暂无本地参考文献。' : 'No local references are currently available.');
        const limitations = (qaTpl.detailedLimitations || [
          currentLang==='zh' ? '当前结果来自本地图谱子图，完整答案仍以后端知识检索为准。' : 'This local answer is based on the demo subgraph rather than the full backend retrieval pipeline.',
          currentLang==='zh' ? '若动态图谱未完全展开，部分关系可能未显示在当前页面。' : 'Some relations may remain hidden when the current graph fragment is incomplete.'
        ]).map(item=>`- ${item}`).join('\n');
        return `${intro}## ${sections.conclusion}\n${title}\n\n## ${sections.mechanism}\n${bulletLines}\n\n## ${sections.evidence}\n${refLines}\n\n## ${sections.limitations}\n${limitations}`;
      };
      const buildSimple=(lead, items, refs, limitItems=5, limitRefs=5)=>{
        const body=(items||[]).slice(0,limitItems).map(i=>`${getRel(i.predicate)} ${getName(i.name, inferType(i.name))}`).join(currentLang==='zh'?'；':'; ');
        const refsHtml=makeRef((refs||[]).slice(0,limitRefs));
          return `${providerLine}${intro}${lead}${body || ui[currentLang].no}${refsHtml}`;
      };

      if(asksRelation){
        const refs=demoData.qa.l1hs_papers || [];
        if(currentAnswerStyle==='detailed'){
          const relation = qaTpl.relation || {};
          const bullets = (relation.bullets || []).map(item=>`- ${item}`).join('\n');
          const refLines = refs.slice(0,6).map(formatRef).join('\n') || getLocalQaTemplate('detailedNoRefs', currentLang==='zh' ? '当前暂无本地参考文献。' : 'No local references are currently available.');
          const limitations = (relation.limitations || []).map(item=>`- ${item}`).join('\n');
          return `${intro}## ${sections.conclusion}\n${relation.title || ''}\n\n## ${sections.mechanism}\n${bullets}\n\n## ${sections.evidence}\n${refLines}\n\n## ${sections.limitations}\n${limitations}`;
        }
        return `${providerLine}${intro}${qaTpl.relation?.simple || (currentLang==='zh' ? 'L1HS 与 LINE-1 的关系属于同一谱系中的层级关系。' : 'L1HS is connected to LINE-1 through a hierarchical relation within the same lineage.')}${makeRef(refs.slice(0,4))}`;
      }

      if(asksLine1 && asksFunction){
        const items=demoData.qa.line1_functions || [];
        return currentAnswerStyle==='detailed'
          ? buildDetailed(qaTpl.line1FunctionTitle || (currentLang==='zh' ? '根据当前本地图谱，LINE-1 相关功能主要涉及逆转录转座、序列转导、插入突变及与其他转座元件协同的过程。' : 'According to the current local graph, LINE-1-related functions mainly involve retrotransposition, sequence transduction, insertional mutagenesis, and interactions with other transposable elements.'), items, items)
          : buildSimple(ui[currentLang].f, items, items);
      }

      if(asksLine1 && asksDisease){
        const items=demoData.qa.line1_diseases || [];
        return currentAnswerStyle==='detailed'
          ? buildDetailed(qaTpl.line1DiseaseTitle || (currentLang==='zh' ? '根据当前本地图谱，LINE-1 与多种神经系统疾病、遗传综合征及肿瘤相关疾病存在结构化关联。' : 'According to the current local graph, LINE-1 shows structured associations with multiple neurological diseases, genetic syndromes, and cancers.'), items, items)
          : buildSimple(ui[currentLang].d, items, items);
      }

      if(lower.includes('l1hs') && asksPaper){
        const refs=demoData.qa.l1hs_papers || [];
        return currentAnswerStyle==='detailed'
          ? buildDetailed(qaTpl.l1hsPaperTitle || (currentLang==='zh' ? '当前本地图谱已经整理出与 L1HS 相关的文献证据，可用于支撑谱系、功能或疾病关系的解释。':'The current local graph already contains literature evidence associated with L1HS.'), refs, refs, 6, 8)
          : `${intro}${ui[currentLang].lp}${makeRef(refs.slice(0,5))}`;
      }

      if(asksLine1 && asksPaper){
        const refs=demoData.qa.line1_papers || [];
        return currentAnswerStyle==='detailed'
          ? buildDetailed(qaTpl.line1PaperTitle || (currentLang==='zh' ? '当前本地图谱已经整理出与 LINE-1 相关的核心文献，可用于支撑疾病、功能和谱系层面的解释。':'The current local graph already contains core literature evidence associated with LINE-1.'), refs, refs, 6, 8)
          : `${intro}${ui[currentLang].pp}${makeRef(refs.slice(0,5))}`;
      }

      if(currentAnswerStyle==='detailed'){
        const fallbackDetailed = qaTpl.fallbackDetailed || {};
        const bullets = (fallbackDetailed.bullets || []).map(item=>`- ${item}`).join('\n');
        const limitations = (fallbackDetailed.limitations || []).map(item=>`- ${item}`).join('\n');
        return `${providerLine}${intro}## ${sections.conclusion}\n${fallbackDetailed.title || ui[currentLang].fallback}\n\n## ${sections.mechanism}\n${bullets}\n\n## ${sections.evidence}\n${fallbackDetailed.evidence || ''}\n\n## ${sections.limitations}\n${limitations}`;
      }
      return `${providerLine}${intro}${ui[currentLang].fallback}`;
    }
    function formatBackendAnswer(result){
      const effectiveProvider = (result.model_provider || currentModelProvider || 'qwen').toLowerCase() === 'deepseek' ? 'deepseek' : 'qwen';
      const styleLabel=currentLang==='zh'
        ? (currentAnswerStyle==='custom'?'自定义模式':result.answer_style==='detailed'?'详细模式':'简单模式')
        : (currentAnswerStyle==='custom'?'Custom mode':result.answer_style==='detailed'?'Detailed mode':'Brief mode');
      const depthLabel=result.answer_depth==='custom'
        ? (currentLang==='zh'
          ? `自定义（row=${result.custom_rows || currentCustomDepth.rows}，references=${result.custom_references || currentCustomDepth.references}）`
          : `Custom (row=${result.custom_rows || currentCustomDepth.rows}, references=${result.custom_references || currentCustomDepth.references})`)
        : (currentLang==='zh'
          ? ({shallow:'浅',medium:'中',deep:'深'}[result.answer_depth] || '浅')
          : ({shallow:'Shallow',medium:'Medium',deep:'Deep'}[result.answer_depth] || 'Shallow'));
      const prefix = currentLang==='zh'
        ? `> ${ui[currentLang].providerPrefix}${effectiveProvider === 'deepseek' ? ui[currentLang].modelDeepSeek : ui[currentLang].modelQwen}\n> ${styleLabel} · 回答深度：${depthLabel}\n\n`
        : `> ${ui[currentLang].providerPrefix}${effectiveProvider === 'deepseek' ? ui[currentLang].modelDeepSeek : ui[currentLang].modelQwen}\n> ${styleLabel} · Depth: ${depthLabel}\n\n`;
      return `${prefix}${result.answer||ui[currentLang].fallback}`;
    }
    function applyAnswerGraph(result, question=''){
      const graph = result && result.graph_context;
      if(!graph || !Array.isArray(graph.elements) || graph.elements.length===0) return false;
      const anchorName = graph.anchor && graph.anchor.name ? graph.anchor.name : (question || '');
      applyGraphElements(graph.elements, anchorName, {graphKind:'dynamic'});
      searchInput.value = anchorName || '';
      nodeDetails.textContent = currentLang==='zh'
        ? '左侧图谱已同步为本次回答使用的局部知识子图。'
        : 'The graph on the left has been synchronized to the local subgraph used for this answer.';
      return true;
    }
    function buildCurrentGraphElements(){
      return cy.elements().map(ele => ({data: JSON.parse(JSON.stringify(ele.data() || {}))}));
    }
    async function answerWithBackend(question){
      const effectiveQuestion = buildEffectiveQuestion(question);
      const customPrompt = currentCustomPrompt;
      const effectiveStyle = currentAnswerStyle==='custom' ? 'custom' : currentAnswerStyle;
      const payload = {
        question:effectiveQuestion,
        question_raw:String(question||'').trim(),
        language:currentLang,
        answer_style:effectiveStyle,
        answer_depth:currentAnswerDepth,
        custom_prompt:customPrompt,
        graph_state:{
          mode: currentGraphKind==='default-tree' ? 'tree' : 'dynamic',
          query: String(searchInput && searchInput.value || '').trim(),
          fixedView: !!fixedView,
          keyNodeLevel: Number(currentKeyNodeLevel || 1) || 1,
          currentElements: buildCurrentGraphElements(),
        }
      };
      payload.model_provider = currentModelProvider;
      if(currentAnswerDepth==='custom'){
        payload.custom_rows = currentCustomDepth.rows;
        payload.custom_references = currentCustomDepth.references;
      }
      const res=await fetch('api/qa.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      const data=await res.json();
      if(!res.ok||!data.ok) throw new Error(data.error||'Backend request failed');
      return {text: formatBackendAnswer(data), result: data};
    }
    cy.on('tap','node',async e => {
      const node = e.target;
      if(fixedView){
        clearHighlights();
        showNode(node);
        return;
      }
      try{
        await loadDynamicGraph(node.data('pmid') || node.data('query_label') || node.data('label'));
      }catch(_err){
        focus(node);
      }
    }); cy.on('tap','edge',e => {
      clearHighlights();
      const edge=e.target;
      if(!fixedView){
        const connected=edge.connectedNodes();
        const others=cy.nodes().difference(connected);
        const otherEdges=cy.edges().difference(edge);
        others.addClass('dimmed');
        otherEdges.addClass('edge-dimmed');
        connected.addClass('neighbor');
        edge.addClass('focus-edge');
      }
      showEdge(edge)
    }); cy.on('tap',e => { if(e.target===cy){clearHighlights(); nodeDetails.textContent=ui[currentLang].empty;} });
    searchInput.addEventListener('input', handleSearch); searchInput.addEventListener('keydown',async e=>{if(e.key==='Enter'){e.preventDefault(); try{await loadDynamicGraph(searchInput.value.trim());}catch(err){nodeDetails.textContent = err && err.message ? err.message : ui[currentLang].empty;}}}); prevBtn.addEventListener('click',()=>{if(currentResultIndex>0){currentResultIndex-=1; updateNav();}}); nextBtn.addEventListener('click',()=>{if(currentResultIndex<searchResults.length-1){currentResultIndex+=1; updateNav();}}); el('focus-level').addEventListener('input',e=>{focusLevel=Number(e.target.value)||0; updateFocusUi(); if(searchResults.length>0&&searchResults[currentResultIndex]) focus(searchResults[currentResultIndex]);});
    el('reset-graph').addEventListener('click',()=>{restoreInitialGraph();});
    el('toggle-fixed-view').addEventListener('click',()=>{
      fixedView = !fixedView;
      updateFixedViewUi();
      nodeDetails.textContent = fixedView ? ui[currentLang].fixedTip : ui[currentLang].empty;
    });
    sendBtn.addEventListener('click',async()=>{const q=userInput.value.trim(); if(!q) return; addMessage(q,'user'); userInput.value=''; const loading=addMessage(currentLang==='zh'?'正在检索图数据库并生成回答…':'Retrieving graph evidence and generating the answer…','assistant'); try{const backend=await answerWithBackend(q); loading.remove(); addMessage(backend.text,'assistant'); applyAnswerGraph(backend.result, q);}catch(err){loading.remove(); const prefix=currentLang==='zh'?'后端暂未连通，当前已回退到本地规则回答。<div class="ref">':'Backend unavailable. The UI has fallen back to local rule-based answering.<div class="ref">'; const suffix=`${err && err.message ? err.message : 'unknown error'}</div>`; setTimeout(()=>addMessage(prefix + suffix + answerLocal(q),'assistant'),120);}});
    userInput.addEventListener('keypress',e => { if(e.key==='Enter') sendBtn.click(); });
    document.addEventListener('click',e => {
      if(!e.target) return;
      if(e.target.id==='mode-simple') setAnswerStyle('simple');
      if(e.target.id==='mode-detailed') setAnswerStyle('detailed');
      if(e.target.id==='mode-custom') openCustomPromptEditor();
      if(e.target.id==='confirm-custom-editor') closeCustomEditor({save:true});
      if(e.target.id==='cancel-custom-editor'){
        customPromptDraft = currentCustomPrompt || '';
        customDepthDraft = {...currentCustomDepth};
        closeCustomEditor({save:false});
      }
      if(e.target.id==='depth-shallow') setAnswerDepth('shallow');
      if(e.target.id==='depth-medium') setAnswerDepth('medium');
      if(e.target.id==='depth-deep') setAnswerDepth('deep');
      if(e.target.id==='depth-custom') openCustomDepthEditor();
      if(e.target.id==='model-qwen') setModelProvider('qwen');
      if(e.target.id==='model-deepseek') setModelProvider('deepseek');
    });
    el('lang-zh').addEventListener('click',()=>{currentLang='zh'; setUi();}); el('lang-en').addEventListener('click',()=>{currentLang='en'; setUi();});
    cy.on('layoutstop',()=>{if(currentGraphKind!=='default-tree') refineGraphLayout(); cy.fit(undefined,55);});
    async function initializePage(){
      await loadUiText();
      await loadLocalQaTemplates();
      await loadTerminology();
      await loadTeDescriptions();
      await loadEntityDescriptions();
      repairModeControls();
      setAnswerStyle('simple');
      setUi();
      el('focus-level').value=String(focusLevel);
      restoreInitialGraph();
    }
    initializePage();
  
