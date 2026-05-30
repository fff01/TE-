import json
import sys
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

base = Path("docs/eval/runs/agent_plugin_live_targeted/raw_events")
for case_id in [
    "AGENT_TARGET_GRAPH",
    "AGENT_TARGET_GRAPH_ANALYTICS",
    "AGENT_TARGET_LITERATURE_READING",
    "AGENT_TARGET_CYPHER_EXPLORER",
]:
    path = base / f"{case_id}.json"
    print(f"\n=== {case_id} ===")
    record = json.loads(path.read_text(encoding="utf-8"))
    agent = record.get("agent", {})
    print("ok:", agent.get("ok"))
    print("errors:", agent.get("errors"))
    print("plugins:", agent.get("used_plugins") or record.get("evaluation", {}).get("agent_plugins"))
    answer = agent.get("answer") or ""
    print("answer:", answer[:1200].replace("\n", "\\n"))
    events = agent.get("events") or []
    print("events:", len(events))
    for event in events:
        if not isinstance(event, dict):
            continue
        etype = event.get("type") or event.get("event")
        payload = event.get("payload")
        text = json.dumps(payload, ensure_ascii=False) if isinstance(payload, (dict, list)) else str(payload)
        if any(token in text for token in [
            "Graph Plugin",
            "Graph Analytics Plugin",
            "Cypher Explorer Plugin",
            "Literature Reading Plugin",
            "failed",
            "error",
            "tool_plan",
            "required_plugins",
            "intent",
        ]):
            print("-", etype, text[:900].replace("\n", "\\n"))
