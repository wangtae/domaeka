-- 009_upgrade_kb_schedule_for_messages.sql
-- kb_schedule 테이블을 통합 메시지 시스템으로 업그레이드

-- 1. kb_schedule 테이블에 메시지 타입 필드 추가
ALTER TABLE `kb_schedule` 
ADD COLUMN `message_type` ENUM('schedule', 'order_complete', 'stock_warning', 'stock_out') DEFAULT 'schedule' COMMENT '메시지 타입' AFTER `description`,
ADD COLUMN `reference_type` VARCHAR(50) DEFAULT NULL COMMENT '참조 타입 (order, item 등)' AFTER `message_type`,
ADD COLUMN `reference_id` VARCHAR(100) DEFAULT NULL COMMENT '참조 ID (주문번호, 상품코드 등)' AFTER `reference_type`,
ADD COLUMN `template_variables` JSON DEFAULT NULL COMMENT '템플릿 변수 데이터' AFTER `reference_id`,
ADD COLUMN `processed_message` TEXT DEFAULT NULL COMMENT '변수 치환된 최종 메시지' AFTER `template_variables`,
ADD COLUMN `delay_minutes` INT(5) DEFAULT 0 COMMENT '생성 시점부터 발송까지 지연 시간(분)' AFTER `send_interval_seconds`;

-- 2. dmk_branch 테이블에 메시지 발송 지연 시간 설정 필드 추가
ALTER TABLE `dmk_branch`
ADD COLUMN `br_order_msg_delay` INT(5) DEFAULT 5 COMMENT '주문 완료 메시지 발송 지연 시간(분)' AFTER `br_order_msg_enabled`,
ADD COLUMN `br_stock_warning_msg_delay` INT(5) DEFAULT 10 COMMENT '품절 임박 메시지 발송 지연 시간(분)' AFTER `br_stock_warning_msg_enabled`,
ADD COLUMN `br_stock_out_msg_delay` INT(5) DEFAULT 5 COMMENT '품절 메시지 발송 지연 시간(분)' AFTER `br_stock_out_msg_enabled`;

-- 3. 메시지 타입별 인덱스 추가 (성능 최적화)
ALTER TABLE `kb_schedule`
ADD INDEX `idx_message_type_status` (`message_type`, `status`),
ADD INDEX `idx_reference` (`reference_type`, `reference_id`),
ADD INDEX `idx_schedule_datetime` (`schedule_date`, `schedule_time`);

-- 4. 기존 스케줄 데이터의 message_type을 'schedule'로 업데이트
UPDATE `kb_schedule` 
SET `message_type` = 'schedule' 
WHERE `message_type` IS NULL;

-- 5. 메시지 템플릿 파서 함수 (MySQL Function)
DELIMITER $$

DROP FUNCTION IF EXISTS `parse_message_template`$$

CREATE FUNCTION `parse_message_template`(
    template TEXT,
    variables JSON
) RETURNS TEXT
DETERMINISTIC
BEGIN
    DECLARE parsed_message TEXT;
    DECLARE i INT DEFAULT 0;
    DECLARE var_count INT;
    DECLARE var_key VARCHAR(100);
    DECLARE var_value VARCHAR(500);
    
    SET parsed_message = template;
    
    -- JSON 배열의 길이 확인
    IF JSON_TYPE(variables) = 'OBJECT' THEN
        SET var_count = JSON_LENGTH(JSON_KEYS(variables));
        
        -- 각 변수를 순회하며 치환
        WHILE i < var_count DO
            SET var_key = JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(variables), CONCAT('$[', i, ']')));
            SET var_value = JSON_UNQUOTE(JSON_EXTRACT(variables, CONCAT('$.', var_key)));
            
            -- {변수명} 형식을 실제 값으로 치환
            SET parsed_message = REPLACE(parsed_message, CONCAT('{', var_key, '}'), IFNULL(var_value, ''));
            
            SET i = i + 1;
        END WHILE;
    END IF;
    
    RETURN parsed_message;
END$$

DELIMITER ;

-- 6. 자동 메시지 등록을 위한 트리거 생성 예시 (주문 완료 시)
-- 주의: 실제 구현은 PHP 코드에서 처리하는 것이 권장됨
/*
DELIMITER $$

CREATE TRIGGER `after_order_complete`
AFTER UPDATE ON `g5_shop_order`
FOR EACH ROW
BEGIN
    -- 주문 상태가 '완료'로 변경되었을 때
    IF NEW.od_status = '완료' AND OLD.od_status != '완료' THEN
        -- kb_schedule에 메시지 발송 예약 등록
        -- 실제 구현은 PHP에서 처리
    END IF;
END$$

DELIMITER ;
*/

-- 7. 뷰(View) 생성 - 메시지 타입별 스케줄 조회
CREATE OR REPLACE VIEW `v_message_schedules` AS
SELECT 
    s.*,
    CASE 
        WHEN s.message_type = 'order_complete' THEN '주문완료'
        WHEN s.message_type = 'stock_warning' THEN '품절임박'
        WHEN s.message_type = 'stock_out' THEN '품절'
        ELSE '스케줄링'
    END AS message_type_name,
    CASE 
        WHEN s.delay_minutes > 0 THEN 
            CONCAT(s.created_at + INTERVAL s.delay_minutes MINUTE)
        ELSE 
            CONCAT(s.schedule_date, ' ', s.schedule_time)
    END AS actual_send_time
FROM kb_schedule s;