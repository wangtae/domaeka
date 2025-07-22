-- kb_server_processes 테이블 재생성 스크립트
-- 작성일: 2025-01-22
-- 용도: Python 서버 프로세스 관리 및 Supervisor 통합

-- 기존 테이블 백업 (안전을 위해)
CREATE TABLE IF NOT EXISTS kb_server_processes_backup AS SELECT * FROM kb_server_processes;

-- 기존 테이블 삭제
DROP TABLE IF EXISTS kb_server_processes;

-- 새로운 테이블 생성
CREATE TABLE kb_server_processes (
    -- 기본 식별 정보
    id INT(11) NOT NULL AUTO_INCREMENT COMMENT '프로세스 고유 ID',
    server_id INT(11) NOT NULL COMMENT 'kb_servers 참조 (서버/컨테이너 ID)',
    process_name VARCHAR(100) NOT NULL COMMENT '프로세스명 (예: domaeka-test-01)',
    
    -- 프로세스 설정
    port INT(11) NOT NULL COMMENT '사용할 포트 번호',
    type ENUM('test', 'live') NOT NULL DEFAULT 'test' COMMENT '프로세스 타입 (Python 서버에서 사용)',
    
    -- 런타임 정보
    pid INT(11) DEFAULT NULL COMMENT '실행중인 프로세스 ID',
    status ENUM('stopped', 'starting', 'running', 'stopping', 'error', 'crashed') NOT NULL DEFAULT 'stopped' COMMENT '프로세스 상태',
    
    -- 모니터링 정보
    cpu_usage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'CPU 사용률 (%)',
    memory_usage DECIMAL(10,2) DEFAULT 0.00 COMMENT '메모리 사용량 (MB)',
    last_heartbeat DATETIME DEFAULT NULL COMMENT '마지막 하트비트 시간',
    
    -- Supervisor 통합
    supervisor_host VARCHAR(100) DEFAULT NULL COMMENT 'Supervisor 호스트명 (Docker 서비스명)',
    supervisor_port INT(11) DEFAULT NULL COMMENT 'Supervisor 웹 UI 포트',
    
    -- 메타 정보
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    
    PRIMARY KEY (id),
    UNIQUE KEY uk_process_name (process_name),
    UNIQUE KEY uk_server_port (server_id, port),
    KEY idx_server_id (server_id),
    KEY idx_status (status),
    KEY idx_last_heartbeat (last_heartbeat),
    
    CONSTRAINT fk_server_id FOREIGN KEY (server_id) REFERENCES kb_servers (server_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='서버 프로세스 관리 테이블';

-- 기존 서버 정보 확인 (없으면 추가)
INSERT IGNORE INTO kb_servers (server_name, server_host, server_port, status, description) VALUES
('domaeka-main', 'localhost', 0, 'healthy', 'Domaeka 메인 서버 (Docker Compose)');

-- 서버 ID 가져오기
SET @server_id = (SELECT server_id FROM kb_servers WHERE server_name = 'domaeka-main' LIMIT 1);

-- 프로세스 데이터 입력
INSERT INTO kb_server_processes 
(server_id, process_name, port, type, supervisor_host, supervisor_port, status) 
VALUES
-- TEST 서버들 (1491-1495)
(@server_id, 'domaeka-test-01', 1491, 'test', 'domaeka-server-test-01', 9101, 'stopped'),
(@server_id, 'domaeka-test-02', 1492, 'test', 'domaeka-server-test-02', 9102, 'stopped'),
(@server_id, 'domaeka-test-03', 1493, 'test', 'domaeka-server-test-03', 9103, 'stopped'),

-- LIVE 서버들 (1496-1500)
(@server_id, 'domaeka-live-01', 1496, 'live', 'domaeka-server-live-01', 9111, 'stopped'),
(@server_id, 'domaeka-live-02', 1497, 'live', 'domaeka-server-live-02', 9112, 'stopped'),
(@server_id, 'domaeka-live-03', 1498, 'live', 'domaeka-server-live-03', 9113, 'stopped');

-- 확인 쿼리
SELECT 
    p.id,
    p.process_name,
    s.server_name,
    p.port,
    p.type,
    p.status,
    p.supervisor_host,
    p.supervisor_port
FROM kb_server_processes p
INNER JOIN kb_servers s ON p.server_id = s.server_id
ORDER BY p.process_name;

-- 인덱스 통계 업데이트
ANALYZE TABLE kb_server_processes;