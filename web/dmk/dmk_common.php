<?php
/**
 * 도매까 공통 설정 파일
 * UTF-8 인코딩 설정 및 기타 전역 설정
 */

// UTF-8 인코딩 설정
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// PHP 내부 인코딩 설정
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// HTTP 입력/출력 인코딩 설정
if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}

// 정규표현식 인코딩 설정
if (function_exists('mb_regex_encoding')) {
    mb_regex_encoding('UTF-8');
}

// 기본 언어 설정
if (function_exists('mb_language')) {
    mb_language('uni');
}

// POST/GET 데이터 인코딩 변환
if (!empty($_POST)) {
    array_walk_recursive($_POST, function(&$item) {
        if (is_string($item)) {
            // 이미 UTF-8인지 확인
            if (!mb_check_encoding($item, 'UTF-8')) {
                // 인코딩 감지 및 변환
                $encoding = mb_detect_encoding($item, array('EUC-KR', 'ISO-8859-1', 'ASCII'), true);
                if ($encoding && $encoding !== 'UTF-8') {
                    $item = mb_convert_encoding($item, 'UTF-8', $encoding);
                }
            }
        }
    });
}

// MySQL UTF-8 설정 강화
function dmk_set_mysql_utf8() {
    sql_query("SET NAMES utf8mb4");
    sql_query("SET CHARACTER SET utf8mb4");
    sql_query("SET character_set_connection=utf8mb4");
    sql_query("SET collation_connection=utf8mb4_unicode_ci");
    sql_query("SET character_set_client=utf8mb4");
    sql_query("SET character_set_results=utf8mb4");
}

// 페이지 로드 시 자동 실행
if (function_exists('sql_query')) {
    dmk_set_mysql_utf8();
}
?>