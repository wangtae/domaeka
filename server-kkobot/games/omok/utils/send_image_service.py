# server/games/omok/utils/send_image_service.py

from core.utils.send_message import send_message_response
from games.omok.utils.board_image_generator import generate_board_image
from games.omok.utils.restrict_area import get_restricted_area
from games.omok.utils.omok_debug import log_board_state


async def send_omok_board_image(board, context, last_move=None, message_text=None, session=None, forbidden_points=None):
    """
    오목판 이미지를 생성하고 전송합니다.
    
    Args:
        board: 현재 바둑판 상태
        context: 메시지 컨텍스트
        last_move: 마지막 착수 위치 (x, y)
        message_text: 함께 전송할 메시지
        session: 오목 게임 세션 (선택사항)
        forbidden_points: 금수 좌표 리스트 (옵션)
    """
    try:
        # 세션에서 스타일 정보 가져오기
        board_style = "classic"  # 기본값 설정
        if session and hasattr(session, 'parameters'):
            board_style = session.parameters.get("board_style", "classic")
        elif context and isinstance(context, dict):
            board_style = context.get("parameters", {}).get("board_style", "classic")
        
        # channel_id 추출
        channel_id = None
        if session and hasattr(session, 'game_id'):
            channel_id = getattr(session, 'game_id', None)
        elif context and isinstance(context, dict):
            channel_id = context.get("channel_id")
        
        # first_move_restrict 영역 계산
        restrict_areas = None
        restrict_type = None
        if session and hasattr(session, 'rule_options'):
            restrict_list = session.rule_options.get("first_move_restrict", [])
            board_size = len(board)
            move_number = len(session.move_history) + 1 if hasattr(session, 'move_history') else 1
            # 1수: center_only
            if move_number == 1 and "center_only" in restrict_list:
                restrict_areas = get_restricted_area(board_size, "center_only")
                restrict_type = "allowed"
            # 3수(흑): area5x5/area7x7(해당 영역 밖만 허용)
            elif move_number == 3:
                if "area5x5" in restrict_list:
                    restrict_areas = get_restricted_area(board_size, "area5x5")
                    restrict_type = "forbidden"
                elif "area7x7" in restrict_list:
                    restrict_areas = get_restricted_area(board_size, "area7x7")
                    restrict_type = "forbidden"
        

        # 이미지 생성
        image_data = await generate_board_image(
            board, last_move, board_style, channel_id=channel_id,
            forbidden_points=forbidden_points,
            restrict_areas=restrict_areas, restrict_type=restrict_type
        )
        
        # 이미지와 메시지를 리스트로 구성
        messages = []
        if message_text:
            messages.append(message_text)
        messages.append(f"IMAGE_BASE64:{image_data}")
        
        # 메시지 전송
        await send_message_response(context, messages)
        
    except Exception as e:
        error_msg = f"오목판 이미지 생성 중 오류가 발생했습니다: {str(e)}"
        await send_message_response(context, error_msg)
