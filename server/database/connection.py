"""
데이터베이스 연결 모듈 - 원본 server와 동일한 방식
"""
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
        logger.info(f"[DB INIT] 커넥션 풀 생성 시도 → host={CONFIG['DBs'][db_name]['HOST']}, "
                    f"db={CONFIG['DBs'][db_name]['NAME']}, pool_size={CONFIG['DBs'][db_name].get('POOL_SIZE', '5-50')}")

        pool = await aiomysql.create_pool(
            host=CONFIG['DBs'][db_name]['HOST'],
            user=CONFIG['DBs'][db_name]['USER'],
            password=CONFIG['DBs'][db_name]['PASSWORD'],
            db=CONFIG['DBs'][db_name]['NAME'],
            charset='utf8mb4',
            autocommit=True,
            minsize=5,
            maxsize=50
        )

        # 전역 변수에 저장
        g.db_pool = pool
        logger.info("[DB INIT] DB 커넥션 풀 생성 완료!")
        return pool

    except Exception as e:
        logger.error(f"[DB INIT ERROR] 커넥션 풀 생성 실패 → {e}", exc_info=True)
        g.db_pool = None
        return None