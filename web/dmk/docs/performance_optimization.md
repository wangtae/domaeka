# 도매까 시스템 성능 최적화

## 1. 채팅방 관리 페이지 성능 최적화

## 문제 진단

1. **N+1 쿼리 문제** (해결됨)
   - 각 채팅방마다 지점명을 조회하기 위해 추가 쿼리 실행
   - 20개 채팅방 표시 시 21개의 쿼리 실행

2. **인덱스 부족**
   - `created_at` 컬럼: ORDER BY에 사용되나 인덱스 없음
   - `status` 컬럼: WHERE 조건에 사용되나 인덱스 없음

## 해결 방안

### 1. N+1 쿼리 해결 (완료)
```sql
-- 변경 전: 각 채팅방마다 별도 쿼리
SELECT * FROM kb_rooms ORDER BY created_at DESC
-- 반복문에서: SELECT mb_name FROM g5_member WHERE mb_id = ?

-- 변경 후: JOIN으로 한번에 조회
SELECT r.*, m.mb_name as owner_name 
FROM kb_rooms r 
LEFT JOIN g5_member m ON r.owner_id = m.mb_id 
ORDER BY r.created_at DESC
```

### 2. 권장 인덱스 추가
```sql
-- 정렬 성능 개선
ALTER TABLE kb_rooms ADD INDEX idx_created_at (created_at DESC);

-- 필터링 성능 개선
ALTER TABLE kb_rooms ADD INDEX idx_status (status);

-- 복합 인덱스 (상태별 최신순 조회)
ALTER TABLE kb_rooms ADD INDEX idx_status_created (status, created_at DESC);
```

### 3. 추가 최적화 제안
- 봇 목록 조회 쿼리 캐싱 고려
- 상태별 통계 쿼리를 메인 쿼리와 통합 가능
- JSON 필드(room_owners, log_settings) 파싱을 필요시에만 수행

## 예상 성능 개선
- 쿼리 수: 21개 → 3개 (메인, 봇목록, 통계)
- 응답 시간: 약 70-80% 개선 예상

## 2. 스케줄링 발송 로그 테이블 최적화

### 문제 진단
- `kb_schedule_logs` 테이블의 `sent_images_1`, `sent_images_2` 컬럼에 Base64 이미지 전체 데이터 저장
- 각 이미지가 수 MB 크기인 경우 로그 한 건당 10MB 이상 차지 가능
- 로그 목록 조회 시 불필요한 대용량 데이터 전송

### 해결 방안

#### 1. 이미지 데이터 대신 메타 정보만 저장 (완료)
```python
# 변경 전: Base64 이미지 전체 데이터 저장
sent_images_1 = schedule.get('message_images_1')  # JSON 배열의 Base64 데이터

# 변경 후: 이미지 개수 정보만 저장
images_1_list = json.loads(schedule['message_images_1'])
images_1_info = f"[이미지 {len(images_1_list)}장 전송]"
```

#### 2. 구현 내용
- `scheduler_service.py`의 `log_send_result` 함수 수정
- 이미지 전체 데이터 대신 "[이미지 N장 전송]" 형태로 저장
- 발송 내역 확인에 필요한 정보만 유지

### 예상 성능 개선
- 저장 공간: 로그 한 건당 10MB → 100B (99.99% 감소)
- 조회 속도: 대용량 텍스트 필드 로드 제거로 10배 이상 개선
- 네트워크 트래픽: 목록 조회 시 전송 데이터 대폭 감소

### 기존 데이터 정리 SQL
```sql
-- 기존 이미지 데이터를 개수 정보로 변환 (선택사항)
UPDATE kb_schedule_logs 
SET sent_images_1 = CASE 
    WHEN sent_images_1 IS NOT NULL AND sent_images_1 != '' 
    THEN '[이미지 데이터 있음]' 
    ELSE NULL 
END,
sent_images_2 = CASE 
    WHEN sent_images_2 IS NOT NULL AND sent_images_2 != '' 
    THEN '[이미지 데이터 있음]' 
    ELSE NULL 
END
WHERE sent_images_1 IS NOT NULL OR sent_images_2 IS NOT NULL;
```