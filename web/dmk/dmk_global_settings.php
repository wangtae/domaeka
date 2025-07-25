<?php
/**
 * 도매까 전역 설정 - 계층별 관리자 메뉴 관리
 * 
 * 본사(super) - 총판(distributor) - 대리점(agency) - 지점(branch)의 4단계 계층 구조
 * 각 계층별 main 관리자의 메뉴 노출 설정을 관리합니다.
 */

if (!defined('_GNUBOARD_')) {
    exit;
}

/**
 * 개발자 환경 설정
 * 
 * 개발자 IP 설정을 통해 캐싱 방지 및 디버깅 기능을 활성화할 수 있습니다.
 * 
 * 사용법:
 * 1. 환경 변수로 설정: define('DMK_DEVELOPER_MODE', true);
 * 2. 특정 IP만 설정: define('DMK_DEVELOPER_IPS', '192.168.1.100,192.168.1.101');
 * 
 * 개발자 모드가 활성화되면:
 * - JavaScript/CSS 파일에 타임스탬프 파라미터가 추가되어 캐싱이 방지됩니다.
 * - 디버그 로그가 활성화됩니다.
 */

// 개발자 모드 설정 (필요시 주석 해제)
// define('DMK_DEVELOPER_MODE', true);

// 특정 개발자 IP 설정 (필요시 주석 해제)
// define('DMK_DEVELOPER_IPS', '192.168.1.100,192.168.1.101,127.0.0.1');

/**
 * 계층별 메뉴 설정
 * 
 * 실제 메뉴 구조 (admin.menu*.php 파일 기준):
 * 
 * === 100XXX: 환경설정 (admin.menu100.php) ===
 * 100000 - 환경설정 (메인)
 * 100100 - 기본환경설정
 * 100200 - 관리권한설정
 * 100280 - 테마설정
 * 100290 - 메뉴설정
 * 100300 - 메일 테스트
 * 100310 - 팝업레이어관리
 * 100400 - 부가서비스
 * 100410 - DB업그레이드
 * 100500 - phpinfo()
 * 100510 - Browscap 업데이트
 * 100520 - 접속로그 변환
 * 100800 - 세션파일 일괄삭제
 * 100900 - 캐시파일 일괄삭제
 * 100910 - 캡챠파일 일괄삭제
 * 100920 - 썸네일파일 일괄삭제
 * 
 * === 180XXX: 봇 관리 (admin.menu180.php) ===
 * 180000 - 봇 관리 (메인)
 * 180100 - 서버 관리 (본사 전용)
 * 180200 - 서버 프로세스 관리 (본사 전용)
 * 180300 - 클라이언트 봇 관리
 * 180400 - 봇 상태 모니터링
 * 180500 - 채팅방 관리
 * 180600 - 스케줄링 발송 관리
 * 180700 - 채팅 내역 조회
 * 
 * === 190XXX: 프랜차이즈 관리 (admin.menu190.php) ===
 * 190000 - 프랜차이즈 관리 (메인)
 * 190100 - 총판관리
 * 190200 - 대리점관리
 * 190300 - 지점관리
 * 190400 - 통계분석
 * 190600 - 관리자관리
 * 190700 - 서브관리자권한설정
 * 190800 - 메뉴권한설정
 * 
 * === 200XXX: 회원관리 (admin.menu200.php) ===
 * 200000 - 회원관리 (메인)
 * 200100 - 회원목록
 * 200200 - 포인트관리
 * 200300 - 회원메일발송
 * 200800 - 접속자집계
 * 200810 - 접속자검색
 * 200820 - 접속자로그삭제
 * 200900 - 투표관리
 * 
 * === 300XXX: 게시판관리 (admin.menu300.php) ===
 * 300000 - 게시판관리 (메인)
 * 300100 - 게시판관리
 * 300200 - 게시판그룹관리
 * 300300 - 인기검색어관리
 * 300400 - 인기검색어순위
 * 300500 - 1:1문의설정
 * 300600 - 내용관리
 * 300700 - FAQ관리
 * 300820 - 글,댓글 현황
 * 
 * === 400XXX: 쇼핑몰관리 (admin.menu400.shop_1of2.php) ===
 * 400000 - 쇼핑몰관리 (메인)
 * 400010 - 쇼핑몰현황
 * 400100 - 쇼핑몰설정
 * 400200 - 분류관리
 * 400300 - 상품관리
 * 400400 - 주문관리
 * 400410 - 미완료주문
 * 400440 - 개인결제관리
 * 400500 - 상품옵션재고관리
 * 400610 - 상품유형관리
 * 400620 - 재고관리
 * 400650 - 사용후기
 * 400660 - 상품문의
 * 400750 - 추가배송비관리
 * 400800 - 쿠폰관리
 * 400810 - 쿠폰존관리
 * 
 * === 500XXX: 쇼핑몰현황/기타 (admin.menu500.shop_2of2.php) ===
 * 500000 - 쇼핑몰현황/기타 (메인)
 * 500100 - 상품판매순위
 * 500110 - 매출현황
 * 500120 - 주문내역출력
 * 500140 - 보관함현황
 * 500210 - 가격비교사이트
 * 500300 - 이벤트관리
 * 500310 - 이벤트일괄처리
 * 500400 - 재입고SMS알림
 * 500500 - 배너관리
 * 
 * === 900XXX: SMS관리 (admin.menu900.php) ===
 * 900000 - SMS 관리 (메인)
 * 900100 - SMS 기본설정
 * 900200 - 회원정보업데이트
 * 900300 - 문자 보내기
 * 900400 - 전송내역-건별
 * 900410 - 전송내역-번호별
 * 900500 - 이모티콘 그룹
 * 900600 - 이모티콘 관리
 * 900700 - 휴대폰번호 그룹
 * 900800 - 휴대폰번호 관리
 * 900900 - 휴대폰번호 파일
 */

// 본사(super) - 모든 메뉴 접근 가능
$DMK_MENU_CONFIG = array(

    
    // 총판(distributor)
    'distributor' => array(
        'allowed_menus' => array(
            // 환경설정 제외

            // 봇 관리 (180XXX)
            '180000', '180100', '180200', '180300', '180400', '180500', '180600', '180610', '180700',
            
            // 프랜차이즈 관리 (190XXX)
            '190000',  '190100', '190200', '190300', '190400', '190600', '190700', '190900',

            // 회원관리 (200XXX)
            '200000', '200100',
            
            // 게시판관리 제외
            
            // 쇼핑몰관리 (400XXX)
            '400000', '400010','400200', '400300', '400400', '400410',  
            '400500', '400610', '400620',
            
            // 쇼핑몰현황/기타 (500XXX)
            '500000', '500100', '500110', '500120', '500140', 
            // SMS관리 제외
        ),
        'menu_titles' => array(
            '180000' => '봇 관리',
            '190000' => '프랜차이즈 관리',
            '200000' => '회원 관리',
            '400000' => '쇼핑몰관리',
            '500000' => '쇼핑몰현황/기타'
        ),
        'sub_menus' => array(
            '180000' => array(
                '180100' => '서버 관리',
                '180200' => '서버 프로세스 관리',
                '180300' => '클라이언트 봇 관리',
                '180400' => '봇 상태 모니터링',
                '180500' => '채팅방 관리',
                '180600' => '스케줄링 발송 관리',
                '180610' => '스케줄링 발송 로그',
                '180700' => '채팅 내역 조회'
            ),
            '190000' => array(
                '190100' => '총판관리',
                '190200' => '대리점관리',
                '190300' => '지점관리',
                '190400' => '통계분석',
                '190600' => '서브관리자관리',
                '190700' => '서브관리자권한설정',
                '190900' => '관리자엑션로그'
            ),
            '200000' => array(
                '200100' => '회원목록',
            ),
            '400000' => array(
                '400010' => '쇼핑몰현황',
                '400400' => '주문관리',
                '400200' => '분류관리', // 총판 전용
                '400300' => '상품관리',  
                '400620' => '재고관리',
                '400610' => '상품유형관리',
                '400500' => '상품옵션재고관리',
                '400410' => '미완료주문'
            ),
            '500000' => array(
                '500110' => '매출현황',
                '500100' => '상품판매순위',
                '500120' => '주문내역출력',
                '500140' => '보관함현황'
            )
        )
    ),
    
    // 대리점(agency)
    'agency' => array(
        'allowed_menus' => array(
            // 환경설정 제외

            // 봇 관리 (180XXX) - 서버관리, 프로세스관리 제외 (본사 전용)
            '180000', '180600',
            
            // 프랜차이즈 관리 (190XXX) - 총판관리, 관리자액션로그 제외
            '190000',  '190200', '190300', '190400', '190600', '190700', 
            
            // 회원관리 (200XXX)
            '200000', '200100',
            
            // 게시판관리 제외
            
            // 쇼핑몰관리 (400XXX)
            '400000', '400010','400300', '400400', '400410',  
            '400500', '400610', '400620',
            
            // 쇼핑몰현황/기타 (500XXX)
            '500000', '500100', '500110', '500120', '500140', 
            // SMS관리 제외
        ),
        'menu_titles' => array(
            '190000' => '프랜차이즈 관리',
            '200000' => '회원 관리',
            '400000' => '쇼핑몰관리',
            '500000' => '쇼핑몰현황/기타'
        ),
        'sub_menus' => array(
            '180000' => array(                
                '180600' => '스케줄링 발송 관리'
            ),
            '190000' => array(
                '190200' => '대리점관리',
                '190300' => '지점관리',
                '190400' => '통계분석',
                '190600' => '서브관리자관리',
                '190700' => '서브관리자권한설정'
            ),
            '200000' => array(
                '200100' => '회원목록',
            ),
            '400000' => array(
                '400010' => '쇼핑몰현황', 
                '400400' => '주문관리', 
                '400300' => '상품관리', 
                '400620' => '재고관리',
                '400610' => '상품유형관리',
                '400500' => '상품옵션재고관리',
                '400410' => '미완료주문'
            ),
            '500000' => array(
                '500110' => '매출현황',
                '500100' => '상품판매순위',
                '500120' => '주문내역출력',
                '500140' => '보관함현황'
            )
        )
    ),
    
    // 지점(branch)
    'branch' => array(
        'allowed_menus' => array(
            // 환경설정 제외

            // 봇 관리 (180XXX) - 서버관리, 프로세스관리 제외 (본사 전용)
            '180000', '180600',
            
            // 프랜차이즈 관리 (190XXX) - 총판관리, 대리점관리, 관리자액션로그 제외
            '190000', '190300', '190600', '190700',
            
            // 회원관리 (200XXX)
            '200000', '200100',
            
            // 게시판관리 제외
            
            // 쇼핑몰관리 (400XXX)
            '400000', '400010','400300', '400400', '400410',  
            '400500', '400610', '400620', 
            
            // 쇼핑몰현황/기타 (500XXX)
            '500000', '500100', '500110', '500120', '500140', 
            // SMS관리 제외
        ), 
        'menu_titles' => array(
            '190000' => '프랜차이즈 관리',
            '200000' => '회원 관리',
            '400000' => '쇼핑몰관리',
            '500000' => '쇼핑몰현황/기타'
        ),
        'sub_menus' => array(
            '180000' => array(                
                '180600' => '스케줄링 발송 관리'
            ),
            '190000' => array(
                '190300' => '지점관리',
                '190600' => '서브관리자관리',
                '190700' => '서브관리자권한설정'
            ),
            '200000' => array(
                '200100' => '회원목록',
            ),
            '400000' => array(
                '400010' => '쇼핑몰현황',
                '400400' => '주문관리', 
                '400300' => '상품관리', 
                '400620' => '재고관리',
                '400610' => '상품유형관리',
                '400500' => '상품옵션재고관리',
                '400410' => '미완료주문'
            ),
            '500000' => array(
                '500110' => '매출현황',
                '500100' => '상품판매순위',
                '500120' => '주문내역출력',
                '500140' => '보관함현황'
            )
        )
    )
);

/**
 * 메뉴 표시 여부 확인 함수
 * 
 * @param string $menu_code 메뉴 코드
 * @param string $user_type 사용자 타입 (super, distributor, agency, branch)
 * @return bool 메뉴 표시 여부
 */
function dmk_is_menu_allowed($menu_code, $user_type) {
    global $DMK_MENU_CONFIG;
    
    // 본사는 모든 메뉴 접근 가능
    if ($user_type === 'super') {
        return true;
    }
    
    // 해당 계층의 설정이 없으면 false
    if (!isset($DMK_MENU_CONFIG[$user_type])) {
        return false;
    }

    $allowed_menus = $DMK_MENU_CONFIG[$user_type]['allowed_menus'];

    // 폼 페이지와 메뉴 코드 매핑 (예: 'agency_form' -> '190200')
    $form_menu_mapping = array(
        'distributor_form' => '190100',
        'agency_form'      => '190200',
        'branch_form'      => '190300',
        // 다른 폼 페이지가 있다면 여기에 추가
    );

    // 폼 메뉴 코드인 경우 숫자 메뉴 코드로 변환
    if (isset($form_menu_mapping[$menu_code])) {
        $menu_code = $form_menu_mapping[$menu_code];
    }

    return in_array($menu_code, $allowed_menus);
}

/**
 * 계층별 메뉴 제목 가져오기
 * 
 * @param string $menu_code 메뉴 코드
 * @param string $user_type 사용자 타입
 * @return string 메뉴 제목 (설정된 제목이 없으면 기본 제목 사용)
 */
function dmk_get_menu_title($menu_code, $user_type) {
    global $DMK_MENU_CONFIG;
    
    if (isset($DMK_MENU_CONFIG[$user_type]['menu_titles'][$menu_code])) {
        return $DMK_MENU_CONFIG[$user_type]['menu_titles'][$menu_code];
    }
    
    return null; // 기본 제목 사용
}

/**
 * 계층별 서브메뉴 가져오기
 * 
 * @param string $parent_menu_code 부모 메뉴 코드
 * @param string $user_type 사용자 타입
 * @return array 서브메뉴 배열
 */
function dmk_get_sub_menus($parent_menu_code, $user_type) {
    global $DMK_MENU_CONFIG;
    
    if (isset($DMK_MENU_CONFIG[$user_type]['sub_menus'][$parent_menu_code])) {
        return $DMK_MENU_CONFIG[$user_type]['sub_menus'][$parent_menu_code];
    }
    
    return array();
}

/**
 * 현재 사용자의 계층 타입 가져오기
 * 
 * @return string 사용자 계층 타입
 */
function dmk_get_current_user_type() {
    global $member, $is_admin;
    
    // 최고 관리자는 'super'로 간주
    if ($is_admin == 'super') {
        return 'super';
    }
    
    if (!$member['mb_id']) {
        return null;
    }
    
    // 회원 정보에서 도매까 타입 확인
    $dmk_mb_type = $member['dmk_mb_type'] ?? 0;
    
    // 도매까 타입에 따라 문자열 반환
    switch ($dmk_mb_type) {
        case 1: // distributor (총판)
            return 'distributor';
        case 2: // agency (대리점)
            return 'agency';
        case 3: // branch (지점)
            return 'branch';
        default:
            return null; // 일반 회원 또는 정의되지 않은 타입
    }
}