# 🕹️ LOA.i 오목 게임 시스템

카카오톡 기반 멀티 유저 오목 게임 기능을 LOA.i 봇 시스템에 통합한 구현입니다.
AI 대전 및 인간 대전을 지원하며, 착수 이미지를 자동 생성해 전송하고, 룰 커스터마이징 및 통계 기록 기능도 포함되어 있습니다.

---

## 📌 주요 기능

### 🎮 게임 시작 명령어

| 명령어 | 설명 |
|--------|------|
| `# AI 오목 시작` | AI와 오목 게임 시작 |
| `# 인간 오목 시작` | 사용자 간 1:1 오목 대전 시작 |
| `# 오목 참여` | 인간 대전 모드에서 두 번째 유저가 참여 |
| `G9` / `g9` | 오목판 착수. 명령어 없이 좌표만 입력해도 작동 |

### 🧠 AI 레벨 및 룰 설정

- 명령어 옵션:
  - `--rule=renju` / `standard` / `freestyle`
  - `--3-3=true` / `false`
  - `--4-4=true` / `false`
  - `--overline=true` / `false`
  - `--ai-level=1~10`

```bash
# 예시
# AI 오목 시작 --rule=renju --3-3=true --ai-level=6
🧩 폴더 구조
perl
복사
편집
games/
└── omok/
    ├── constants.py              # 보드 설정, 기본 룰 정의
    ├── state/
    │   └── game_session.py       # 게임 세션 상태 관리
    ├── engine/
    │   ├── rule_engine.py        # 승리 판정 알고리즘
    │   └── ai_engine.py          # AI 착수 로직
    ├── handlers/
    │   ├── start_game_handler.py # 게임 시작 처리
    │   ├── join_game_handler.py  # 유저 참여 처리
    │   └── omok_command_handler.py # 착수 처리
    ├── db/
    │   └── omok_stats_dao.py     # 게임 결과 및 유저 통계 저장
    └── utils/
        └── board_renderer.py     # 오목판 이미지 생성 (PIL)
📸 착수 이미지
착수 시마다 오목판 이미지를 자동 생성하여 전송

최근 착수 좌표는 빨간 점으로 강조 표시

예시:

복사
편집
흑돌 착수: G9
(이미지 함께 전송)

🗃️ DB 테이블
kb_omok_user_stats

필드	설명
user_id	사용자 ID
wins / losses	승/패 수
games_played	전체 경기 수
rating	사용자 레이팅
last_played	마지막 플레이 시각
kb_omok_game_history

필드	설명
game_id	게임 고유 ID
player1_id / player2_id	참가자 ID
winner_id	승자 ID
rule / custom_rules	적용된 룰
moves	착수 기록(JSON)
started_at / ended_at	시작/종료 시각
📥 메시지 처리 통합
message_processor.py에서 G9 또는 g9과 같은 단일 좌표 입력을 자동 착수로 처리

오목 세션이 진행 중인 채널에서만 해당 기능 작동

🚧 향후 계획
# 오목 종료 명령 추가

기권 처리 (# 기권)

점수 기반 레이팅 및 랭킹

다인 관전 모드

룰 편집 GUI