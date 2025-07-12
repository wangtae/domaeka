# services/profile_enneagram_service.py
import json
import core.globals as g
from core.logger import logger
from services.llm_fallback_service import call_llm_with_fallback
from core.db_utils import fetch_recent_messages
from services.image_service import create_and_send_image

# 애니어그램 유형 설명
ENNEAGRAM_TYPES = {
    "1": "개혁가 - 원칙적이고 이성적이며 완벽주의적",
    "2": "조력가 - 배려심이 많고 대인관계에 집중",
    "3": "성취자 - 성공 지향적이고 적응력이 뛰어남",
    "4": "예술가 - 개인주의적이고 예민한 감수성",
    "5": "사색가 - 통찰력 있고 독립적인 관찰자",
    "6": "충성가 - 헌신적이고 안전을 추구",
    "7": "열정가 - 활기차고 다재다능한 낙천주의자",
    "8": "도전가 - 강력하고 독립적인 결단력",
    "9": "중재자 - 수용적이고 평화를 추구"
}


async def analyze_enneagram(bot_name, channel_id, user_hash, sender=None, room_name=None):
    """
    사용자의 대화 히스토리를 분석하여 애니어그램 유형을 판별합니다.

    Args:
        bot_name (str): 봇 이름
        channel_id (str): 채널 ID
        user_hash (str): 사용자 해시
        sender (str, optional): 발신자 이름
        room_name (str, optional): 방 이름

    Returns:
        str: 애니어그램 분석 결과
    """
    # 대화 로그 가져오기 (최대 100개)
    history = await fetch_recent_messages(
        pool=g.db_pool,
        channel_id=channel_id,
        user_hash=user_hash,
        limit=500
    )

    # 히스토리 체크
    if len(history) < 50:
        return f"📉 애니어그램 분석을 위한 최소 대화 수는 50개입니다. 현재는 {len(history)}개밖에 없어요."

    # 대화 내용 구성
    messages_text = "\n".join([f"- {msg}" for msg in history])

    # 시스템 프롬프트 구성
    system_prompt = (
        f"당신은 애니어그램 성격 유형 분석 전문가입니다. 주어진 대화 기록을 기반으로 다음 사용자의 애니어그램 유형을 분석해주세요.\n\n"
        f"사용자 이름: {sender}\n\n"
        f"애니어그램의 9가지 유형은 다음과 같습니다:\n"
        f"1번 유형: 개혁가 - 원칙적이고 이성적이며 완벽주의적\n"
        f"2번 유형: 조력가 - 배려심이 많고 대인관계에 집중\n"
        f"3번 유형: 성취자 - 성공 지향적이고 적응력이 뛰어남\n"
        f"4번 유형: 예술가 - 개인주의적이고 예민한 감수성\n"
        f"5번 유형: 사색가 - 통찰력 있고 독립적인 관찰자\n"
        f"6번 유형: 충성가 - 헌신적이고 안전을 추구\n"
        f"7번 유형: 열정가 - 활기차고 다재다능한 낙천주의자\n"
        f"8번 유형: 도전가 - 강력하고 독립적인 결단력\n"
        f"9번 유형: 중재자 - 수용적이고 평화를 추구\n\n"
        f"각 유형의 특성을 고려하고, 사용자의 대화에서 드러나는 생각, 동기, 두려움, 행동 패턴을 분석해주세요.\n"
        f"주요 유형 외에도 날개 유형(wing)과 통합/분열 방향도 고려해주세요.\n\n"
        f"결과는 다음 형식으로 제공해주세요:\n\n"
        f"1. 주요 애니어그램 유형: [번호와 이름]\n"
        f"2. 날개 유형: [가능한 날개 유형]\n"
        f"3. 주요 특성: [간략한 설명]\n"
        f"4. 분석 근거: [관찰된 행동 패턴과 표현 방식]\n"
        f"5. 성장 방향: [개인 성장을 위한 제안]\n\n"
        f"[대화 기록]\n{messages_text}"
    )

    # LLM 프로바이더 설정
    providers = [
        {
            "name": "openai",
            "timeout": 30,
            "model": "gpt-4o",
            "retry": 0,
            "system_prompt": system_prompt
        },
        {
            "name": "gemini",
            "model": "gemini-1.5-pro",
            "timeout": 30,
            "retry": 0,
            "system_prompt": system_prompt
        },
        {
            "name": "grok",
            "model": "grok-3-latest",
            "timeout": 30,
            "retry": 0,
            "system_prompt": system_prompt
        }
    ]

    # 메시지 객체 생성
    received_message = {
        "bot_name": bot_name,
        "channel_id": channel_id,
        "user_hash": user_hash,
        "sender": sender,
        "room": room_name,
    }

    # LLM 호출
    user_prompt = f"{sender}님의 애니어그램 유형을 분석해주세요."
    result = await call_llm_with_fallback(received_message, user_prompt, providers)

    if not result:
        return "애니어그램 분석 중 오류가 발생했습니다. 나중에 다시 시도해 주세요."

    # 애니어그램 유형 추출
    enneagram_type = None
    for type_num in ENNEAGRAM_TYPES.keys():
        if f"유형: {type_num}" in result or f"{type_num}번 유형" in result:
            enneagram_type = type_num
            break

    # 이미지 생성 및 전송
    if enneagram_type:
        image_prompt = f"{sender}님의 애니어그램 유형은 {enneagram_type}번 {ENNEAGRAM_TYPES.get(enneagram_type, '').split(' - ')[0]}입니다. {ENNEAGRAM_TYPES.get(enneagram_type, '')}. 이 성격 유형을 대표하는 상징적인 이미지를 생성해주세요. 애니어그램 상징과 함께 유형의 특성을 시각적으로 표현해주세요."
        
        # writer 정보가 없으므로 None으로 설정 (create_and_send_image에서 경고 로그 남김)
        received_message["writer"] = None
        await create_and_send_image(image_prompt, received_message)

    # 결과 형식화
    final_result = f"🔮 {sender}님의 애니어그램 분석 결과\n\n{result}"
    return final_result
