import aiomysql
import json
from datetime import datetime
import core.globals as g
from core.logger import logger


async def record_game_result(pool, game_id, winner_id, moves):
    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.execute("""
                INSERT INTO kb_omok_game_history (game_id, player1_id, player2_id, winner_id, moves)
                VALUES (%s, %s, %s, %s, %s)
            """, (game_id, moves[0][0], moves[1][0], winner_id, str(moves)))
            await conn.commit()


async def update_user_stats(pool, user_id, won):
    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            if won:
                await cur.execute("""
                    INSERT INTO kb_omok_user_stats (user_id, wins, games_played)
                    VALUES (%s, 1, 1)
                    ON DUPLICATE KEY UPDATE wins = wins + 1, games_played = games_played + 1
                """, (user_id,))
            else:
                await cur.execute("""
                    INSERT INTO kb_omok_user_stats (user_id, losses, games_played)
                    VALUES (%s, 1, 1)
                    ON DUPLICATE KEY UPDATE losses = losses + 1, games_played = games_played + 1
                """, (user_id,))
            await conn.commit()


async def save_game_result(game_session, winner_id):
    """
    게임 결과를 저장하고 플레이어 통계를 업데이트합니다.
    
    Args:
        game_session: OmokSession 객체
        winner_id: 승자 ID (무승부인 경우 None)
    """
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                # 게임 히스토리 저장
                moves_json = json.dumps([
                    {"x": x, "y": y, "color": color}
                    for x, y, color in game_session.move_history
                ])
                
                custom_rules_json = json.dumps(game_session.rule_options)
                
                # INSERT 대신 REPLACE 사용하여 중복 키 처리
                await cur.execute("""
                    REPLACE INTO kb_omok_game_history 
                    (game_id, player1_id, player2_id, ai_level, rule, custom_rules, 
                     winner_id, moves, started_at, ended_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """, (
                    game_session.game_id,
                    game_session.player1["user_id"],
                    game_session.player2["user_id"] if not game_session.ai_level else None,
                    game_session.ai_level,
                    game_session.rule,
                    custom_rules_json,
                    winner_id,
                    moves_json,
                    game_session.started_at,
                    datetime.now()
                ))
                
                # 플레이어 통계 업데이트
                players = [game_session.player1]
                if not game_session.ai_level:  # AI가 아닌 경우에만 player2 추가
                    players.append(game_session.player2)
                
                for player in players:
                    # 현재 통계 조회
                    await cur.execute("""
                        SELECT games_played, wins, losses, draws, rating 
                        FROM kb_omok_user_stats 
                        WHERE user_id = %s
                    """, (player["user_id"],))
                    
                    stats = await cur.fetchone()
                    
                    if not stats:  # 첫 게임인 경우
                        games_played = 0
                        wins = 0
                        losses = 0
                        draws = 0
                        rating = 1000
                    else:
                        games_played = stats[0]
                        wins = stats[1]
                        losses = stats[2]
                        draws = stats[3]
                        rating = stats[4]
                    
                    # 통계 업데이트
                    games_played += 1
                    
                    if winner_id is None:  # 무승부
                        draws += 1
                    elif winner_id == player["user_id"]:  # 승리
                        wins += 1
                        rating += 25  # 승리 시 레이팅 증가
                    else:  # 패배
                        losses += 1
                        rating = max(1, rating - 20)  # 패배 시 레이팅 감소 (최소 1)
                    
                    # DB 업데이트
                    await cur.execute("""
                        INSERT INTO kb_omok_user_stats 
                        (user_id, user_name, games_played, wins, losses, draws, rating)
                        VALUES (%s, %s, %s, %s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE
                        user_name = VALUES(user_name),
                        games_played = VALUES(games_played),
                        wins = VALUES(wins),
                        losses = VALUES(losses),
                        draws = VALUES(draws),
                        rating = VALUES(rating)
                    """, (
                        player["user_id"],
                        player["user_name"],
                        games_played,
                        wins,
                        losses,
                        draws,
                        rating
                    ))
                
                await conn.commit()
                
    except Exception as e:
        logger.error(f"[OMOK] 게임 결과 저장 중 오류 발생: {e}")
        raise


async def get_user_stats(user_id):
    """
    사용자의 전적 통계를 조회합니다.
    
    Args:
        user_id: 사용자 ID
        
    Returns:
        dict: 사용자 통계 정보
    """
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("""
                    SELECT games_played, wins, losses, draws, rating
                    FROM kb_omok_user_stats
                    WHERE user_id = %s
                """, (user_id,))
                
                stats = await cur.fetchone()
                
                if not stats:
                    return {
                        "games_played": 0,
                        "wins": 0,
                        "losses": 0,
                        "draws": 0,
                        "rating": 1000,
                        "win_rate": 0
                    }
                
                # 승률 계산
                win_rate = (stats[1] / stats[0]) * 100 if stats[0] > 0 else 0
                
                return {
                    "games_played": stats[0],
                    "wins": stats[1],
                    "losses": stats[2],
                    "draws": stats[3],
                    "rating": stats[4],
                    "win_rate": round(win_rate, 1)
                }
                
    except Exception as e:
        logger.error(f"[OMOK] 사용자 통계 조회 중 오류 발생: {e}")
        raise


async def get_ai_stats(user_id, ai_level):
    """
    특정 레벨의 AI와의 전적을 조회합니다.
    
    Args:
        user_id: 사용자 ID
        ai_level: AI 레벨
        
    Returns:
        dict: AI와의 전적 정보
    """
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("""
                    SELECT 
                        COUNT(*) as total_games,
                        SUM(CASE WHEN winner_id = %s THEN 1 ELSE 0 END) as wins,
                        SUM(CASE WHEN winner_id IS NULL THEN 1 ELSE 0 END) as draws,
                        SUM(CASE WHEN winner_id IS NOT NULL AND winner_id != %s THEN 1 ELSE 0 END) as losses
                    FROM kb_omok_game_history
                    WHERE (player1_id = %s OR player2_id = %s)
                    AND ai_level = %s
                """, (user_id, user_id, user_id, user_id, ai_level))
                
                stats = await cur.fetchone()
                
                if not stats or stats[0] == 0:  # total_games가 0인 경우
                    return {
                        "total_games": 0,
                        "wins": 0,
                        "losses": 0,
                        "draws": 0,
                        "win_rate": 0
                    }
                
                total_games = stats[0] or 0
                wins = stats[1] or 0
                draws = stats[2] or 0
                losses = stats[3] or 0
                
                win_rate = (wins / total_games) * 100 if total_games > 0 else 0
                
                return {
                    "total_games": total_games,
                    "wins": wins,
                    "losses": losses,
                    "draws": draws,
                    "win_rate": round(win_rate, 1)
                }
                
    except Exception as e:
        logger.error(f"[OMOK] AI 전적 조회 중 오류 발생: {e}")
        raise


async def get_vs_stats(user_id, opponent_id):
    """
    특정 상대와의 전적을 조회합니다.
    
    Args:
        user_id: 사용자 ID
        opponent_id: 상대방 ID
        
    Returns:
        dict: 상대와의 전적 정보
    """
    try:
        async with g.db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("""
                    SELECT 
                        COUNT(*) as total_games,
                        SUM(CASE WHEN winner_id = %s THEN 1 ELSE 0 END) as wins,
                        SUM(CASE WHEN winner_id IS NULL THEN 1 ELSE 0 END) as draws,
                        SUM(CASE WHEN winner_id = %s THEN 1 ELSE 0 END) as losses
                    FROM kb_omok_game_history
                    WHERE ((player1_id = %s AND player2_id = %s)
                        OR (player1_id = %s AND player2_id = %s))
                    AND ai_level IS NULL
                """, (user_id, opponent_id, user_id, opponent_id, opponent_id, user_id))
                
                stats = await cur.fetchone()
                columns = [desc[0] for desc in cur.description]
                if not stats or stats[0] == 0:
                    return {
                        "total_games": 0,
                        "wins": 0,
                        "losses": 0,
                        "draws": 0,
                        "win_rate": 0
                    }
                stats_dict = dict(zip(columns, stats))
                win_rate = (stats_dict["wins"] / stats_dict["total_games"]) * 100 if stats_dict["total_games"] > 0 else 0
                return {
                    "total_games": stats_dict["total_games"],
                    "wins": stats_dict["wins"],
                    "losses": stats_dict["losses"],
                    "draws": stats_dict["draws"],
                    "win_rate": round(win_rate, 1)
                }
                
    except Exception as e:
        logger.error(f"[OMOK] 상대 전적 조회 중 오류 발생: {e}")
        raise
