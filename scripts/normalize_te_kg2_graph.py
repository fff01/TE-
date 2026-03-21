import json
import re
from collections import Counter
from pathlib import Path

from semantic_aliases import EXTRA_DISEASE_ALIASES, EXTRA_FUNCTION_ALIASES, EXTRA_TE_ALIASES

INPUT_FILE = Path("data/raw/te_kg2.jsonl")
NORMALIZED_JSONL = Path("data/processed/te_kg2_normalized_output.jsonl")
GRAPH_SEED_JSON = Path("data/processed/te_kg2_graph_seed.json")
REPORT_JSON = Path("data/processed/te_kg2_normalization_report.json")


RELATION_ALIASES = {
    "report": "报道",
    "involve in": "参与",
    "participate in": "参与",
    "associate with": "参与",
    "correlate with": "参与",
    "lead to": "导致",
    "cause": "导致",
    "drive": "促进",
    "promote": "促进",
    "mediate": "介导",
    "affect": "影响",
    "regulate": "调控",
    "utilize": "利用",
    "use": "利用",
    "perform": "执行",
    "suppress": "抑制",
    "inhibit": "抑制",
    "trigger": "触发",
    "induce": "诱导",
    "increase risk": "增加风险",
    "modulate": "调节",
    "contribute to": "促成",
    "undergo": "发生",
    "activate": "激活",
    "facilitate": "促进",
    "disrupt": "破坏",
    "generate": "产生",
    "serve as": "充当",
    "enable": "使能",
    "explain": "解释",
    "supply": "提供",
    "predispose to": "易感",
    "is regulated by": "被调控",
    "alter": "改变",
    "exhibit": "表现为",
    "lack": "缺失",
    "characterizes": "表征",
}


TE_ALIASES = {
    "line1": "LINE-1",
    "line-1": "LINE-1",
    "line 1": "LINE-1",
    "long interspersed element-1": "LINE-1",
    "long interspersed nuclear element-1": "LINE-1",
    "human line-1": "LINE-1",
    "human line1": "LINE-1",
    "l1hs": "L1HS",
    "l1hs-specific": "L1HS",
    "l1hs/ta": "L1HS",
    "l1 ta": "L1-Ta",
    "l1-ta": "L1-Ta",
    "sva_f": "SVA_F",
}


DISEASE_ALIASES = {
    "alzheimer's disease": "Alzheimer's disease",
    "alzheimer’s disease": "Alzheimer's disease",
    "huntington's disease": "Huntington's disease",
    "huntington disease": "Huntington's disease",
    "down syndrome": "Down syndrome",
    "rett syndrome": "Rett syndrome",
    "autism spectrum disorder": "Autism spectrum disorder (ASD)",
    "autism spectrum disorders": "Autism spectrum disorder (ASD)",
    "autism spectrum disorder (asd)": "Autism spectrum disorder (ASD)",
    "ataxia telangiectasia": "ataxia telangiectasia",
    "hepatocellular carcinoma": "Hepatocellular carcinoma",
    "non-small cell lung cancer": "non-small cell lung cancer",
    "chronic granulomatous disease": "Chronic granulomatous disease",
    "b cell malignancies": "B-cell malignancies",
    "head-and-neck squamous cell carcinoma": "Head and neck squamous cell carcinoma",
    "x-linked dystonia-parkinsonism": "X-linked dystonia parkinsonism",
    "x-linked dystonia-parkinsonism (xdp)": "X-linked dystonia parkinsonism (XDP)",
    "age related diseases": "age-related diseases",
    "head-and-neck cancer": "head and neck cancer",
    "nonobstructive azoospermia": "non-obstructive azoospermia",
    "śá1-antitrypsin deficiency": "ŚÁ-1 antitrypsin deficiency",
}


FUNCTION_ALIASES = {
    "retrotransposition": "retrotransposition",
    "line-1 retrotransposition": "LINE-1 retrotransposition",
    "l1 retrotransposition": "L1 retrotransposition",
    "genome instability": "genome instability",
    "genomic instability": "genome instability",
    "insertional mutation": "insertional mutation",
    "insertional mutagenesis": "insertional mutagenesis",
    "dna damage": "DNA damage",
    "dna repair": "DNA repair",
    "dna damage response": "DNA damage response",
    "non-homologous end-joining (nhej)": "Non-homologous end joining (NHEJ)",
    "nonhomologous end joining (nhej)": "Non-homologous end joining (NHEJ)",
    "5'-transduction": "5' transduction",
    "cell type-specific expression": "Cell type specific expression",
    "cis-preference": "Cis preference",
    "coevolution": "Co-evolution",
    "dna-binding": "DNA binding",
    "dna cleavage by l1-endonuclease": "DNA cleavage by L1 endonuclease",
    "dna double-strand break repair by nonhomologous end joining": "DNA double-strand break repair by non-homologous end joining",
    "derepression": "De-repression",
    "exon-trapping": "Exon trapping",
    "expression upregulation": "Expression up-regulation",
    "gene down-regulation": "Gene downregulation",
    "l1-rnp formation": "L1 RNP formation",
    "l1-retrotransposition-induced mutagenesis": "L1 retrotransposition-induced mutagenesis",
    "line1 de-repression": "LINE-1 derepression",
    "line1 expression": "LINE-1 expression",
    "line1 hypomethylation": "LINE-1 hypomethylation",
    "line1 reactivation": "LINE-1 reactivation",
    "line1 transcription": "LINE-1 transcription",
    "line1 mediated retrotransposition": "LINE-1-mediated retrotransposition",
    "mono-allelic expression": "Monoallelic expression",
    "nonallelic homologous recombination": "Non-allelic homologous recombination",
    "nonallelic homologous recombination (nahr)": "Non-allelic homologous recombination (NAHR)",
    "non-viral gene transfer": "Nonviral gene transfer",
    "rna-binding": "RNA binding",
    "reverse-transcriptase activity": "Reverse transcriptase activity",
    "t-cell engineering": "T cell engineering",
    "target-site duplication (tsd)": "Target site duplication (TSD)",
    "target-site primed reverse transcription (tprt)": "Target-primed reverse transcription (TPRT)",
    "target primed reverse transcription (tprt)": "Target-primed reverse transcription (TPRT)",
    "target site-primed reverse transcription": "Target-site primed reverse transcription",
    "transcriptional derepression": "Transcriptional de-repression",
    "twin-priming": "Twin priming",
    "type-i interferon response": "Type I interferon response",
    "up-regulation": "Upregulation",
    "x-chromosome inactivation": "X chromosome inactivation",
    "x-inactivation": "X inactivation",
    "cell-cycle regulation": "cell cycle regulation",
    "hypomethylation": "hypo-methylation",
    "interchromosomal translocation": "inter-chromosomal translocation",
}

TE_ALIASES.update(EXTRA_TE_ALIASES)
DISEASE_ALIASES.update(EXTRA_DISEASE_ALIASES)
FUNCTION_ALIASES.update(EXTRA_FUNCTION_ALIASES)


def normalize_whitespace(text: str) -> str:
    text = str(text or "")
    text = text.replace("\u2010", "-").replace("\u2011", "-").replace("\u2012", "-")
    text = text.replace("\u2013", "-").replace("\u2014", "-").replace("\u2212", "-")
    text = text.replace("\u00a0", " ")
    return re.sub(r"\s+", " ", text).strip()


def normalize_key(text: str) -> str:
    return normalize_whitespace(text).casefold()


def normalize_compare_key(text: str) -> str:
    text = normalize_key(text)
    text = text.replace("’", "'").replace("`", "'")
    text = re.sub(r"\(.*?\)", "", text)
    text = re.sub(r"[\s\-_]", "", text)
    return text


def iter_json_objects(path: Path):
    decoder = json.JSONDecoder()
    with path.open("r", encoding="utf-8") as handle:
        for lineno, line in enumerate(handle, start=1):
            raw = line.strip()
            if not raw:
                continue
            index = 0
            while index < len(raw):
                obj, end = decoder.raw_decode(raw, index)
                yield lineno, obj
                index = end
                while index < len(raw) and raw[index].isspace():
                    index += 1


def load_repbase_ids(path: Path) -> dict[str, str]:
    mapping: dict[str, str] = {}
    if not path.exists():
        return mapping
    with path.open("r", encoding="utf-8", errors="replace") as handle:
        for line in handle:
            if not line.startswith("ID"):
                continue
            parts = line.strip().split()
            if len(parts) >= 2:
                canonical = parts[1].strip()
                mapping[canonical.casefold()] = canonical
    return mapping


REPBASE_ID_LOOKUP = load_repbase_ids(Path("TE_Repbase.txt"))


def infer_line1_subfamily(name: str) -> str | None:
    normalized = normalize_whitespace(name).upper()
    normalized = normalized.replace("LINE1", "L1").replace("LINE-1", "L1")
    normalized = normalized.replace(" ", "")
    match = re.fullmatch(r"L1(PA\d+A?|PB\d+|MA\d+|HS)", normalized)
    if match:
        return normalized
    return None


def canonicalize_entity(entity_type: str, name: str) -> str:
    base = normalize_whitespace(name)
    if not base:
        return ""

    compare_key = normalize_compare_key(base)
    key = normalize_key(base)

    if entity_type == "transposons":
        if key in TE_ALIASES:
            return TE_ALIASES[key]
        subfamily = infer_line1_subfamily(base)
        if subfamily:
            return subfamily
        repbase = REPBASE_ID_LOOKUP.get(base.casefold())
        if repbase:
            return repbase
        return base

    if entity_type == "diseases":
        if key in DISEASE_ALIASES:
            return DISEASE_ALIASES[key]
        if compare_key in {normalize_compare_key(k) for k in DISEASE_ALIASES}:
            for alias, canonical in DISEASE_ALIASES.items():
                if compare_key == normalize_compare_key(alias):
                    return canonical
        return base

    if entity_type == "functions":
        if key in FUNCTION_ALIASES:
            return FUNCTION_ALIASES[key]
        if compare_key in {normalize_compare_key(k) for k in FUNCTION_ALIASES}:
            for alias, canonical in FUNCTION_ALIASES.items():
                if compare_key == normalize_compare_key(alias):
                    return canonical
        return base

    return base


def canonicalize_relation(name: str) -> str:
    base = normalize_key(name)
    return RELATION_ALIASES.get(base, normalize_whitespace(name))


def dedupe_entities(items: list[dict], entity_type: str) -> list[dict]:
    deduped: dict[str, dict] = {}
    for item in items:
        raw_name = normalize_whitespace(item.get("name", ""))
        if not raw_name:
            continue
        canonical_name = canonicalize_entity(entity_type, raw_name)
        if not canonical_name:
            continue
        description = normalize_whitespace(item.get("description", ""))
        dedupe_key = canonical_name.casefold()
        if dedupe_key not in deduped:
            deduped[dedupe_key] = {"name": canonical_name, "description": description}
        elif not deduped[dedupe_key]["description"] and description:
            deduped[dedupe_key]["description"] = description
    return sorted(deduped.values(), key=lambda x: x["name"].casefold())


def infer_entity_type(name: str, entities: dict) -> str:
    key = normalize_key(name)
    for entity_type, items in entities.items():
        for item in items:
            if normalize_key(item["name"]) == key:
                return entity_type
    if infer_line1_subfamily(name):
        return "transposons"
    return "functions"


def normalize_record(record: dict) -> dict:
    entities = record.get("entities", {}) or {}
    normalized_entities = {
        "transposons": dedupe_entities(entities.get("transposons", []) or [], "transposons"),
        "diseases": dedupe_entities(entities.get("diseases", []) or [], "diseases"),
        "functions": dedupe_entities(entities.get("functions", []) or [], "functions"),
        "papers": dedupe_entities(entities.get("papers", []) or [], "papers"),
    }

    relation_seen: dict[tuple[str, str, str], dict] = {}
    for rel in record.get("relations", []) or []:
        source_type = infer_entity_type(rel.get("source", ""), normalized_entities)
        target_type = infer_entity_type(rel.get("target", ""), normalized_entities)
        source = canonicalize_entity(source_type, rel.get("source", ""))
        target = canonicalize_entity(target_type, rel.get("target", ""))
        relation = canonicalize_relation(rel.get("relation", ""))
        description = normalize_whitespace(rel.get("description", ""))
        if not source or not target or not relation:
            continue
        dedupe_key = (source.casefold(), relation.casefold(), target.casefold())
        if dedupe_key not in relation_seen:
            relation_seen[dedupe_key] = {
                "source": source,
                "relation": relation,
                "target": target,
                "description": description,
            }
        elif not relation_seen[dedupe_key]["description"] and description:
            relation_seen[dedupe_key]["description"] = description

    pmid = normalize_whitespace(record.get("pmid", ""))
    return {
        "pmid": pmid,
        "entities": normalized_entities,
        "relations": sorted(
            relation_seen.values(),
            key=lambda x: (x["source"].casefold(), x["relation"].casefold(), x["target"].casefold()),
        ),
    }


def build_graph_seed(records: list[dict]) -> dict:
    node_buckets = {
        "transposons": {},
        "diseases": {},
        "functions": {},
        "papers": {},
    }
    relation_buckets: dict[tuple[str, str, str], dict] = {}

    for record in records:
        pmid = record.get("pmid", "")
        paper_entities = record["entities"].get("papers", [])
        if paper_entities:
            paper = dict(paper_entities[0])
            paper["pmid"] = pmid
            node_buckets["papers"][pmid] = paper
        elif pmid:
            node_buckets["papers"][pmid] = {
                "pmid": pmid,
                "name": f"PMID:{pmid}",
                "description": "",
            }

        for entity_type in ("transposons", "diseases", "functions"):
            for item in record["entities"].get(entity_type, []):
                key = item["name"].casefold()
                if key not in node_buckets[entity_type]:
                    node_buckets[entity_type][key] = dict(item)

        for rel in record.get("relations", []):
            key = (rel["source"].casefold(), rel["relation"].casefold(), rel["target"].casefold())
            if key not in relation_buckets:
                relation_buckets[key] = {
                    "source": rel["source"],
                    "relation": rel["relation"],
                    "target": rel["target"],
                    "description": rel.get("description", ""),
                    "pmids": [],
                }
            relation_buckets[key]["pmids"].append(pmid)

    return {
        "nodes": {
            entity_type: sorted(bucket.values(), key=lambda x: x["name"].casefold())
            for entity_type, bucket in node_buckets.items()
        },
        "relations": sorted(
            (
                {
                    **value,
                    "pmids": sorted({pmid for pmid in value["pmids"] if pmid}),
                }
                for value in relation_buckets.values()
            ),
            key=lambda x: (x["source"].casefold(), x["relation"].casefold(), x["target"].casefold()),
        ),
        "lineage_relations": [],
    }


def build_report(records: list[dict], graph_seed: dict) -> dict:
    relation_counter = Counter()
    node_counts = {group: len(items) for group, items in graph_seed["nodes"].items()}
    for rel in graph_seed["relations"]:
        relation_counter[rel["relation"]] += 1
    return {
        "records": len(records),
        "paper_nodes": node_counts["papers"],
        "te_nodes": node_counts["transposons"],
        "disease_nodes": node_counts["diseases"],
        "function_nodes": node_counts["functions"],
        "relation_count": len(graph_seed["relations"]),
        "top_relations": relation_counter.most_common(20),
    }


def main() -> None:
    if not INPUT_FILE.exists():
        raise FileNotFoundError(f"Missing input file: {INPUT_FILE}")

    normalized_records = []
    for _lineno, obj in iter_json_objects(INPUT_FILE):
        normalized_records.append(normalize_record(obj))

    with NORMALIZED_JSONL.open("w", encoding="utf-8") as handle:
        for record in normalized_records:
            handle.write(json.dumps(record, ensure_ascii=False) + "\n")

    graph_seed = build_graph_seed(normalized_records)
    with GRAPH_SEED_JSON.open("w", encoding="utf-8") as handle:
        json.dump(graph_seed, handle, ensure_ascii=False, indent=2)

    report = build_report(normalized_records, graph_seed)
    REPORT_JSON.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")

    print(f"Normalized records: {len(normalized_records)}")
    print(f"Wrote: {NORMALIZED_JSONL}")
    print(f"Wrote: {GRAPH_SEED_JSON}")
    print(f"Wrote: {REPORT_JSON}")


if __name__ == "__main__":
    main()
