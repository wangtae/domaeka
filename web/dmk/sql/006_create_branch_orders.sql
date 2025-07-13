-- 지점 주문 확장 테이블 생성
CREATE TABLE IF NOT EXISTS `dmk_branch_orders` (
  `order_id` varchar(40) NOT NULL COMMENT '주문번호',
  `br_id` varchar(20) NOT NULL COMMENT '지점 ID',
  `br_shortcut_code` varchar(20) DEFAULT NULL COMMENT '지점 단축코드',
  `order_date` date NOT NULL COMMENT '주문일자',
  `delivery_type` varchar(20) NOT NULL DEFAULT 'PICKUP' COMMENT '배송방식 (PICKUP/DELIVERY)',
  `order_status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '주문상태',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
  PRIMARY KEY (`order_id`),
  KEY `idx_br_id` (`br_id`),
  KEY `idx_order_date` (`order_date`),
  KEY `idx_order_status` (`order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='지점 주문 확장 정보';

-- 주문 처리 내역 테이블
CREATE TABLE IF NOT EXISTS `dmk_order_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(40) NOT NULL COMMENT '주문번호',
  `status` varchar(20) NOT NULL COMMENT '상태',
  `comment` text COMMENT '코멘트',
  `created_by` varchar(20) DEFAULT NULL COMMENT '처리자',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '처리일시',
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='주문 처리 내역';