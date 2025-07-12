# 이 모듈은 데이터베이스(kb_rooms, kb_room_schedules, kb_room_global_config 테이블)의 설정 데이터를 통합하여
# KakaoBot 서버에서 사용하는 봇별 설정 파일을 생성합니다.
#
# 주요 로직은 다음과 같습니다:
# 1. `kb_room_global_config` 테이블에서 모든 설정 필드의 기본값을 로드합니다.
# 2. `kb_rooms` 테이블에서 각 방(room)별 설정 데이터를 로드합니다.
# 3. `get_config_value` 헬퍼 함수를 사용하여, 방별 설정이 존재하고 유효한 경우 해당 값을 사용하고,
#    그렇지 않은 경우 `kb_room_global_config`에 정의된 기본값을 사용합니다.
#    (즉, 방별 설정이 글로벌 설정을 오버라이드하는 계층적 구조를 따릅니다.)
# 4. 스케줄 데이터(`kb_room_schedules`)는 해당 방 설정에 추가됩니다.
# 5. 최종적으로 통합된 설정 데이터를 'config/bots-settings/{bot_name}.json' 파일로 저장합니다.
#
# 이 방식을 통해 설정의 유연성을 높이고, 새로운 설정 항목 추가 시 코드 수정 없이 데이터베이스만으로 관리가 가능해집니다.
import aiomysql
import json
import os
import re
from pathlib import Path
from core.logger import logger
from core import globals as g

def clean_json_string(json_str):
    """
    JSON 문자열에서 제어 문자를 정리하여 파싱 가능한 형태로 변환
    """
    if not json_str:
        return json_str
    
    # 모든 제어 문자를 제거하거나 적절히 변환
    import string
    
    # 1단계: \r\n, \r을 \n으로 통일
    cleaned = json_str.replace('\r\n', '\n').replace('\r', '\n')
    
    # 2단계: 출력 가능하지 않은 제어 문자 제거 (단, 일부 허용된 문자는 유지)
    # JSON에서 허용되는 제어 문자: \n(10), \t(9), 공백(32) 등
    allowed_chars = set('\n\t ')
    cleaned_chars = []
    
    for char in cleaned:
        if char in string.printable or char in allowed_chars:
            cleaned_chars.append(char)
        else:
            # 제어 문자를 공백으로 대체하거나 제거
            if ord(char) < 32:  # 제어 문자인 경우
                cleaned_chars.append(' ')  # 공백으로 대체
            else:
                cleaned_chars.append(char)
    
    cleaned = ''.join(cleaned_chars)
    
    # 3단계: JSON 내부 문자열에서 이스케이프되지 않은 개행/탭 문자 처리
    # 이미 JSON 파싱을 시도해보고 실패하면 추가 정리
    try:
        json.loads(cleaned)
        return cleaned
    except json.JSONDecodeError:
        # JSON 문자열 내부의 제어 문자를 이스케이프 처리
        # 따옴표 안의 제어 문자만 이스케이프
        in_string = False
        escape_next = False
        result = []
        
        for i, char in enumerate(cleaned):
            if escape_next:
                result.append(char)
                escape_next = False
            elif char == '\\':
                result.append(char)
                escape_next = True
            elif char == '"':
                result.append(char)
                in_string = not in_string
            elif in_string and char == '\n':
                result.append('\\n')
            elif in_string and char == '\t':
                result.append('\\t')
            else:
                result.append(char)
        
        return ''.join(result)

async def generate_bot_settings_from_db(bot_name: str = None):
    """
    kb_rooms, kb_room_schedules, kb_room_global_config 테이블의 데이터를 통합하여
    봇별 설정 JSON 파일을 생성하고 저장합니다.
    
    Args:
        bot_name (str, optional): 특정 봇의 설정만 생성할 경우 봇 이름. None이면 모든 봇 생성.
    
    Returns:
        tuple: (success: bool, message: str)
    """
    try:
        if not hasattr(g, 'db_pool') or g.db_pool is None:
            return False, "데이터베이스 연결 풀이 초기화되지 않았습니다."
            
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cursor:
                # 1. kb_room_global_config 데이터 가져오기 (기본값 설정)
                logger.info("[Bot Settings Generator] 글로벌 설정 로딩 중...")
                await cursor.execute("SELECT config_key, config_json, is_json FROM kb_room_global_config")
                global_configs = {}
                for row in await cursor.fetchall():
                    config_key = row['config_key']
                    config_json_str = row['config_json']
                    is_json_flag = bool(row['is_json'])

                    try:
                        parsed_value = json.loads(clean_json_string(config_json_str)) if is_json_flag else config_json_str
                        global_configs[config_key] = {'value': parsed_value, 'is_json': is_json_flag}
                        logger.debug(f"[Bot Settings Generator] 글로벌 설정 로드: {config_key} (is_json: {is_json_flag})")
                    except json.JSONDecodeError as e:
                        logger.warning(f"[Bot Settings Generator] 글로벌 설정 JSON 파싱 실패: {config_key} - {e}")
                        global_configs[config_key] = {'value': None, 'is_json': is_json_flag}
                
                logger.info(f"[Bot Settings Generator] 글로벌 설정 {len(global_configs)}개 로드 완료")
                
                # 2. kb_rooms 데이터 가져오기 (특정 봇 필터링 옵션)
                if bot_name:
                    logger.info(f"[Bot Settings Generator] '{bot_name}' 봇의 방 설정 데이터 로딩 중...")
                    await cursor.execute("SELECT * FROM kb_rooms WHERE bot_name = %s ORDER BY created_at ASC", (bot_name,))
                else:
                    logger.info("[Bot Settings Generator] 모든 봇의 방 설정 데이터 로딩 중...")
                    await cursor.execute("SELECT * FROM kb_rooms ORDER BY created_at ASC")
                rooms_data = await cursor.fetchall()
                logger.info(f"[Bot Settings Generator] 방 설정 {len(rooms_data)}개 로드 완료")
                
                # 3. kb_room_schedules 데이터 가져오기 (특정 봇 필터링 옵션)
                if bot_name:
                    logger.info(f"[Bot Settings Generator] '{bot_name}' 봇의 스케줄 데이터 로딩 중...")
                    await cursor.execute("SELECT * FROM kb_room_schedules WHERE bot_name = %s", (bot_name,))
                else:
                    logger.info("[Bot Settings Generator] 모든 봇의 스케줄 데이터 로딩 중...")
                    await cursor.execute("SELECT * FROM kb_room_schedules")
                schedules_data = await cursor.fetchall()
                logger.info(f"[Bot Settings Generator] 스케줄 {len(schedules_data)}개 로드 완료")
        
        # 헬퍼 함수: 방별 설정 또는 글로벌 설정 반환 (JSON 파싱 및 빈 값 처리 개선)
        def get_config_value(room_db_row, global_configs_map, key):
            """방별 설정이 있으면 사용하고, 없으면 글로벌 설정 사용. JSON 파싱 및 빈 값 처리."""
            room_value = room_db_row.get(key)
            
            # Determine is_json from global_configs_map
            is_json_flag = global_configs_map.get(key, {}).get('is_json', False)

            if is_json_flag:
                try:
                    # DB에서 읽은 값이 None이 아니고 빈 문자열이 아닌 경우에만 JSON 파싱 시도
                    if room_value is not None and str(room_value).strip() != '':
                        parsed_room_value = json.loads(clean_json_string(str(room_value)))
                    else:
                        parsed_room_value = None # 빈 문자열이나 None은 None으로 처리하여 기본값 로직 타도록
                except json.JSONDecodeError as e:
                    logger.warning(f"[Bot Settings Generator] 방 설정 '{key}' JSON 파싱 실패: room_id={room_db_row.get('room_id')} - {e}")
                    parsed_room_value = None # 파싱 실패 시 기본값 로직 타도록
            else:
                parsed_room_value = room_value

            # 파싱된 값이 None이 아니거나, 빈 dict/list가 아닌 경우 방별 설정 사용
            if parsed_room_value is not None and \
               not (is_json_flag and (parsed_room_value == {} or parsed_room_value == [])):
                logger.debug(f"[Bot Settings Generator] {key} 방별 설정 사용.")
                return parsed_room_value
            elif key in global_configs_map:
                logger.debug(f"[Bot Settings Generator] {key} 글로벌 설정 사용.")
                return global_configs_map[key]['value']
            else:
                logger.warning(f"[Bot Settings Generator] {key} 설정을 찾을 수 없음. 기본값 없음.")
                return None
        
        # 4. 봇별 데이터 구조 생성
        bots_data = {}
        for room in rooms_data:
            current_bot_name = room['bot_name']
            room_id = room['room_id']
            
            if current_bot_name not in bots_data:
                bots_data[current_bot_name] = {}
            
            logger.debug(f"[Bot Settings Generator] 방 설정 생성: {current_bot_name}/{room_id}")
            
            room_config = {
                'room_name': room['room_name'],
                'bot_nickname': room['bot_nickname'],
                'schedules': []
            }
            
            # Dynamic generation of room_config based on global_configs
            for config_key in global_configs.keys():
                value = get_config_value(room, global_configs, config_key)
                if value is not None:
                    room_config[config_key] = value
            
            # None 값 제거
            room_config = {k: v for k, v in room_config.items() if v is not None}
            
            bots_data[current_bot_name][room_id] = room_config
        
        # 5. 스케줄 데이터 통합
        for schedule in schedules_data:
            schedule_bot_name = schedule['bot_name']
            room_id = schedule['room_id']
            
            if schedule_bot_name in bots_data and room_id in bots_data[schedule_bot_name]:
                logger.debug(f"[Bot Settings Generator] 스케줄 추가: {schedule_bot_name}/{room_id}")
                try:
                    # JSON 데이터 파싱 (제어 문자 정리 적용)
                    days = json.loads(clean_json_string(schedule['days']))
                    times = json.loads(clean_json_string(schedule['times']))
                    messages = json.loads(clean_json_string(schedule['messages']))
                    
                    schedule_item = {
                        'days': days,
                        'times': times,
                        'messages': messages
                    }
                    
                    # TTS 설정이 있으면 추가
                    if schedule['tts']:
                        try:
                            schedule_item['tts'] = json.loads(clean_json_string(schedule['tts']))
                        except json.JSONDecodeError as e:
                            logger.warning(f"[Bot Settings Generator] TTS JSON 파싱 실패: bot_name={schedule_bot_name}, room_id={room_id} - {e}")
                    
                    bots_data[schedule_bot_name][room_id]['schedules'].append(schedule_item)
                    
                except json.JSONDecodeError as e:
                    logger.warning(f"[Bot Settings Generator] 스케줄 JSON 파싱 실패: bot_name={schedule_bot_name}, room_id={room_id} - {e}")
            else:
                logger.warning(f"[Bot Settings Generator] 스케줄에 해당하는 방이 없음: {schedule_bot_name}/{room_id}")
        
        total_schedules = sum(len([s for room in bot_rooms.values() for s in room.get('schedules', [])]) for bot_rooms in bots_data.values())
        logger.info(f"[Bot Settings Generator] 스케줄 {total_schedules}개 통합 완료")
        
        # 6. 봇별로 개별 JSON 파일 저장
        settings_dir = Path("config/bots-settings")
        settings_dir.mkdir(parents=True, exist_ok=True)
        
        generated_files = []
        for current_bot_name, bot_rooms in bots_data.items():
            output_file_path = settings_dir / f"{current_bot_name}.json"
            
            with open(output_file_path, 'w', encoding='utf-8') as f:
                json.dump(bot_rooms, f, ensure_ascii=False, indent=2)
            
            generated_files.append(str(output_file_path))
            logger.warning(f"[Bot Settings Generator] {output_file_path} 파일 생성 완료.")
        
        if bot_name:
            if bot_name in bots_data:
                return True, f"성공적으로 '{bot_name}' 봇 설정 파일을 생성했습니다: {settings_dir / f'{bot_name}.json'}"
            else:
                return False, f"'{bot_name}' 봇의 설정 데이터를 찾을 수 없습니다."
        else:
            return True, f"성공적으로 {len(generated_files)}개 봇의 설정 파일을 생성했습니다: {', '.join([Path(f).name for f in generated_files])}"
        
    except Exception as e:
        logger.error(f"[Bot Settings Generator] 봇 설정 파일 생성 실패: {e}")
        return False, f"파일 생성 실패: {e}"

async def generate_all_bot_settings_from_db():
    """
    모든 봇의 설정 파일을 생성합니다.
    
    Returns:
        tuple: (success: bool, message: str)
    """
    return await generate_bot_settings_from_db()

# 하위 호환성을 위한 레거시 함수명 지원
async def generate_schedule_rooms_from_db_json():
    """
    하위 호환성을 위한 레거시 함수. generate_bot_settings_from_db()를 호출합니다.
    
    Returns:
        tuple: (success: bool, message: str)
    """
    logger.warning("[Bot Settings Generator] 레거시 함수 'generate_schedule_rooms_from_db_json' 호출됨. 'generate_bot_settings_from_db' 사용을 권장합니다.")
    return await generate_bot_settings_from_db()

async def generate_bot_commands_from_db(bot_name: str):
    """
    kb_commands 테이블의 데이터를 통합하여 봇별 명령어 JSON 파일을 생성하고 저장합니다.
    글로벌 명령어와 봇별 명령어를 계층적으로 병합합니다.
    
    Args:
        bot_name (str): 명령어를 생성할 봇 이름.
    
    Returns:
        tuple: (success: bool, message: str)
    """
    try:
        if not hasattr(g, 'db_pool') or g.db_pool is None:
            return False, "데이터베이스 연결 풀이 초기화되지 않았습니다."
            
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cursor:
                # 1. 글로벌 명령어 로드
                logger.info(f"[Bot Commands Generator] 글로벌 명령어 로딩 중...")
                await cursor.execute("SELECT * FROM kb_commands WHERE command_type = %s", ('global',))
                global_commands_raw = await cursor.fetchall()
                
                global_commands = {}
                for cmd_row in global_commands_raw:
                    cmd_key = cmd_row['command_key']
                    global_commands[cmd_key] = {k: v for k, v in cmd_row.items() if k != 'command_type'}
                    # JSON 필드 파싱 및 None 처리
                    for json_field in ['command_keywords', 'command_keyword_aliases', 'command_optional_words', 'command_aliases', 'parameters']:
                        if global_commands[cmd_key].get(json_field) is not None:
                            try:
                                global_commands[cmd_key][json_field] = json.loads(clean_json_string(global_commands[cmd_key][json_field]))
                            except json.JSONDecodeError as e:
                                logger.warning(f"[Bot Commands Generator] 글로벌 명령어 '{cmd_key}' {json_field} JSON 파싱 실패: {e}")
                                global_commands[cmd_key][json_field] = None # 파싱 실패 시 None으로 설정

                logger.info(f"[Bot Commands Generator] 글로벌 명령어 {len(global_commands)}개 로드 완료")

                # 2. 봇별 명령어 로드 (글로벌 명령어 오버라이드)
                logger.info(f"[Bot Commands Generator] '{bot_name}' 봇별 명령어 로딩 중...")
                await cursor.execute("SELECT * FROM kb_commands WHERE command_type = %s AND bot_name = %s", ('bot', bot_name))
                bot_commands_raw = await cursor.fetchall()

                # 글로벌 명령어를 기본으로 복사
                final_commands = {k: v.copy() for k, v in global_commands.items()}
                
                for cmd_row in bot_commands_raw:
                    cmd_key = cmd_row['command_key']
                    if cmd_key not in final_commands:
                        final_commands[cmd_key] = {} # 새로운 봇별 명령어가 있는 경우 초기화
                    
                    # 봇별 필드로 글로벌 명령어 오버라이드 또는 추가
                    for k, v in cmd_row.items():
                        if k == 'command_type':
                            continue # command_type은 저장하지 않음
                        if v is not None: # None이 아니면 봇별 값으로 오버라이드
                            # JSON 필드 파싱 및 None 처리
                            if k in ['command_keywords', 'command_keyword_aliases', 'command_optional_words', 'command_aliases', 'parameters']:
                                try:
                                    final_commands[cmd_key][k] = json.loads(clean_json_string(v))
                                except json.JSONDecodeError as e:
                                    logger.warning(f"[Bot Commands Generator] 봇별 명령어 '{cmd_key}' {k} JSON 파싱 실패: {e}")
                                    final_commands[cmd_key][k] = None
                            else:
                                final_commands[cmd_key][k] = v
                        elif k not in final_commands[cmd_key]: # 봇별 필드가 None이고 글로벌에도 없으면 None으로 설정
                             final_commands[cmd_key][k] = None # 명시적으로 None 설정

                logger.info(f"[Bot Commands Generator] '{bot_name}' 봇별 명령어 {len(bot_commands_raw)}개 병합 완료")

        # 3. JSON 파일로 저장
        commands_dir = Path(g.BASE_DIR) / "config" / "bots-commands"
        commands_dir.mkdir(parents=True, exist_ok=True)
        
        file_path = commands_dir / f"{bot_name}.json"
        
        with open(file_path, 'w', encoding='utf-8') as f:
            json.dump(final_commands, f, ensure_ascii=False, indent=4)
        
        logger.info(f"[Bot Commands Generator] '{bot_name}' 명령어 파일 저장 완료: {file_path}")
        return True, f"'{bot_name}' 봇 명령어 파일이 성공적으로 생성되었습니다."

    except Exception as e:
        logger.error(f"[Bot Commands Generator] '{bot_name}' 봇 명령어 파일 생성 중 오류 발생: {e}", exc_info=True)
        return False, f"'{bot_name}' 봇 명령어 파일 생성 중 오류가 발생했습니다: {e}"

async def generate_bot_settings_from_new_db(bot_name: str = None):
    """
    새로운 KB 핵심 테이블들(kb_admin_*, kb_config_*)을 사용하여
    봇별 설정 JSON 파일을 생성하고 저장합니다.
    
    Args:
        bot_name (str, optional): 특정 봇의 설정만 생성할 경우 봇 이름. None이면 모든 봇 생성.
    
    Returns:
        tuple: (success: bool, message: str)
    """
    try:
        if not hasattr(g, 'db_pool') or g.db_pool is None:
            return False, "데이터베이스 연결 풀이 초기화되지 않았습니다."
            
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cursor:
                
                # 1. 글로벌 설정 로드
                logger.info("[New Bot Settings Generator] 글로벌 설정 로딩 중...")
                await cursor.execute("""
                    SELECT setting_key, setting_value, category, description 
                    FROM kb_config_rooms_global 
                    WHERE is_active = 1
                """)
                global_settings = {}
                for row in await cursor.fetchall():
                    setting_key = row['setting_key']
                    try:
                        setting_value = json.loads(row['setting_value']) if row['setting_value'] else None
                        global_settings[setting_key] = setting_value
                        logger.debug(f"[New Bot Settings Generator] 글로벌 설정 로드: {setting_key}")
                    except json.JSONDecodeError as e:
                        logger.warning(f"[New Bot Settings Generator] 글로벌 설정 JSON 파싱 실패: {setting_key} - {e}")
                        global_settings[setting_key] = None
                
                logger.info(f"[New Bot Settings Generator] 글로벌 설정 {len(global_settings)}개 로드 완료")
                
                # 2. 봇 정보 로드
                if bot_name:
                    logger.info(f"[New Bot Settings Generator] '{bot_name}' 봇 정보 로딩 중...")
                    await cursor.execute("""
                        SELECT bot_id, bot_name, display_name, description, is_active
                        FROM kb_admin_bots 
                        WHERE bot_name = %s AND is_active = 1
                    """, (bot_name,))
                else:
                    logger.info("[New Bot Settings Generator] 모든 봇 정보 로딩 중...")
                    await cursor.execute("""
                        SELECT bot_id, bot_name, display_name, description, is_active
                        FROM kb_admin_bots 
                        WHERE is_active = 1
                    """)
                bots_data = await cursor.fetchall()
                logger.info(f"[New Bot Settings Generator] 봇 {len(bots_data)}개 로드 완료")
                
                if not bots_data:
                    return False, f"활성화된 봇을 찾을 수 없습니다. (bot_name: {bot_name})"
                
                # 3. 각 봇별로 설정 파일 생성
                generated_files = []
                for bot_info in bots_data:
                    current_bot_id = bot_info['bot_id']
                    current_bot_name = bot_info['bot_name']
                    
                    logger.info(f"[New Bot Settings Generator] '{current_bot_name}' 봇 설정 생성 중...")
                    
                    # 3-1. 봇별 설정 로드
                    await cursor.execute("""
                        SELECT setting_key, setting_value, category, description 
                        FROM kb_config_rooms_bot 
                        WHERE bot_id = %s AND is_active = 1
                    """, (current_bot_id,))
                    bot_settings = {}
                    for row in await cursor.fetchall():
                        setting_key = row['setting_key']
                        try:
                            setting_value = json.loads(row['setting_value']) if row['setting_value'] else None
                            bot_settings[setting_key] = setting_value
                            logger.debug(f"[New Bot Settings Generator] 봇별 설정 로드: {current_bot_name}/{setting_key}")
                        except json.JSONDecodeError as e:
                            logger.warning(f"[New Bot Settings Generator] 봇별 설정 JSON 파싱 실패: {current_bot_name}/{setting_key} - {e}")
                            bot_settings[setting_key] = None
                    
                    # 3-2. 해당 봇의 방 정보 로드
                    await cursor.execute("""
                        SELECT room_id, channel_id, room_name, description, is_active,
                               member_count, last_message_at, total_messages
                        FROM kb_admin_rooms 
                        WHERE bot_id = %s AND is_active = 1
                        ORDER BY created_at ASC
                    """, (current_bot_id,))
                    rooms_data = await cursor.fetchall()
                    logger.info(f"[New Bot Settings Generator] '{current_bot_name}' 봇의 방 {len(rooms_data)}개 로드 완료")
                    
                    # 3-3. 봇별 JSON 구조 생성
                    bot_json = {}
                    
                    for room in rooms_data:
                        room_id = room['room_id']
                        channel_id = room['channel_id']
                        
                        # 방별 설정 로드
                        await cursor.execute("""
                            SELECT setting_key, setting_value, category, description 
                            FROM kb_config_room_specific 
                            WHERE room_id = %s AND is_active = 1
                        """, (room_id,))
                        room_settings = {}
                        for row in await cursor.fetchall():
                            setting_key = row['setting_key']
                            try:
                                setting_value = json.loads(row['setting_value']) if row['setting_value'] else None
                                room_settings[setting_key] = setting_value
                                logger.debug(f"[New Bot Settings Generator] 방별 설정 로드: {current_bot_name}/{channel_id}/{setting_key}")
                            except json.JSONDecodeError as e:
                                logger.warning(f"[New Bot Settings Generator] 방별 설정 JSON 파싱 실패: {current_bot_name}/{channel_id}/{setting_key} - {e}")
                                room_settings[setting_key] = None
                        
                        # 방별 스케줄 로드
                        await cursor.execute("""
                            SELECT schedule_name, schedule_type, schedule_time, schedule_days,
                                   message_content, message_type, is_active
                            FROM kb_config_room_schedules 
                            WHERE room_id = %s AND is_active = 1
                            ORDER BY schedule_time ASC
                        """, (room_id,))
                        schedules_data = await cursor.fetchall()
                        
                        # 계층적 설정 병합 (글로벌 < 봇별 < 방별)
                        room_config = {}
                        
                        # 기본 방 정보
                        room_config['room_name'] = room['room_name']
                        room_config['bot_nickname'] = bot_info['display_name'] or current_bot_name
                        
                        # 설정 병합: 글로벌 -> 봇별 -> 방별 순으로 오버라이드
                        for setting_key in set(list(global_settings.keys()) + list(bot_settings.keys()) + list(room_settings.keys())):
                            final_value = None
                            
                            # 글로벌 설정부터 시작
                            if setting_key in global_settings:
                                final_value = global_settings[setting_key]
                            
                            # 봇별 설정으로 오버라이드
                            if setting_key in bot_settings and bot_settings[setting_key] is not None:
                                final_value = bot_settings[setting_key]
                            
                            # 방별 설정으로 최종 오버라이드
                            if setting_key in room_settings and room_settings[setting_key] is not None:
                                final_value = room_settings[setting_key]
                            
                            if final_value is not None:
                                room_config[setting_key] = final_value
                        
                        # 스케줄 추가
                        if schedules_data:
                            schedules = []
                            for schedule in schedules_data:
                                try:
                                    schedule_days = json.loads(schedule['schedule_days']) if schedule['schedule_days'] else []
                                    schedule_item = {
                                        'days': schedule_days,
                                        'times': [schedule['schedule_time'].strftime('%H:%M')],
                                        'messages': [schedule['message_content']]
                                    }
                                    schedules.append(schedule_item)
                                except json.JSONDecodeError as e:
                                    logger.warning(f"[New Bot Settings Generator] 스케줄 JSON 파싱 실패: {current_bot_name}/{channel_id} - {e}")
                            
                            if schedules:
                                room_config['schedules'] = schedules
                        
                        # None 값 제거
                        room_config = {k: v for k, v in room_config.items() if v is not None}
                        
                        # channel_id를 키로 사용
                        bot_json[channel_id] = room_config
                    
                    # 4. JSON 파일 저장
                    settings_dir = Path("config/bots-settings")
                    settings_dir.mkdir(parents=True, exist_ok=True)
                    
                    output_file_path = settings_dir / f"{current_bot_name}.json"
                    
                    with open(output_file_path, 'w', encoding='utf-8') as f:
                        json.dump(bot_json, f, ensure_ascii=False, indent=2)
                    
                    generated_files.append(str(output_file_path))
                    logger.warning(f"[New Bot Settings Generator] {output_file_path} 파일 생성 완료.")
                
                if bot_name:
                    if generated_files:
                        return True, f"성공적으로 '{bot_name}' 봇 설정 파일을 생성했습니다: {generated_files[0]}"
                    else:
                        return False, f"'{bot_name}' 봇의 설정 데이터를 찾을 수 없습니다."
                else:
                    return True, f"성공적으로 {len(generated_files)}개 봇의 설정 파일을 생성했습니다: {', '.join([Path(f).name for f in generated_files])}"
        
    except Exception as e:
        logger.error(f"[New Bot Settings Generator] 봇 설정 파일 생성 실패: {e}")
        return False, f"파일 생성 실패: {e}"

async def generate_bot_commands_from_new_db(bot_name: str):
    """
    새로운 KB 핵심 테이블들을 사용하여 봇별 명령어 JSON 파일을 생성하고 저장합니다.
    
    Args:
        bot_name (str): 명령어를 생성할 봇 이름.
    
    Returns:
        tuple: (success: bool, message: str)
    """
    try:
        if not hasattr(g, 'db_pool') or g.db_pool is None:
            return False, "데이터베이스 연결 풀이 초기화되지 않았습니다."
            
        async with g.db_pool.acquire() as conn:
            await conn.set_charset('utf8mb4')
            async with conn.cursor(aiomysql.DictCursor) as cursor:
                
                # 1. 봇 정보 확인
                await cursor.execute("""
                    SELECT bot_id, bot_name FROM kb_admin_bots 
                    WHERE bot_name = %s AND is_active = 1
                """, (bot_name,))
                bot_info = await cursor.fetchone()
                
                if not bot_info:
                    return False, f"활성화된 '{bot_name}' 봇을 찾을 수 없습니다."
                
                bot_id = bot_info['bot_id']
                
                # 2. 글로벌 명령어 로드
                logger.info(f"[New Bot Commands Generator] 글로벌 명령어 로딩 중...")
                await cursor.execute("""
                    SELECT gc.command_key, gc.command_name, gc.command_trigger, 
                           gc.command_aliases, gc.command_keywords, gc.handler_type,
                           gc.description, gc.help_text, gc.admin_only, gc.parameters,
                           cc.category_name
                    FROM kb_config_commands_global gc
                    LEFT JOIN kb_config_command_categories cc ON gc.category_id = cc.category_id
                    WHERE gc.is_active = 1
                """)
                global_commands_raw = await cursor.fetchall()
                
                global_commands = {}
                for cmd_row in global_commands_raw:
                    cmd_key = cmd_row['command_key']
                    global_commands[cmd_key] = {}
                    
                    for k, v in cmd_row.items():
                        if k == 'command_key':
                            continue
                        
                        # JSON 필드 파싱
                        if k in ['command_aliases', 'command_keywords', 'parameters'] and v is not None:
                            try:
                                global_commands[cmd_key][k] = json.loads(v)
                            except json.JSONDecodeError as e:
                                logger.warning(f"[New Bot Commands Generator] 글로벌 명령어 '{cmd_key}' {k} JSON 파싱 실패: {e}")
                                global_commands[cmd_key][k] = None
                        else:
                            global_commands[cmd_key][k] = v
                
                logger.info(f"[New Bot Commands Generator] 글로벌 명령어 {len(global_commands)}개 로드 완료")
                
                # 3. 봇별 명령어 구독 및 오버라이드 로드
                logger.info(f"[New Bot Commands Generator] '{bot_name}' 봇별 명령어 구독 로딩 중...")
                await cursor.execute("""
                    SELECT cs.command_id, cs.command_trigger, cs.command_aliases, 
                           cs.command_keywords, cs.admin_only, cs.parameters,
                           gc.command_key
                    FROM kb_config_commands_subscription cs
                    JOIN kb_config_commands_global gc ON cs.command_id = gc.command_id
                    WHERE cs.bot_id = %s AND cs.command_type IN ('bot', 'global') 
                          AND cs.is_active = 1 AND gc.is_active = 1
                """, (bot_id,))
                bot_subscriptions = await cursor.fetchall()
                
                # 최종 명령어 구성
                final_commands = {}
                
                # 구독된 명령어만 포함
                for sub in bot_subscriptions:
                    cmd_key = sub['command_key']
                    
                    if cmd_key in global_commands:
                        # 글로벌 명령어를 기본으로 복사
                        final_commands[cmd_key] = global_commands[cmd_key].copy()
                        
                        # 봇별 오버라이드 적용
                        for k, v in sub.items():
                            if k in ['command_id', 'command_key']:
                                continue
                            
                            if v is not None:  # None이 아니면 오버라이드
                                if k in ['command_aliases', 'command_keywords', 'parameters']:
                                    try:
                                        final_commands[cmd_key][k] = json.loads(v)
                                    except json.JSONDecodeError as e:
                                        logger.warning(f"[New Bot Commands Generator] 봇별 명령어 '{cmd_key}' {k} JSON 파싱 실패: {e}")
                                        final_commands[cmd_key][k] = None
                                else:
                                    final_commands[cmd_key][k] = v
                
                logger.info(f"[New Bot Commands Generator] '{bot_name}' 봇 명령어 {len(final_commands)}개 구성 완료")
                
                # 4. JSON 파일로 저장
                commands_dir = Path(g.BASE_DIR) / "config" / "bots-commands"
                commands_dir.mkdir(parents=True, exist_ok=True)
                
                file_path = commands_dir / f"{bot_name}.json"
                
                with open(file_path, 'w', encoding='utf-8') as f:
                    json.dump(final_commands, f, ensure_ascii=False, indent=4)
                
                logger.info(f"[New Bot Commands Generator] '{bot_name}' 명령어 파일 저장 완료: {file_path}")
                return True, f"'{bot_name}' 봇 명령어 파일이 성공적으로 생성되었습니다."
        
    except Exception as e:
        logger.error(f"[New Bot Commands Generator] '{bot_name}' 봇 명령어 파일 생성 중 오류 발생: {e}", exc_info=True)
        return False, f"'{bot_name}' 봇 명령어 파일 생성 중 오류가 발생했습니다: {e}" 