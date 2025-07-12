import datetime
from collections import Counter
from core import globals as g
from typing import Optional

async def get_chat_rank(channel_id: str, room_name: str, only_meaningful: bool = False, days: int = 30) -> str:
    # 쿼리 동적 구성
    query = f"""
        SELECT sender
        FROM kb_chat_logs
        WHERE channel_id = %s
          AND server_timestamp >= NOW() - INTERVAL {days} DAY
    """
    if only_meaningful:
        query += " AND is_meaningful = 1"

    async with g.db_pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.execute(query, (channel_id,))
            rows = await cur.fetchall()

    if not rows:
        return f"📊 최근 {days}일간 채팅 기록이 없습니다."

    senders = [row[0] for row in rows if row[0]]
    total = len(senders)
    counter = Counter(senders).most_common(100)

    # Medal emojis for top 3 positions
    medals = ["🥇", "🥈", "🥉"]

    if only_meaningful:
        lines = [f"🏆 최근 {days}일 채팅 순위입니다!" + "\u200b" * 500 + "\n\n의미있는 메시지로만 산출된 순위 입니다.\n\n" + "—" * 10]
    else:
        lines = [f"🏆 최근 {days}일 채팅 순위입니다." + "\u200b" * 500 + "\n\n모든 메시지로 산출된 순위 입니다.\n\n" + "—" * 10]

    for i, (sender, count) in enumerate(counter):
        ratio = count / total
        percent = round(ratio * 100, 1)  # 소수점 1자리까지 퍼센트 표시
        bar_blocks = round(ratio * 30)  # 전체를 10단계 막대로 환산
        bar = "𝅛" * bar_blocks + "𝅚" * (30 - bar_blocks)

        # Use medal emoji for top 3, regular ranking for others
        if i < 3:
            rank_line = f"{medals[i]} {sender} : {count}회\n{bar} ({percent}%)"
        else:
            rank_line = f"[{i+1}] {sender} : {count}회\n{bar} ({percent}%)"

        lines.append(rank_line)
        lines.append("—" * 10)

    return "\n".join(lines)

async def get_raw_chat_ranking(channel_id: str, period_minutes: int, only_meaningful: bool = False, exclude_bots: bool = False, bot_name: Optional[str] = None):
    """
    채널 ID와 기간(분)을 기반으로 사용자별 메시지 수를 카운트하여 raw 데이터를 반환합니다.
    봇 제외 옵션을 포함합니다.
    """
    query = """
        SELECT sender
        FROM kb_chat_logs
        WHERE channel_id = %s
          AND server_timestamp >= NOW() - INTERVAL %s MINUTE
    """
    args = [channel_id, period_minutes]

    if only_meaningful:
        query += " AND is_meaningful = 1"
    
    if exclude_bots and bot_name:
        query += " AND sender NOT LIKE %s AND sender NOT LIKE %s"
        args.append(bot_name)
        args.append(f"{bot_name}(봇)")

    async with g.db_pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.execute(query, args)
            rows = await cur.fetchall()

    senders = [row[0] for row in rows if row[0]]
    return Counter(senders)