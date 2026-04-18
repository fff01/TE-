import json
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

from tekg2_entity_overrides import apply_record_overrides


ROOT = Path(__file__).resolve().parents[1]
INPUT_JSONL = ROOT / "data" / "processed" / "tekg2" / "tekg2_0413_clean.jsonl"
OUTPUT_JSONL = ROOT / "data" / "processed" / "tekg2" / "tekg2_0413_clean.jsonl"
OUTPUT_REPORT = ROOT / "data" / "processed" / "tekg2" / "tekg2_attention_override_report.json"


def main() -> None:
    lines_out = []
    updated_records = 0
    stats = Counter()

    with INPUT_JSONL.open("r", encoding="utf-8") as handle:
        for line in handle:
            raw = line.strip()
            if not raw:
                continue
            record = json.loads(raw)
            updated_record, record_stats = apply_record_overrides(record)
            if record_stats:
                updated_records += 1
                stats.update(record_stats)
            lines_out.append(json.dumps(updated_record, ensure_ascii=False))

    OUTPUT_JSONL.write_text("\n".join(lines_out) + "\n", encoding="utf-8")

    report = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "input_file": str(INPUT_JSONL),
        "output_file": str(OUTPUT_JSONL),
        "updated_records": updated_records,
        **dict(stats),
    }
    OUTPUT_REPORT.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
