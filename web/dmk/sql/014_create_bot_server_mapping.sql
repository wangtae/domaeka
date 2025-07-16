/**
 * [선택사항] 봇-서버 매핑 테이블
 * 
 * 주의: 간소화된 구현에서는 이 테이블이 필요하지 않습니다.
 * 각 서버가 메모리에서 연결된 봇을 관리하는 방식을 사용합니다.
 * 
 * 이 테이블은 다음과 같은 경우에만 생성하세요:
 * - 봇 연결 이력을 추적하고 싶은 경우
 * - 서버 간 봇 분포를 모니터링하고 싶은 경우
 * - 중앙 집중식 봇 관리가 필요한 경우
 */

-- [선택사항] kb_bot_server_mapping 테이블 생성
-- CREATE TABLE IF NOT EXISTS `kb_bot_server_mapping` (
--   `bot_name` varchar(30) NOT NULL COMMENT '봇 이름',
--   `server_id` varchar(50) NOT NULL COMMENT '서버 식별자 (host:port)',
--   `connected_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '연결 시작 시간',
--   `last_ping_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '마지막 ping 수신 시간',
--   `is_active` tinyint(1) DEFAULT 1 COMMENT '활성 연결 여부',
--   PRIMARY KEY (`bot_name`),
--   KEY `idx_server_active` (`server_id`, `is_active`),
--   KEY `idx_last_ping` (`last_ping_at`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='봇-서버 연결 매핑 정보';

-- kb_servers 테이블에 필요한 컬럼 추가 (이미 테이블이 있다면)
ALTER TABLE `kb_servers` 
  ADD COLUMN IF NOT EXISTS `last_heartbeat` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '마지막 하트비트 시간',
  ADD COLUMN IF NOT EXISTS `server_port` int(11) NOT NULL DEFAULT 0 COMMENT '서버 포트' AFTER `server_host`;

-- 인덱스 추가
ALTER TABLE `kb_servers` 
  ADD KEY IF NOT EXISTS `idx_heartbeat` (`last_heartbeat`, `status`),
  ADD UNIQUE KEY IF NOT EXISTS `idx_host_port` (`server_host`, `server_port`);

-- 초기 데이터 정리 (비활성 연결 정리)
-- 실행 시 60초 이상 ping이 없는 연결은 비활성화
UPDATE `kb_bot_server_mapping` 
SET `is_active` = 0 
WHERE `last_ping_at` < DATE_SUB(NOW(), INTERVAL 60 SECOND) 
  AND `is_active` = 1;