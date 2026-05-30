from pathlib import Path

p = Path("api/agent/orchestrator/traits/AcademicAgentPlanningTrait.php")
for i, line in enumerate(p.read_text(encoding="utf-8").splitlines(), 1):
    if 120 <= i <= 150 or 280 <= i <= 305:
        print(f"{i:5}: {line}")
