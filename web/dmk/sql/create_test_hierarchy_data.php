<?php
/**
 * Domaeka 프로젝트 - 계층 구조 테스트 데이터 생성 스크립트
 * 
 * 이 스크립트는 영카트 최고관리자 > 총판 > 대리점 > 지점 계층 구조의
 * 테스트 데이터를 그누보드5 표준 방식으로 생성합니다.
 * 
 * 실행 방법: 브라우저에서 http://도메인/dmk/sql/create_test_hierarchy_data.php 접속
 * 
 * @author Domaeka Development Team
 * @version 1.0
 * @since 2024-01-20
 */

// 그누보드 환경 로드
require_once '../../_common.php';

// 최고관리자만 실행 가능
if ($is_admin != 'super') {
    die('최고관리자만 실행할 수 있습니다.');
}

echo "<h2>도매까 계층 구조 테스트 데이터 생성</h2>";
echo "<p>영카트 최고관리자 > 총판 > 대리점 > 지점 계층 구조의 테스트 데이터를 생성합니다.</p>";

try {
    // 1. 기존 테스트 데이터 삭제 (있다면)
    echo "<h3>1. 기존 테스트 데이터 정리</h3>";
    
    $test_members = array('distributor1', 'distributor2', 'agency1', 'agency2', 'branch1', 'branch2', 'branch3');
    foreach ($test_members as $mb_id) {
        $sql = "DELETE FROM {$g5['member_table']} WHERE mb_id = '" . sql_escape_string($mb_id) . "'";
        sql_query($sql);
    }
    
    $sql = "DELETE FROM dmk_branch WHERE br_id IN ('BR001', 'BR002', 'BR003')";
    sql_query($sql);
    
    $sql = "DELETE FROM dmk_agency WHERE ag_id IN ('AG001', 'AG002')";
    sql_query($sql);
    
    echo "기존 테스트 데이터 삭제 완료<br>";

    // 2. 총판 관리자 계정 생성
    echo "<h3>2. 총판 관리자 계정 생성</h3>";
    
    $distributors = array(
        array('distributor1', '총판1', 'distributor1@domaeka.com'),
        array('distributor2', '총판2', 'distributor2@domaeka.com')
    );
    
    foreach ($distributors as $dist) {
        $mb_id = $dist[0];
        $mb_name = $dist[1];
        $mb_email = $dist[2];
        $mb_password = get_encrypt_string('123456'); // 임시 비밀번호
        
        $sql = "INSERT INTO {$g5['member_table']} SET
                    mb_id = '" . sql_escape_string($mb_id) . "',
                    mb_password = '" . sql_escape_string($mb_password) . "',
                    mb_name = '" . sql_escape_string($mb_name) . "',
                    mb_nick = '" . sql_escape_string($mb_name) . "',
                    mb_email = '" . sql_escape_string($mb_email) . "',
                    mb_level = 9,
                    mb_datetime = '" . G5_TIME_YMDHIS . "',
                    mb_ip = '" . $_SERVER['REMOTE_ADDR'] . "',
                    dmk_mb_type = 1,
                    dmk_ag_id = NULL,
                    dmk_br_id = NULL,
                    mb_email_certify = '" . G5_TIME_YMDHIS . "',
                    mb_mailling = 1,
                    mb_open = 1";
        
        sql_query($sql);
        echo "총판 관리자 {$mb_name} ({$mb_id}) 생성 완료<br>";
    }

    // 3. 대리점 정보 생성
    echo "<h3>3. 대리점 정보 생성</h3>";
    
    $agencies = array(
        array('AG001', '서울중앙대리점', '김대표', '02-1234-5678', '서울특별시 강남구 테헤란로 123', 'agency1'),
        array('AG002', '부산남부대리점', '이대표', '051-9876-5432', '부산광역시 해운대구 센텀로 456', 'agency2')
    );
    
    foreach ($agencies as $agency) {
        $sql = "INSERT INTO dmk_agency SET
                    ag_id = '" . sql_escape_string($agency[0]) . "',
                    ag_name = '" . sql_escape_string($agency[1]) . "',
                    ag_ceo_name = '" . sql_escape_string($agency[2]) . "',
                    ag_phone = '" . sql_escape_string($agency[3]) . "',
                    ag_address = '" . sql_escape_string($agency[4]) . "',
                    ag_mb_id = '" . sql_escape_string($agency[5]) . "',
                    ag_datetime = '" . G5_TIME_YMDHIS . "',
                    ag_status = 1";
        
        sql_query($sql);
        echo "대리점 {$agency[1]} ({$agency[0]}) 생성 완료<br>";
    }

    // 4. 대리점 관리자 계정 생성
    echo "<h3>4. 대리점 관리자 계정 생성</h3>";
    
    $agency_admins = array(
        array('agency1', '대리점1', 'agency1@domaeka.com', 'AG001'),
        array('agency2', '대리점2', 'agency2@domaeka.com', 'AG002')
    );
    
    foreach ($agency_admins as $admin) {
        $mb_id = $admin[0];
        $mb_name = $admin[1];
        $mb_email = $admin[2];
        $ag_id = $admin[3];
        $mb_password = get_encrypt_string('123456'); // 임시 비밀번호
        
        $sql = "INSERT INTO {$g5['member_table']} SET
                    mb_id = '" . sql_escape_string($mb_id) . "',
                    mb_password = '" . sql_escape_string($mb_password) . "',
                    mb_name = '" . sql_escape_string($mb_name) . "',
                    mb_nick = '" . sql_escape_string($mb_name) . "',
                    mb_email = '" . sql_escape_string($mb_email) . "',
                    mb_level = 8,
                    mb_datetime = '" . G5_TIME_YMDHIS . "',
                    mb_ip = '" . $_SERVER['REMOTE_ADDR'] . "',
                    dmk_mb_type = 2,
                    dmk_ag_id = '" . sql_escape_string($ag_id) . "',
                    dmk_br_id = NULL,
                    mb_email_certify = '" . G5_TIME_YMDHIS . "',
                    mb_mailling = 1,
                    mb_open = 1";
        
        sql_query($sql);
        echo "대리점 관리자 {$mb_name} ({$mb_id}) 생성 완료<br>";
    }

    // 5. 지점 정보 생성
    echo "<h3>5. 지점 정보 생성</h3>";
    
    $branches = array(
        array('BR001', 'AG001', '강남1호점', '박점장', '02-2345-6789', '서울특별시 강남구 강남대로 789', 'branch1'),
        array('BR002', 'AG001', '강남2호점', '최점장', '02-3456-7890', '서울특별시 강남구 논현로 321', 'branch2'),
        array('BR003', 'AG002', '해운대점', '정점장', '051-8765-4321', '부산광역시 해운대구 해운대해변로 654', 'branch3')
    );
    
    foreach ($branches as $branch) {
        $sql = "INSERT INTO dmk_branch SET
                    br_id = '" . sql_escape_string($branch[0]) . "',
                    ag_id = '" . sql_escape_string($branch[1]) . "',
                    br_name = '" . sql_escape_string($branch[2]) . "',
                    br_ceo_name = '" . sql_escape_string($branch[3]) . "',
                    br_phone = '" . sql_escape_string($branch[4]) . "',
                    br_address = '" . sql_escape_string($branch[5]) . "',
                    br_mb_id = '" . sql_escape_string($branch[6]) . "',
                    br_datetime = '" . G5_TIME_YMDHIS . "',
                    br_status = 1";
        
        sql_query($sql);
        echo "지점 {$branch[2]} ({$branch[0]}) 생성 완료<br>";
    }

    // 6. 지점 관리자 계정 생성
    echo "<h3>6. 지점 관리자 계정 생성</h3>";
    
    $branch_admins = array(
        array('branch1', '지점1', 'branch1@domaeka.com', 'AG001', 'BR001'),
        array('branch2', '지점2', 'branch2@domaeka.com', 'AG001', 'BR002'),
        array('branch3', '지점3', 'branch3@domaeka.com', 'AG002', 'BR003')
    );
    
    foreach ($branch_admins as $admin) {
        $mb_id = $admin[0];
        $mb_name = $admin[1];
        $mb_email = $admin[2];
        $ag_id = $admin[3];
        $br_id = $admin[4];
        $mb_password = get_encrypt_string('123456'); // 임시 비밀번호
        
        $sql = "INSERT INTO {$g5['member_table']} SET
                    mb_id = '" . sql_escape_string($mb_id) . "',
                    mb_password = '" . sql_escape_string($mb_password) . "',
                    mb_name = '" . sql_escape_string($mb_name) . "',
                    mb_nick = '" . sql_escape_string($mb_name) . "',
                    mb_email = '" . sql_escape_string($mb_email) . "',
                    mb_level = 7,
                    mb_datetime = '" . G5_TIME_YMDHIS . "',
                    mb_ip = '" . $_SERVER['REMOTE_ADDR'] . "',
                    dmk_mb_type = 3,
                    dmk_ag_id = '" . sql_escape_string($ag_id) . "',
                    dmk_br_id = '" . sql_escape_string($br_id) . "',
                    mb_email_certify = '" . G5_TIME_YMDHIS . "',
                    mb_mailling = 1,
                    mb_open = 1";
        
        sql_query($sql);
        echo "지점 관리자 {$mb_name} ({$mb_id}) 생성 완료<br>";
    }

    echo "<h3>✅ 계층 구조 테스트 데이터 생성 완료!</h3>";
    echo "<div style='background: #f0f8ff; padding: 15px; border: 1px solid #ccc; margin: 10px 0;'>";
    echo "<h4>생성된 계층 구조:</h4>";
    echo "<p><strong>영카트 최고관리자 (현재 로그인한 관리자)</strong></p>";
    echo "<p>├── 총판1 (distributor1) - 비밀번호: 123456</p>";
    echo "<p>│   ├── 서울중앙대리점 (AG001) - 대리점1 (agency1)</p>";
    echo "<p>│   │   ├── 강남1호점 (BR001) - 지점1 (branch1)</p>";
    echo "<p>│   │   └── 강남2호점 (BR002) - 지점2 (branch2)</p>";
    echo "<p>└── 총판2 (distributor2) - 비밀번호: 123456</p>";
    echo "<p>    └── 부산남부대리점 (AG002) - 대리점2 (agency2)</p>";
    echo "<p>        └── 해운대점 (BR003) - 지점3 (branch3)</p>";
    echo "</div>";
    
    echo "<h4>다음 단계:</h4>";
    echo "<ol>";
    echo "<li>관리자 메뉴 > 회원관리 > 총판관리 에서 총판 목록 확인</li>";
    echo "<li>관리자 메뉴 > 회원관리 > 대리점관리 에서 대리점 목록 확인</li>";
    echo "<li>관리자 메뉴 > 회원관리 > 지점관리 에서 지점 목록 확인</li>";
    echo "<li>각 계층별 관리자로 로그인하여 권한별 접근 제한 테스트</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>오류 발생: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3, h4 { color: #333; }
p { margin: 5px 0; }
</style> 