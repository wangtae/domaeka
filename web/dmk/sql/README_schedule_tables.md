# 스케줄링 시스템 데이터베이스 설정 가이드

## 개요
카카오봇 스케줄링 시스템을 위한 데이터베이스 테이블 생성 및 설정 가이드입니다.

## 실행 순서

### 1. 스케줄 관련 테이블 확인 및 생성
```bash
# 이미 kb_schedule, kb_schedule_logs 테이블이 존재하는 경우 건너뜁니다
mysql -u root -p domaeka < 013_create_schedule_tables.sql
```

### 2. 봇 디바이스 이미지 설정 추가 (이미 실행했다면 건너뜁니다)
```bash
mysql -u root -p domaeka < 012_add_image_resize_settings.sql
```

### 3. [선택사항] kb_servers 테이블 업데이트
```bash
# 서버 하트비트 기능이 필요한 경우에만 실행
mysql -u root -p domaeka < 014_create_bot_server_mapping.sql
```

**참고**: 간소화된 구현에서는 `kb_bot_server_mapping` 테이블이 필요하지 않습니다. 각 서버가 메모리에서 연결된 봇을 관리합니다.

## 테이블 구조

### kb_schedule (스케줄 설정)
- 스케줄링 발송 설정을 저장
- 1회성, 매일, 주간 반복 지원
- 텍스트 + 이미지 그룹 2개까지 지원

### kb_schedule_logs (발송 로그)
- 모든 발송 시도 및 결과 기록
- 성공/실패/부분성공 상태 추적
- API 응답 및 오류 메시지 저장

### kb_bot_server_mapping (봇-서버 매핑)
- 현재 어떤 봇이 어떤 서버에 연결되어 있는지 추적
- ping 기반 자동 연결 상태 관리
- 다중 서버 환경에서 필수

### kb_servers (서버 정보)
- 각 서버의 상태 및 용량 관리
- 하트비트 기반 서버 상태 추적
- 부하 분산을 위한 정보 제공

## 주요 인덱스

1. **스케줄 폴링 최적화**
   - `idx_schedule_polling`: 활성 스케줄 빠른 조회
   - `idx_target_bot`: 봇별 스케줄 조회

2. **봇-서버 매핑 최적화**
   - `idx_server_active`: 서버별 활성 봇 조회
   - `idx_last_ping`: 타임아웃 봇 정리

3. **로그 조회 최적화**
   - `idx_status_time`: 상태별 시간별 로그 조회

## 운영 시 주의사항

1. **정기적인 데이터 정리**
   ```sql
   -- 30일 이상 된 로그 삭제
   DELETE FROM kb_schedule_logs 
   WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
   
   -- 비활성 봇 매핑 정리
   DELETE FROM kb_bot_server_mapping 
   WHERE is_active = 0 
   AND last_ping_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
   ```

2. **인덱스 최적화**
   ```sql
   -- 인덱스 통계 업데이트
   ANALYZE TABLE kb_schedule;
   ANALYZE TABLE kb_schedule_logs;
   ANALYZE TABLE kb_bot_server_mapping;
   ```

3. **테이블 파티셔닝 (대용량 운영 시)**
   - kb_schedule_logs 테이블은 월별 파티셔닝 권장
   - kb_chat_logs와 동일한 파티셔닝 전략 적용

## 트러블슈팅

### Collation 오류 발생 시
```sql
-- 테이블 collation 통일
ALTER TABLE kb_schedule CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE kb_schedule_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE kb_bot_server_mapping CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 외래키 오류 발생 시
```sql
-- 외래키 임시 비활성화
SET FOREIGN_KEY_CHECKS = 0;
-- SQL 실행
SET FOREIGN_KEY_CHECKS = 1;
```