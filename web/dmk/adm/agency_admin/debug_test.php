<?php
// 오류 표시 활성화
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>도매까 디버깅 테스트</h1>";

echo "<h2>1. 기본 PHP 동작 확인</h2>";
echo "<p>PHP 버전: " . phpversion() . "</p>";
echo "<p>현재 시간: " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>2. 파일 경로 확인</h2>";
echo "<p>현재 디렉토리: " . __DIR__ . "</p>";
echo "<p>_GNUBOARD_ 상수 정의 전</p>";

// 그누보드 상수 확인
if (!defined('_GNUBOARD_')) {
    echo "<p style='color:red;'>_GNUBOARD_ 상수가 정의되지 않음</p>";
    define('_GNUBOARD_', true);
} else {
    echo "<p style='color:green;'>_GNUBOARD_ 상수가 이미 정의됨</p>";
}

echo "<h2>3. _common.php 포함 시도</h2>";
try {
    if (file_exists('./_common.php')) {
        echo "<p style='color:green;'>_common.php 파일 존재함</p>";
        include_once './_common.php';
        echo "<p style='color:green;'>_common.php 포함 성공</p>";
    } else {
        echo "<p style='color:red;'>_common.php 파일이 존재하지 않음</p>";
        
        // 다른 경로 시도
        if (file_exists('../../../../_common.php')) {
            echo "<p style='color:blue;'>상위 경로에서 _common.php 발견</p>";
            include_once '../../../../_common.php';
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>오류: " . $e->getMessage() . "</p>";
}

echo "<h2>4. 그누보드 변수 확인</h2>";
if (isset($g5)) {
    echo "<p style='color:green;'>\$g5 변수 존재</p>";
    echo "<pre>" . print_r($g5, true) . "</pre>";
} else {
    echo "<p style='color:red;'>\$g5 변수 없음</p>";
}

echo "<h2>5. 데이터베이스 연결 확인</h2>";
try {
    if (function_exists('sql_query')) {
        $result = sql_query("SELECT 1 as test");
        if ($result) {
            echo "<p style='color:green;'>데이터베이스 연결 성공</p>";
        } else {
            echo "<p style='color:red;'>데이터베이스 쿼리 실패</p>";
        }
    } else {
        echo "<p style='color:red;'>sql_query 함수 없음</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>DB 오류: " . $e->getMessage() . "</p>";
}

echo "<h2>6. 도매까 권한 라이브러리 확인</h2>";
try {
    $auth_lib_path = G5_PATH.'/dmk/adm/lib/admin.auth.lib.php';
    if (file_exists($auth_lib_path)) {
        echo "<p style='color:green;'>권한 라이브러리 파일 존재: {$auth_lib_path}</p>";
        include_once($auth_lib_path);
        
        if (function_exists('dmk_get_admin_auth')) {
            echo "<p style='color:green;'>dmk_get_admin_auth 함수 존재</p>";
            $dmk_auth = dmk_get_admin_auth();
            echo "<pre>권한 정보: " . print_r($dmk_auth, true) . "</pre>";
        } else {
            echo "<p style='color:red;'>dmk_get_admin_auth 함수 없음</p>";
        }
    } else {
        echo "<p style='color:red;'>권한 라이브러리 파일 없음: {$auth_lib_path}</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>권한 라이브러리 오류: " . $e->getMessage() . "</p>";
}

echo "<h2>7. 도매까 테이블 확인</h2>";
try {
    if (function_exists('sql_query')) {
        $result = sql_query("SELECT COUNT(*) as cnt FROM dmk_agency");
        if ($result) {
            $row = sql_fetch_array($result);
            echo "<p style='color:green;'>dmk_agency 테이블: {$row['cnt']}개 레코드</p>";
        } else {
            echo "<p style='color:red;'>dmk_agency 테이블 조회 실패</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>테이블 조회 오류: " . $e->getMessage() . "</p>";
}

echo "<h2>8. 현재 사용자 확인</h2>";
if (isset($member)) {
    echo "<p style='color:green;'>로그인 사용자: " . $member['mb_id'] . " (" . $member['mb_name'] . ")</p>";
} else {
    echo "<p style='color:red;'>로그인 정보 없음</p>";
}

echo "<h2>완료</h2>";
echo "<p>이 페이지가 정상적으로 표시되면 기본 환경은 문제없습니다.</p>";
?> 