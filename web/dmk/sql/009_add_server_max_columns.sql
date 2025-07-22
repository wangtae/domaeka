-- kb_ping_monitor 테이블에 서버 CPU/메모리 최대값 컬럼 추가
-- 평균값과 함께 최대값을 저장하여 부하 패턴 파악 용이

ALTER TABLE kb_ping_monitor 
ADD COLUMN server_cpu_max DECIMAL(5,2) DEFAULT 0.00 COMMENT '서버 CPU 최대 사용률 (%)' AFTER server_cpu_usage,
ADD COLUMN server_memory_max DECIMAL(10,2) DEFAULT 0.00 COMMENT '서버 메모리 최대 사용량 (MB)' AFTER server_memory_usage;

-- 인덱스 추가 (필요시)
-- ALTER TABLE kb_ping_monitor ADD INDEX idx_server_cpu_max (server_cpu_max);
-- ALTER TABLE kb_ping_monitor ADD INDEX idx_server_memory_max (server_memory_max);