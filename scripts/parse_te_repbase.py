from __future__ import annotations

import json
from pathlib import Path
from typing import Any


ROOT = Path(r"D:\wamp64\www\TE-")
RAW_PATH = ROOT / "data" / "raw" / "TE_Repbase.txt"
OUT_PATH = ROOT / "data" / "processed" / "te_repbase_structured.json"
REPORT_PATH = ROOT / "data" / "processed" / "te_repbase_report.json"


SINGLE_VALUE_TAGS = {"ID", "AC", "DE", "NM", "OS", "SQ"}
LIST_VALUE_TAGS = {"DT", "KW", "OC", "DR"}
REFERENCE_VALUE_TAGS = {"RN", "RP", "RA", "RT", "RL"}


def normalize_space(value: str) -> str:
    return " ".join(value.strip().split())


def parse_keyword_lines(values: list[str]) -> list[str]:
    text = " ".join(values).replace(";", "; ")
    parts = [normalize_space(item) for item in text.split(";")]
    return [item for item in parts if item]


def parse_sequence_summary(value: str) -> dict[str, Any]:
    summary = normalize_space(value)
    result: dict[str, Any] = {"raw": summary}
    pieces = [part.strip() for part in summary.split(";") if part.strip()]
    if pieces:
        result["headline"] = pieces[0]
    for piece in pieces[1:]:
        if " " not in piece:
            continue
        left, right = piece.split(" ", 1)
        if left.isdigit():
            result[right.strip().lower()] = int(left)
    return result


def parse_entry(block: str) -> dict[str, Any]:
    entry: dict[str, Any] = {
        "id": "",
        "accession": "",
        "name": "",
        "description": "",
        "dates": [],
        "keywords": [],
        "species": "",
        "classification": [],
        "cross_references": [],
        "references": [],
        "sequence_summary": {},
        "sequence": "",
    }
    current_tag = ""
    current_ref: dict[str, str] | None = None
    sequence_lines: list[str] = []

    for raw_line in block.splitlines():
        line = raw_line.rstrip()
        if not line or line == "XX":
            continue
        if line == "//":
            break

        if line.startswith("     "):
            continuation = normalize_space(line)
            if current_tag == "SQ":
                sequence_lines.append("".join(ch for ch in continuation.lower() if ch in {"a", "c", "g", "t", "n"}))
            elif current_tag in REFERENCE_VALUE_TAGS and current_ref is not None:
                field_map = {
                    "RA": "authors",
                    "RT": "title",
                    "RL": "journal",
                    "RP": "positions",
                    "RN": "index",
                }
                key = field_map.get(current_tag)
                if key:
                    current_ref[key] = normalize_space(f"{current_ref.get(key, '')} {continuation}")
            elif current_tag == "OC":
                entry["classification"].extend([item for item in [part.strip() for part in continuation.split(";")] if item])
            elif current_tag == "KW":
                entry["keywords"].append(continuation)
            elif current_tag == "DT":
                entry["dates"].append(continuation)
            elif current_tag == "DR":
                entry["cross_references"].append(continuation)
            elif current_tag in {"DE", "NM", "OS", "ID", "AC"}:
                mapped_key = {
                    "DE": "description",
                    "NM": "name",
                    "OS": "species",
                    "ID": "id",
                    "AC": "accession",
                }[current_tag]
                entry[mapped_key] = normalize_space(f"{entry.get(mapped_key, '')} {continuation}")
            continue

        tag = line[:2]
        value = line[5:] if len(line) > 5 else ""
        current_tag = tag

        if tag == "ID":
            entry["id"] = normalize_space(value.split("repbase;", 1)[0])
        elif tag == "AC":
            entry["accession"] = normalize_space(value)
        elif tag == "NM":
            entry["name"] = normalize_space(value)
        elif tag == "DE":
            entry["description"] = normalize_space(value)
        elif tag == "DT":
            entry["dates"].append(normalize_space(value))
        elif tag == "KW":
            entry["keywords"].append(normalize_space(value))
        elif tag == "OS":
            entry["species"] = normalize_space(value)
        elif tag == "OC":
            entry["classification"].extend([item for item in [part.strip() for part in value.split(";")] if item])
        elif tag == "DR":
            entry["cross_references"].append(normalize_space(value))
        elif tag == "SQ":
            entry["sequence_summary"] = parse_sequence_summary(value)
            current_ref = None
        elif tag == "RN":
            current_ref = {"index": normalize_space(value)}
            entry["references"].append(current_ref)
        elif tag in REFERENCE_VALUE_TAGS:
            if current_ref is None:
                current_ref = {}
                entry["references"].append(current_ref)
            field_map = {
                "RP": "positions",
                "RA": "authors",
                "RT": "title",
                "RL": "journal",
            }
            current_ref[field_map[tag]] = normalize_space(value)

    entry["keywords"] = parse_keyword_lines(entry["keywords"])
    entry["sequence"] = "".join(sequence_lines)
    if not entry["name"]:
        entry["name"] = entry["id"]
    return entry


def build_index(entries: list[dict[str, Any]]) -> dict[str, str]:
    index: dict[str, str] = {}
    for entry in entries:
        canonical = entry["id"] or entry["name"]
        candidates = [entry["id"], entry["name"], *entry.get("keywords", [])]
        for candidate in candidates:
            if not candidate:
                continue
            key = normalize_space(candidate).lower()
            if key and key not in index:
                index[key] = canonical
    return index


def main() -> None:
    text = RAW_PATH.read_text(encoding="utf-8")
    blocks = [block.strip() for block in text.replace("\r\n", "\n").split("//") if block.strip()]
    entries = [parse_entry(block) for block in blocks]
    entries = [entry for entry in entries if entry["id"]]

    name_index = build_index(entries)
    payload = {
        "meta": {
            "source": str(RAW_PATH),
            "entry_count": len(entries),
        },
        "entries": entries,
        "name_index": name_index,
    }
    OUT_PATH.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")

    report = {
        "source": str(RAW_PATH),
        "output": str(OUT_PATH),
        "entry_count": len(entries),
        "with_description": sum(1 for entry in entries if entry["description"]),
        "with_keywords": sum(1 for entry in entries if entry["keywords"]),
        "with_references": sum(1 for entry in entries if entry["references"]),
        "with_sequence": sum(1 for entry in entries if entry["sequence"]),
    }
    REPORT_PATH.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")


if __name__ == "__main__":
    main()
