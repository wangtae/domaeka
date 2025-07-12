ALLOWED_BOARD_SIZES = [7, 9, 11, 13, 15, 17, 19]
BOARD_SIZE_DEFAULT = 15
ALPHABETS = [chr(i) for i in range(ord('A'), ord('A') + max(ALLOWED_BOARD_SIZES))]

# 오목 룰 요소별 DRY 구조 (RULE_ELEMENTS.md 적극 반영)
RULE_ELEMENTS = {
    "overline": {
        "desc": "장목(6목 이상)",
        "type": "per_color_action",
        "default": {"black": "invalid", "white": "invalid"},
        "actions": ["win", "invalid", "block"]
    },
    "double_three": {
        "desc": "삼삼 금수",
        "type": "per_color_bool",
        "default": {"black": True, "white": True}
    },
    "double_four": {
        "desc": "사사 금수",
        "type": "per_color_bool",
        "default": {"black": False, "white": False}
    },
    "forbidden_action": {
        "desc": "금수 착수 처리 방식",
        "type": "action",
        "default": "block",
        "actions": ["block", "lose", "warning"]
    },
    "swap_rule": {
        "desc": "초반 swap 룰",
        "type": "enum",
        "default": "none",
        "choices": ["none", "swap1", "swap2", "swap3"]
    },
    "first_move_restrict": {
        "desc": "초반 착수 제한",
        "type": "list",
        "default": ["none"],
        "choices": ["center_only", "none", "area5x5", "area7x7"]
    },
    "allow_draw": {
        "desc": "무승부 허용 여부",
        "type": "bool",
        "default": True
    }
}

# 룰 요소별 유저 표기 명칭
RULE_DISPLAY_NAMES = {
    "overline": "장목",
    "double_three": "삼삼",
    "double_four": "사사",
    "forbidden_action": "금수 처리",
    "swap_rule": "스왑룰",
    "first_move_restrict": "초반 착수 제한",
    "allow_draw": "무승부 허용"
}

# 값에 따른 유저 표기 포맷
RULE_VALUE_DISPLAY_FORMATS = {
    "overline": {
        "win": "승리",
        "invalid": "무효",
        "forbidden": "금지"
    },
    "double_three": {
        True: "금지",
        False: "허용"
    },
    "double_four": {
        True: "금지",
        False: "허용"
    },
    "forbidden_action": {
        "block": "착수 불가",
        "lose": "패배",
        "warning": "경고"
    },
    "swap_rule": {
        "none": "없음",
        "swap1": "스왑1",
        "swap2": "스왑2",
        "swap3": "스왑3"
    },
    "first_move_restrict": {
        "center_only": "중앙만 허용",
        "none": "제한 없음",
        "area5x5": "5x5 영역 제한",
        "area7x7": "7x7 영역 제한"
    },
    "allow_draw": {
        True: "허용",
        False: "불가"
    }
} 

RULESETS = {
    "freestyle": {
        "name": "프리스타일",
        "overline": {"black": "invalid", "white": "invalid"}, 
        "double_three": {"black": False, "white": False},
        "double_four": {"black": False, "white": False},
        "forbidden_action": "block",
        "swap_rule": "none",
        "first_move_restrict": ["none"],
        "board_size": 15,
        "allow_draw": True
    },
    "standard": {
        "name": "일반룰",
        "overline": {"black": "invalid", "white": "invalid"}, 
        "double_three": {"black": False, "white": False},
        "double_four": {"black": False, "white": False},
        "forbidden_action": "block",
        "swap_rule": "none",
        "first_move_restrict": ["none"],
        "board_size": 15,
        "allow_draw": True
    },
    "pro": {
        "name": "프로룰",
        "overline": {"black": "win", "white": "win"},
        "double_three": {"black": False, "white": False},
        "double_four": {"black": False, "white": False},
        "forbidden_action": "block",
        "swap_rule": "none",
        "first_move_restrict": ["center_only", "none", "area5x5"],
        "board_size": 15,
        "allow_draw": True
    },
    "longpro": {
        "name": "롱프로룰",
        "overline": {"black": "win", "white": "win"},
        "double_three": {"black": False, "white": False},
        "double_four": {"black": False, "white": False},
        "forbidden_action": "block",
        "swap_rule": "none",
        "first_move_restrict": ["center_only", "none", "area7x7"],
        "board_size": 15,
        "allow_draw": True
    },
    "renju": {
        "name": "렌주룰",
        "overline": {"black": "invalid", "white": "win"},
        "double_three": {"black": True, "white": False},
        "double_four": {"black": True, "white": False},
        "forbidden_action": "block",
        "swap_rule": "none",
        "first_move_restrict": ["none"],
        "board_size": 15,
        "allow_draw": True
    }
}

DEFAULT_RULE = "freestyle"
DEFAULT_RULE_OPTIONS = RULESETS[DEFAULT_RULE]

PIECES = {
    "black": "흑",
    "white": "백"
}

# 게임 모드 선택 관련 설정
GAME_MODE_SELECTION = {
    "message": "🎮 오목  선택해주세요:\n\n1️⃣ AI 대전\n2️⃣ 유저 대전\n\n'1' 또는 '2'를 입력해 주세요.",
    "choices": {
        "1": {
            "mode": "ai",
            "description": "AI와 1:1 대국"
        },
        "2": {
            "mode": "user",
            "description": "다른 사용자와 1:1 대국"
        }
    },
    "timeout_seconds": 30,
    "input_pattern": r"^[12]$"
}

# 게임 세션 상태
SESSION_STATES = {
    "WAITING_MODE_SELECTION": "waiting_mode_selection",  # 게임 모드 선택 대기
    "WAITING_PLAYER": "waiting_player",                 # 상대방 참여 대기
    "IN_GAME": "in_game",                              # 게임 진행 중
    "FINISHED": "finished"                             # 게임 종료
}

# 오목판 스타일 설정
BOARD_STYLES = {
    "classic": {
        "name": "클래식",
        "description": "플랫한 단색 배경과 단순한 원형 돌 디자인",
        "background_color": (240, 217, 181),  # 밝은 나무색(플랫)
        "grid_color": (0, 0, 0),
        "grid_alpha": 255,
        "stone_shadow": False,
        "flat_stone": True,
        "wood_texture": False
    },
    "wood": {
        "name": "고급 나무",
        "description": "자연스러운 나무 질감과 입체감 있는 3D 바둑돌",
        "background_color": (240, 217, 181),
        "grid_color": (0, 0, 0),
        "grid_alpha": 180,
        "stone_shadow": False,
        "flat_stone": False,
        "wood_texture": True
    }
}

# 기본 바둑판 스타일 (board-style)
DEFAULT_BOARD_STYLE = "wood"

# 게임 설정
GAME_SETTINGS = {
    "default_ai_mode": "hybrid",      # AI 모드 기본값 (hybrid 또는 llm)
    "default_ai_level": 5,            # AI 레벨 기본값 (1-10)
    "move_timeout_seconds": 300,       # 착수 제한 시간
    "board_size": BOARD_SIZE_DEFAULT,         # 바둑판 크기
    "default_board_style": DEFAULT_BOARD_STYLE  # 기본 바둑판 스타일
}

OMOK_USAGE_GUIDE = (
    '🏁 오목 게임 상세 사용법' + ('\u200b' * 500) + '''

[명령어]
- # 오목 시작 : 오목 게임을 시작합니다.
- # 오목 참여 : 유저 대전인 경우 다른 유저가 이 명령을 입력하면 대전에 참여 됩니다.
- # 오목 종료 : 오목 게임을 종료합니다. (승패 저장 안됨)

[명령어 파라메터]
- # 오목 시작 --rule-set=standard : standard, pro, longpro, renju 중 선택 (각각 일반룰, 프로룰 롱프로룰, 렌주룰을 의미, 기본값: standard)
- # 오목 시작 --board-size=15 : 7, 9, 11, 13, 15, 17, 19 (오목판 크기를 설정합니다. 기본값: 15)
- # 오목 시작 --style-wood : clasic, wood (오목판 종류를 설정합니다. 기본값: wood)
- # 오목 시작 --ban-spot : true (금수 지점을 표시합니다, 기본값: false)
- # 오목 시작 --mode=고급 : 기본, 고급 중 하나 선택 (기본은 알고리즘 AI이며 고급은 LLM 기반 AI 입니다. (기본값: 기본)

[룰셋 세부 설정용 명령어 파라메터]
- # 오목 시작 --overline_black=win --overline_white=invalid : 장목(6목 이상) 처리 방식(흑/백)
- # 오목 시작 --double-three_black=false --double-three_white=true : 삼삼 금수(흑/백)
- # 오목 시작 --double-four_black=false --double-four_white=true : 사사 금수(흑/백)
- # 오목 시작 --forbidden-action=block : 금수 착수 처리 방식(block/lose/warning)

※ 각 파라메터는 룰셋의 세부 규칙을 직접 변경할 때 사용합니다. 예) 흑만 삼삼 금수로 하고 싶으면 --double-three_black=true --double-three_white=false

※ 착수 타임아웃에 도달하면 패배 처리됩니다. 
※ AI의 실력은 계속 보강해 나갈 예정이며, AI의 레벨을 정할 수 있을지는 미확정입니다.
※ 현시점에서는 mode가 고급(LLM AI) 이라고 해서 AI 실력이 더 좋지는 않습니다. (미정)
※ 오목 게임의 기본 설정은 톡방마다 다르므로 톡방별 설정을 확인해주세요. (추후 지원)
※ 현재 선택된 게임의 룰에 대한 자세한 설명은 각 룰셋 선택 시 안내됩니다.
※ 일반룰(standard)만 테스트 완료되었으며, 나머지 룰셋은 테스트 중입니다.
※ 선택된 룰셋의 세부 룰를 변경할 수 있는 명령어 파라메터를 제공합니다. (일반 룰에서 흑만 삼삼 금수로 할지등)

현재 기본으로 제공되는 룰의 정의는 다음과 같습니다.

"standard": {
    "name": "일반룰",
    "overline": {"black": "invalid", "white": "invalid"}, 
    "double_three": {"black": True, "white": True},
    "double_four": {"black": False, "white": False},
    "forbidden_action": "block",
    "swap_rule": "none",
    "first_move_restrict": ["none"],
    "board_size": 15,
    "allow_draw": True
},
"pro": {
    "name": "프로룰",
    "overline": {"black": "win", "white": "win"},
    "double_three": {"black": False, "white": False},
    "double_four": {"black": False, "white": False},
    "forbidden_action": "block",
    "swap_rule": "none",
    "first_move_restrict": ["center_only", "none", "area5x5"],
    "board_size": 15,
    "allow_draw": True
},
"longpro": {
    "name": "롱프로룰",
    "overline": {"black": "win", "white": "win"},
    "double_three": {"black": False, "white": False},
    "double_four": {"black": False, "white": False},
    "forbidden_action": "block",
    "swap_rule": "none",
    "first_move_restrict": ["center_only", "none", "area7x7"],
    "board_size": 15,
    "allow_draw": True
},
"renju": {
    "name": "렌주룰",
    "overline": {"black": "invalid", "white": "win"},
    "double_three": {"black": True, "white": False},
    "double_four": {"black": True, "white": False},
    "forbidden_action": "block",
    "swap_rule": "none",
    "first_move_restrict": ["none"],
    "board_size": 15,
    "allow_draw": True
}
'''
)

# 오목 세부 룰 파라미터 키 목록
RULE_DETAIL_KEYS = [
    "overline_black", "overline_white",
    "double-three_black", "double-three_white",
    "double-four_black", "double-four_white",
    "forbidden-action"
]
