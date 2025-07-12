#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
cache_service.py: 명령어 응답 캐싱 시스템
LOA.i 챗봇 시스템의 명령어 결과를 캐싱하기 위한 유틸리티

작성일: 2025-04-17
"""

import json
import hashlib
import datetime
import logging
from typing import Dict, List, Optional, Union, Any

from core import globals as g
from core.logger import logger  # get_logger 대신 logger 직접 사용

# 캐시 테이블 생성 SQL (필요시 실행)
CREATE_CACHE_TABLE_SQL = """
CREATE TABLE IF NOT EXISTS kb_message_cache (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    command_type VARCHAR(50) NOT NULL COMMENT '명령어 유형 (openai, gemini, bible 등)',
    request_key VARCHAR(255) NOT NULL COMMENT '요청의 고유 키 (해시 또는 정규화된 요청 내용)',
    request_content TEXT COMMENT '원본 요청 내용',
    response_messages JSON NOT NULL COMMENT '응답 메시지 배열 (JSON 형식)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '응답 생성 시간',
    expires_at TIMESTAMP NULL COMMENT '캐시 만료 시간',
    hit_count INT DEFAULT 1 COMMENT '캐시 히트 횟수',
    last_accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '마지막 접근 시간',
    
    UNIQUE INDEX idx_command_request (command_type, request_key),
    INDEX idx_expiry (expires_at),
    INDEX idx_last_accessed (last_accessed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='명령어 응답 메시지 캐싱 테이블';
"""


async def init_cache_table() -> None:
    """
    캐시 테이블이 없을 경우 테이블을 생성합니다.
    서버 시작 시 한 번 호출해야 합니다.
    """
    try:
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor() as cursor:
                await cursor.execute(CREATE_CACHE_TABLE_SQL)
                logger.info("캐시 테이블 초기화 완료")
    except Exception as e:
        logger.error(f"캐시 테이블 초기화 중 오류 발생: {e}")


async def is_cache_enabled(command_type: str, bot_name: str = None) -> bool:
    """주어진 명령어 타입에 대해 캐싱이 활성화되어 있는지 확인합니다."""

    try:
        # 봇별 명령어 데이터 확인
        if bot_name:
            from core.command_loader import get_bot_commands
            bot_data = get_bot_commands(bot_name)
            if bot_data:
                command_data = bot_data.get("command_data", {})
                for prefix, data in command_data.items():
                    if data.get('type') == command_type:
                        result = data.get("cache_enabled", False)
                        logger.debug(f"[CACHE] 봇 {bot_name} 명령어 {prefix}(타입:{command_type})의 캐시 활성화 여부: {result}")
                        return result

        # 폴백: 전역 json_command_data 확인 (레거시 호환성)
        if not hasattr(g, 'json_command_data') or g.json_command_data is None:
            logger.warning("json_command_data가 초기화되지 않았습니다. 캐싱 비활성화.")
            return False

        # type으로 명령어 찾기
        for prefix, data in g.json_command_data.items():
            if data.get('type') == command_type:
                result = data.get("cache_enabled", False)
                logger.debug(f"[CACHE] 레거시 명령어 {prefix}(타입:{command_type})의 캐시 활성화 여부: {result}")
                return result

        # 타입에 해당하는 명령어를 찾지 못함
        logger.debug(f"[CACHE] 타입 {command_type}에 해당하는 명령어를 찾지 못함")
        return False
    except Exception as e:
        logger.error(f"캐시 설정 확인 중 오류: {e}")
        return False


async def get_cache_minutes(command_type: str, bot_name: str = None) -> int:
    """명령어 타입에 대한 캐싱 유효 시간(분)을 반환합니다."""
    try:
        # 봇별 명령어 데이터 확인
        if bot_name:
            from core.command_loader import get_bot_commands
            bot_data = get_bot_commands(bot_name)
            if bot_data:
                command_data = bot_data.get("command_data", {})
                for prefix, data in command_data.items():
                    if data.get('type') == command_type:
                        minutes = data.get("cache_minutes", 1)
                        logger.debug(f"[CACHE] 봇 {bot_name} 명령어 {prefix}(타입:{command_type})의 캐시 유효시간: {minutes}분")
                        return minutes

        # 폴백: 전역 json_command_data 확인 (레거시 호환성)
        # type으로 명령어 찾기
        for prefix, data in g.json_command_data.items():
            if data.get('type') == command_type:
                minutes = data.get("cache_minutes", 1)
                logger.debug(f"[CACHE] 레거시 명령어 {prefix}(타입:{command_type})의 캐시 유효시간: {minutes}분")
                return minutes

        # 기본값 반환
        return 1
    except Exception as e:
        logger.error(f"캐시 유효시간 확인 중 오류: {e}")
        return 1


def generate_request_key(prompt: str) -> str:
    """
    요청 내용을 기반으로 고유한 요청 키를 생성합니다.

    Args:
        prompt: 요청 내용 문자열

    Returns:
        str: MD5 해시 기반 요청 키
    """
    return hashlib.md5(prompt.encode('utf-8')).hexdigest()


async def get_cached_response(command_type: str, prompt: str) -> Optional[List[str]]:
    """
    캐시에서 명령어 응답을 검색합니다.

    Args:
        command_type: 명령어 타입 (ex: 'openai', 'gemini', 'bible')
        prompt: 명령어 요청 내용

    Returns:
        Optional[List[str]]: 캐시된 응답 메시지 목록 또는 None
    """
    logger.debug(f"[CACHE] get_cached_response 호출: {command_type}, {prompt[:30]}{'...' if len(prompt) > 30 else ''}")

    try:
        # 캐싱이 비활성화되어 있으면 None 반환
        if not await is_cache_enabled(command_type):
            logger.debug(f"[CACHE] {command_type} 캐싱 비활성화됨")
            return None

        # 요청 키 생성
        request_key = generate_request_key(prompt)
        logger.debug(f"[CACHE] 요청 키: {request_key}")

        # 캐시된 응답 조회
        query = """
            SELECT response_messages 
            FROM kb_message_cache 
            WHERE command_type = %s AND request_key = %s
            AND (expires_at IS NULL OR expires_at > NOW())
        """

        try:
            async with g.db_pool.acquire() as conn:
                await conn.set_charset('utf8mb4')
                async with conn.cursor() as cursor:
                    logger.debug(f"[CACHE] DB 조회 시작: {command_type}, {request_key}")
                    await cursor.execute(query, [command_type, request_key])
                    result = await cursor.fetchone()

                    if result:
                        # 캐시 히트 카운트 업데이트
                        logger.debug(f"[CACHE] 캐시 히트: {command_type}, {request_key}")
                        await cursor.execute(
                            "UPDATE kb_message_cache SET hit_count = hit_count + 1, last_accessed_at = NOW() "
                            "WHERE command_type = %s AND request_key = %s",
                            [command_type, request_key]
                        )
                        await conn.commit()  # 명시적 커밋 추가
                        logger.info(f"[CACHE] 캐시 히트: {command_type} - {prompt[:30]}{'...' if len(prompt) > 30 else ''}")

                        # JSON 문자열에서 Python 객체로 변환
                        try:
                            response = json.loads(result[0])
                            logger.debug(f"[CACHE] 응답 로드 성공: {len(response)} 항목")
                            return response
                        except Exception as json_err:
                            logger.error(f"[CACHE] JSON 파싱 오류: {json_err}")
                            return None

                    logger.debug(f"[CACHE] 캐시 미스: {command_type} - {prompt[:30]}{'...' if len(prompt) > 30 else ''}")
                    return None
        except Exception as db_err:
            logger.error(f"[CACHE] DB 조회 중 오류 발생: {db_err}", exc_info=True)
            return None
    except Exception as e:
        logger.error(f"[CACHE] 캐시 조회 중 예외 발생: {e}", exc_info=True)
        return None  # 어떤 오류가 발생하더라도 안전하게 None 반환


async def cache_response(command_type: str, prompt: str,
                         messages: Union[str, List[str]]) -> None:
    """
    명령어 응답을 캐시에 저장합니다.

    Args:
        command_type: 명령어 타입 (ex: 'openai', 'gemini', 'bible')
        prompt: 명령어 요청 내용
        messages: 응답 메시지 (문자열 또는 문자열 리스트)
    """
    logger.debug(f"[CACHE] cache_response 호출: {command_type}, {prompt[:30]}{'...' if len(prompt) > 30 else ''}")

    try:
        # 캐싱이 비활성화되어 있으면 저장하지 않음
        if not await is_cache_enabled(command_type):
            logger.debug(f"[CACHE] {command_type} 캐싱 비활성화됨, 저장 건너뜀")
            return

        # 문자열 응답을 리스트로 변환
        if isinstance(messages, str):
            messages = [messages]
            logger.debug(f"[CACHE] 단일 문자열을 리스트로 변환: {len(messages[0])} 글자")
        else:
            logger.debug(f"[CACHE] 메시지 리스트: {len(messages)} 항목")

        # 메시지 길이 로깅
        total_length = sum(len(str(msg)) for msg in messages)
        logger.debug(f"[CACHE] 총 메시지 길이: {total_length} 글자")

        # JSON 직렬화 테스트
        try:
            # ensure_ascii=False 옵션 추가
            json_str = json.dumps(messages, ensure_ascii=False, indent=2)
            logger.debug(f"[CACHE] JSON 직렬화 성공: {len(json_str)} 글자")
        except Exception as json_err:
            logger.error(f"[CACHE] JSON 직렬화 실패: {json_err}")
            return

        request_key = generate_request_key(prompt)
        logger.debug(f"[CACHE] 요청 키: {request_key}")

        # 명령어 타입에 따른 캐시 유효 시간 계산
        cache_minutes = await get_cache_minutes(command_type)
        expires_at = (datetime.datetime.now() + datetime.timedelta(minutes=cache_minutes)).strftime('%Y-%m-%d %H:%M:%S')
        logger.debug(f"[CACHE] 만료 시간: {expires_at}")

        # 중복 방지를 위한 REPLACE INTO 사용
        query = """
            REPLACE INTO kb_message_cache 
            (command_type, request_key, request_content, response_messages, expires_at, hit_count, last_accessed_at)
            VALUES (%s, %s, %s, %s, %s, 1, NOW())
        """

        try:
            async with g.db_pool.acquire() as conn:
                await conn.set_charset('utf8mb4')
                async with conn.cursor() as cursor:
                    logger.debug(f"[CACHE] DB 저장 시작: {command_type}, {request_key}")
                    await cursor.execute(
                        query,
                        [command_type, request_key, prompt, json_str, expires_at]
                    )
                    await conn.commit()  # 명시적 커밋 추가
                    logger.info(f"[CACHE] 캐시 저장 완료: {command_type} - {prompt[:30]}{'...' if len(prompt) > 30 else ''}, 유효기간: {cache_minutes}분")
        except Exception as db_err:
            logger.error(f"[CACHE] DB 저장 중 오류 발생: {db_err}", exc_info=True)
    except Exception as e:
        logger.error(f"[CACHE] 캐시 저장 중 예외 발생: {e}", exc_info=True)


async def clear_expired_cache() -> int:
    """
    만료된 캐시 항목을 정리합니다.
    주기적으로 실행하는 것이 좋습니다.

    Returns:
        int: 삭제된 캐시 항목 수
    """
    logger.debug("[CACHE] 만료된 캐시 정리 시작")
    query = "DELETE FROM kb_message_cache WHERE expires_at < NOW()"

    try:
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor() as cursor:
                await cursor.execute(query)
                deleted_count = cursor.rowcount
                await conn.commit()  # 명시적 커밋 추가
                logger.info(f"[CACHE] 만료된 캐시 {deleted_count}개 정리 완료")
                return deleted_count
    except Exception as e:
        logger.error(f"[CACHE] 캐시 정리 중 오류 발생: {e}", exc_info=True)
        return 0


async def clear_cache_by_command(command_type: str) -> int:
    """
    특정 명령어 타입의 모든 캐시를 정리합니다.

    Args:
        command_type: 정리할 명령어 타입

    Returns:
        int: 삭제된 캐시 항목 수
    """
    logger.debug(f"[CACHE] {command_type} 명령어 캐시 정리 시작")
    query = "DELETE FROM kb_message_cache WHERE command_type = %s"

    try:
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor() as cursor:
                await cursor.execute(query, [command_type])
                deleted_count = cursor.rowcount
                await conn.commit()  # 명시적 커밋 추가
                logger.info(f"[CACHE] {command_type} 명령어의 캐시 {deleted_count}개 정리 완료")
                return deleted_count
    except Exception as e:
        logger.error(f"[CACHE] 캐시 정리 중 오류 발생: {e}", exc_info=True)
        return 0


async def get_cache_stats() -> Dict[str, Any]:
    """
    캐시 시스템의 통계 정보를 반환합니다.

    Returns:
        Dict: 캐시 통계 정보
    """
    logger.debug("[CACHE] 캐시 통계 조회 시작")
    stats = {
        "total_entries": 0,
        "by_command": {},
        "expires_soon": 0,
        "most_used": []
    }

    try:
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor() as cursor:
                # 전체 항목 수
                await cursor.execute("SELECT COUNT(*) FROM kb_message_cache")
                stats["total_entries"] = (await cursor.fetchone())[0]
                logger.debug(f"[CACHE] 총 항목 수: {stats['total_entries']}")

                # 명령어별 항목 수
                await cursor.execute(
                    "SELECT command_type, COUNT(*) as count FROM kb_message_cache GROUP BY command_type"
                )
                results = await cursor.fetchall()
                for row in results:
                    stats["by_command"][row[0]] = row[1]
                logger.debug(f"[CACHE] 명령어별 항목 수: {len(stats['by_command'])} 유형")

                # 곧 만료되는 항목 수 (1시간 이내)
                await cursor.execute(
                    "SELECT COUNT(*) FROM kb_message_cache "
                    "WHERE expires_at IS NOT NULL AND expires_at < DATE_ADD(NOW(), INTERVAL 1 HOUR)"
                )
                stats["expires_soon"] = (await cursor.fetchone())[0]
                logger.debug(f"[CACHE] 곧 만료되는 항목 수: {stats['expires_soon']}")

                # 가장 많이 사용된 항목들 (상위 5개)
                await cursor.execute(
                    "SELECT command_type, request_content, hit_count, created_at "
                    "FROM kb_message_cache ORDER BY hit_count DESC LIMIT 5"
                )
                results = await cursor.fetchall()
                for row in results:
                    stats["most_used"].append({
                        "command_type": row[0],
                        "request_content": row[1][:50] + ('...' if len(row[1]) > 50 else ''),
                        "hit_count": row[2],
                        "created_at": row[3].strftime('%Y-%m-%d %H:%M:%S')
                    })
                logger.debug(f"[CACHE] 가장 많이 사용된 항목: {len(stats['most_used'])}개")

                return stats
    except Exception as e:
        logger.error(f"[CACHE] 캐시 통계 조회 중 오류 발생: {e}", exc_info=True)
        return stats


async def admin_cache_command(action: str, params: Optional[Dict] = None) -> str:
    """
    관리자 명령어를 통한 캐시 관리 기능

    Args:
        action: 수행할 작업 (stats, clear, clear_all)
        params: 추가 매개변수

    Returns:
        str: 결과 메시지
    """
    logger.info(f"[CACHE] 관리자 명령 수행: {action}, 매개변수: {params}")
    params = params or {}

    if action == "stats":
        stats = await get_cache_stats()
        result = f"📊 캐시 통계\n\n"
        result += f"총 항목 수: {stats['total_entries']}개\n"
        result += f"곧 만료되는 항목: {stats['expires_soon']}개\n\n"

        if stats['by_command']:
            result += "명령어별 항목 수:\n"
            for cmd, count in stats['by_command'].items():
                result += f"- {cmd}: {count}개\n"

        if stats['most_used']:
            result += "\n가장 많이 사용된 항목 (상위 5개):\n"
            for idx, item in enumerate(stats['most_used']):
                result += f"{idx+1}. {item['command_type']} ({item['hit_count']}회): {item['request_content']}\n"

        return result

    elif action == "clear":
        command_type = params.get("command_type")
        if not command_type:
            return "❌ 명령어 유형을 지정해주세요."

        count = await clear_cache_by_command(command_type)
        return f"✅ {command_type} 명령어의 캐시 {count}개를 정리했습니다."

    elif action == "clear_all":
        try:
            async with g.db_pool.acquire() as conn:
                await conn.set_charset('utf8mb4')
                async with conn.cursor() as cursor:
                    await cursor.execute("SELECT COUNT(*) FROM kb_message_cache")
                    total = (await cursor.fetchone())[0]

                    await cursor.execute("TRUNCATE TABLE kb_message_cache")
                    await conn.commit()

                    return f"✅ 모든 캐시 항목 {total}개를 정리했습니다."
        except Exception as e:
            logger.error(f"[CACHE] 전체 캐시 정리 중 오류: {e}", exc_info=True)
            return f"❌ 캐시 정리 중 오류가 발생했습니다: {str(e)}"

    else:
        return f"❌ 알 수 없는 캐시 관리 명령: {action}"