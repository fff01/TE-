<?php
declare(strict_types=1);

require_once __DIR__ . '/expression_repository.php';

const TEKG_COEXPRESSION_VERSION = 'v1_abs0.4_fdr0.05_res1.8';
const TEKG_COEXPRESSION_CONTEXTS = [
    'cancer_cell_line' => 'Cancer cell line',
    'normal_cell_line' => 'Normal cell line',
    'normal_tissue' => 'Normal tissue',
];
const TEKG_COEXPRESSION_TIER_PRIORITY = ['core_case' => 4, 'high_confidence' => 3, 'searchable_all' => 2, 'not_recommended_default' => 1];

final class CoexpressionRepositoryException extends RuntimeException {
    private string $repositoryErrorCode; private array $repositoryDetails;
    public function __construct(string $errorCode, string $message, array $details = []) { parent::__construct($message); $this->repositoryErrorCode=$errorCode; $this->repositoryDetails=$details; }
    public function errorCode(): string { return $this->repositoryErrorCode; }
    public function details(): array { return $this->repositoryDetails; }
}
function tekg_coexpression_rows(string $sql, array $params=[], ?string $types=null): array {
    try { return tekg_expression_fetch_all($sql,$params,$types); }
    catch (Throwable) { throw new CoexpressionRepositoryException('data_contract_error','The approved co-expression data could not be served.'); }
}
function tekg_coexpression_active_version(): array {
    $rows=tekg_coexpression_rows('SELECT id,version_key,method,thresholds_json,default_te,default_context,interpretation_limit FROM coexpression_analysis_versions WHERE is_active=1 LIMIT 2');
    if (count($rows)!==1 || $rows[0]['version_key']!==TEKG_COEXPRESSION_VERSION) throw new CoexpressionRepositoryException('data_contract_error','The approved co-expression result set is unavailable.');
    return $rows[0];
}
function tekg_coexpression_catalog(): array {
    $v=tekg_coexpression_active_version();
    $rows=tekg_coexpression_rows('SELECT center_te, context_key, display_tier, recommended_default FROM coexpression_networks WHERE version_id=? ORDER BY center_te COLLATE utf8mb4_general_ci, context_key',[(int)$v['id']],'i');
    $items=[]; foreach($rows as $row){$te=$row['center_te']; if(!isset($items[$te]))$items[$te]=['te'=>$te,'available_contexts'=>[],'best_tier'=>null,'recommended_default'=>false,'_p'=>-1]; $items[$te]['available_contexts'][]=$row['context_key']; $p=TEKG_COEXPRESSION_TIER_PRIORITY[$row['display_tier']]??-1; if($p>$items[$te]['_p']){$items[$te]['_p']=$p;$items[$te]['best_tier']=$row['display_tier'];}$items[$te]['recommended_default']=$items[$te]['recommended_default']||((int)$row['recommended_default']===1);}
    foreach($items as &$item){sort($item['available_contexts']);unset($item['_p']);} unset($item);
    $items = array_values($items); usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['te'], $b['te']));
    $thresholds=json_decode((string)$v['thresholds_json'],true); if(!is_array($thresholds))throw new CoexpressionRepositoryException('data_contract_error','The co-expression version metadata is invalid.');
    return ['version'=>$v['version_key'],'method'=>$v['method'],'thresholds'=>$thresholds,'default_selection'=>['te'=>$v['default_te'],'context'=>$v['default_context']],'contexts'=>array_map(static fn($id,$label)=>['id'=>$id,'label'=>$label],array_keys(TEKG_COEXPRESSION_CONTEXTS),array_values(TEKG_COEXPRESSION_CONTEXTS)),'items'=>$items,'interpretation_limit'=>$v['interpretation_limit']];
}
function tekg_coexpression_load_network(string $teName,string $context): array {
    if(!array_key_exists($context,TEKG_COEXPRESSION_CONTEXTS))throw new CoexpressionRepositoryException('invalid_context','The requested co-expression context is invalid.');
    $v=tekg_coexpression_active_version(); $known=tekg_coexpression_rows('SELECT DISTINCT center_te FROM coexpression_networks WHERE version_id=? AND LOWER(center_te)=LOWER(?)',[(int)$v['id'],trim($teName)],'is');
    if(count($known)!==1)throw new CoexpressionRepositoryException('unknown_te','The requested TE is not present in the approved co-expression catalog.'); $te=$known[0]['center_te'];
    $available=tekg_coexpression_rows('SELECT context_key FROM coexpression_networks WHERE version_id=? AND center_te=? ORDER BY context_key',[(int)$v['id'],$te],'is'); $contexts=array_column($available,'context_key');
    $network=tekg_coexpression_rows('SELECT * FROM coexpression_networks WHERE version_id=? AND center_te=? AND context_key=? LIMIT 1',[(int)$v['id'],$te,$context],'iss');
    if($network===[])throw new CoexpressionRepositoryException('network_unavailable',"No display network is available for {$te} in {$context}.",['available_contexts'=>$contexts]); $n=$network[0];
    $nodes=tekg_coexpression_rows('SELECT node_id id,label,feature_type,role,module_id,is_center,is_module_hub,degree_hint FROM coexpression_network_nodes WHERE network_id=? ORDER BY node_order',[(int)$n['id']],'i'); foreach($nodes as &$node){$node['is_center']=(int)$node['is_center']===1;$node['is_module_hub']=(int)$node['is_module_hub']===1;if($node['degree_hint']!==null)$node['degree_hint']=(float)$node['degree_hint'];}unset($node);
    $edges=tekg_coexpression_rows('SELECT source_id source,target_id target,correlation,abs_correlation,fdr,pair_type,role FROM coexpression_network_edges WHERE network_id=? ORDER BY edge_order',[(int)$n['id']],'i'); foreach($edges as &$edge){$edge['correlation']=(float)$edge['correlation'];$edge['abs_correlation']=(float)$edge['abs_correlation'];$edge['fdr']=(float)$edge['fdr'];}unset($edge);
    if(count($nodes)>50||count($edges)>150)throw new CoexpressionRepositoryException('data_contract_error','The co-expression network exceeds the approved display size.'); $terms=json_decode((string)$n['enrichment_terms_json'],true); if(!is_array($terms))throw new CoexpressionRepositoryException('data_contract_error','The co-expression enrichment metadata is invalid.');
    return ['version'=>$v['version_key'],'selection'=>['te'=>$te,'context'=>$context,'available_contexts'=>$contexts,'display_tier'=>$n['display_tier'],'quality_flag'=>$n['quality_flag'],'recommended_default'=>(int)$n['recommended_default']===1],'module'=>['id'=>$n['module_id'],'type'=>$n['module_type'],'size'=>(int)$n['module_size'],'te_count'=>(int)$n['te_count'],'gene_count'=>(int)$n['gene_count'],'confidence'=>$n['confidence'],'candidate_label'=>$n['candidate_label'],'top_enriched_terms'=>array_values($terms)],'interpretation'=>['statement_en'=>$n['statement_en'],'statement_zh'=>$n['statement_zh'],'limit'=>$v['interpretation_limit']],'nodes'=>$nodes,'edges'=>$edges];
}
