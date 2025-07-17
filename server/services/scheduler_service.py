"""
스케줄링 메시지 발송 서비스
kb_schedule 테이블의 스케줄에 따라 카카오톡 메시지를 자동 발송
"""
import asyncio
import json
import time
from datetime import datetime, timedelta
from typing import List, Dict, Any, Optional
from core.logger import logger
import core.globals as g
from core.response_utils import send_json_response
import os


class SchedulerService:
    """스케줄 메시지 발송 서비스"""
    
    def __init__(self):
        self.polling_interval = 60  # 1분마다 폴링
        self.ping_timeout = 60  # 60초 이상 ping 없으면 비활성으로 간주
        self.catch_up_window = 300  # 5분 이내 미발송 건 처리
        self.running = False
        self.db_pool = None
        
    async def start(self):
        """스케줄러 시작"""
        logger.info("[SCHEDULER] 스케줄러 서비스 시작")
        self.running = True
        self.db_pool = g.db_pool
        
        # 서버 시작 후 잠시 대기 (봇들이 연결될 시간 확보)
        await asyncio.sleep(10)
        
        # 미발송 건 처리
        await self.handle_missed_schedules()
        
        # 폴링 시작 - 매분 정시에 실행
        while self.running:
            try:
                # 현재 시간
                now = datetime.now()
                
                # 다음 정시까지 대기 시간 계산
                seconds_until_next_minute = 60 - now.second
                if seconds_until_next_minute == 60:
                    seconds_until_next_minute = 0
                
                # 다음 정시까지 대기
                if seconds_until_next_minute > 0:
                    logger.debug(f"[SCHEDULER] {seconds_until_next_minute}초 후 정시 실행")
                    await asyncio.sleep(seconds_until_next_minute)
                
                # 정시에 폴링 실행
                await self.poll_and_process()
                
                # 다음 실행까지 남은 시간 계산 (처리 시간 고려)
                now = datetime.now()
                remaining_seconds = 60 - now.second
                if remaining_seconds > 0:
                    await asyncio.sleep(remaining_seconds)
                    
            except Exception as e:
                logger.error(f"[SCHEDULER] 폴링 중 오류: {e}")
                # 오류 발생 시에도 다음 정시까지 대기
                now = datetime.now()
                await asyncio.sleep(60 - now.second)
    
    async def stop(self):
        """스케줄러 중지"""
        logger.info("[SCHEDULER] 스케줄러 서비스 중지")
        self.running = False
    
    def get_active_bots(self) -> List[str]:
        """현재 활성 봇 목록 조회"""
        from core.globals import clients
        
        active_bots = []
        
        # clients는 {(bot_name, device_id): writer} 형태로 관리됨
        for (bot_name, device_id), writer in clients.items():
            if writer and not writer.is_closing():
                if bot_name not in active_bots:
                    active_bots.append(bot_name)
        
        logger.debug(f"[SCHEDULER] 활성 클라이언트: {list(clients.keys())}")
        
        return active_bots
    
    def is_bot_connected(self, bot_name: str) -> bool:
        """특정 봇 연결 상태 확인"""
        from core.globals import clients
        
        # clients에서 해당 봇이 연결되어 있는지 확인
        for (bot, device_id), writer in clients.items():
            if bot == bot_name and writer and not writer.is_closing():
                return True
        
        return False
    
    async def poll_and_process(self):
        """스케줄 폴링 및 처리"""
        now = datetime.now()
        
        # 현재 연결된 봇 목록
        active_bots = self.get_active_bots()
        if not active_bots:
            logger.debug("[SCHEDULER] 연결된 봇이 없어 스케줄 처리 건너뜀")
            return
        
        logger.debug(f"[SCHEDULER] 활성 봇 목록: {active_bots}")
        
        # 발송할 스케줄 조회
        schedules = await self.get_pending_schedules(active_bots)
        if not schedules:
            return
            
        logger.info(f"[SCHEDULER] 발송 대기 스케줄 {len(schedules)}건")
        
        # 봇별로 스케줄 그룹화 (같은 봇에 여러 메시지가 있을 경우 순차 처리)
        bot_schedules = {}
        for schedule in schedules:
            bot_name = schedule['target_bot_name']
            if bot_name not in bot_schedules:
                bot_schedules[bot_name] = []
            bot_schedules[bot_name].append(schedule)
        
        # 여러 봇이 있을 경우 봇별로 분산 실행
        if len(bot_schedules) > 1:
            # 다음 정시까지 남은 시간을 고려하여 간격 조정
            remaining_seconds = 60 - now.second - 5  # 5초 여유
            interval = min(10, max(1, remaining_seconds // len(bot_schedules)))
            logger.info(f"[SCHEDULER] {len(bot_schedules)}개 봇에 {interval}초 간격으로 분산 실행")
            
            # 각 봇의 스케줄을 비동기 태스크로 생성하여 분산 실행
            tasks = []
            for i, (bot_name, bot_schedule_list) in enumerate(bot_schedules.items()):
                delay = i * interval
                task = asyncio.create_task(self._process_bot_schedules(bot_name, bot_schedule_list, delay))
                tasks.append(task)
            
            # 모든 태스크 완료 대기
            await asyncio.gather(*tasks, return_exceptions=True)
        else:
            # 1개 봇만 있으면 즉시 처리
            bot_name = list(bot_schedules.keys())[0]
            await self._process_bot_schedules(bot_name, bot_schedules[bot_name], 0)
    
    async def _process_bot_schedules(self, bot_name: str, schedules: List[Dict], delay: int):
        """특정 봇의 스케줄들을 처리"""
        if delay > 0:
            await asyncio.sleep(delay)
        
        # 봇 연결 확인
        if not self.is_bot_connected(bot_name):
            logger.warning(f"[SCHEDULER] 봇 연결 끊김, {len(schedules)}건 스케줄 건너뜀: {bot_name}")
            return
        
        # 같은 봇의 여러 스케줄은 순차적으로 처리
        for schedule in schedules:
            try:
                await self.process_schedule(schedule)
                # 같은 봇에 연속 발송 시 짧은 대기
                if len(schedules) > 1:
                    await asyncio.sleep(0.5)
            except Exception as e:
                logger.error(f"[SCHEDULER] 스케줄 처리 실패 {schedule['id']}: {e}")
    
    async def _process_schedule_with_delay(self, schedule: Dict, delay: int):
        """지연 시간 후 스케줄 처리"""
        if delay > 0:
            await asyncio.sleep(delay)
        
        # 처리 직전 봇 연결 재확인
        if self.is_bot_connected(schedule['target_bot_name']):
            await self.process_schedule(schedule)
        else:
            logger.warning(f"[SCHEDULER] 봇 연결 끊김, 스케줄 건너뜀: {schedule['target_bot_name']}")
    
    async def get_pending_schedules(self, bot_names: List[str]) -> List[Dict]:
        """발송 대기 중인 스케줄 조회"""
        if not bot_names or not self.db_pool:
            return []
        
        bot_placeholders = ','.join(['%s'] * len(bot_names))
        
        query = f"""
            SELECT s.* 
            FROM kb_schedule s
            WHERE s.status = 'active'
            AND s.target_bot_name IN ({bot_placeholders})
            AND NOW() BETWEEN s.valid_from AND s.valid_until
            AND s.next_send_at <= NOW()
            AND (s.last_sent_at IS NULL OR s.last_sent_at < s.next_send_at)
            ORDER BY s.next_send_at ASC, s.id ASC
            LIMIT 10
        """
        
        async with self.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                await cursor.execute(query, bot_names)
                columns = [desc[0] for desc in cursor.description]
                rows = await cursor.fetchall()
                
                schedules = []
                for row in rows:
                    schedule = dict(zip(columns, row))
                    schedules.append(schedule)
                
                return schedules
    
    async def process_schedule(self, schedule: Dict):
        """스케줄 처리"""
        try:
            logger.info(f"[SCHEDULER] 스케줄 처리 시작: {schedule['id']} - {schedule['title']}")
            
            # 처리 시작 즉시 last_sent_at 업데이트 (중복 방지)
            result = await self.update_last_sent_at(schedule['id'])
            if not result:
                logger.warning(f"[SCHEDULER] 이미 다른 서버가 처리함: {schedule['id']}")
                return
            
            # 실제 메시지 발송
            success = await self.send_scheduled_messages(schedule)
            
            # 발송 결과 로깅
            await self.log_send_result(schedule, 'success' if success else 'failed')
            
            # 다음 발송 시간 계산 및 업데이트
            next_send_at = self.calculate_next_send_time(schedule)
            if next_send_at:
                await self.update_next_send_time(schedule['id'], next_send_at)
            else:
                # 1회성 스케줄 완료
                await self.complete_schedule(schedule['id'])
            
            logger.info(f"[SCHEDULER] 스케줄 처리 완료: {schedule['id']}")
            
        except Exception as e:
            logger.error(f"[SCHEDULER] 스케줄 처리 실패 {schedule['id']}: {e}")
            await self.log_send_result(schedule, 'failed', str(e))
    
    async def send_scheduled_messages(self, schedule: Dict) -> bool:
        """스케줄에 따른 메시지 전송"""
        try:
            bot_name = schedule['target_bot_name']
            device_id = schedule.get('target_device_id', '')  # device_id 추가
            room_id = schedule['target_room_id']
            
            # device_id가 없는 경우 경고
            if not device_id:
                logger.warning(f"[SCHEDULER] device_id 없음, 스케줄 {schedule['id']} 건너뜀")
                return False
            
            # 메시지 구성 요소
            text = schedule.get('message_text')
            images_1 = json.loads(schedule.get('message_images_1', '[]'))
            images_2 = json.loads(schedule.get('message_images_2', '[]'))
            interval = schedule.get('send_interval_seconds', 1)
            media_wait_1 = schedule.get('media_wait_time_1', 0)
            media_wait_2 = schedule.get('media_wait_time_2', 0)
            
            # 발송할 구성 요소 결정
            components = []
            if text:
                components.append(('text', text, None))
            if images_1:
                components.append(('images', images_1, media_wait_1))
            if images_2:
                components.append(('images', images_2, media_wait_2))
            
            if not components:
                logger.warning(f"[SCHEDULER] 발송할 내용 없음: {schedule['id']}")
                return False
            
            # 순차적으로 발송
            for i, (comp_type, content, wait_time) in enumerate(components):
                if comp_type == 'text':
                    await self.send_text_message(bot_name, device_id, room_id, content)
                elif comp_type == 'images':
                    await self.send_images(bot_name, device_id, room_id, content, wait_time)
                
                # 다음 컴포넌트가 있으면 대기
                if i < len(components) - 1:
                    await asyncio.sleep(interval)
            
            return True
            
        except Exception as e:
            logger.error(f"[SCHEDULER] 메시지 발송 실패: {e}")
            return False
    
    async def send_text_message(self, bot_name: str, device_id: str, room_id: str, text: str):
        """텍스트 메시지 발송"""
        # 연결된 클라이언트 찾기
        from core.globals import clients
        from core.response_utils import send_message_response
        
        # bot_name과 device_id로 정확한 클라이언트 찾기
        client_key = (bot_name, device_id)
        if client_key in clients:
            writer = clients[client_key]
            if writer and not writer.is_closing():
                # context 구성
                context = {
                    'client_key': client_key,
                    'room': room_id,
                    'channel_id': room_id,
                    'bot_name': bot_name,
                    'writer': writer
                }
                await send_message_response(context, text)
                logger.info(f"[SCHEDULER] 텍스트 발송: {bot_name}@{device_id} -> {room_id}")
                return
        
        logger.warning(f"[SCHEDULER] 봇 연결 없음: {bot_name}@{device_id}")
    
    async def send_images(self, bot_name: str, device_id: str, room_id: str, images: List[Dict], wait_time: int):
        """이미지 발송 (Base64 방식)"""
        from core.globals import clients
        from core.response_utils import send_message_response
        
        # bot_name과 device_id로 정확한 클라이언트 찾기
        client_key = (bot_name, device_id)
        if client_key in clients:
            writer = clients[client_key]
            if writer and not writer.is_closing():
                # Base64 이미지 데이터 리스트 생성
                base64_images = []
                for img_info in images:
                    # img_info는 {'base64': 'base64data...', 'name': 'filename.jpg'} 형태
                    base64_data = img_info.get('base64', '')
                    if base64_data:
                        base64_images.append(base64_data)
                    else:
                        logger.warning(f"[SCHEDULER] Base64 데이터 없음")
                
                if base64_images:
                    # context 구성
                    context = {
                        'client_key': client_key,
                        'room': room_id,
                        'channel_id': room_id,
                        'bot_name': bot_name,
                        'writer': writer
                    }
                    
                    # IMAGE_BASE64 형식으로 전송
                    image_message = f"IMAGE_BASE64:{'|||'.join(base64_images)}"
                    await send_message_response(context, image_message, media_wait_time=wait_time)
                    logger.info(f"[SCHEDULER] 이미지 {len(base64_images)}개 발송: {bot_name}@{device_id} -> {room_id}")
                    return
        
        logger.warning(f"[SCHEDULER] 봇 연결 없음: {bot_name}@{device_id}")
    
    async def update_last_sent_at(self, schedule_id: int) -> bool:
        """last_sent_at 업데이트 (중복 방지)"""
        if not self.db_pool:
            return False
        
        query = """
            UPDATE kb_schedule 
            SET last_sent_at = NOW()
            WHERE id = %s 
            AND (last_sent_at IS NULL OR last_sent_at < next_send_at)
        """
        
        async with self.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                await cursor.execute(query, [schedule_id])
                await conn.commit()
                return cursor.rowcount > 0
    
    async def update_next_send_time(self, schedule_id: int, next_send_at: datetime):
        """다음 발송 시간 업데이트"""
        if not self.db_pool:
            return
        
        query = """
            UPDATE kb_schedule 
            SET next_send_at = %s, send_count = send_count + 1
            WHERE id = %s
        """
        
        async with self.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                await cursor.execute(query, [next_send_at, schedule_id])
                await conn.commit()
    
    async def complete_schedule(self, schedule_id: int):
        """스케줄 완료 처리"""
        if not self.db_pool:
            return
        
        query = """
            UPDATE kb_schedule 
            SET status = 'completed', send_count = send_count + 1
            WHERE id = %s
        """
        
        async with self.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                await cursor.execute(query, [schedule_id])
                await conn.commit()
    
    def calculate_next_send_time(self, schedule: Dict) -> Optional[datetime]:
        """다음 발송 시간 계산"""
        if schedule['schedule_type'] == 'once':
            return None  # 1회성은 다음 발송 없음
        
        # schedule_time을 time 객체로 변환
        schedule_time = schedule.get('schedule_time')
        if isinstance(schedule_time, str):
            # HH:MM:SS 형식의 문자열인 경우
            schedule_time = datetime.strptime(schedule_time, '%H:%M:%S').time()
        elif isinstance(schedule_time, timedelta):
            # timedelta인 경우 시간으로 변환
            hours = int(schedule_time.total_seconds() // 3600)
            minutes = int((schedule_time.total_seconds() % 3600) // 60)
            seconds = int(schedule_time.total_seconds() % 60)
            schedule_time = datetime.now().replace(hour=hours, minute=minutes, second=seconds).time()
        
        if schedule['schedule_type'] == 'daily':
            # 매일 반복: 다음날 같은 시간
            next_date = datetime.now().date() + timedelta(days=1)
            return datetime.combine(next_date, schedule_time)
        
        elif schedule['schedule_type'] == 'weekly':
            # 주간 반복: 다음 해당 요일 같은 시간
            current_date = datetime.now().date()
            weekdays = schedule['schedule_weekdays'].split(',') if schedule['schedule_weekdays'] else []
            
            for i in range(1, 8):  # 최대 7일 후까지 검색
                check_date = current_date + timedelta(days=i)
                weekday_name = check_date.strftime('%A').lower()
                
                if weekday_name in weekdays:
                    return datetime.combine(check_date, schedule_time)
        
        return None
    
    async def log_send_result(self, schedule: Dict, status: str, error_message: str = None):
        """발송 결과 로깅"""
        if not self.db_pool:
            return
        
        query = """
            INSERT INTO kb_schedule_logs 
            (schedule_id, target_room_id, sent_message_text, sent_images_1, sent_images_2,
             send_components, status, error_message, scheduled_at, started_at, completed_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        
        # 발송된 구성 요소 확인
        components = []
        if schedule.get('message_text'):
            components.append('text')
        if schedule.get('message_images_1') and schedule['message_images_1'] != '[]':
            components.append('images_1')
        if schedule.get('message_images_2') and schedule['message_images_2'] != '[]':
            components.append('images_2')
        
        async with self.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                await cursor.execute(query, [
                    schedule['id'],
                    schedule['target_room_id'],
                    schedule.get('message_text'),
                    schedule.get('message_images_1'),
                    schedule.get('message_images_2'),
                    ','.join(components),
                    status,
                    error_message,
                    schedule['next_send_at'],
                    datetime.now(),
                    datetime.now() if status == 'success' else None
                ])
                await conn.commit()
    
    async def handle_missed_schedules(self):
        """서버 재시작 시 미발송 건 처리"""
        logger.info("[SCHEDULER] 미발송 스케줄 확인 중...")
        
        active_bots = self.get_active_bots()
        if not active_bots:
            logger.info("[SCHEDULER] 연결된 봇 없음, 미발송 처리 건너뜀")
            return
        
        missed_schedules = await self.get_missed_schedules(active_bots)
        logger.info(f"[SCHEDULER] 미발송 스케줄 {len(missed_schedules)}건 발견")
        
        for schedule in missed_schedules:
            if await self.check_not_already_sent(schedule):
                await self.process_schedule(schedule)
    
    async def get_missed_schedules(self, bot_names: List[str]) -> List[Dict]:
        """미발송 스케줄 조회"""
        if not bot_names or not self.db_pool:
            return []
        
        bot_placeholders = ','.join(['%s'] * len(bot_names))
        
        query = f"""
            SELECT s.* FROM kb_schedule s
            WHERE s.status = 'active'
            AND s.target_bot_name IN ({bot_placeholders})
            AND s.next_send_at < NOW()
            AND s.next_send_at > DATE_SUB(NOW(), INTERVAL %s SECOND)
            AND (s.last_sent_at IS NULL OR s.last_sent_at < s.next_send_at)
            ORDER BY s.next_send_at ASC
        """
        
        async with self.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                await cursor.execute(query, bot_names + [self.catch_up_window])
                columns = [desc[0] for desc in cursor.description]
                rows = await cursor.fetchall()
                
                schedules = []
                for row in rows:
                    schedule = dict(zip(columns, row))
                    schedules.append(schedule)
                
                return schedules
    
    async def check_not_already_sent(self, schedule: Dict) -> bool:
        """중복 발송 방지를 위한 로그 확인"""
        if not self.db_pool:
            return False
        
        query = """
            SELECT COUNT(*) as cnt FROM kb_schedule_logs
            WHERE schedule_id = %s
            AND scheduled_at = %s
            AND status IN ('success', 'partial')
        """
        
        async with self.db_pool.acquire() as conn:
            async with conn.cursor() as cursor:
                await cursor.execute(query, [schedule['id'], schedule['next_send_at']])
                row = await cursor.fetchone()
                return row[0] == 0


# 전역 스케줄러 인스턴스
scheduler_service = SchedulerService()