import aiomysql
from config.loader import load_config
from core.logger import logger
from core.performance import measure_performance
import core.globals as g

CONFIG = load_config()

@measure_performance  # 소요 시간 로깅 데코레이터



async def init_db_pool():
    
    db_name = g.DB_NAME
    
    """
    aiomysql을 사용하여 비동기 DB 커넥션 풀 생성
    :return: DB 풀 객체
    """
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

        logger.info("[DB INIT] DB 커넥션 풀 생성 완료!")
        return pool

    except Exception as e:
        logger.error(f"[DB INIT ERROR] 커넥션 풀 생성 실패 → {e}", exc_info=True)
        return None
