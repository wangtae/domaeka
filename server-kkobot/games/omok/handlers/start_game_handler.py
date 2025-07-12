# ✅ games/omok/handlers/start_game_handler.py
import re
from games.omok.constants import RULESETS, DEFAULT_RULE_OPTIONS, GAME_MODE_SELECTION, BOARD_STYLES, DEFAULT_BOARD_STYLE, GAME_SETTINGS, OMOK_USAGE_GUIDE
from games.omok.constants import RULE_DETAIL_KEYS
from games.omok.state.game_session import OmokSession
from games.omok.handlers.omok_globals import omok_sessions, reset_omok_timeout, clear_omok_timeout
from core.utils.send_message import send_message_response
from games.omok.utils.send_image_service import send_omok_board_image
import logging
import core.globals as g
from games.omok.utils.board_size import parse_board_size_param
from games.omok.engine.rule_engine import get_rule_guide
from games.omok.utils.user_utils import get_user_id, get_user_name
from games.omok.utils.piece_utils import to_internal_piece, normalize_color, normalize_board

logger = logging.getLogger(__name__)

# RULE_DETAIL_KEYS를 언더스코어 버전으로 변환
NORMALIZED_RULE_DETAIL_KEYS = [k.replace("-", "_") for k in RULE_DETAIL_KEYS]

def parse_options(text):
    """
    명령어 옵션을 파싱합니다.
    
    Args:
        text: 명령어 문자열
        
    Returns:
        dict: 파싱된 옵션들
    """
    options = {}
    logger.info(f"[OMOK] 파싱할 명령어: {text}")
    
    # 기본 명령어 제거
    text = text.replace("# 오목 시작", "").strip()
    logger.info(f"[OMOK] 기본 명령어 제거 후: {text}")
    
    # 옵션 파싱 (--key=value 형식)
    pattern = r'--(\w+)=([^\-\s][^\s]*)'
    matches = re.finditer(pattern, text)
    
    for match in matches:
        key, value = match.groups()
        options[key] = value
        logger.info(f"[OMOK] 파싱된 옵션: {key}={value}")
    
    return options


def normalize_keys(d):
    return {k.replace('-', '_'): v for k, v in d.items()}


def apply_rule_overrides(rule_opts, options):
    """
    하이픈(-)과 언더스코어(_) 혼용 옵션 키를 모두 지원하여 rule_opts를 options로 오버라이드합니다.
    (색상별/단일 옵션 모두 지원)
    """
    rule_opts = dict(rule_opts)  # 복사본 생성
    # 모든 옵션 키를 하이픈/언더스코어 변환 버전까지 포함하여 검사
    all_option_keys = set(options.keys())
    for raw_key in all_option_keys:
        key = raw_key.replace("-", "_")
        # overline_black 등
        if key.startswith("overline_"):
            color = key.split("_")[1]
            if "overline" not in rule_opts or not isinstance(rule_opts["overline"], dict):
                rule_opts["overline"] = {"black": "invalid", "white": "invalid"}
            rule_opts["overline"][color] = options[raw_key]
        elif key.startswith("double_three_"):
            color = key.split("_")[2]
            if "double_three" not in rule_opts or not isinstance(rule_opts["double_three"], dict):
                rule_opts["double_three"] = {"black": True, "white": True}
            val = options[raw_key]
            rule_opts["double_three"][color] = (val in [True, "true", "True", "1", 1] and str(val).lower() == "true")
        elif key.startswith("double_four_"):
            color = key.split("_")[2]
            if "double_four" not in rule_opts or not isinstance(rule_opts["double_four"], dict):
                rule_opts["double_four"] = {"black": False, "white": False}
            val = options[raw_key]
            rule_opts["double_four"][color] = (val in [True, "true", "True", "1", 1] and str(val).lower() == "true")
        elif key == "forbidden_action":
            rule_opts["forbidden_action"] = options[raw_key]
    return rule_opts


async def handle_start_command(prompt, parameters, context):
    """
    오목 게임을 시작하는 핸들러
    
    Args:
        prompt (str): 명령어 프롬프트
        parameters (dict): 명령어 파라미터
        context (dict): 메시지 컨텍스트
    """
    channel_id = context["channel_id"]
    sender = context.get("sender")
    bot_name = context.get("bot_name")
    channel_id_str = str(channel_id)
    room_config = g.schedule_rooms.get(bot_name, {}).get(channel_id_str, {})
    omok_settings = room_config.get("omok_settings", {})
    # 이미 세션이 존재하고 player2가 등록된 경우에만 중복 메시지 출력
    existing_session = omok_sessions.get(channel_id)
    if existing_session and existing_session.player2 is not None:
        await send_message_response(context, "이미 진행 중인 오목 게임이 있습니다. 먼저 종료해주세요.")
        return []

    try:
        # 명령어에서 파싱한 옵션과 전달받은 파라미터 병합 (key를 snake_case로 변환)
        options = normalize_keys(parse_options(prompt))
        parameters = normalize_keys(parameters)
        parameters.update(options)  # 명령어 파싱 결과가 항상 우선 적용되도록 순서 변경
        logger.info(f"[OMOK][DEBUG][BOARD_SIZE] 병합된 parameters: {parameters}")
        
        # ban-spot/visualize_forbidden 파라미터 일원화
        if str(parameters.get("ban_spot", parameters.get("visualize_forbidden", "false"))).lower() == "true":
            parameters["visualize_forbidden"] = True
        else:
            parameters["visualize_forbidden"] = False

        rule_name = parameters.get("rule", parameters.get("rule_set", "standard"))
        rule_opts = dict(RULESETS.get(rule_name, DEFAULT_RULE_OPTIONS))  # 복사본 생성
        # 오버라이드 적용 (DRY 함수 사용)
        rule_opts = apply_rule_overrides(rule_opts, parameters)
        rule_display_name = rule_opts.get("name", rule_name)
        ai_level = int(parameters.get("ai-level", 5))  # AI 대전 시 사용될 기본값
        debug_mode = str(parameters.get("debug", "false")).lower() == "true"
        user_mode = parameters.get("mode", "기본").strip().lower()
        if user_mode in ["고급", "advanced", "llm", "gpt"]:
            internal_mode = "llm"
        else:
            internal_mode = "hybrid"
        logger.info(f"[OMOK][DEBUG] omok_settings: {omok_settings}")
        logger.info(f"[OMOK][DEBUG] omok_settings['board_style']: {omok_settings.get('board_style')}")
        # board-style 처리
        board_style = None
        if '--board-style' in options:
            board_style = options['--board-style'].lower()
        elif 'board_style' in options:
            board_style = options['board_style'].lower()
        elif 'board_style' in omok_settings:
            board_style = omok_settings['board_style'].lower()
        else:
            board_style = DEFAULT_BOARD_STYLE
        logger.info(f"[OMOK][DEBUG] board_style 최종값: {board_style}")
        if board_style not in BOARD_STYLES:
            board_style = DEFAULT_BOARD_STYLE
            logger.warning(f"[OMOK] 잘못된 스타일 '{board_style}' 지정됨, 기본값으로 대체")
        style_info = BOARD_STYLES[board_style]
        rule_desc = []
        if not rule_opts.get("3-3", True): rule_desc.append("삼삼 금지")
        if not rule_opts.get("4-4", True): rule_desc.append("사사 금지")
        if rule_opts.get("overline", False): rule_desc.append("장목 허용")
        rule_str = get_rule_guide(rule_opts)
        game_id = f"{channel_id}-{get_user_name(context)}"
        # player1 정보는 항상 userHash(고유값), sender(닉네임)로만 할당 (userHash/user_hash 모두 지원)
        try:
            user_id = get_user_id(context)
        except ValueError as e:
            await send_message_response(context, str(e))
            return []
        user_name = get_user_name(context)
        player1 = {
            "user_id": user_id,
            "user_name": user_name
        }
        # player2는 유저 대전 대기 시 None, AI 대전 시 AI 정보
        player2 = None  # 항상 None으로 초기화 (모드 선택 전)
        if internal_mode == "ai":
            ai_level = int(parameters.get("ai_level", 5))
            player2 = {"user_id": "AI", "user_name": f"AI (레벨 {ai_level})"}
        turn = "black"  # 흑(player1)이 먼저 두도록
        logger.info(f"[OMOK][DEBUG] 오목 시작: player1={player1}, player2={player2}, state=init, channel_id={channel_id}")
        omok_settings = room_config.get("omok_settings", {})
        move_timeout = int(omok_settings.get("move_timeout_seconds", GAME_SETTINGS["move_timeout_seconds"]))        
        start_timeout = int(omok_settings.get("start_timeout_seconds", 60))
        board_size = parse_board_size_param(parameters)
        # ban_spot(금수 표시) 방별 설정 반영
        if "ban_spot" in omok_settings:
            parameters["visualize_forbidden"] = bool(omok_settings["ban_spot"])
        # board_style 방별 설정 반영 (명령어 파라미터가 없을 때만)
        if "board_style" in omok_settings and "board_style" not in parameters:
            parameters["board_style"] = omok_settings["board_style"]
        # 반드시 move_timeout 값을 parameters에 반영
        parameters["move_timeout_seconds"] = move_timeout
        logger.info(f"[OMOK][DEBUG][PARAMETERS] 세션 생성 직전 parameters: {parameters}")
        logger.info(f"[OMOK][DEBUG][BOARD_SIZE] parse_board_size_param 결과: {board_size}")
        forbidden_points = None
        if parameters["visualize_forbidden"]:
            from games.omok.engine.rule_engine import get_forbidden_points
            forbidden_points = get_forbidden_points(
                [[0 if v is None else v for v in row] for row in [[None]*board_size for _ in range(board_size)]],
                rule_opts,
                "black"
            )
        # swap_rule 파라미터가 있으면 rule_options에 반영
        swap_rule = (parameters.get("swap_rule") or "none").lower()
        logger.info(f"[OMOK][DEBUG] swap_rule 파싱 결과: {swap_rule}")
        if swap_rule in ("swap1", "swap2"):
            rule_opts = dict(rule_opts)
            rule_opts["swap_rule"] = swap_rule
            logger.info(f"[OMOK][DEBUG] rule_opts에 swap_rule 반영: {rule_opts['swap_rule']}")

        # player1-stone 파라미터 파싱 및 색상/턴 결정
        player1_stone = parameters.get("player1_stone", parameters.get("player1-stone", "")).lower()
        if player1_stone == "white":
            player1_color = "white"
            player2_color = "black"
            turn = "black"  # 흑(상대)이 먼저 두도록
        else:
            player1_color = "black"
            player2_color = "white"
            turn = "black"  # 흑(player1)이 먼저 두도록

        session = OmokSession(
            game_id=game_id,
            player1=player1,
            player2=player2,
            ai_level=ai_level if internal_mode == "ai" else None,
            rule=rule_name,
            rule_options=rule_opts,
            ai_mode=internal_mode,
            parameters=parameters,
            player1_color=player1_color,
            player2_color=player2_color
        )
        session.turn = turn
        omok_sessions[channel_id] = session
        logger.info(f"[OMOK][DEBUG][BOARD_SIZE] OmokSession 생성 후 session.board_size: {session.board_size}")
        context["parameters"] = {
            "debug": debug_mode,
            "mode": internal_mode,
            "board_style": board_style,
            "move_timeout_seconds": move_timeout,
            "start_timeout_seconds": start_timeout,
            "board_size": board_size,
            "swap_rule": swap_rule
        }
        # start_timeout 등록
        clear_omok_timeout(context)
        logger.info(f"[OMOK][DEBUG] 오목 시작: start_timeout 등록 시도: start_timeout={start_timeout}")
        reset_omok_timeout(context, start_timeout)
        logger.info(f"[OMOK][DEBUG] 오목 시작: start_timeout 등록 완료: start_timeout={start_timeout}")

        # 일반 오목은 기존 메시지
        await send_message_response(context, session.get_mode_selection_message())

        return []

    except Exception as e:
        logger.error(f"[OMOK] 게임 시작 처리 중 오류: {e}")
        await send_message_response(context, f"게임 시작 처리 중 오류가 발생했습니다: {e}")
        return []
