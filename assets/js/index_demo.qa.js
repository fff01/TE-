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
      if(window.__TEKG_RENDERER_MODE === 'g6' && window.__TEKG_G6_DYNAMIC_GRAPH && typeof window.__TEKG_G6_DYNAMIC_GRAPH.render === 'function'){
        currentGraphKind='dynamic';
        window.__TEKG_G6_DYNAMIC_GRAPH.render(graph.elements, anchorName, graph);
      }else{
        applyGraphElements(graph.elements, anchorName, {graphKind:'dynamic'});
      }
      searchInput.value = anchorName || '';
      nodeDetails.textContent = currentLang==='zh'
        ? '左侧图谱已同步为本次回答使用的局部知识子图。'
        : 'The graph on the left has been synchronized to the local subgraph used for this answer.';
      return true;
    }
    async function answerWithBackend(question){
      const effectiveQuestion = buildEffectiveQuestion(question);
      const customPrompt = currentCustomPrompt;
      const effectiveStyle = currentAnswerStyle==='custom' ? 'custom' : currentAnswerStyle;
      const payload = {question:effectiveQuestion,language:currentLang,answer_style:effectiveStyle,answer_depth:currentAnswerDepth,custom_prompt:customPrompt};
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
      if(currentGraphKind==='default-tree' && node.data('toggle_node')){
        if(node.data('toggle_state') === 'expanded'){
          collapseDefaultTreeBranch(node.data('toggle_parent'));
        }else{
          expandDefaultTreeBranch(node.data('toggle_parent'));
        }
        return;
      }
      if(window.__TEKG_EMBED_MODE === 'home-preview'){
        clearHighlights();
        showNode(node);
        return;
      }
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
    searchInput.addEventListener('input', handleSearch); searchInput.addEventListener('keydown',async e=>{if(e.key==='Enter'){e.preventDefault(); try{await loadDynamicGraph(searchInput.value.trim());}catch(err){nodeDetails.textContent = err && err.message ? err.message : ui[currentLang].empty;}}}); prevBtn.addEventListener('click',()=>{if(currentResultIndex>0){currentResultIndex-=1; updateNav();}}); nextBtn.addEventListener('click',()=>{if(currentResultIndex<searchResults.length-1){currentResultIndex+=1; updateNav();}}); 
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
      if(e.target.id==='toggle-focus-view' || e.target.id==='focus-view-text'){
        focusLevel = focusLevel===100 ? 0 : 100;
        updateFocusUi();
        if(searchResults.length>0&&searchResults[currentResultIndex]) focus(searchResults[currentResultIndex]);
      }
      if(e.target.id==='decrease-key-node-level'){
        currentKeyNodeLevel = Math.max(1, currentKeyNodeLevel - 1);
        updateKeyNodeLevelUi();
      }
      if(e.target.id==='increase-key-node-level'){
        currentKeyNodeLevel = Math.min(3, currentKeyNodeLevel + 1);
        updateKeyNodeLevelUi();
      }
    });
    el('lang-zh').addEventListener('click',()=>{currentLang='zh'; setUi();}); el('lang-en').addEventListener('click',()=>{currentLang='en'; setUi();});
    cy.on('layoutstop',()=>{
      if(currentGraphKind!=='default-tree'){
        refineGraphLayout();
        cy.fit(undefined,55);
      }
    });
    async function initializePage(){
      await Promise.allSettled([
        loadUiText(),
        loadLocalQaTemplates(),
        loadTerminology(),
        loadTeDescriptions(),
        loadEntityDescriptions()
      ]);
      try{
        repairModeControls();
        setAnswerStyle('simple');
        setUi();
        updateFocusUi();
      }finally{
        if(window.__TEKG_RENDERER_MODE === 'g6'){
          window.dispatchEvent(new CustomEvent('tekg:shared-ready'));
        }else{
          restoreInitialGraph();
        }
      }
    }
    initializePage();
  
