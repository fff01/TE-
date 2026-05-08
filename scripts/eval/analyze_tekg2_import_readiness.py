import argparse
import json
import sys
from pathlib import Path

SCRIPTS_ROOT = next(parent for parent in Path(__file__).resolve().parents if parent.name == "scripts")
if str(SCRIPTS_ROOT) not in sys.path:
    sys.path.insert(0, str(SCRIPTS_ROOT))

from build.build_tekg2_seed_from_standardized_new import build_seed


def main():
    parser = argparse.ArgumentParser(description="Analyze cleaned TEKG2 jsonl for Neo4j import readiness.")
    parser.add_argument("input_file", type=Path)
    parser.add_argument("report_file", type=Path)
    args = parser.parse_args()

    input_file = args.input_file.resolve()
    report_file = args.report_file.resolve()

    _seed, report = build_seed(input_file)
    report["input_file"] = str(input_file)

    report_file.parent.mkdir(parents=True, exist_ok=True)
    report_file.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
