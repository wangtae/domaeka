<?php
/**
 * 지점 URL 매핑 테이블 생성 스크립트
 * 
 * 이 스크립트는 한 번만 실행하면 됩니다.
 * 실행 후에는 보안상 삭제하는 것을 권장합니다.
 */

include_once('./_common.php');

// 관리자만 실행 가능
if (!$is_admin) {
    die('관리자만 실행할 수 있습니다.');
}

echo "<h2>도매까 지점 URL 매핑 테이블 생성</h2>";

try {
    // 1. 지점 URL 매핑 테이블 생성
    $sql1 = "
    CREATE TABLE IF NOT EXISTS `dmk_branch_url` (
        `bu_id` int(11) NOT NULL AUTO_INCREMENT,
        `br_id` varchar(20) NOT NULL COMMENT '지점 ID',
        `br_url_code` varchar(50) NOT NULL COMMENT 'URL 코드 (친화적 이름)',
        `br_url_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '활성 상태 (1:활성, 0:비활성)',
        `bu_priority` int(11) NOT NULL DEFAULT 0 COMMENT '우선순위 (높을수록 우선)',
        `bu_description` varchar(255) DEFAULT NULL COMMENT '설명',
        `bu_create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
        `bu_update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
        PRIMARY KEY (`bu_id`),
        UNIQUE KEY `uk_br_url_code` (`br_url_code`),
        KEY `idx_br_id` (`br_id`),
        KEY `idx_active` (`br_url_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='지점 URL 매핑 테이블';
    ";
    
    $result1 = sql_query($sql1);
    if ($result1) {
        echo "<p>✅ dmk_branch_url 테이블 생성 완료</p>";
    } else {
        echo "<p>❌ dmk_branch_url 테이블 생성 실패: " . sql_error() . "</p>";
    }
    
    // 2. 지점 접속 로그 테이블 생성
    $sql2 = "
    CREATE TABLE IF NOT EXISTS `dmk_branch_access_log` (
        `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
        `br_code` varchar(50) NOT NULL COMMENT '접속한 지점 코드',
        `br_id` varchar(20) NOT NULL COMMENT '실제 지점 ID',
        `access_time` datetime NOT NULL COMMENT '접속 시간',
        `user_ip` varchar(45) NOT NULL COMMENT '사용자 IP',
        `user_agent` text COMMENT '사용자 에이전트',
        `referer` varchar(500) DEFAULT NULL COMMENT '리퍼러',
        PRIMARY KEY (`log_id`),
        KEY `idx_br_code` (`br_code`),
        KEY `idx_br_id` (`br_id`),
        KEY `idx_access_time` (`access_time`),
        KEY `idx_user_ip` (`user_ip`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='지점 접속 로그 테이블';
    ";
    
    $result2 = sql_query($sql2);
    if ($result2) {
        echo "<p>✅ dmk_branch_access_log 테이블 생성 완료</p>";
    } else {
        echo "<p>❌ dmk_branch_access_log 테이블 생성 실패: " . sql_error() . "</p>";
    }
    
    // 3. 기본 URL 매핑 데이터 삽입
    $default_mappings = [
        ['BR003', 'haeundae', 1, 10, '해운대점'],
        ['BR003', 'busan', 1, 9, '부산점 (해운대점)'],
        ['BR001', 'gangnam', 1, 10, '강남점'],
        ['BR001', 'seoul', 1, 9, '서울점 (강남점)'],
        ['BR002', 'hongdae', 1, 10, '홍대점'],
        ['BR002', 'mapo', 1, 9, '마포점 (홍대점)'],
        ['BR004', 'suwon', 1, 10, '수원점'],
        ['BR004', 'gyeonggi', 1, 9, '경기점 (수원점)'],
        ['BR005', 'incheon', 1, 10, '인천점'],
        ['BR006', 'daegu', 1, 10, '대구점'],
        ['BR007', 'gwangju', 1, 10, '광주점'],
        ['BR008', 'daejeon', 1, 10, '대전점'],
        ['BR009', 'ulsan', 1, 10, '울산점'],
        ['BR010', 'jeju', 1, 10, '제주점'],
    ];
    
    echo "<h3>기본 URL 매핑 데이터 삽입</h3>";
    $success_count = 0;
    
    foreach ($default_mappings as $mapping) {
        list($br_id, $url_code, $active, $priority, $description) = $mapping;
        
        // 중복 체크
        $check_sql = "SELECT bu_id FROM dmk_branch_url WHERE br_url_code = '" . sql_escape_string($url_code) . "'";
        $existing = sql_fetch($check_sql);
        
        if (!$existing) {
            $insert_sql = "
            INSERT INTO dmk_branch_url (br_id, br_url_code, br_url_active, bu_priority, bu_description) 
            VALUES (
                '" . sql_escape_string($br_id) . "',
                '" . sql_escape_string($url_code) . "',
                $active,
                $priority,
                '" . sql_escape_string($description) . "'
            )";
            
            if (sql_query($insert_sql)) {
                echo "<p>✅ {$url_code} → {$br_id} 매핑 추가</p>";
                $success_count++;
            } else {
                echo "<p>❌ {$url_code} 매핑 추가 실패: " . sql_error() . "</p>";
            }
        } else {
            echo "<p>⚠️ {$url_code} 매핑이 이미 존재합니다.</p>";
        }
    }
    
    echo "<h3>완료 요약</h3>";
    echo "<p>✅ 총 {$success_count}개의 URL 매핑이 추가되었습니다.</p>";
    
    // 4. 테스트 URL 출력
    echo "<h3>테스트 URL</h3>";
    echo "<ul>";
    foreach ($default_mappings as $mapping) {
        $url_code = $mapping[1];
        $description = $mapping[4];
        echo "<li><a href='" . G5_URL . "/{$url_code}' target='_blank'>{$url_code}</a> - {$description}</li>";
    }
    echo "</ul>";
    
    echo "<hr>";
    echo "<p><strong>주의사항:</strong></p>";
    echo "<ul>";
    echo "<li>이 스크립트는 한 번만 실행하면 됩니다.</li>";
    echo "<li>실행 후에는 보안상 이 파일을 삭제하는 것을 권장합니다.</li>";
    echo "<li>URL 매핑은 관리자 페이지에서 관리할 수 있습니다.</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p>❌ 오류 발생: " . $e->getMessage() . "</p>";
}
?> 