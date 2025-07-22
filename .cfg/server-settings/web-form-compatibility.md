# 웹 관리자 폼 호환성 가이드

## 현재 상황

`server_process_form.php`의 필드와 새로운 `kb_server_processes` 테이블 구조가 다릅니다. 
다음 중 하나를 선택해야 합니다:

### 옵션 1: 웹 폼 수정 (권장)

server_process_form.php에서 다음 부분을 수정:

```php
// 변경 전
<select name="process_type">
    <option value="main">메인 프로세스</option>
    <option value="worker">워커 프로세스</option>
    <option value="monitor">모니터링 프로세스</option>
    <option value="backup">백업 프로세스</option>
</select>

// 변경 후
<select name="type">
    <option value="test">테스트</option>
    <option value="live">라이브</option>
</select>
```

제거할 필드들:
- `mode` (실행 모드) - `type` 필드로 통합
- `log_level` - 별도 설정 파일로 관리
- `auto_restart` - Supervisor에서 관리
- `description` - 필요시 별도 테이블로 분리

### 옵션 2: 확장 테이블 생성

웹 관리자 전용 확장 정보를 위한 별도 테이블:

```sql
CREATE TABLE kb_server_processes_extended (
    process_id INT(11) NOT NULL,
    process_category ENUM('main', 'worker', 'monitor', 'backup') DEFAULT 'main',
    mode ENUM('test', 'prod', 'dev') DEFAULT 'test',
    log_level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR') DEFAULT 'INFO',
    auto_restart ENUM('Y', 'N') DEFAULT 'Y',
    description TEXT,
    
    PRIMARY KEY (process_id),
    CONSTRAINT fk_process_id FOREIGN KEY (process_id) 
        REFERENCES kb_server_processes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 권장사항

**옵션 1 (웹 폼 수정)을 권장**합니다. 이유:
1. 시스템 전체의 일관성 유지
2. Python 서버와 웹 관리자가 동일한 데이터 구조 사용
3. 유지보수가 간단함
4. 불필요한 복잡성 제거

## 수정이 필요한 웹 파일들

1. `/web/adm/server_process_form.php` - 폼 필드 수정
2. `/web/adm/server_process_form_update.php` - 저장 로직 수정
3. `/web/adm/server_process_list.php` - 목록 표시는 이미 호환됨