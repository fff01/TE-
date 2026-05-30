from pathlib import Path

path = Path("api/agent/orchestrator/EntityNormalizer.php")
for i, line in enumerate(path.read_text(encoding="utf-8").splitlines(), start=1):
    if 1 <= i <= 90 or 180 <= i <= 470:
        print(f"{i:5}: {line}")
