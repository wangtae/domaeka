<?php
/**
 * 도매까 관리자 공통 라이브러리
 * 
 * 전역적으로 사용되는 공통 함수들을 정의합니다.
 * - CSS/JS 버전 관리 시스템
 * - 개발자 IP 감지
 * - 기타 공통 유틸리티 함수들
 */

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 방지

/**
 * 개발자 IP 감지
 * 
 * 현재 접속 IP가 개발자 IP로 설정되어 있는지 확인합니다.
 * 
 * @return bool 개발자 IP 여부
 */
function dmk_is_developer_ip() {
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // DMK_DEVELOPER_MODE가 true인 경우 모든 IP에서 개발자 모드
    if (defined('DMK_DEVELOPER_MODE') && DMK_DEVELOPER_MODE === true) {
        return true;
    }
    
    // DMK_DEVELOPER_IPS가 설정된 경우 해당 IP들만 개발자 모드
    if (defined('DMK_DEVELOPER_IPS')) {
        $developer_ips = explode(',', DMK_DEVELOPER_IPS);
        $developer_ips = array_map('trim', $developer_ips);
        
        if (in_array($current_ip, $developer_ips)) {
            return true;
        }
    }
    
    // 기본 개발자 IP 목록 (로컬 개발 환경)
    $default_developer_ips = [
        '127.0.0.1',      // localhost
        '::1',            // localhost IPv6
        '192.168.1.100',  // 예시 개발자 IP
        '192.168.0.100',  // 예시 개발자 IP
        '124.62.66.233'   // 실제 개발자 IP
    ];
    
    return in_array($current_ip, $default_developer_ips);
}

/**
 * CSS/JS 파일 캐싱 방지를 위한 버전 파라미터 생성
 * 
 * 개발자 IP인 경우: 타임스탬프 사용 (실시간 캐싱 방지)
 * 일반 사용자인 경우: 기존 그누보드 버전 상수 사용 (배포 시 캐싱 갱신)
 * 
 * @param string $file_path 파일 경로 (파일 확장자로 타입 판단)
 * @return string 캐싱 방지 파라미터
 */
function dmk_get_cache_buster($file_path = '') {
    if (dmk_is_developer_ip()) {
        // 개발자 IP인 경우 타임스탬프 사용 (실시간 캐싱 방지)
        return '?t=' . time();
    } else {
        // 일반 사용자인 경우 기존 그누보드 버전 상수 사용 (배포 시 캐싱 갱신)
        $version = '';
        
        // 파일 확장자로 타입 판단
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        if ($extension === 'css' && defined('G5_CSS_VER')) {
            // CSS 파일인 경우 G5_CSS_VER 사용
            $version = G5_CSS_VER;
        } elseif ($extension === 'js' && defined('G5_JS_VER')) {
            // JS 파일인 경우 G5_JS_VER 사용
            $version = G5_JS_VER;
        } elseif (defined('CSSJS_VER')) {
            // 도매까 전용 버전 사용
            $version = CSSJS_VER;
        } else {
            // 기본값
            $version = '1.0.0';
        }
        
        return '?ver=' . $version;
    }
}

/**
 * 전역 CSS/JS 버전 관리 함수
 * 
 * 개발자 IP인 경우 타임스탬프를, 일반 사용자인 경우 기존 그누보드 버전 상수를 사용합니다.
 * 
 * @param string $file_path 파일 경로 (파일 확장자로 타입 판단)
 * @return string 버전 파라미터
 */
function dmk_get_cssjs_version($file_path = '') {
    return dmk_get_cache_buster($file_path);
}

/**
 * CSS/JS 파일 URL 생성 함수
 * 
 * @param string $file_path 파일 경로 (예: '/adm/js/script.js')
 * @return string 캐싱 방지 파라미터가 포함된 완전한 URL
 */
function dmk_get_cssjs_url($file_path) {
    $base_url = G5_DMK_URL;
    $version_param = dmk_get_cache_buster($file_path);
    
    return $base_url . $file_path . $version_param;
}

/**
 * CSS 파일 태그 생성
 * 
 * @param string $file_path CSS 파일 경로 (예: '/adm/css/style.css')
 * @return string CSS link 태그
 */
function dmk_css_tag($file_path) {
    return '<link rel="stylesheet" href="' . dmk_get_cssjs_url($file_path) . '">';
}

/**
 * JavaScript 파일 태그 생성
 * 
 * @param string $file_path JS 파일 경로 (예: '/adm/js/script.js')
 * @return string script 태그
 */
function dmk_js_tag($file_path) {
    return '<script src="' . dmk_get_cssjs_url($file_path) . '"></script>';
}

/**
 * CSS/JS 파일 URL만 반환 (태그 없이)
 * 
 * @param string $file_path 파일 경로
 * @return string URL
 */
function dmk_asset_url($file_path) {
    return dmk_get_cssjs_url($file_path);
}

/**
 * 도매까 관리자 권한 확인
 * 
 * @return array 관리자 권한 정보
 */
function dmk_get_admin_auth() {
    global $g5, $member;
    
    if (!$member['mb_id']) {
        return [
            'is_super' => false,
            'mb_type' => '',
            'dt_id' => '',
            'ag_id' => '',
            'br_id' => ''
        ];
    }
    
    // 본사 관리자 확인
    if ($member['mb_level'] >= 10) {
        return [
            'is_super' => true,
            'mb_type' => 'super',
            'type' => 'super',  // 호환성을 위해 추가
            'dt_id' => '',
            'ag_id' => '',
            'br_id' => '',
            'key' => '',
            'name' => '본사'
        ];
    }
    
    // 총판 관리자 확인
    if ($member['mb_level'] == 8) {
        $dt_sql = "SELECT dt_id FROM dmk_distributor WHERE dt_id = '".sql_escape_string($member['mb_id'])."' AND dt_status = 1";
        $dt_result = sql_query($dt_sql);
        if (sql_num_rows($dt_result) > 0) {
            $dt_row = sql_fetch_array($dt_result);
            return [
                'is_super' => false,
                'mb_type' => 'distributor',
                'dt_id' => $dt_row['dt_id'],
                'ag_id' => '',
                'br_id' => ''
            ];
        }
    }
    
    // 대리점 관리자 확인
    if ($member['mb_level'] == 6) {
        $ag_sql = "SELECT ag_id, dt_id FROM dmk_agency WHERE ag_id = '".sql_escape_string($member['mb_id'])."' AND ag_status = 1";
        $ag_result = sql_query($ag_sql);
        if (sql_num_rows($ag_result) > 0) {
            $ag_row = sql_fetch_array($ag_result);
            return [
                'is_super' => false,
                'mb_type' => 'agency',
                'dt_id' => $ag_row['dt_id'],
                'ag_id' => $ag_row['ag_id'],
                'br_id' => ''
            ];
        }
    }
    
    // 지점 관리자 확인
    if ($member['mb_level'] == 4) {
        $br_sql = "SELECT br_id, ag_id FROM dmk_branch WHERE br_id = '".sql_escape_string($member['mb_id'])."' AND br_status = 1";
        $br_result = sql_query($br_sql);
        if (sql_num_rows($br_result) > 0) {
            $br_row = sql_fetch_array($br_result);
            
            // 지점의 소속 대리점 정보 조회
            $ag_info_sql = "SELECT dt_id FROM dmk_agency WHERE ag_id = '".sql_escape_string($br_row['ag_id'])."'";
            $ag_info_result = sql_query($ag_info_sql);
            $ag_info_row = sql_fetch_array($ag_info_result);
            
            return [
                'is_super' => false,
                'mb_type' => 'branch',
                'dt_id' => $ag_info_row['dt_id'],
                'ag_id' => $br_row['ag_id'],
                'br_id' => $br_row['br_id']
            ];
        }
    }
    
    // 일반 회원
    return [
        'is_super' => false,
        'mb_type' => 'member',
        'dt_id' => '',
        'ag_id' => '',
        'br_id' => ''
    ];
}

/**
 * 도매까 관리자 타입 상수 정의
 */
if (!defined('DMK_MB_TYPE_DISTRIBUTOR')) define('DMK_MB_TYPE_DISTRIBUTOR', 'distributor');
if (!defined('DMK_MB_TYPE_AGENCY')) define('DMK_MB_TYPE_AGENCY', 'agency');
if (!defined('DMK_MB_TYPE_BRANCH')) define('DMK_MB_TYPE_BRANCH', 'branch');

/**
 * 안전한 SQL 이스케이프 함수
 * 
 * @param string $string 이스케이프할 문자열
 * @return string 이스케이프된 문자열
 */
function dmk_sql_escape($string) {
    return sql_escape_string($string);
}

/**
 * XSS 방지를 위한 문자열 정리
 * 
 * @param string $string 정리할 문자열
 * @return string 정리된 문자열
 */
function dmk_clean_xss($string) {
    return clean_xss_tags($string);
}

/**
 * 도매까 관리자 페이지 공통 헤더
 * 
 * @param string $title 페이지 제목
 * @param array $options 추가 옵션
 */
function dmk_admin_header($title, $options = []) {
    global $g5;
    
    $g5['title'] = $title;
    include_once(G5_ADMIN_PATH.'/admin.head.php');
    
    // 추가 CSS/JS 포함
    if (isset($options['css'])) {
        foreach ($options['css'] as $css_file) {
            echo dmk_css_tag($css_file);
        }
    }
    
    if (isset($options['js'])) {
        foreach ($options['js'] as $js_file) {
            echo dmk_js_tag($js_file);
        }
    }
}

/**
 * 도매까 관리자 페이지 공통 푸터
 */
function dmk_admin_footer() {
    include_once(G5_ADMIN_PATH.'/admin.tail.php');
}

/**
 * 도매까 관리자 페이지 로컬 네비게이션
 * 
 * @param string $title 네비게이션 제목
 * @param array $breadcrumbs 브레드크럼 배열
 */
function dmk_admin_nav($title, $breadcrumbs = []) {
    echo '<div class="local_ov01 local_ov">';
    echo '<span class="btn_ov01"><span class="ov_txt">' . $title . '</span></span>';
    echo '</div>';
    
    if (!empty($breadcrumbs)) {
        echo '<div class="local_desc01 local_desc">';
        echo '<ul class="breadcrumb">';
        foreach ($breadcrumbs as $breadcrumb) {
            echo '<li>' . $breadcrumb . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
}

/**
 * 도매까 관리자 페이지 테이블 헤더
 * 
 * @param string $caption 테이블 캡션
 * @param array $columns 컬럼 배열
 */
function dmk_admin_table_header($caption, $columns) {
    echo '<div class="tbl_head01 tbl_wrap">';
    echo '<table>';
    echo '<caption>' . $caption . '</caption>';
    echo '<thead><tr>';
    foreach ($columns as $column) {
        echo '<th scope="col">' . $column . '</th>';
    }
    echo '</tr></thead>';
    echo '<tbody>';
}

/**
 * 도매까 관리자 페이지 테이블 푸터
 */
function dmk_admin_table_footer() {
    echo '</tbody></table></div>';
}

/**
 * 도매까 관리자 페이지 설명 섹션
 * 
 * @param string $title 섹션 제목
 * @param string $content 섹션 내용
 */
function dmk_admin_section($title, $content) {
    echo '<div class="local_desc01 local_desc">';
    echo '<h3>' . $title . '</h3>';
    echo $content;
    echo '</div>';
}

?> 