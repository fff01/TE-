from __future__ import annotations

import sys
from pathlib import Path

SCRIPTS_ROOT = next(parent for parent in Path(__file__).resolve().parents if parent.name == "scripts")
if str(SCRIPTS_ROOT) not in sys.path:
    sys.path.insert(0, str(SCRIPTS_ROOT))

import csv
import json
from path_helpers import data_path, taxonomy_te234_path


SEED_JSON = data_path("processed", "tekg2", "tekg2_seed.json")
OUT_CSV = taxonomy_te234_path("te_234_template.csv")

FIELDS = [
    "TE",
    "Class",
    "Subclass",
    "Order",
    "Superfamily",
    "Family",
    "Subfamily",
    "Subclade",
]


def main() -> None:
    payload = json.loads(SEED_JSON.read_text(encoding="utf-8"))
    items = payload["nodes"]["transposons"]

    with OUT_CSV.open("w", newline="", encoding="utf-8-sig") as fh:
        writer = csv.DictWriter(fh, fieldnames=FIELDS)
        writer.writeheader()
        for item in items:
            if isinstance(item, dict):
                te_name = item.get("name") or item.get("label") or item.get("id") or ""
            else:
                te_name = str(item)
            writer.writerow(
                {
                    "TE": str(te_name).strip(),
                    "Class": "",
                    "Subclass": "",
                    "Order": "",
                    "Superfamily": "",
                    "Family": "",
                    "Subfamily": "",
                    "Subclade": "",
                }
            )

    print(f"Wrote: {OUT_CSV}")
    print(f"Rows: {len(items)}")


if __name__ == "__main__":
    main()
