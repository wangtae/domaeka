from .omok_globals import omok_sessions, reset_omok_timeout, clear_omok_timeout
from core.utils.send_message import send_message_response
from games.omok.constants import PIECES
from games.omok.utils.send_image_service import send_omok_board_image
from games.omok.engine.ai_engine import choose_swap_color
from .coord_utils import parse_move_coord, is_valid_coord
from core.logger import logger
from games.omok.utils.omok_debug import log_board_state
from games.omok.utils.piece_utils import to_internal_piece
import traceback

async def handle_swap_move(command, context):
    session = omok_sessions.get(context["channel_id"])
    if not session:
        await send_message_response(context, "진행 중인 게임이 없습니다.")
        return []
    if session.swap_rule not in ("swap1", "swap2"):
        return []
    # 오프닝 3수(placing_1~3)만 opening_player만 둘 수 있음
    if session.swap_stage in ("placing_1", "placing_2", "placing_3"):
        user_id = context.get("userHash") or context.get("user_hash")
        if not user_id:
            await send_message_response(context, "유저 정보가 올바르지 않습니다. (userHash 누락)")
            return []
        opening_uid = session.opening_player.get("user_id")
        # user_id(고유값)로만 체크
        if user_id != opening_uid:
            await send_message_response(context, f"오프닝 3수는 {session.opening_player['user_name']}님만 둘 수 있습니다.")
            return []
    return await process_swap_opening_step(session, command, context)

async def process_swap_opening_step(session, command, context):
    # 상태 진입 시점에 모든 핵심 필드 상세 로그
    logger.info(f"[OMOK][SWAP][상태체크] session={session}, board_size={getattr(session, 'board_size', None)}, board type={type(getattr(session, 'board', None))}, swap_moves={getattr(session, 'swap_moves', None)}, swap_stage={getattr(session, 'swap_stage', None)}, state={getattr(session, 'state', None)}")
    if hasattr(session, 'board') and isinstance(session.board, list):
        for idx, row in enumerate(session.board):
            logger.info(f"[OMOK][SWAP][상태체크] board[{idx}] type={type(row)}, value={row}")
    log_board_state(session, context, tag="swap_opening_step 진입")
    move_timeout = session.parameters.get('move_timeout_seconds', 30)
    board_size = session.board_size
    # board 전체 무결성 체크 및 복구
    if session.board is None or not isinstance(session.board, list) or len(session.board) != board_size:
        logger.error(f"[OMOK][ERROR][SWAP] session.board가 None이거나 잘못됨: {session.board}")
        log_board_state(session, context, tag="swap_opening_step board None 복구")
        session.board = [[None for _ in range(board_size)] for _ in range(board_size)]
        await send_message_response(context, "오목판 데이터 오류가 감지되어 복구했습니다. 다시 시도해 주세요.")
        reset_omok_timeout(context, move_timeout)
        return []
    for idx, row in enumerate(session.board):
        if row is None or len(row) != board_size:
            logger.error(f"[OMOK][ERROR][SWAP] board[{idx}]가 None이거나 길이 불일치: {row}")
            log_board_state(session, context, tag=f"swap_opening_step row None 복구 idx={idx}")
            session.board[idx] = [None for _ in range(board_size)]
            await send_message_response(context, f"오목판 데이터 오류가 감지되어 복구했습니다. (행 {idx}) 다시 시도해 주세요.")
            reset_omok_timeout(context, move_timeout)
            return []
    try:
        x, y = parse_move_coord(command, board_size)
        logger.debug(f"[OMOK][DEBUG][SWAP] swap_stage={session.swap_stage}, board_size={board_size}, board 상태: {session.board}")
        for idx, row in enumerate(session.board):
            if row is None or len(row) != board_size:
                logger.error(f"[OMOK][ERROR][SWAP] board[{idx}]가 None이거나 길이 불일치: {row}")
                log_board_state(session, context, tag=f"swap_opening_step try row None idx={idx}")
                await send_message_response(context, f"오목판 데이터 오류가 발생했습니다. 관리자에게 문의해 주세요. (행 {idx})")
                return []
        if not is_valid_coord(x, y, board_size):
            await send_message_response(context, f"좌표가 오목판 범위를 벗어났습니다. (A-{chr(ord('A')+board_size-1)}, 1-{board_size})")
            return []
        # 중복 착수 이중 체크: board와 swap_moves 모두
        if session.board[y][x] is not None or any((mx == x and my == y) for mx, my, _ in session.swap_moves):
            await send_message_response(context, "이미 돌이 놓인 위치입니다. 다른 위치를 선택해 주세요.")
            return []
    except ValueError as e:
        await send_message_response(context, f"⚠️ {str(e)}")
        return []

    idx_before = len(session.swap_moves)
    color = 'black' if idx_before in (0, 2) else 'white'
    session.board[y][x] = to_internal_piece(color)
    session.swap_moves.append((x, y, color))
    idx = len(session.swap_moves)

    if idx == 1:
        await send_omok_board_image(
            board=session.board,
            context=context,
            last_move=(x, y),
            message_text=f"{session.opening_player['user_name']}님이 계속 착수해 주세요. (2번째 수)",
            session=session
        )
        reset_omok_timeout(context, move_timeout)
        return []
    elif idx == 2:
        await send_omok_board_image(
            board=session.board,
            context=context,
            last_move=(x, y),
            message_text=f"{session.opening_player['user_name']}님이 마지막 착수를 해주세요. (3번째 수)",
            session=session
        )
        reset_omok_timeout(context, move_timeout)
        return []
    elif idx == 3:
        await send_omok_board_image(
            board=session.board,
            context=context,
            last_move=(x, y),
            message_text=(
                f"3수 착수 완료!\n\n" +
                ("이제 상대가 돌 색깔을 선택해 주세요.\n\n'흑' 또는 '백'을 입력해 주세요." if session.swap_rule == "swap1" else "이제 상대가 '흑', '백' 또는 '추가착수'를 입력해 주세요.")
            ),
            session=session
        )
        session.swap_stage = "choose_action" if session.swap_rule == "swap2" else "choose_color"
        if session.swap_rule == "swap1" and session.ai_level:
            session.swap_stage = "choose_color"
            ai_choice = choose_swap_color(session.board, session.swap_moves, session, context)
            await handle_swap_color_choice('흑' if ai_choice == 'black' else '백', context)
        reset_omok_timeout(context, move_timeout)
        return []
    # swap2에서 추가착수 단계
    if session.swap_rule == "swap2" and session.swap_stage in ("placing_4", "placing_5"):
        color = 'black' if idx in (3, 5) else 'white'
        session.board[y][x] = to_internal_piece(color)
        session.swap_moves.append((x, y, color))
        logger.debug(f"[OMOK][DEBUG][SWAP] 추가착수 후 board 상태: {session.board}")
        log_board_state(session, context, tag="swap_opening_step 추가착수 후")
        for idx2, row in enumerate(session.board):
            if row is None or len(row) != board_size:
                logger.error(f"[OMOK][ERROR][SWAP] (추가착수 후) board[{idx2}]가 None이거나 길이 불일치: {row}")
                log_board_state(session, context, tag=f"swap_opening_step 추가착수 후 row None idx={idx2}")
        if session.swap_stage == "placing_4":
            await send_omok_board_image(
                board=session.board,
                context=context,
                last_move=(x, y),
                message_text=f"{session.opening_player['user_name']}님이 계속 추가 착수해 주세요. (5번째 수)",
                session=session
            )
            session.swap_stage = "placing_5"
        elif session.swap_stage == "placing_5":
            await send_omok_board_image(
                board=session.board,
                context=context,
                last_move=(x, y),
                message_text=f"6수 착수 완료!\n\n이제 최초 플레이어가 돌 색깔을 선택해 주세요.\n\n'흑' 또는 '백'을 입력해 주세요.",
                session=session
            )
            session.swap_stage = "choose_color"
        reset_omok_timeout(context, move_timeout)
        return []
    # 그 외(색/스왑 선택 등)는 별도 핸들러에서 처리
    await send_message_response(context, "현재는 착수 단계가 아닙니다. 색 또는 스왑 선택을 입력해 주세요.")
    reset_omok_timeout(context, move_timeout)
    return []

async def handle_swap_action_choice(command, context):
    session = omok_sessions.get(context["channel_id"])
    if not session or session.swap_rule != "swap2" or session.swap_stage != "choose_action":
        await send_message_response(context, "현재 스왑/추가착수 선택 단계가 아닙니다.")
        return []
    action = command.strip()
    move_timeout = session.parameters.get('move_timeout_seconds', 30)
    if action not in ("흑", "백", "추가착수"):
        await send_message_response(context, "'흑', '백' 또는 '추가착수'만 입력해 주세요.")
        reset_omok_timeout(context, move_timeout)
        return []
    if action in ("흑", "백"):
        # 색 선택 분기 재사용
        return await handle_swap_color_choice(action, context)
    else:
        session.swap_stage = "placing_4"
        await send_message_response(context, "4번째 수를 착수해 주세요.")
        reset_omok_timeout(context, move_timeout)
        return []

def apply_swap_moves_to_board(session):
    # board를 빈 상태로 초기화
    session.board = [[None for _ in range(session.board_size)] for _ in range(session.board_size)]
    session.move_history = []
    for x, y, color in session.swap_moves:
        session.board[y][x] = to_internal_piece(color)
        session.move_history.append((x, y, to_internal_piece(color)))

async def handle_swap_color_choice(command, context):
    session = omok_sessions.get(context["channel_id"])
    if not session or session.swap_stage != "choose_color":
        await send_message_response(context, "현재 색 선택 단계가 아닙니다.")
        return []
    # 색 선택은 color_chooser만 가능
    user_id = context.get("userHash") or context.get("user_hash")
    if not user_id:
        await send_message_response(context, "유저 정보가 올바르지 않습니다. (userHash 누락)")
        return []
    if user_id != session.color_chooser.get("user_id"):
        await send_message_response(context, f"색 선택은 {session.color_chooser['user_name']}님만 할 수 있습니다.")
        return []
    color_input = command.strip()
    move_timeout = session.parameters.get('move_timeout_seconds', 30)
    if color_input not in ("흑", "백"):
        await send_message_response(context, "'흑' 또는 '백'만 입력해 주세요.")
        reset_omok_timeout(context, move_timeout)
        return []
    chosen_color = "black" if color_input == "흑" else "white"
    player1 = session.player1
    player2 = session.player2 if session.player2 else {"user_id": "AI", "user_name": "AI"}
    # opening_player가 player1인지 player2인지에 따라 매핑
    if session.opening_player == player1:
        if chosen_color == "black":
            session.player1_color = "black"
            session.player2_color = "white"
            session.turn = "black"
        else:
            session.player1_color = "white"
            session.player2_color = "black"
            session.turn = "black"
    else:
        if chosen_color == "black":
            session.player2_color = "black"
            session.player1_color = "white"
            session.turn = "black"
        else:
            session.player2_color = "white"
            session.player1_color = "black"
            session.turn = "black"
    # board, move_history 동기화
    apply_swap_moves_to_board(session)
    # swap_stage → normal, 일반룰로 전환
    session.swap_stage = "normal"
    session.state = "playing"
    await send_message_response(context, "돌 색이 확정되었습니다! 흑(●)이 먼저 착수해 주세요.")
    clear_omok_timeout(context)
    return [] 