<?php
// 오류 보고 설정 (디버깅을 위해 추가)
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('_GNUBOARD_', true); // 그누보드 핵심 상수 정의 (가장 먼저 선언되어야 함)

// 그누보드 전체 환경 설정 파일 포함 (G5_ADMIN_PATH, G5_DMK_PATH 등 전역 변수 정의)
// from dmk/adm/_ajax/_common.php to project_root/adm/_common.php
include_once(dirname(__FILE__).'/../../../adm/_common.php');

// 도매까 관리자 권한 라이브러리 포함 (G5_DMK_PATH가 정의된 후)
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// AJAX 요청이므로 HTML 헤더 출력 방지 및 JSON Content-Type 설정
@header('Content-Type: application/json');

// CORS 허용 (개발 환경에서 필요할 수 있음)
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
// header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept'); 