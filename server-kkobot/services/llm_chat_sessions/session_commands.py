"""
세션 관련 명령어 처리 모듈
- 채팅 세션 시작/종료/연장 명령어 처리 
- 설정 및 권한 확인
"""
import time
from core.logger import logger
from core.utils.send_message import send_message_response
from core.utils.auth_utils import is_admin
import core.globals as g

# session_manager.py에서 구현된 함수들 import
from core.sessions import session_manager


async def handle_session_command(context, command_type):
    """세션 관련 명령어 처리"""
    bot_name = context.get("bot_name", "")
    channel_id = context.get("channel_id")
    room = context.get("room")
    sender = context.get("sender")
    user_hash = context.get("user_hash")

    # 기본 컨텍스트 확인 - 간소화
    if not channel_id or not user_hash or not sender:
        logger.error(f"[세션명령어] 컨텍스트 불완전 → channel_id={channel_id}, user_hash={user_hash}, sender={sender}")
        context = {
            'bot_name': bot_name,
            'channel_id': channel_id,
            'room': room
        }
        await send_message_response(context, "⚠️ 세션 명령을 처리할 수 없습니다. 잠시 후 다시 시도해주세요.")
        return False

    # 방 설정 확인
    schedule_data = g.schedule_rooms.get(bot_name, {}).get(str(channel_id), {})
    chat_sessions_config = schedule_data.get("chat_sessions", {})

    # 채팅 세션 기능 자체가 비활성화된 경우
    if not chat_sessions_config:
        await send_message_response(context, "⚠️ 이 채팅방에서는 채팅 세션 기능이 지원되지 않습니다.")
        return False

    logger.info(f"[세션명령어] 처리 시작 → 명령: {command_type}, 채널: {channel_id}, 사용자: {sender}")

    # 현재 활성화된 세션 확인 (개인 및 그룹)
    existing_personal_session = session_manager.get_active_session(user_hash, None)
    existing_group_session = session_manager.get_active_session(None, channel_id)

    # 명령어가 세션 종료가 아닌데 이미 활성화된 세션이 있는 경우 강화된 체크
    if command_type != "end_chat" and command_type == "start_private_chat" and existing_group_session:
        session_type = "그룹"
        await send_message_response(context,
                                        f"이미 진행 중인 {session_type} 채팅 세션이 있습니다. 먼저 '# 채팅종료'로 기존 세션을 종료해주세요.")
        return False
    elif command_type != "end_chat" and command_type == "start_group_chat" and existing_personal_session:
        session_type = "개인"
        await send_message_response(context,
                                        f"이미 진행 중인 {session_type} 채팅 세션이 있습니다. 먼저 '# 채팅종료'로 기존 세션을 종료해주세요.")
        return False

    try:
        if command_type == "start_private_chat":

            # 개인 채팅 세션 시작
            private_chat_settings = chat_sessions_config.get("private_chat", {})
            if not private_chat_settings.get("enabled", False):
                await send_message_response(context, "⚠️ 이 채팅방에서는 개인 채팅 기능이 활성화되어 있지 않습니다.")
                return False

            # 이미 활성화된 세션이 있는지 확인
            active_session = session_manager.get_active_session(user_hash, channel_id)
            if active_session:
                session_type = "개인" if active_session["type"] == "private" else "그룹"
                await send_message_response(context, f"이미 진행 중인 {session_type} 채팅 세션이 있습니다.")
                return False

            # 새 세션 생성
            result = await session_manager.create_private_session(
                user_hash, channel_id, sender, room, private_chat_settings
            )

            # 성공 메시지 또는 오류 메시지
            first_message = result["message"]
            await send_message_response(context, first_message)

            if result["success"]:
                timeout_minutes = private_chat_settings.get("session_timeout_minutes", 10)
                welcome_message = f"""📱 {sender}님과의 개인 채팅 세션이 시작되었습니다.
                
⏰ {timeout_minutes}분 동안 대화할 수 있으며, '# 시간연장' 명령으로 시간을 연장할 수 있습니다.
❌ 채팅을 종료하려면 '# 채팅종료'를 입력하세요.

지금부터 {sender}님과 1:1 대화를 시작합니다. 무엇을 도와드릴까요?"""
                await send_message_response(context, welcome_message)

            return result["success"]

        elif command_type == "start_group_chat":
            # 그룹 채팅 세션 시작
            group_chat_settings = chat_sessions_config.get("group_chat", {})
            if not group_chat_settings.get("enabled", False):
                await send_message_response(context, "⚠️ 이 채팅방에서는 그룹 채팅 기능이 활성화되어 있지 않습니다.")
                return False

            # 이미 활성화된 세션이 있는지 확인
            active_session = session_manager.get_active_session(None, channel_id)
            if active_session:
                session_type = "개인" if active_session["type"] == "private" else "그룹"
                await send_message_response(context, f"이미 진행 중인 {session_type} 채팅 세션이 있습니다.")
                return False

            # 새 세션 생성
            result = await session_manager.create_group_session(
                channel_id, user_hash, sender, room, group_chat_settings
            )

            # 성공 메시지 또는 오류 메시지
            first_message = result["message"]
            await send_message_response(context, first_message)

            if result["success"]:
                timeout_minutes = group_chat_settings.get("session_timeout_minutes", 15)
                welcome_message = f"""👨‍👩‍👧‍👦 {sender}님이 그룹 채팅 세션을 시작했습니다.
                
⏰ {timeout_minutes}분 동안 모든 분들과 대화할 수 있으며, '# 시간연장' 명령으로 시간을 연장할 수 있습니다.
❌ 채팅을 종료하려면 '# 채팅종료'를 입력하세요. (세션 시작자 또는 관리자만 가능)

이 채팅방의 모든 대화에 참여할 준비가 되었습니다. 무엇이든 물어보세요!"""
                await send_message_response(context, welcome_message)

            return result["success"]

        elif command_type == "extend_chat":
            # 채팅 세션 연장
            # 개인 세션 확인
            active_session = session_manager.get_active_session(user_hash, channel_id)

            # 개인 세션이 없으면 그룹 세션 확인
            if not active_session:
                active_session = session_manager.get_active_session(None, channel_id)

            if not active_session:
                await send_message_response(context, "현재 활성화된 채팅 세션이 없습니다.")
                return False

            # 그룹 세션이고 참여자인지 확인
            if active_session["type"] == "group":
                group_session = active_session["session"]
                if user_hash not in group_session.get("participants", {}):
                    group_session["participants"][user_hash] = sender
                    logger.info(f"[세션명령어] 그룹 세션 참여자 추가 → {sender}")

            # 연장 분 설정
            extension_minutes = 5

            # 세션 연장
            result = await session_manager.extend_session(active_session["type"], active_session["key"], extension_minutes)

            # 성공 메시지 또는 오류 메시지
            if result["success"]:
                remaining_minutes = int((result["new_end_time"] - time.time()) / 60)
                await send_message_response(context,
                                                f"✅ 채팅 세션이 {extension_minutes}분 연장되었습니다. 남은 시간: {remaining_minutes}분")
            else:
                await send_message_response(context, result["message"])

            return result["success"]

        elif command_type == "end_chat":
            # 채팅 세션 종료
            # 개인 세션 확인
            active_session = session_manager.get_active_session(user_hash, channel_id)

            # 개인 세션이 없으면 그룹 세션 확인
            if not active_session and channel_id:
                active_session = session_manager.get_active_session(None, channel_id)

            if not active_session:
                await send_message_response(context, "현재 활성화된 채팅 세션이 없습니다.")
                return False

            # 개인 세션은 본인만 종료 가능
            if active_session["type"] == "private" and active_session["key"] != user_hash:
                await send_message_response(context, "다른 사용자의 개인 채팅 세션은 종료할 수 없습니다.")
                return False

            # 그룹 세션은 시작자나 관리자만 종료 가능
            if active_session["type"] == "group":
                group_session = active_session["session"]
                initiator_hash = group_session.get("initiator_hash")

                if initiator_hash != user_hash and not is_admin(channel_id, user_hash):
                    await send_message_response(context,
                                                    "그룹 채팅 세션은 시작한 사용자나 관리자만 종료할 수 있습니다.")
                    return False

            # 세션 종료
            result = await session_manager.end_session(active_session["type"], active_session["key"], reason="사용자 요청")

            # 대화 참여 히스토리 초기화
            from core.conversation_joiner import message_history
            if channel_id in message_history:
                message_history[channel_id].clear()
                logger.info(f"[세션명령어] 채널 대화 참여 히스토리 초기화 → {channel_id}")

            # 세션 종료 메시지
            if result["success"]:
                metrics = result["metrics"]

                # 세션 시작 시간 가져오기
                start_time = active_session["session"]["start_time"] if "session" in active_session else active_session[
                    "start_time"]

                # 전체 시간을 초 단위로 계산
                total_seconds = int(time.time() - start_time)

                # 분과 초로 변환
                calculated_minutes = total_seconds // 60
                calculated_seconds = total_seconds % 60

                # 시간 표시 (항상 초 단위 포함)
                time_display = f"{calculated_minutes}분 {calculated_seconds}초"

                if active_session["type"] == "private":
                    farewell_message = f"""📱 {sender}님과의 개인 채팅 세션이 종료되었습니다.
                                        
⏱️ 총 대화 시간: {time_display}
💬 주고받은 메시지: {metrics["total_messages"]}개
        
다시 대화하고 싶으시면 '# 채팅시작' 명령어를 입력해주세요.
즐거운 시간이었습니다! 👋"""
                else:  # group
                    participants_count = len(
                        active_session["session"].get("participants", {})) if "session" in active_session else len(
                        active_session.get("participants", {}))
                    farewell_message = f"""👨‍👩‍👧‍👦 그룹 채팅 세션이 종료되었습니다.
                            
⏱️ 총 대화 시간: {time_display}
💬 주고받은 메시지: {metrics["total_messages"]}개
👥 참여한 사용자: {participants_count}명
        
다시 그룹 대화를 시작하려면 '# 그룹채팅시작' 명령어를 입력해주세요.
모두와 대화할 수 있어 즐거웠습니다! 👋"""

                await send_message_response(context, farewell_message)
            else:
                await send_message_response(context, result["message"])

            return result["success"]

        else:
            # 알 수 없는 명령어
            await send_message_response(context, "알 수 없는 채팅 세션 명령어입니다.")
            return False

    except Exception as e:
        logger.exception(f"[세션명령어] 처리 중 오류 발생: {str(e)}")
        await send_message_response(context, "⚠️ 세션 명령 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.")
        return False
