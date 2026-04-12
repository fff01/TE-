<?php
$pageTitle='TE-KG Expression Detail';
$activePage='expression';
$protoCurrentPath='/TE-/expression_detail.php';
$protoSubtitle='Expression Detail View';
require __DIR__ . '/site_i18n.php';
require __DIR__ . '/api/expression_data.php';

function exd_dataset_labels_from_summary(array $summary): array {
  $labels = [];
  foreach (['normal_tissue' => 'Normal Tissue', 'normal_cell_line' => 'Normal Cell Line', 'cancer_cell_line' => 'Cancer Cell Line'] as $key => $label) {
    if (!empty($summary[$key . '_available'])) {
      $labels[] = $label;
    }
  }
  return $labels;
}

function exd_fmt($v,$d=2){return ($v===null||$v==='')?'-':number_format((float)$v,$d,'.',',');}
function exd_metric_label(string $metric): string {
  return match (tekg_expression_normalize_metric($metric)) {
    'mean' => 'Mean',
    'max' => 'Max',
    default => 'Median',
  };
}
function exd_metric_value(array $row, string $metric): ?float {
  $key = match (tekg_expression_normalize_metric($metric)) {
    'mean' => 'mean_value',
    'max' => 'max_value',
    default => 'median_value',
  };
  return isset($row[$key]) && $row[$key] !== '' ? (float)$row[$key] : null;
}
function exd_chart_payload(array $dataset, string $metric, string $title, string $teName): array {
  $labels = [];
  $values = [];
  $sampleCounts = [];
  foreach (($dataset['contexts'] ?? []) as $row) {
    $labels[] = (string)(($row['context_full_name'] ?? '') ?: ($row['context_label'] ?? '-'));
    $values[] = exd_metric_value($row, $metric) ?? 0.0;
    $sampleCounts[] = (int)($row['sample_count'] ?? 0);
  }
  return [
    'title' => $title . ' (' . $teName . ')',
    'metric_label' => exd_metric_label($metric),
    'labels' => $labels,
    'values' => $values,
    'sample_counts' => $sampleCounts,
  ];
}
function exd_render_section(string $id,string $title,array $dataset,string $metric,string $teName,string $barColor): void {
  $s=$dataset['summary']??[]; $chartPayload = exd_chart_payload($dataset,$metric,$title,$teName); ?>
  <section id="<?= htmlspecialchars($id,ENT_QUOTES,'UTF-8') ?>" class="detail-panel">
    <h3><?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?></h3>
    <div class="detail-cards">
      <div class="detail-card"><span>Top Median Context</span><strong><?= htmlspecialchars((string)(($s['top_context_median_full_name']??'')?:($s['top_context_median']??'-')),ENT_QUOTES,'UTF-8') ?></strong></div>
      <div class="detail-card"><span>Median Of Median</span><strong><?= htmlspecialchars(exd_fmt($s['median_of_median']??null),ENT_QUOTES,'UTF-8') ?></strong></div>
      <div class="detail-card"><span>Mean Of Mean</span><strong><?= htmlspecialchars(exd_fmt($s['mean_of_mean']??null),ENT_QUOTES,'UTF-8') ?></strong></div>
      <div class="detail-card"><span>Max Of Max</span><strong><?= htmlspecialchars(exd_fmt($s['max_of_max']??null),ENT_QUOTES,'UTF-8') ?></strong></div>
      <div class="detail-card"><span>Cv Of Median</span><strong><?= htmlspecialchars(exd_fmt($s['cv_of_median']??null),ENT_QUOTES,'UTF-8') ?></strong></div>
      <div class="detail-card"><span>Expression Breadth</span><strong><?= htmlspecialchars((string)($s['breadth_of_median']??'-'),ENT_QUOTES,'UTF-8') ?></strong></div>
    </div>
    <div class="detail-chart-shell">
      <div class="detail-chart-header">
        <h4><?= htmlspecialchars($title . ' Plot',ENT_QUOTES,'UTF-8') ?></h4>
        <span><?= htmlspecialchars(exd_metric_label($metric) . ' Normalized Expression',ENT_QUOTES,'UTF-8') ?></span>
      </div>
      <div class="detail-chart-frame">
        <div id="<?= htmlspecialchars($id,ENT_QUOTES,'UTF-8') ?>-plot" class="detail-plot" data-plotly-payload="<?= htmlspecialchars(json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),ENT_QUOTES,'UTF-8') ?>" data-plot-color="<?= htmlspecialchars($barColor,ENT_QUOTES,'UTF-8') ?>"></div>
      </div>
    </div>
  </section>
<?php }

$siteLang=site_lang();
$siteRenderer=site_renderer();
$teQuery=trim((string)($_GET['te']??''));
$metric=tekg_expression_normalize_metric((string)($_GET['metric']??'median'));
$sort=tekg_expression_normalize_sort((string)($_GET['sort']??'default'));
$detail=null; $error=null;
try { if($teQuery!=='') $detail=tekg_expression_fetch_detail_bundle($teQuery,$metric,$sort); }
catch(Throwable $e){ $error=$e->getMessage(); }
$sections=[['id'=>'expression-detail-summary','label'=>'Summary']];
if(is_array($detail)){
  if(isset($detail['datasets']['normal_tissue'])) $sections[]=['id'=>'expression-detail-normal-tissue','label'=>'Normal Tissue'];
  if(isset($detail['datasets']['normal_cell_line'])) $sections[]=['id'=>'expression-detail-normal-cell-line','label'=>'Normal Cell Line'];
  if(isset($detail['datasets']['cancer_cell_line'])) $sections[]=['id'=>'expression-detail-cancer-cell-line','label'=>'Cancer Cell Line'];
}
require __DIR__ . '/head.php';
?>
<style>
html{scroll-behavior:smooth}.expression-shell{background:#f5f9ff;min-height:calc(100vh - 82px);padding:34px 0 54px}.proto-container{max-width:1480px;margin:0 auto;padding:0 28px}
.detail-toolbar{--w:196px;display:grid;grid-template-columns:var(--w) minmax(0,1fr);gap:18px;align-items:center;margin-bottom:18px}.detail-back{display:inline-flex;align-items:center;justify-content:flex-start;gap:8px;width:100%;min-height:48px;padding:0 14px;border:1px solid #dfe6ef;border-radius:10px;background:#fff;color:#214b8d;font-size:15px;font-weight:700;text-decoration:none;box-sizing:border-box;box-shadow:0 8px 24px rgba(25,56,105,.05);margin-left:-12px;white-space:nowrap}
.detail-search-form{min-width:0;width:min(100%,440px);justify-self:end}.detail-search-box{position:relative;display:block;width:100%}.detail-search-icon{position:absolute;top:50%;left:18px;transform:translateY(-50%);width:18px;height:18px;color:#88a0bf;pointer-events:none}.detail-query{width:100%;min-height:50px;border:1px solid #d8e0ea;border-radius:999px;background:#fff;padding:0 18px 0 48px;color:#49627f;font-size:14px;box-sizing:border-box;box-shadow:0 8px 24px rgba(25,56,105,.05)}.detail-query:focus,.detail-select:focus{outline:none}
.detail-layout{display:grid;grid-template-columns:196px minmax(0,1fr);gap:18px}.detail-sidebar{display:grid;gap:14px;align-content:start;position:sticky;top:108px;height:fit-content;margin-left:-12px}.detail-nav,.detail-controls{background:#fff;border:1px solid #dfe6ef;border-radius:10px;box-shadow:0 8px 24px rgba(25,56,105,.05);padding:14px 12px;display:grid;gap:8px}.detail-nav-title,.detail-controls-title{margin:0 0 6px;padding:0 8px;color:#6f8198;font-size:13px;font-weight:700;letter-spacing:.03em}.detail-nav-link{display:block;padding:11px 12px;border-radius:8px;color:#61789f;font-size:15px;font-weight:600}.detail-nav-link.is-active,.detail-nav-link:hover{background:#eef4ff;color:#214b8d}.detail-control-group{display:grid;gap:8px}.detail-control-label{padding:0 8px;color:#6f8198;font-size:12px;font-weight:700;letter-spacing:.02em}.detail-select{width:100%;min-height:42px;border:1px solid #d8e0ea;border-radius:8px;background:#fff;padding:0 12px;color:#49627f;font-size:14px;box-sizing:border-box}.detail-content{min-width:0;display:grid;gap:14px}
.detail-panel{background:#fff;border:1px solid #dfe6ef;border-radius:10px;box-shadow:0 4px 14px rgba(34,56,92,.05);padding:22px 24px}.detail-panel h3{margin:0 0 18px;font-size:22px;font-weight:700;color:#1b3558}.detail-summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.detail-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.detail-card{display:grid;gap:8px;padding:16px 18px;border-radius:10px;background:#f7faff;border:1px solid #dbe7f8}.detail-card span{color:#7a8ba5;font-size:12px;font-weight:700;letter-spacing:.02em}.detail-card strong{color:#254670;font-size:16px;font-weight:700;line-height:1.5}
.detail-chart-shell{margin-top:18px;border:1px solid #dfe6ef;border-radius:10px;background:#fbfdff;overflow:hidden}.detail-chart-header{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 18px;border-bottom:1px solid #e6edf7;background:#f7faff}.detail-chart-header h4{margin:0;color:#254670;font-size:18px;font-weight:700}.detail-chart-header span{color:#6f8198;font-size:13px;font-weight:600}.detail-chart-frame{overflow:hidden;padding:10px 12px 14px}.detail-plot{height:420px;width:100%}
.expression-error{background:#fff7f7;border:1px solid #f0c5c5;color:#a54343;padding:16px 18px;border-radius:10px;box-shadow:0 4px 14px rgba(34,56,92,.05)}.expression-empty{margin:0;color:#5f6f86;font-size:15px;line-height:1.8}
@media (max-width:1180px){.detail-toolbar,.detail-layout,.detail-cards,.detail-summary-grid{grid-template-columns:1fr}.detail-sidebar{position:static;margin-left:0}.detail-search-form{width:100%;justify-self:stretch}.detail-chart-header{flex-direction:column;align-items:flex-start}}
</style>
<section class="expression-shell">
  <div class="proto-container">
    <div id="expressionDetailPage">
      <section class="detail-toolbar">
        <a class="detail-back" href="<?= htmlspecialchars(site_url_with_state('/TE-/expression.php',$siteLang,$siteRenderer),ENT_QUOTES,'UTF-8') ?>">&larr; Back To Expression</a>
        <form class="detail-search-form" method="get" action="<?= htmlspecialchars('/TE-/expression_detail.php',ENT_QUOTES,'UTF-8') ?>">
          <input type="hidden" name="lang" value="<?= htmlspecialchars($siteLang,ENT_QUOTES,'UTF-8') ?>">
          <input type="hidden" name="renderer" value="<?= htmlspecialchars($siteRenderer,ENT_QUOTES,'UTF-8') ?>">
          <input type="hidden" name="metric" value="<?= htmlspecialchars($metric,ENT_QUOTES,'UTF-8') ?>">
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort,ENT_QUOTES,'UTF-8') ?>">
          <div class="detail-search-box">
            <svg class="detail-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"></circle><path d="m20 20-3.8-3.8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>
            <input class="detail-query" type="text" name="te" value="<?= htmlspecialchars($teQuery,ENT_QUOTES,'UTF-8') ?>" placeholder="Search A TE For Expression Detail">
          </div>
        </form>
      </section>

      <div id="expressionDetailResults">
        <?php if($teQuery==='' ): ?>
          <div class="detail-panel"><h3>Expression Detail</h3><p class="expression-empty">Search For A TE To Open Its Expression Detail View.</p></div>
        <?php elseif($error!==null || !is_array($detail)): ?>
          <div class="expression-error"><?= htmlspecialchars($error ?? 'No Expression Detail Is Available For The Requested TE Yet.',ENT_QUOTES,'UTF-8') ?></div>
        <?php else: ?>
          <?php $summary=$detail['browse_summary']; $datasetLabels=exd_dataset_labels_from_summary($summary); ?>
          <div class="detail-layout">
            <aside class="detail-sidebar">
              <nav class="detail-nav" aria-label="Expression Detail Sections">
                <div class="detail-nav-title">Detail Sections</div>
                <?php foreach($sections as $section): ?>
                  <a class="detail-nav-link" data-detail-nav-link href="#<?= htmlspecialchars($section['id'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($section['label'],ENT_QUOTES,'UTF-8') ?></a>
                <?php endforeach; ?>
              </nav>
              <form class="detail-controls" method="get" action="<?= htmlspecialchars('/TE-/expression_detail.php',ENT_QUOTES,'UTF-8') ?>">
                <input type="hidden" name="lang" value="<?= htmlspecialchars($siteLang,ENT_QUOTES,'UTF-8') ?>">
                <input type="hidden" name="renderer" value="<?= htmlspecialchars($siteRenderer,ENT_QUOTES,'UTF-8') ?>">
                <input type="hidden" name="te" value="<?= htmlspecialchars($teQuery,ENT_QUOTES,'UTF-8') ?>">
                <div class="detail-controls-title">Display Controls</div>
                <div class="detail-control-group">
                  <label class="detail-control-label" for="expression-detail-metric">Metric</label>
                  <select class="detail-select" id="expression-detail-metric" name="metric" data-detail-auto-submit>
                    <option value="median" <?= $metric==='median'?'selected':'' ?>>Median</option>
                    <option value="mean" <?= $metric==='mean'?'selected':'' ?>>Mean</option>
                    <option value="max" <?= $metric==='max'?'selected':'' ?>>Max</option>
                  </select>
                </div>
                <div class="detail-control-group">
                  <label class="detail-control-label" for="expression-detail-sort">Order</label>
                  <select class="detail-select" id="expression-detail-sort" name="sort" data-detail-auto-submit>
                    <option value="default" <?= $sort==='default'?'selected':'' ?>>Default Order</option>
                    <option value="high_to_low" <?= $sort==='high_to_low'?'selected':'' ?>>High To Low</option>
                    <option value="low_to_high" <?= $sort==='low_to_high'?'selected':'' ?>>Low To High</option>
                  </select>
                </div>
              </form>
            </aside>
            <div class="detail-content">
              <section id="expression-detail-summary" class="detail-panel">
                <h3>Summary</h3>
                <div class="detail-summary-grid">
                  <div class="detail-card"><span>Datasets</span><strong><?= htmlspecialchars($datasetLabels===[]?'-':implode(' / ',$datasetLabels),ENT_QUOTES,'UTF-8') ?></strong></div>
                  <div class="detail-card"><span>Top Global Context</span><strong><?= htmlspecialchars((string)(($summary['global_top_context_median_full_name']??'')?:($summary['global_top_context_median']??'-')),ENT_QUOTES,'UTF-8') ?></strong></div>
                  <div class="detail-card"><span>Top Global Dataset</span><strong><?= htmlspecialchars((string)($summary['global_top_context_median_dataset']??'-'),ENT_QUOTES,'UTF-8') ?></strong></div>
                  <div class="detail-card"><span>Global Median</span><strong><?= htmlspecialchars(exd_fmt($summary['global_top_context_median_value']??null),ENT_QUOTES,'UTF-8') ?></strong></div>
                </div>
              </section>
              <?php if(isset($detail['datasets']['normal_tissue'])) exd_render_section('expression-detail-normal-tissue','Normal Tissue',$detail['datasets']['normal_tissue'],$metric,(string)$detail['te_name'],'#7fa6d8'); ?>
              <?php if(isset($detail['datasets']['normal_cell_line'])) exd_render_section('expression-detail-normal-cell-line','Normal Cell Line',$detail['datasets']['normal_cell_line'],$metric,(string)$detail['te_name'],'#6fb6a6'); ?>
              <?php if(isset($detail['datasets']['cancer_cell_line'])) exd_render_section('expression-detail-cancer-cell-line','Cancer Cell Line',$detail['datasets']['cancer_cell_line'],$metric,(string)$detail['te_name'],'#d38fb4'); ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
<script>
(function(){
  function initDetailNav(root){
    const navLinks=Array.from(root.querySelectorAll('[data-detail-nav-link]'));
    if(navLinks.length===0){ return; }
    const sections=navLinks.map(link=>document.getElementById((link.getAttribute('href')||'').replace(/^#/,''))).filter(Boolean);
    function refresh(){
      const pivot=window.scrollY+180;
      let current=sections[0]||null;
      sections.forEach(function(section){ if(section.offsetTop<=pivot){ current=section; } });
      navLinks.forEach(function(link){
        const id=(link.getAttribute('href')||'').replace(/^#/,'');
        link.classList.toggle('is-active',current!==null&&current.id===id);
      });
    }
    window.addEventListener('scroll',refresh,{passive:true});
    refresh();
  }

  function initDetailCharts(root){
    if(typeof Plotly==='undefined'){ return; }
    function lightenHex(hex, amount){
      const clean=(hex||'').replace('#','');
      if(clean.length!==6){ return hex; }
      const num=parseInt(clean,16);
      const r=(num>>16)&255; const g=(num>>8)&255; const b=num&255;
      const mix=function(channel){ return Math.min(255, Math.round(channel + (255 - channel) * amount)); };
      return '#' + [mix(r),mix(g),mix(b)].map(function(v){ return v.toString(16).padStart(2,'0'); }).join('');
    }
    root.querySelectorAll('[data-plotly-payload]').forEach(function(node){
      try {
        const payload = JSON.parse(node.getAttribute('data-plotly-payload') || '{}');
        const color = node.getAttribute('data-plot-color') || '#7fa6d8';
        const hoverColor = lightenHex(color, 0.22);
        const labels = Array.isArray(payload.labels) ? payload.labels : [];
        const values = Array.isArray(payload.values) ? payload.values : [];
        const sampleCounts = Array.isArray(payload.sample_counts) ? payload.sample_counts : [];
        const customData = labels.map(function(_, idx){ return [sampleCounts[idx] || 0]; });
        const baseColors = labels.map(function(){ return color; });
        const hoverTemplate = '<b>%{x}</b><br>' + (payload.metric_label || 'Median') + ': %{y:.3f}<br>Sample Count: %{customdata[0]}<extra></extra>';
        Plotly.newPlot(node, [{
          type:'bar',
          x: labels,
          y: values,
          marker:{color:baseColors, line:{color:color, width:1}},
          customdata: customData,
          hovertemplate: hoverTemplate,
          hoverlabel:{bgcolor:'#ffffff',bordercolor:'#8db7ef',font:{size:14,color:'#203d67'}}
        }], {
          title:{text: payload.title || '', font:{size:16,color:'#254670'}},
          margin:{l:68,r:22,t:54,b:128},
          paper_bgcolor:'rgba(0,0,0,0)',
          plot_bgcolor:'#ffffff',
          hovermode:'closest',
          bargap:0.5,
          xaxis:{tickangle:-34, automargin:true, tickfont:{size:11,color:'#4d6180'}, gridcolor:'#eef3f9'},
          yaxis:{title:{text:(payload.metric_label || 'Median') + ' Normalized Expression'}, automargin:true, tickfont:{size:12,color:'#4d6180'}, gridcolor:'#e7eef8', zerolinecolor:'#d7e1ef'},
          showlegend:false,
          font:{family:'Inter, Segoe UI, Microsoft YaHei, sans-serif', color:'#254670'}
        }, {
          displaylogo:false,
          responsive:true,
          modeBarButtonsToRemove:['lasso2d','select2d','autoScale2d','toggleSpikelines']
        }).then(function(){
          node.on('plotly_hover', function(eventData){
            if(!eventData || !eventData.points || !eventData.points[0]){ return; }
            const pointIndex = eventData.points[0].pointNumber;
            const colors = labels.map(function(){ return color; });
            colors[pointIndex] = hoverColor;
            Plotly.restyle(node, {'marker.color':[colors]});
            Plotly.relayout(node, {shapes:[{type:'rect',xref:'x',yref:'paper',x0:labels[pointIndex],x1:labels[pointIndex],x0shift:-0.5,x1shift:0.5,y0:0,y1:1,fillcolor:'rgba(102,116,138,0.08)',line:{width:0},layer:'below'}]});
          });
          node.on('plotly_unhover', function(){
            Plotly.restyle(node, {'marker.color':[baseColors]});
            Plotly.relayout(node, {shapes:[]});
          });
        });
      } catch (error) {
        console.error('Plotly render failed', error);
      }
    });
  }

  async function refreshDetailResults(url){
    const currentScrollY = window.scrollY;
    try {
      const response = await fetch(url, {headers:{'X-Requested-With':'fetch'}, credentials:'same-origin'});
      if(!response.ok){ throw new Error('HTTP ' + response.status); }
      const html = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html,'text/html');
      const nextPage = doc.getElementById('expressionDetailPage');
      const livePage = document.getElementById('expressionDetailPage');
      if(!nextPage || !livePage){ throw new Error('Expression detail fragment was not found in the response.'); }
      livePage.outerHTML = nextPage.outerHTML;
      window.history.pushState({ expressionDetailUrl: url }, '', url);
      window.scrollTo(0, currentScrollY);
      const freshRoot = document.getElementById('expressionDetailPage');
      if(freshRoot){ initDetailNav(freshRoot); initDetailCharts(freshRoot); }
    } catch(error){
      console.error('Expression detail refresh failed:', error);
      window.location.href = url;
    }
  }

  function initDetailAsync(root){
    root.querySelectorAll('[data-detail-auto-submit]').forEach(function(select){
      select.addEventListener('change', function(){
        const form = select.form;
        if(!form){ return; }
        const url = new URL(form.action || window.location.href, window.location.origin);
        const formData = new FormData(form);
        url.search = '';
        formData.forEach(function(value,key){
          if(typeof value === 'string' && value !== ''){ url.searchParams.set(key, value); }
        });
        refreshDetailResults(url.toString());
      });
    });
  }

  const root=document.getElementById('expressionDetailPage');
  if(root){ initDetailNav(root); initDetailCharts(root); initDetailAsync(root); }
  window.addEventListener('popstate', function(){ refreshDetailResults(window.location.href); });
})();
</script>
<?php require __DIR__ . '/foot.php'; ?>