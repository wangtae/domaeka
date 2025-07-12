"""
주기적인 유저 대화 요약을 발송하는 서비스 모듈입니다.
설정된 주기에 따라 채팅 순위를 기반으로 유저를 선택하고, 각 유저의 대화 요약을 생성하여 발송합니다.
"""

import random
import datetime
from collections import Counter
from typing import List, Dict, Any, Optional

from core import globals as g
from core.logger import logger
from services.chat_rank_service import get_raw_chat_ranking
from services.conversation_summary_service import handle_user_conversation_summary
from core.db_utils import send_message_response

async def send_periodic_user_summaries(context: Dict[str, Any]) -> List[str]:
    """
    주기적인 유저 대화 요약을 생성하고 발송합니다.
    context에서 설정값을 읽어와 동작합니다.
    """
    bot_name = context.get("bot_name")
    channel_id = context.get("channel_id")
    room_name = context.get("room")
    sender = context.get("sender", "Scheduler") # 스케줄러로 발송될 경우 sender 지정
    
    # 룸별 주기적 유저 요약 설정 가져오기
    room_settings = g.schedule_rooms.get(bot_name, {}).get(channel_id, {})
    periodic_summary_settings = room_settings.get("periodic_user_summary", {})

    enabled = periodic_summary_settings.get("enabled", False)
    if not enabled:
        logger.info(f"[주기적유저요약] {room_name} ({channel_id}) 방은 기능이 비활성화되어 있습니다.")
        return ["@no-reply"]

    period_minutes = periodic_summary_settings.get("period_minutes", 10080) # 기본값 7일
    user_selection_method = periodic_summary_settings.get("user_selection_method", "top_n")
    num_users_to_select = periodic_summary_settings.get("num_users_to_select", 3)
    message_delivery_method = periodic_summary_settings.get("message_delivery_method", "individual_messages")
    exclude_bots_from_ranking = periodic_summary_settings.get("exclude_bots_from_ranking", True)
    kakao_readmore_config = periodic_summary_settings.get("kakao_readmore", {"type": "lines", "value": 1})

    logger.info(f"[주기적유저요약] {room_name} ({channel_id}) 방 유저 요약 시작.")
    logger.debug(f"[주기적유저요약] 설정: {periodic_summary_settings}")

    # 명령어 정의에서 always_meaningful 값 가져오기
    command_info = context.get("command_info", {})
    always_meaningful_setting = command_info.get("always_meaningful", False) # 기본값 False

    # 1. 기간별 채팅 순위 raw 데이터 가져오기
    user_message_counts = await get_raw_chat_ranking(channel_id, period_minutes, always_meaningful_setting, exclude_bots_from_ranking, bot_name)
    
    if not user_message_counts:
        logger.info(f"[주기적유저요약] 최근 {period_minutes}분간 채팅 기록이 없어 요약을 생성하지 않습니다.")
        return [f"📊 최근 {period_minutes}분간 채팅 기록이 없습니다. 주기적 유저 요약을 생성할 수 없습니다."]

    if not user_message_counts:
        logger.info(f"[주기적유저요약] 유효한 유저가 없어 요약을 생성하지 않습니다.")
        return ["채팅 순위에 유효한 유저가 없어 주기적 유저 요약을 생성할 수 없습니다."]

    # 3. 유저 선택
    selected_users = []
    sorted_users = user_message_counts.most_common() # (user, count) 튜플 리스트

    if user_selection_method == "top_n":
        selected_users = [user for user, _ in sorted_users[:num_users_to_select]]
        logger.info(f"[주기적유저요약] 상위 {num_users_to_select}명 선택: {selected_users}")
    elif user_selection_method == "random_n":
        # 유저 목록이 num_users_to_select 보다 적으면 전부 선택
        if len(sorted_users) <= num_users_to_select:
            selected_users = [user for user, _ in sorted_users]
        else:
            selected_users = random.sample([user for user, _ in sorted_users], num_users_to_select)
        logger.info(f"[주기적유저요약] 랜덤 {num_users_to_select}명 선택: {selected_users}")
    else:
        logger.warning(f"[주기적유저요약] 알 수 없는 유저 선택 방법: {user_selection_method}. 상위 N명으로 기본값 처리.")
        selected_users = [user for user, _ in sorted_users[:num_users_to_select]]

    if not selected_users:
        logger.warning(f"[주기적유저요약] 선택된 유저가 없어 요약을 생성하지 않습니다.")
        return ["선택된 유저가 없어 주기적 유저 요약을 생성할 수 없습니다."]

    all_summaries: List[str] = []

    for user_name in selected_users:
        user_context = context.copy()
        user_context["sender"] = user_name # 요약할 유저를 sender로 설정 (handle_user_conversation_summary 내부 로직 활용)
        user_context["user_hash"] = await g.get_user_hash_by_name(g.db_pool, channel_id, user_name) # 유저 해시도 설정

        # handle_user_conversation_summary 함수는 prompt에 "--user-name" 파라미터를 기대합니다.
        # 이 경우에는 내부적으로 sender를 사용하도록 수정했으므로, 명시적으로 prompt를 전달할 필요는 없습니다.
        # 그러나, default_days 대신 period_minutes를 넘겨주기 위해 parameters를 구성해야 합니다.
        summary_parameters = {
            "recent-days": period_minutes / (60 * 24), # 분을 일수로 변환
            "user-name": user_name # 명시적으로 사용자 이름 전달 (fallback 로직에도 있지만, 정확성을 위해)
        }
        
        logger.info(f"[주기적유저요약] {user_name} 유저의 대화 요약 생성 시작 (기간: {period_minutes}분).")
        user_summary_result = await handle_user_conversation_summary(
            prompt=f"--user-name={user_name} --recent-days={period_minutes / (60 * 24)}",
            parameters=summary_parameters,
            received_message=user_context
        )
        
        if user_summary_result and user_summary_result != "@no-reply":
            # 카카오톡 더보기 적용 (1줄만 표시)
            formatted_summary = g.apply_kakao_readmore(user_summary_result, kakao_readmore_config.get("type", "lines"), kakao_readmore_config.get("value", 1))
            all_summaries.append(f"🏆 {user_name}님의 최근 대화 요약 ({period_minutes / (60 * 24):.0f}일)\n" + formatted_summary)
            logger.info(f"[주기적유저요약] {user_name} 유저 요약 생성 완료.")
        else:
            logger.warning(f"[주기적유저요약] {user_name} 유저 요약 생성 실패 또는 내용 없음.")

    if not all_summaries:
        logger.info(f"[주기적유저요약] 생성된 유저 요약 메시지가 없습니다.")
        return ["생성된 유저 요약 메시지가 없습니다."]

    # 헤더 메시지 생성
    period_days = int(period_minutes / (60 * 24))
    selection_method_text = "상위" if user_selection_method == "top_n" else "랜덤"
    header_message = (
        f"📊 최근 {period_days}일 채팅 순위 기반으로 {selection_method_text} {num_users_to_select}명에 대한 대화 요약입니다.\n" +
        "\u200b" * 500 + "\n\n" + "—" * 10
    )

    # 4. 메시지 발송 방식에 따라 최종 메시지 구성 및 발송
    if message_delivery_method == "individual_messages":
        logger.info(f"[주기적유저요약] 개별 메시지로 발송합니다.")
        # 헤더 메시지 먼저 발송
        await send_message_response(context, [header_message])
        
        for summary_msg in all_summaries:
            # 개별 메시지 발송 시 send_message_response 사용
            await send_message_response(context, [summary_msg])
        return ["@no-reply"] # send_message_response가 직접 메시지를 보내므로 여기서는 응답하지 않음
    elif message_delivery_method == "single_message":
        logger.info(f"[주기적유저요약] 단일 메시지로 합쳐서 발송합니다.")
        final_message = header_message + "\n\n" + "✨ 주기적 유저 대화 요약 리포트 ✨\n\n" + "\n\n" + "\n".join(all_summaries)
        final_message = g.apply_kakao_readmore(final_message, kakao_readmore_config.get("type", "lines"), kakao_readmore_config.get("value", 1))
        
        return [final_message]
    else:
        logger.warning(f"[주기적유저요약] 알 수 없는 메시지 발송 방식: {message_delivery_method}. 단일 메시지로 발송.")
        final_message = header_message + "\n\n" + "✨ 주기적 유저 대화 요약 리포트 ✨\n\n" + "\n\n" + "\n".join(all_summaries)
        final_message = g.apply_kakao_readmore(final_message, kakao_readmore_config.get("type", "lines"), kakao_readmore_config.get("value", 1))
        return [final_message]
