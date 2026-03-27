    const DEFAULT_TREE_COLLAPSE_THRESHOLD = 6;
    const DEFAULT_TREE_TOGGLE_OFFSET = 92;
    const DEFAULT_TREE_X_STEP = 300;
    const DEFAULT_TREE_Y_STEP = 50;
    const DEFAULT_TREE_ROOT_LEFT_SHIFT = 28;
    let expandedTreeBranches = new Set();
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
    const defaultTreeSource = (() => {
      const nodes = new Map();
      const children = new Map();
      const edges = new Map();
      let rootId = null;
      let leftMargin = -560;
      for(const item of initialElements){
        const d = item && item.data ? item.data : null;
        if(!d || d.source) continue;
        const clone = JSON.parse(JSON.stringify(item));
        nodes.set(d.id, clone);
        if(d.tree_depth === 0 && !rootId){
          rootId = d.id;
          leftMargin = clone.position?.x ?? leftMargin;
        }
      }
      for(const item of initialElements){
        const d = item && item.data ? item.data : null;
        if(!d || !d.source || !d.target) continue;
        if(!nodes.has(d.source) || !nodes.has(d.target)) continue;
        const key = `${d.source}=>${d.target}`;
        edges.set(key, JSON.parse(JSON.stringify(item)));
        if(!children.has(d.source)) children.set(d.source, []);
        children.get(d.source).push(d.target);
      }
      for(const [parentId, childIds] of children.entries()){
        childIds.sort((a, b) => (nodes.get(a)?.position?.y ?? 0) - (nodes.get(b)?.position?.y ?? 0));
      }
      return {nodes, children, edges, rootId, leftMargin: leftMargin - DEFAULT_TREE_ROOT_LEFT_SHIFT};
    })();
    function collectTreeDescendants(nodeId, bucket = new Set()){
      const childIds = defaultTreeSource.children.get(nodeId) || [];
      for(const childId of childIds){
        if(bucket.has(childId)) continue;
        bucket.add(childId);
        collectTreeDescendants(childId, bucket);
      }
      return bucket;
    }
    function buildCollapsedDefaultTreeElements(){
      if(!defaultTreeSource.rootId) return JSON.parse(JSON.stringify(initialElements));
      const visibleEdges = [];
      const visibleNodes = [];
      const toggleNodes = [];
      const toggleParents = [];
      const positioned = new Map();
      let nextLeafIndex = 0;
      const visit = (nodeId, depth) => {
        const sourceNode = defaultTreeSource.nodes.get(nodeId);
        if(!sourceNode) return 0;
        const childIds = defaultTreeSource.children.get(nodeId) || [];
        const isCollapsible = childIds.length >= DEFAULT_TREE_COLLAPSE_THRESHOLD;
        const shouldCollapse = isCollapsible && !expandedTreeBranches.has(nodeId);
        const node = JSON.parse(JSON.stringify(sourceNode));
        node.position = {
          x: defaultTreeSource.leftMargin + depth * DEFAULT_TREE_X_STEP,
          y: 0
        };
        let y = 0;
        if(!childIds.length || shouldCollapse){
          y = nextLeafIndex * DEFAULT_TREE_Y_STEP;
          nextLeafIndex += 1;
        }else{
          const childYs = [];
          for(const childId of childIds){
            const childY = visit(childId, depth + 1);
            childYs.push(childY);
            const edge = defaultTreeSource.edges.get(`${nodeId}=>${childId}`);
            if(edge) visibleEdges.push(JSON.parse(JSON.stringify(edge)));
          }
          y = childYs.reduce((sum, value) => sum + value, 0) / childYs.length;
        }
        node.position.y = y;
        positioned.set(nodeId, node);
        visibleNodes.push(node);
        if(isCollapsible) toggleParents.push(nodeId);
        return y;
      };
      visit(defaultTreeSource.rootId, 0);
      if(positioned.size){
        const ys = Array.from(positioned.values()).map(node => node.position?.y ?? 0);
        const center = (Math.min(...ys) + Math.max(...ys)) / 2;
        for(const node of positioned.values()){
          node.position.y -= center;
        }
      }
      for(const parentId of toggleParents){
        const parent = positioned.get(parentId);
        if(!parent) continue;
        const expanded = expandedTreeBranches.has(parentId);
        toggleNodes.push({
          position: {
            x: (parent.position?.x ?? 0) + DEFAULT_TREE_TOGGLE_OFFSET,
            y: parent.position?.y ?? 0
          },
          data: {
            id: `TREE_TOGGLE_${parentId}`,
            label: expanded ? '-' : '+',
            type: 'TreeToggle',
            toggle_node: true,
            toggle_parent: parentId,
            toggle_state: expanded ? 'expanded' : 'collapsed',
            tree_depth: parent.data?.tree_depth ?? 0
          }
        });
      }
      visibleNodes.sort((a, b) => {
        const ax = a.position?.x ?? 0;
        const bx = b.position?.x ?? 0;
        if(ax !== bx) return ax - bx;
        return (a.position?.y ?? 0) - (b.position?.y ?? 0);
      });
      return [...visibleNodes, ...visibleEdges, ...toggleNodes];
    }
    function expandDefaultTreeBranch(parentId){
      if(!parentId) return;
      expandedTreeBranches.add(parentId);
      applyGraphElements(initialElements, parentId, {graphKind:'default-tree', treeFocus:'branch'});
    }
    function collapseDefaultTreeBranch(parentId){
      if(!parentId) return;
      expandedTreeBranches.delete(parentId);
      for(const descendantId of collectTreeDescendants(parentId)){
        expandedTreeBranches.delete(descendantId);
      }
      applyGraphElements(initialElements, parentId, {graphKind:'default-tree', treeFocus:'branch'});
    }
    const cy = cytoscape({container:el('cy'),elements:[],style:[{selector:'node',style:{'label':e=>e.data('toggle_node') ? (e.data('label') || '+') : getName(e.data('label'),e.data('type'),e.data('description')||'',e.data('pmid')||''),'font-size':e=>{const d=e.data('tree_depth'); if(d===0) return '30px'; if(d===1) return '22px'; if(d===2) return '16px'; if(d===3) return '13px'; return '11px';},'min-zoomed-font-size':9,'text-valign':'center','text-halign':'center','background-color':e=>currentGraphKind==='default-tree' && e.data('type')==='TE' ? getTreeDepthColor(e.data('tree_depth') ?? 3) : getTypeColor(e.data('type')),'color':'#0f172a','text-outline-width':3,'text-outline-color':'#fff','width':'label','height':'label','padding':e=>{const d=e.data('tree_depth'); if(d===0) return '28px'; if(d===1) return '20px'; if(d===2) return '14px'; if(d===3) return '10px'; return '8px';},'text-wrap':'wrap','text-max-width':150,'border-width':2,'border-color':'#fff','shape':'round-rectangle'}},{selector:'node[toggle_node]',style:{'label':'data(label)','font-size':'16px','font-weight':'700','text-outline-width':0,'background-color':'#ffffff','color':'#2563eb','width':22,'height':22,'padding':0,'border-width':1.5,'border-color':'#9db8ff','shape':'round-rectangle','corner-radius':'999px','shadow-blur':8,'shadow-color':'rgba(37,99,235,0.16)','shadow-opacity':1}},{selector:'edge',style:{'width':e=>currentGraphKind==='default-tree' ? 2.4 : 2,'line-color':e=>currentGraphKind==='default-tree' ? '#9db8ff' : '#cbd5e1','target-arrow-color':'#64748b','target-arrow-shape':e=>currentGraphKind==='default-tree' ? 'none' : 'triangle','curve-style':e=>currentGraphKind==='default-tree' ? 'taxi' : 'bezier','taxi-direction':'rightward','taxi-turn':e=>currentGraphKind==='default-tree' ? 28 : 0,'label':e=>{const rel=e.data('relation')||''; if(currentGraphKind==='default-tree') return ''; return rel==='EVIDENCE_RELATION'?'':getRel(rel)},'font-size':'9px','min-zoomed-font-size':8,'text-rotation':e=>currentGraphKind==='default-tree' ? 'none' : 'autorotate','color':'#334155','text-background-color':'rgba(255,255,255,0.92)','text-background-opacity':1,'text-background-padding':'2px','text-outline-width':0}},{selector:'.highlight',style:{'border-width':6,'border-color':'#0f172a','font-weight':'800','overlay-color':'#2563eb','overlay-opacity':0.12,'overlay-padding':'16px','shadow-blur':18,'shadow-color':'#2563eb','shadow-opacity':0.35,'shadow-offset-x':0,'shadow-offset-y':0,'z-index':999}},{selector:'.neighbor',style:{'border-width':4,'border-color':'#60a5fa','overlay-color':'#93c5fd','overlay-opacity':0.03,'overlay-padding':'6px','opacity':GRAPH_TUNING.neighborOpacity,'shadow-blur':4,'shadow-color':'#93c5fd','shadow-opacity':0.08}},{selector:'.dimmed',style:{'opacity':GRAPH_TUNING.dimmedOpacity}},{selector:'.focus-edge',style:{'label':e=>currentGraphKind==='default-tree' ? '' : getRel(e.data('relation')),'width':4,'line-color':'#2563eb','target-arrow-color':'#2563eb','target-arrow-shape':e=>currentGraphKind==='default-tree' ? 'none' : 'triangle','curve-style':e=>currentGraphKind==='default-tree' ? 'taxi' : 'bezier','taxi-direction':'rightward','taxi-turn':e=>currentGraphKind==='default-tree' ? 30 : 0,'font-size':'11px','color':'#0f172a','text-background-color':'rgba(255,255,255,0.98)','text-background-opacity':1,'text-background-padding':'4px','opacity':0.95}},{selector:'.edge-dimmed',style:{'opacity':0.28}}],layout:buildLayout(),wheelSensitivity:.2});
    window.__TEKG_CY = cy;
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
      const renderElements = currentGraphKind==='default-tree' ? buildCollapsedDefaultTreeElements() : elements;
      cy.elements().remove();
      cy.add(normalizeElementsForGraphKind(renderElements, currentGraphKind));
      cy.style().update();
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
            if(options.treeFocus === 'branch'){
              const branchNodes = [];
              const stack = [target];
              const seen = new Set();
              while(stack.length){
                const current = stack.pop();
                if(!current || seen.has(current.id())) continue;
                seen.add(current.id());
                branchNodes.push(current);
                current.outgoers('node').forEach(child => {
                  if(!child.data('toggle_node')) stack.push(child);
                });
              }
              const branchEles = cy.collection(branchNodes).union(cy.collection(branchNodes).connectedEdges());
              cy.fit(branchEles, 150);
              const desiredZoom = Math.max(cy.zoom(), 0.92);
              cy.animate({center:{eles:target}, zoom:desiredZoom, duration:260});
            }else{
              cy.fit(undefined,55);
            }
          } else {
            nodeDetails.textContent=ui[currentLang].empty;
            cy.fit(undefined,55);
          }
          return;
        }
        if(target) focus(target); else cy.fit(undefined,55);
      }, 320);
    }
    function restoreInitialGraph(){
      searchInput.value='';
      clearHighlights();
      searchResults=[]; currentResultIndex=-1;
      expandedTreeBranches = new Set();
      focusLevel=0;
      updateFocusUi();
      updateKeyNodeLevelUi();
      applyGraphElements(initialElements, 'TE', {graphKind:'default-tree'});
    }
    async function loadDynamicGraph(query, fallbackToCurrent=false){
      const keyword=(query||'').trim();
      if(!keyword) return;
      const response = await fetch(`api/graph.php?q=${encodeURIComponent(keyword)}&key_level=${encodeURIComponent(String(currentKeyNodeLevel || 1))}`);
      const payload = await response.json();
      if(!response.ok || !payload.ok) throw new Error(payload.error || 'Graph request failed');
      if(!payload.elements || payload.elements.length===0){
        throw new Error(currentLang==='zh' ? '?????????????' : 'No matching graph fragment was found.');
      }
      applyGraphElements(payload.elements, payload.anchor && payload.anchor.name ? payload.anchor.name : keyword, {graphKind:'dynamic'});
      searchInput.value = payload.anchor && payload.anchor.name ? payload.anchor.name : keyword;
      return payload;
    }
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
    function updateFocusUi(){
      const btn = el('toggle-focus-view');
      const label = el('focus-view-text');
      const isLocal = focusLevel===100;
      if(label){
        const globalText = ui[currentLang].focusGlobal || (currentLang==='zh' ? '全局' : 'Global');
        const localText = ui[currentLang].focusLocal || (currentLang==='zh' ? '局部' : 'Local');
        label.textContent = `${ui[currentLang].focus}：${isLocal ? localText : globalText}`;
      }
      if(btn) btn.classList.toggle('active', isLocal);
    }
    function updateKeyNodeLevelUi(){
      const label = el('key-node-level-text');
      const decreaseBtn = el('decrease-key-node-level');
      const increaseBtn = el('increase-key-node-level');
      if(label) label.textContent = `${ui[currentLang].keyNodeLevel || (currentLang==='zh' ? '关键节点级数' : 'Key-node level')}：${currentKeyNodeLevel}`;
      if(decreaseBtn) decreaseBtn.disabled = currentKeyNodeLevel <= 1;
      if(increaseBtn) increaseBtn.disabled = currentKeyNodeLevel >= 3;
    }
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
    function setUi(){document.documentElement.lang=currentLang==='zh'?'zh-CN':'en'; el('page-title').textContent=ui[currentLang].pageTitle; el('page-badge').textContent=ui[currentLang].badge; el('graph-title').textContent=ui[currentLang].graphTitle; el('qa-title').textContent=ui[currentLang].qaTitle; el('page-footer').textContent=ui[currentLang].footer; el('reset-text').textContent=ui[currentLang].reset; searchInput.placeholder=ui[currentLang].search; userInput.placeholder=ui[currentLang].ph; if(!userInput.value || userInput.value===ui.zh.q || userInput.value===ui.en.q) userInput.value=ui[currentLang].q; el('lang-zh').classList.toggle('active', currentLang==='zh'); el('lang-en').classList.toggle('active', currentLang==='en'); updateAnswerModeUi(); updateFocusUi(); updateKeyNodeLevelUi(); updateFixedViewUi(); if(searchResults.length===0) nodeDetails.textContent=ui[currentLang].empty; else showNode(searchResults[currentResultIndex]); renderIntro(); cy.style().update(); const highlighted = cy.nodes('.highlight')[0]; if(highlighted) showNode(highlighted); updateNav();}
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

