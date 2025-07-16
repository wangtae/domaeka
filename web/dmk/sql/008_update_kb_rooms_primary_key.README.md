# kb_rooms 테이블 구조 개선 가이드

## 변경 사항

### 문제점
- 기존: `room_id`만 PRIMARY KEY로 사용하여 같은 채팅방에 여러 봇이 있을 경우 구분 불가
- `room_id` = `{channel_id}_{bot_name}` 형식으로 생성되어 봇별 디바이스 구분 불가

### 해결 방안
- PRIMARY KEY를 `(room_id, bot_name, device_id)` 복합키로 변경
- `room_id`는 순수 채널 ID만 저장
- 같은 채팅방에 여러 봇/디바이스가 존재할 수 있도록 지원

## 적용 방법

### 1. 데이터베이스 마이그레이션

```bash
# 주의: 기존 데이터가 있는 경우 백업 필수!
mysql -u root -p domaeka < 008_update_kb_rooms_primary_key.sql
```

### 2. 서버 재시작

서버 코드가 이미 수정되어 있으므로 DB 마이그레이션 후 서버 재시작:

```bash
# 서버 재시작
python main.py --port=1490 --mode=test
```

### 3. 웹 관리자 페이지 확인

- 채팅방 관리 페이지에서 정상 작동 확인
- 수정 시 봇별로 구분되어 표시되는지 확인

## 변경된 파일

### 서버 (Python)
- `server/database/db_utils.py`
  - `check_room_approval()`: bot_name, device_id로 특정 봇의 방 조회
  - `register_room_if_new()`: device_id 필수로 변경

### 웹 관리자 (PHP)
- `web/dmk/adm/bot/room_list.php`: URL 파라미터에 bot_name, device_id 추가
- `web/dmk/adm/bot/room_form.php`: 복합키 조회 지원
- `web/dmk/adm/bot/room_form_update.php`: 복합키 업데이트 지원

## 주의사항

1. **기존 데이터 마이그레이션**
   - 기존 `room_id`에서 봇 이름 부분이 제거됨
   - 예: `18445682959392711_LOA.i` → `18445682959392711`

2. **device_id 필수**
   - 새로운 구조에서는 device_id가 필수
   - device_id가 없는 기존 데이터는 수동으로 처리 필요

3. **호환성**
   - 웹 관리자 페이지는 마이그레이션 전후 모두 작동하도록 구현
   - 서버는 device_id가 없으면 방 등록 불가