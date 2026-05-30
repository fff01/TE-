from pathlib import Path

path = Path("api/agent/bootstrap/run_store.php")
for i, line in enumerate(path.read_text(encoding="utf-8").splitlines(), start=1):
    if 120 <= i <= 175:
        print(f"{i:5}: {line}")
