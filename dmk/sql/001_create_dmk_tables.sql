-- Domaeka 프로젝트 - 새로운 테이블 생성 스크립트
-- 작성일: 2024-01-20
-- 목적: 본사-대리점-지점 계층 구조 관리를 위한 테이블 생성

-- 1. 대리점 정보 테이블
CREATE TABLE `dmk_agency` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `ag_id` varchar(20) NOT NULL COMMENT '대리점 시스템 ID (예: AG001) - 동시에 관리자 회원 ID',
  `ag_name` varchar(100) NOT NULL COMMENT '대리점명',
  `ag_ceo_name` varchar(50) DEFAULT NULL COMMENT '대표자명',
  `ag_phone` varchar(20) DEFAULT NULL COMMENT '대표 전화번호',
  `ag_address` varchar(255) DEFAULT NULL COMMENT '주소',
  `ag_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록일시 (UTC)',
  `ag_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '대리점 상태 (1: 활성, 0: 비활성)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dmk_agency_ag_id` (`ag_id`),
  KEY `idx_dmk_agency_status` (`ag_status`),
  KEY `idx_dmk_agency_datetime` (`ag_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='대리점 정보 테이블';

-- 2. 지점 정보 테이블
CREATE TABLE `dmk_branch` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `br_id` varchar(20) NOT NULL COMMENT '지점 시스템 ID (예: BR001) - 동시에 관리자 회원 ID',
  `ag_id` varchar(20) NOT NULL COMMENT '소속 대리점 ID',
  `br_name` varchar(100) NOT NULL COMMENT '지점명',
  `br_ceo_name` varchar(50) DEFAULT NULL COMMENT '지점 대표자명',
  `br_phone` varchar(20) DEFAULT NULL COMMENT '지점 대표 전화번호',
  `br_address` varchar(255) DEFAULT NULL COMMENT '지점 주소',
  `br_shortcut_code` varchar(20) DEFAULT NULL COMMENT 'URL 단축 코드 (8-12자)',
  `br_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록일시 (UTC)',
  `br_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '지점 상태 (1: 활성, 0: 비활성)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dmk_branch_br_id` (`br_id`),
  UNIQUE KEY `uk_dmk_branch_shortcut_code` (`br_shortcut_code`),
  KEY `idx_dmk_branch_ag_id` (`ag_id`),
  KEY `idx_dmk_branch_status` (`br_status`),
  KEY `idx_dmk_branch_datetime` (`br_datetime`),
  CONSTRAINT `fk_dmk_branch_ag_id` FOREIGN KEY (`ag_id`) REFERENCES `dmk_agency` (`ag_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='지점 정보 테이블'; 