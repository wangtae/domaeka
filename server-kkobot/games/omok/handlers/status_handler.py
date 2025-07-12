from .omok_globals import omok_sessions
from core.utils.send_message import send_message_response
from games.omok.engine.ai_engine import format_move_history
from games.omok.utils.send_image_service import send_omok_board_image
from games.omok.constants import PIECES

async def handle_status_command(command, context):
    """
    현재 게임 상태와 마지막 착수 현황을 보여줍니다.
    """
    channel_id = context["channel_id"]
    session = omok_sessions.get(channel_id)
    
    if not session:
        await send_message_response(context, "진행 중인 게임이 없습니다.")
        return []
        
    # 마지막 착수 정보 가져오기
    last_move = session.get_last_move()
    last_move_text = ""
    
    if last_move:
        x, y = last_move
        coord = f"{chr(ord('A') + x)}{y + 1}"
        last_piece = PIECES['black' if session.turn == 'white' else 'white']
        last_move_text = f"마지막 착수: {coord} ({last_piece})\n"
    
    # 현재 차례 정보
    current_turn = "AI" if session.ai_level and session.turn == 'white' else (
        session.player1['user_name'] if session.turn == 'black' else 
        session.player2['user_name'] if session.player2 else "대기 중"
    )
    current_piece = PIECES[session.turn]
    
    # 디버그 모드 확인
    debug_mode = session.parameters.get("debug", False)
    
    # 상태 메시지 생성
    status_msg = (
        f"📋 현재 게임 현황\n\n"
        f"{last_move_text}"
        f"현재 차례: {current_turn} ({current_piece})\n"
    )
    
    if debug_mode:
        history_text = format_move_history(session.move_history)
        status_msg = f"{status_msg}\n📝 착수 현황:\n{history_text}"
    
    # 바둑판 이미지와 함께 현황 전송
    await send_omok_board_image(
        board=session.board,
        context=context,
        last_move=last_move,
        message_text=status_msg
    )
    
    return [] 