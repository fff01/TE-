import argparse
import json
from pathlib import Path

from build_tekg2_seed_from_standardized_new import build_seed


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
