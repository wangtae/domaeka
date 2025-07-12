# 오목 룰 요소 및 설정값 설계 문서

이 문서는 오목 엔진에서 사용할 수 있는 "룰 요소(Feature)"와 각 요소별 "설정 가능한 값"을 정리한 설계 문서입니다.

---

## 1. 표 형태 요약

| 요소명                | 설명                                      | 설정값(예시)                                   | 적용 색상 옵션         | 비고/예시                      | 사용자 안내 예시 |
|----------------------|------------------------------------------|------------------------------------------------|-----------------------|-------------------------------|------------------|
| overline             | 장목(6목 이상) 처리 방식                  | forbidden(금수), invalid(무효), win(승리)       | black, white, all     | 색상별로 다르게 지정 가능      | 장목: 6목 이상을 두면 흑은 패배, 백은 무효 |
| double_three         | 삼삼(3-3) 금수 적용 여부                  | true, false                                    | black, white, all     | 색상별로 다르게 지정 가능      | 삼삼: 흑은 한 수로 열린 3을 2개 이상 만들면 금수 |
| double_four          | 사사(4-4) 금수 적용 여부                  | true, false                                    | black, white, all     | 색상별로 다르게 지정 가능      | 사사: 흑은 한 수로 열린 4를 2개 이상 만들면 금수 |
| forbidden_action     | 금수 착수 시 처리 방식                    | lose(패배), block(안내만)                      | -                     | 금수 착수 시 패배/안내 선택    | 금수: 두면 패배 또는 착수 불가 안내 |
| swap_rule            | Swap 룰 적용 여부                         | none, swap, swap2, swap3                       | -                     | swap2, swap3: 변형 스왑        | 스왑: 초반 돌 배치 후 흑/백 선택 가능 |
| first_move_restrict  | 첫 수(들) 위치 제한                       | none, center_only, area3x3, area5x5, area7x7, [center_only, none, area5x5] | - | 배열로 착수 순서별 제한 가능 | 첫 수 제한: 1수는 중앙, 3수는 5x5 이내 등 |
| board_size           | 바둑판 크기                               | 19, 15, 13 등                                  | -                     | 표준 19, 소형 15, 13 등        | 바둑판 크기: 15x15 등 |
| allow_draw           | 무승부 허용 여부                          | true, false                                    | -                     | true: 무승부 인정              | 무승부: 둘 곳이 없으면 무승부 인정 |

---

## 2. JSON 포맷 예시

```json
{
  "overline": { "black": "forbidden", "white": "invalid" },
  "double_three": { "black": true, "white": false },
  "double_four": { "black": true, "white": false },
  "forbidden_action": "lose",
  "swap_rule": "none",
  "first_move_restrict": ["center_only", "none", "area5x5"],
  "board_size": 15,
  "allow_draw": true
}
```

---

## 3. 각 요소별 상세 설명

- **overline**: 장목(6목 이상) 처리 방식  
  - forbidden: 금수(두면 패배)  
  - invalid: 무효(승리 아님, 게임 계속)  
  - win: 승리로 인정  
  - 색상별로 {"black": "forbidden", "white": "invalid"} 등으로 지정 가능
  - **승리 조건은 overline 설정에 따라 자동으로 결정됨 (5목만 승리, 5목 이상도 승리 등)**
- **double_three**: 삼삼 금수  
  - true: 금수(두면 패배)  
  - false: 허용  
  - 색상별로 {"black": true, "white": false} 등으로 지정 가능
- **double_four**: 사사 금수  
  - true: 금수(두면 패배)  
  - false: 허용  
  - 색상별로 {"black": true, "white": false} 등으로 지정 가능
- **forbidden_action**: 금수 착수 시 처리 방식  
  - lose: 금수에 착수하면 즉시 패배  
  - block: 금수 자리에 착수 시 "금수 자리입니다" 등 안내만 하고 착수 불가
  - **금수 판별 시, 거짓 금수(실제로는 금수가 아닌데 금수처럼 보이는 자리)는 금수로 간주하지 않음. 반드시 진짜 금수만 금수로 처리해야 함.**
- **swap_rule**: Swap 룰 적용  
  - none: 미적용  
  - swap: 기본 스왑(초반 돌 3개 배치 후 상대가 흑/백 선택)  
  - swap2: 초반 돌 3개 배치 후 상대가 흑/백 선택 또는 추가 2수 배치, 이후 상대가 색 선택  
  - swap3: 초반 돌 3개 배치 후 상대가 흑/백 선택 또는 추가 2수 배치, 이후 상대가 색 선택 또는 추가 2수 배치, 마지막으로 상대가 색 선택  
  - **스왑 룰은 흑의 유리함을 줄이기 위한 전략적 오프닝 룰로, 초반 돌 배치와 색 선택의 주도권을 상대에게 넘기는 방식**
- **first_move_restrict**: 첫 수(들) 위치 제한  
  - none: 제한 없음  
  - center_only: 중앙 한 칸  
  - area3x3: 중앙 3x3 이내  
  - area5x5: 중앙 5x5 이내  
  - area7x7: 중앙 7x7 이내  
  - **배열로 지정 시**: 착수 순서별로 각기 다른 제한 가능  
    - 예: ["center_only", "none", "area5x5"] → 1수: 중앙, 2수: 제한 없음, 3수: 5x5 이내
- **board_size**: 바둑판 크기  
  - 19, 15, 13 등
- **allow_draw**: 무승부 허용 여부  
  - true: 무승부 인정  
  - false: 무승부 없음

---

## 4. 예시: Pro 오목 초반 제한 룰셋

```json
{
  "overline": { "black": "forbidden", "white": "invalid" },
  "double_three": { "black": true, "white": false },
  "double_four": { "black": true, "white": false },
  "forbidden_action": "block",
  "swap_rule": "none",
  "first_move_restrict": ["center_only", "none", "area5x5"],
  "board_size": 15,
  "allow_draw": true
}
```

---

## 💡 UX/시각화 메모

- **first_move_restrict(첫 수 위치 제한) 룰이 적용되는 턴까지**
  - 오목판에 해당 제한 영역(예: center_only, area5x5 등)을 빨간색 등으로 시각적으로 표시하면 직관적이고 편리함
  - 제한이 해제되는 턴 이후에는 표시를 제거
- **금수 안내/시각화 시**
  - 실제 금수뿐 아니라 거짓 금수(실제로는 금수가 아닌데 금수처럼 보이는 자리)도 판별하여 안내해야 함

---

## 🏁 26주형(렌주 오프닝 룰) 설명 및 적용 예시

- **26주형**은 렌주에서 처음 세 수의 패턴을 의미합니다.
- 렌주 오프닝 룰에서는 다음과 같이 진행됩니다:
  1. 흑의 첫 수는 반드시 천원(가운데)에 둡니다.
  2. 백의 두 번째 수는 천원과 인접한 3x3 정사각형(한 칸 이내)에만 둘 수 있습니다.
  3. 흑의 세 번째 수는 천원으로부터 두 칸 떨어진 5x5 정사각형(중심에서 상하좌우 2칸 이내)에만 둘 수 있습니다.
- 이때 흑이 천원으로부터 두 칸 떨어진 5x5 범위에 3번째 수를 놓는 패턴을 **주형**이라고 하며, 백이 천원과 바로 가로세로로 접하게 막는 것을 **직접막기(직접주형)**, 대각선으로 막는 것을 **간접막기(간접주형)**라고 합니다.
- 대칭을 제외하면 직접주형/간접주형 각각 13가지, 총 26가지 패턴이 존재합니다.
- 렌주 오프닝 룰에서는 반드시 26주형 중 하나로 시작해야 합니다.

### first_move_restrict 예시

```json
"first_move_restrict": ["center_only", "area3x3", "area5x5"]
```

- 1수: 중앙, 2수: 3x3 이내, 3수: 5x5 이내로 제한하여 26주형을 구현할 수 있습니다. 

---

## 📦 시스템 기본 룰셋

### 1. 일반룰 (장목 무효, 삼삼 금수, 사사 금수 없음)
- 장목(6목 이상)은 무효(승리 아님), 삼삼 금수(흑/백 모두), 사사 금수 없음, 초반 제한 없음

```json
{
  "name": "일반룰",
  "overline": { "black": "invalid", "white": "invalid" },
  "double_three": { "black": true, "white": true },
  "double_four": { "black": false, "white": false },
  "forbidden_action": "block",
  "swap_rule": "none",
  "first_move_restrict": "none",
  "board_size": 15,
  "allow_draw": true
}
```

---

### 2. 프로룰 (프로 오목)
- 금수 없음, 장목 허용, 초반 3수 제한(1수: 중앙, 2수: 제한 없음, 3수: 5x5 이내)

```json
{
  "name": "프로룰",
  "overline": { "black": "win", "white": "win" },
  "double_three": { "black": false, "white": false },
  "double_four": { "black": false, "white": false },
  "forbidden_action": "block",
  "swap_rule": "none",
  "first_move_restrict": ["center_only", "none", "area5x5"],
  "board_size": 15,
  "allow_draw": true
}
```

### 3. 롱프로룰 (롱프로 오목)
- 금수 없음, 장목 허용, 초반 3수 제한(1수: 중앙, 2수: 제한 없음, 3수: 7x7 이내)

```json
{
  "name": "롱프로룰",
  "overline": { "black": "win", "white": "win" },
  "double_three": { "black": false, "white": false },
  "double_four": { "black": false, "white": false },
  "forbidden_action": "block",
  "swap_rule": "none",
  "first_move_restrict": ["center_only", "none", "area7x7"],
  "board_size": 15,
  "allow_draw": true
}
```

---

### 4. 렌주룰 (렌주 공식룰)
- 흑 금수(삼삼/사사/장목), 백은 장목 허용, 26주형 오프닝(1수: 중앙, 2수: 3x3 이내, 3수: 5x5 이내)

```json
{
  "name": "렌주룰",
  "overline": { "black": "forbidden", "white": "win" },
  "double_three": { "black": true, "white": false },
  "double_four": { "black": true, "white": false },
  "forbidden_action": "block",
  "swap_rule": "none",
  "first_move_restrict": ["center_only", "area3x3", "area5x5"],
  "board_size": 15,
  "allow_draw": true
}
```

---

## 🛠️ 명령어 파라미터 및 룰셋 사용 가이드

### 1. 룰 요소별 명령어 파라미터 매핑

- 각 요소는 명령어 파라미터로 직접 지정할 수 있습니다.
- 예시:
  - `board_size` → `--board-size=15`
  - `forbidden_action` → `--forbidden-action=block`
  - `swap_rule` → `--swap-rule=swap2`
  - `first_move_restrict` → `--first-move-restrict=center_only,none,area5x5`

### 2. 룰셋 선택 및 오버라이드

- 시스템 기본 룰셋(예: 일반룰, 프로룰, 롱프로룰, 렌주룰)은 아래와 같이 선택할 수 있습니다.
  - `--rule-set=프로룰`
- 선택한 룰셋의 설정값을 일부만 오버라이드(덮어쓰기)할 수 있습니다.
  - 예: `--rule-set=프로룰 --forbidden-action=lose`
    - 프로룰의 모든 설정을 기본값으로 사용하되, forbidden_action만 lose로 변경

### 3. 색상별 옵션(예: double_three, overline 등) 지정 방법

- 색상별 옵션이 있는 경우, 파라미터명 뒤에 언더스코어와 색상명을 붙여 지정합니다.
  - 예: `--double-three_black=true --double-three_white=false`
  - 예: `--overline_black=forbidden --overline_white=win`
- 여러 색상에 대해 동시에 지정하려면 각각 별도 파라미터로 나열합니다.

### 4. 배열(리스트) 옵션 지정 방법

- 배열로 지정하는 옵션(예: first_move_restrict)은 콤마(,)로 구분하여 나열합니다.
  - 예: `--first-move-restrict=center_only,none,area5x5`

### 5. 전체 예시

- 기본 룰셋만 사용:
  - `--rule-set=렌주룰`
- 룰셋 + 일부 옵션 오버라이드:
  - `--rule-set=프로룰 --forbidden-action=lose --double-three_black=true`
- 모든 옵션 직접 지정:
  - `--board-size=15 --overline_black=forbidden --overline_white=win --double-three_black=true --double-three_white=false --first-move-restrict=center_only,none,area5x5`

### 6. 권장 파라미터 네이밍 규칙

- 소문자, 하이픈(-) 구분, 색상별 옵션은 언더스코어(_)로 구분
- 예: `--double-three_black`, `--overline_white`, `--first-move-restrict`

### 7. 구현 시 참고사항

- 명령어 파라미터로 지정된 값이 있으면 해당 값이 룰셋의 기본값을 덮어씀(오버라이드)
- 색상별 옵션이 없는 경우(예: board_size)는 단일 파라미터로 지정
- 배열 옵션은 콤마(,)로 구분하여 입력받아 리스트로 변환
- 잘못된 파라미터 입력 시 사용자에게 안내 필요 

---

## 🚦 시스템 적용 흐름

### 1. 기본 룰셋의 저장 및 관리
- 모든 시스템 기본 룰셋(일반룰, 프로룰, 롱프로룰, 렌주룰 등)은 `omok/constants.py`에 JSON 또는 딕셔너리 형태로 저장되어 관리됩니다.
- 예시: `DEFAULT_RULE_SETS = { "일반룰": {...}, "프로룰": {...}, ... }`

### 2. schedule-rooms.json에서 룰셋 선택
- 각 방(채널)별 오목 설정은 `schedule-rooms.json`에 저장됩니다.
- 오목 설정에서 사용할 룰셋을 기본 룰셋 중에서 선택할 수 있습니다.
- 예시:
```json
{
  "omok": {
    "rule_set": "프로룰"
  }
}
```
- 해당 방에서 오목 게임을 시작하면 지정된 룰셋이 기본값으로 적용됩니다.

### 3. 명령어 프롬프트에서 세부 설정 오버라이드
- 사용자는 명령어 프롬프트(예: 채팅 명령어, CLI 등)를 통해 룰셋의 세부 설정을 자유롭게 변경(오버라이드)할 수 있습니다.
- 예시:
  - `--rule-set=프로룰 --forbidden-action=lose --double-three_black=true`
- 이 경우, 프로룰의 모든 설정을 기본값으로 사용하되, forbidden_action과 double_three_black만 사용자가 지정한 값으로 덮어씁니다.

### 4. 적용 우선순위
1. **명령어 파라미터**: 사용자가 직접 지정한 값이 최우선으로 적용됩니다.
2. **schedule-rooms.json**: 방(채널) 설정에서 지정한 룰셋이 그 다음으로 적용됩니다.
3. **omok/constants.py**: 시스템에 정의된 기본 룰셋이 최종 기본값으로 사용됩니다. 

---

## 🧩 파이썬/LLM 통합 룰 함수 설계 가이드

### 목적
- 각 룰 요소별 파이썬 함수에 실제 판별 로직과 LLM 프롬프트용 자연어 설명을 함께 포함하여, 파이썬 AI와 LLM AI가 완전히 동일한 룰 인식을 갖도록 설계합니다.

### 설계 패턴
- 각 함수에 `as_prompt=True`(또는 유사 파라미터)를 넘기면 LLM에게 전달할 자연어 설명을 반환합니다.
- 실제 판별 시에는 as_prompt=False(기본값)로 동작합니다.

#### 예시 코드

```python
# 삼삼 금수 판별 함수 예시
def is_double_three(board, x, y, color, as_prompt=False):
    """
    삼삼 금수 판별 함수
    - 실제 판별: (board, x, y, color) 기준으로 삼삼 금수 여부 반환
    - as_prompt=True: LLM 프롬프트용 설명 반환
    """
    prompt = (
        "삼삼 금수란, 한 수로 열린 3(양쪽이 막히지 않은 3목)을 두 개 이상 동시에 만드는 경우를 말합니다. "
        "이 룰에서는 흑과 백 모두 삼삼 금수가 금지되어 있습니다. "
        "따라서 한 번의 착수로 열린 3을 두 개 이상 만들면 해당 수는 불법수(금수)입니다."
    )
    if as_prompt:
        return prompt
    # 실제 판별 로직 구현
    # ...
    return is_forbidden
```

#### LLM 프롬프트 자동 생성 예시

```python
llm_prompts = [
    is_double_three(as_prompt=True),
    is_overline(as_prompt=True),
    is_double_four(as_prompt=True),
    # ...
]
system_prompt = "\n\n".join(llm_prompts)
```

### 장점
- 파이썬 AI와 LLM AI가 완전히 동일한 룰 인식/설명을 공유
- 룰 변경 시 한 곳만 수정하면 됨(DRY)
- LLM 프롬프트 자동화 및 일관성 유지

### 구현 시 권장 사항
- 각 함수에 자연어 설명을 명확하고 구체적으로 작성
- as_prompt=True로 호출 시 LLM이 이해할 수 있는 문장으로 반환
- 실제 판별 로직과 설명이 항상 동기화되도록 관리

---

## 🤖 [AI 설계 및 개선 원칙]

- AI(파이썬/LLM)는 반드시 정해진 rule(금수, 삼삼, 장목 등)을 엄격히 지키는 범위 내에서 승리 전략을 실행해야 함
- 파이썬 AI와 LLM AI 모두 동일한 룰 설명/판별 로직을 공유하며, 프롬프트도 자동 생성되도록 설계해야 함
- rule_engine.py의 판별 함수(as_prompt=True 지원)와 룰셋 구조를 활용해 LLM 프롬프트를 자동 생성/동기화
- AI는 착수 후보 선정 단계에서 금수/삼삼/장목 등 금지 규칙을 반드시 미리 체크하고, 금수 자리는 후보에서 제외해야 함
- 실제 착수 처리 단계(place_stone)에서도 최종적으로 rule을 한 번 더 검증하여 이중 안전장치 적용
- rule 지키는 로직이 완벽히 구현된 후, AI의 승리 전략(공격/수비/패턴 인식 등) 고도화가 다음 과제임
- LLM AI도 동일하게 rule 설명/판별/전략 프롬프트가 자동 반영되도록 일관성 유지
- 향후 AI 개선 방향: ① 룰 체크 완성 → ② 전략 고도화(공격/수비/패턴/학습 등)

---

## 🟦 오목판 크기(board_size) 동적 지원 가이드

### 1. 기본값 및 동적 지정
- 오목판(board)의 기본 크기는 15x15로 설정합니다.
- 사용자는 명령어 파라미터로 `--board-size=13`, `--board-size=15`, `--board-size=19` 등 원하는 크기를 지정할 수 있습니다.

### 2. 엔진/로직 적용
- board 객체 생성, 모든 판별 함수, 영역 제한, 승리 판정 등에서 board_size를 동적으로 사용해야 합니다.
- 하드코딩된 크기(예: 15, 7, 0~14 등)는 사용하지 않고, 항상 board_size를 인자로 넘기거나 board 객체에서 동적으로 참조해야 합니다.

### 3. 이미지 생성 연동
- 오목판 이미지를 생성할 때도 board_size에 맞게 13x13, 15x15, 19x19 등으로 정확히 그려지도록 구현해야 합니다.
- 이미지 렌더러/생성 함수에도 board_size를 반드시 인자로 전달해야 합니다.

### 4. 명령어 파라미터 예시
- 기본: 15x15 (별도 지정 없을 때)
- 13x13로 변경: `--board-size=13`
- 19x19로 변경: `--board-size=19`

### 5. 테스트 방법
- 명령어 파라미터로 board_size를 바꿔가며 실제 오목판 생성, 착수, 금수, 승리 판정, 이미지 생성이 모두 정상 동작하는지 확인합니다.
- first_move_restrict 등 영역 제한도 board_size에 따라 자동 계산되는지 반드시 검증합니다.

### 6. 구현 시 권장 사항
- board_size를 모든 함수에 명시적으로 넘기거나, board 객체에서 동적으로 참조하는 습관을 들이세요.
- 다양한 크기의 오목판을 완벽하게 지원할 수 있도록, 모든 기능이 board_size에 의존하도록 설계하세요.

---

## 🚧 [TODO] 아직 미구현/스텁 상태인 룰 요소 및 구현 메모

- **forbidden_action**: 금수 착수 시 실제 처리 방식(lose, block, warning 등)은 엔진/게임 흐름에서 별도 구현 필요. 현재는 금수 여부/사유만 반환.
- **swap_rule**: swap, swap2, swap3 등 오프닝 특수 룰은 별도 착수 제한/교환 로직 필요. (아직 미구현)
- **first_move_restrict**: 첫수(또는 초반 몇 수)에 대한 위치 제한 로직(예: center_only, area5x5 등) 별도 구현 필요. (아직 미구현)
- **allow_draw**: 무승부 허용 여부는 실제 게임 종료/판정 로직에서 반영 필요. (아직 미구현)
- **기타 확장 요소**: forbidden_point, forbidden_area 등 룰셋에 따라 특정 좌표/영역 금수 등은 필요시 추가 구현.

> 위 항목들은 RULE_ELEMENTS.md 명세에는 정의되어 있으나, 실제 엔진/게임 흐름에서의 구현이 아직 완료되지 않았거나 스텁 상태임. 향후 구현 및 테스트 필요.

---

## �� [AI 설계 및 개선 원칙]

- OMOK_SYSTEM_PROMPT(기본 프롬프트)는 전략 명세의 표준이지만, 실제 LLM에 전달되는 system_prompt는 상황/실험/운영 목적에 따라 별도 수정, 확장, 커스터마이즈가 가능함. (예: 대회/연구/특수 모드 등)
- 프롬프트 커스터마이즈 시에도 반드시 룰/전략/설명 동기화 원칙을 지켜야 하며, 코드와 명세의 일관성을 유지해야 함.

--- 