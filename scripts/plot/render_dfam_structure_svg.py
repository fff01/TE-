from __future__ import annotations

import argparse
import json
from pathlib import Path
import sys

ROOT = Path(r"D:\wamp64\www\TE-")
if str(ROOT / "scripts" / "plot") not in sys.path:
    sys.path.insert(0, str(ROOT / "scripts" / "plot"))

from base_SVG import render_structure_svg  # noqa: E402

CATALOG_PATH = ROOT / "data" / "processed" / "dfam" / "dfam_curated_catalog.json"
PLOTS_DIR = ROOT / "data" / "processed" / "dfam" / "plots"

FRAGMENT_LABELS = {
    "fragment_3end": "3' end fragment model",
    "fragment_5end": "5' end fragment model",
    "fragment_internal": "internal fragment model",
    "fragment_ltr": "LTR fragment model",
    "unknown_fragment": "fragment model",
}


def load_catalog() -> dict:
    return json.loads(CATALOG_PATH.read_text(encoding="utf-8"))


def entry_for_accession(catalog: dict, accession: str) -> dict | None:
    index = catalog.get("accession_index", {}).get(accession)
    if index is None:
        return None
    entries = catalog.get("entries", [])
    if not isinstance(index, int) or index < 0 or index >= len(entries):
        return None
    return entries[index]


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("accession")
    parser.add_argument("--force", action="store_true")
    args = parser.parse_args()

    catalog = load_catalog()
    entry = entry_for_accession(catalog, args.accession)
    if entry is None:
        print(json.dumps({"ok": False, "error": "accession_not_found", "accession": args.accession}))
        return 1

    output_path = PLOTS_DIR / f"{args.accession}.svg"
    if output_path.exists() and not args.force:
        print(json.dumps({
            "ok": True,
            "cached": True,
            "output_path": str(output_path),
            "accession": args.accession,
        }))
        return 0

    feature_table = entry.get("feature_table", []) or []
    model_type = str(entry.get("model_type") or "full")
    fragment_label = FRAGMENT_LABELS.get(model_type, "")
    subtitle_parts = []
    if entry.get("display_classification"):
        subtitle_parts.append(str(entry["display_classification"]))
    if entry.get("accession"):
        subtitle_parts.append(str(entry["accession"]))
    subtitle = " | ".join(subtitle_parts)

    render_structure_svg(
        sequence_length=int(entry.get("length_bp") or 1),
        title=str(entry.get("name") or args.accession),
        output_path=output_path,
        feature_table=feature_table,
        subtitle=subtitle,
        fragment_label=fragment_label,
    )

    print(json.dumps({
        "ok": True,
        "cached": False,
        "output_path": str(output_path),
        "accession": args.accession,
        "feature_count": len(feature_table),
        "model_type": model_type,
    }))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
