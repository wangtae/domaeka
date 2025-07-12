"""
데이터베이스 연결 모듈 - 원본 server와 동일한 방식
"""
import asyncio
import aiomysql
from config.loader import load_config
from core.logger import logger
import core.globals as g

CONFIG = load_config()


async def init_db_pool():
    """
    aiomysql을 사용하여 비동기 DB 커넥션 풀 생성
    원본 server와 동일한 방식
    """
    db_name = g.DB_NAME
    
    try:
        logger.info(f"[DB INIT] 커넥션 풀 생성 시도 → host={CONFIG['DBs'][db_name]['HOST']}:{CONFIG['DBs'][db_name]['PORT']}, "
                    f"db={CONFIG['DBs'][db_name]['NAME']}, pool_size={CONFIG['DBs'][db_name].get('POOL_SIZE', '5-50')}")

        # 연결 시도 중임을 알림
        logger.info("[DB INIT] aiomysql.create_pool() 호출 중...")
        
        pool = await asyncio.wait_for(
            aiomysql.create_pool(
                host=CONFIG['DBs'][db_name]['HOST'],
                port=CONFIG['DBs'][db_name]['PORT'],
                user=CONFIG['DBs'][db_name]['USER'],
                password=CONFIG['DBs'][db_name]['PASS'],
                db=CONFIG['DBs'][db_name]['NAME'],
                charset='utf8mb4',
                autocommit=True,
                minsize=5,
                maxsize=50
            ),
            timeout=10.0  # 10초 타임아웃 설정
        )

        # 전역 변수에 저장
        g.db_pool = pool
        logger.info("[DB INIT] DB 커넥션 풀 생성 완료!")
        return pool

    except asyncio.TimeoutError:
        logger.error("[DB INIT ERROR] 데이터베이스 연결 타임아웃 (10초)")
        g.db_pool = None
        return None
    except Exception as e:
        logger.error(f"[DB INIT ERROR] 커넥션 풀 생성 실패 → {e}", exc_info=True)
        g.db_pool = None
        return None