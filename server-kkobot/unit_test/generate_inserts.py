import json

with open('config/schedule-rooms.json', encoding='utf-8') as f:
    data = json.load(f)

rooms = data.get('LOA.i', {})  # 최상위 키가 'LOA.i'임에 주의

room_sqls = []
schedule_sqls = []

for room_id, room in rooms.items():
    # 방 정보
    fields = [
        'room_id', 'room_name', 'bot_nickname', 'room_concurrency', 'room_owners',
        'allowed_categories', 'log_settings', 'ignored_users', 'nickname_alert',
        'llm_settings', 'chat_sessions', 'conversation_summary', 'youtube_summary',
        'webpage_summary', 'enable_investment_news', 'default_bibles', 'omok_settings',
        'conversation_join'
    ]
    values = []
    for field in fields: 
        v = room.get(field)
        if isinstance(v, (dict, list)):
            values.append("'" + json.dumps(v, ensure_ascii=False).replace("'", "''") + "'")
        elif v is None:
            values.append('NULL')
        elif isinstance(v, bool):
            values.append('1' if v else '0')
        elif isinstance(v, int):
            values.append(str(v))
        else:
            values.append("'" + str(v).replace("'", "''") + "'")
    room_sqls.append(
        f"INSERT INTO kb_rooms ({', '.join(fields)}) VALUES ({', '.join(values)});"
    )

    # 스케줄 정보
    for sched in room.get('schedules', []):
        sched_fields = ['room_id', 'days', 'times', 'messages', 'tts']
        sched_values = [
            "'" + room_id + "'",
            "'" + json.dumps(sched.get('days', []), ensure_ascii=False).replace("'", "''") + "'",
            "'" + json.dumps(sched.get('times', []), ensure_ascii=False).replace("'", "''") + "'",
            "'" + json.dumps(sched.get('messages', []), ensure_ascii=False).replace("'", "''") + "'",
        ]
        tts = sched.get('tts')
        if tts is not None:
            sched_values.append("'" + json.dumps(tts, ensure_ascii=False).replace("'", "''") + "'")
        else:
            sched_values.append('NULL')
        schedule_sqls.append(
            f"INSERT INTO kb_room_schedules ({', '.join(sched_fields)}) VALUES ({', '.join(sched_values)});"
        )

with open('insert_kb_rooms.sql', 'w', encoding='utf-8') as f:
    f.write('\n'.join(room_sqls))

with open('insert_kb_room_schedules.sql', 'w', encoding='utf-8') as f:
    f.write('\n'.join(schedule_sqls))