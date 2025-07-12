# services/profile_mbti_service.py
import json
import core.globals as g
from core.logger import logger
from services.llm_fallback_service import call_llm_with_fallback
from core.db_utils import fetch_recent_messages
from services.image_service import create_and_send_image

# MBTI 유형 설명 데이터
MBTI_TYPES = {
    "INTJ": "분석적이고 전략적인 사고를 가진 '건축가' 유형",
    "INTP": "혁신적이고 논리적인 '논리술사' 유형",
    "ENTJ": "대담하고 결단력 있는 '통솔자' 유형",
    "ENTP": "창의적이고 논쟁을 즐기는 '변론가' 유형",
    "INFJ": "통찰력 있고 이상주의적인 '옹호자' 유형",
    "INFP": "이상적이고 창의적인 '중재자' 유형",
    "ENFJ": "카리스마 있고 영감을 주는 '선도자' 유형",
    "ENFP": "열정적이고 창의적인 '활동가' 유형",
    "ISTJ": "책임감 있고 실용적인 '현실주의자' 유형",
    "ISFJ": "헌신적이고 따뜻한 '수호자' 유형",
    "ESTJ": "효율적이고 질서정연한 '경영자' 유형",
    "ESFJ": "배려심 깊고 사교적인 '집정관' 유형",
    "ISTP": "대담하고 실용적인 '장인' 유형",
    "ISFP": "예술적이고 탐험을 좋아하는 '모험가' 유형",
    "ESTP": "에너지 넘치는 '사업가' 유형",
    "ESFP": "자발적이고 열정적인 '연예인' 유형"
}


async def analyze_mbti(bot_name, channel_id, user_hash, sender=None, room_name=None):
    """
    사용자의 대화 히스토리를 분석하여 MBTI 유형을 판별합니다.

    Args:
        bot_name (str): 봇 이름
        channel_id (str): 채널 ID
        user_hash (str): 사용자 해시
        sender (str, optional): 발신자 이름
        room_name (str, optional): 방 이름

    Returns:
        str: MBTI 분석 결과
    """
    # 대화 로그 가져오기 (최대 100개)
    history = await fetch_recent_messages(
        pool=g.db_pool,
        channel_id=channel_id,
        user_hash=user_hash,
        limit=500
    )

    # 히스토리 체크
    if len(history) < 30:
        return f"📉 MBTI 분석을 위한 최소 대화 수는 30개입니다. 현재는 {len(history)}개밖에 없어요."

    # 대화 내용 구성
    messages_text = "\n".join([f"- {msg}" for msg in history])

    # 시스템 프롬프트 구성
    system_prompt = (
        f"당신은 MBTI 성격 유형 분석 전문가입니다. 주어진 대화 기록을 기반으로 다음 사용자의 MBTI 유형을 분석해주세요.\n\n"
        f"사용자 이름: {sender}\n\n"
        f"분석 시 다음 사항을 고려해주세요:\n"
        f"1. 외향(E)/내향(I): 사회적 상호작용에서 에너지를 얻는지 또는 소모하는지\n"
        f"2. 감각(S)/직관(N): 구체적 정보와 세부 사항을 선호하는지 vs 패턴과 가능성을 중시하는지\n"
        f"3. 사고(T)/감정(F): 결정 시 논리와 객관성을 우선시하는지 vs 가치와 조화를 중시하는지\n"
        f"4. 판단(J)/인식(P): 계획과 구조를 선호하는지 vs 유연성과 적응성을 선호하는지\n\n"
        f"각 지표별로 근거를 제시하고, 최종 MBTI 유형(예: ENFP)을 결정해주세요. 결과는 다음 형식으로 제공해주세요:\n\n"
        f"1. MBTI 유형: [16가지 유형 중 하나]\n"
        f"2. 주요 특성: [간략한 설명]\n"
        f"3. 분석 근거: [각 지표별 특성과 관찰된 행동 패턴]\n"
        f"4. 잠재적 강점: [3-4가지]\n"
        f"5. 개발 가능한 영역: [2-3가지]\n\n"
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
    user_prompt = f"{sender}님의 MBTI 유형을 분석해주세요."
    result = await call_llm_with_fallback(received_message, user_prompt, providers)

    if not result:
        return "MBTI 분석 중 오류가 발생했습니다. 나중에 다시 시도해 주세요."

    # MBTI 유형 추출
    mbti_type = None
    for type_code in MBTI_TYPES.keys():
        if type_code in result:
            mbti_type = type_code
            break

    # 이미지 생성 및 전송
    if mbti_type:
        image_prompt = f"{sender}님의 MBTI 유형은 {mbti_type}입니다. {MBTI_TYPES.get(mbti_type, '')}. 이 성격 유형을 대표하는 상징적인 이미지를 생성해주세요. 밝고 긍정적인 색상으로, 성격 특성을 시각적으로 표현해주세요."
        
        # writer 정보가 없으므로 None으로 설정 (create_and_send_image에서 경고 로그 남김)
        received_message["writer"] = None
        await create_and_send_image(image_prompt, received_message)

    # 결과 형식화
    final_result = f"🧠 {sender}님의 MBTI 분석 결과\n\n{result}"
    return final_result
