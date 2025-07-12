import json
from pathlib import Path

HISTORY_FILE = Path("/home/loa/projects/py/kakao-bot/server/config/history/history.json")


def get_version_history_message():
    try:
        with HISTORY_FILE.open("r", encoding="utf-8") as f:
            version_data = json.load(f)

        sorted_versions = sorted(version_data.keys(), reverse=True)
        lines = ["📦 현재까지의 버전 히스토리입니다:\n"]

        for ver in sorted_versions:
            lines.append(f"🌀 {ver}")
            for log in version_data[ver]:
                lines.append(f"   - {log}")
            lines.append("")

        return "\n".join(lines)
    except Exception as e:
        return f"[ERROR] history.json 로드 실패: {str(e)}"
