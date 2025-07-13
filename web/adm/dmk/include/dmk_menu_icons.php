<?php
/**
 * 도매까 메뉴 아이콘 매핑 설정
 * FontAwesome 아이콘을 메뉴별로 정의
 */

if (!defined('_GNUBOARD_')) exit;

/**
 * 메뉴별 FontAwesome 아이콘 매핑
 * [메뉴코드] => [일반상태_아이콘, 활성상태_아이콘, 색상]
 */
$dmk_menu_icons = [
    // 환경설정
    '100' => [
        'icon' => 'fa-cog',
        'active_icon' => 'fa-cog',
        'color' => '#6c757d',
        'active_color' => '#007bff',
        'title' => '환경설정'
    ],
    
    // 봇 관리 (카카오톡 봇)
    '180' => [
        'icon' => 'fa-robot',
        'active_icon' => 'fa-robot', 
        'color' => '#28a745',
        'active_color' => '#20c997',
        'title' => '봇 관리'
    ],
    
    // 프랜차이즈 관리 (도매까 핵심)
    '190' => [
        'icon' => 'fa-sitemap',
        'active_icon' => 'fa-sitemap',
        'color' => '#dc3545',
        'active_color' => '#fd7e14',
        'title' => '프랜차이즈 관리'
    ],
    
    // 회원관리
    '200' => [
        'icon' => 'fa-users',
        'active_icon' => 'fa-users',
        'color' => '#17a2b8',
        'active_color' => '#20c997',
        'title' => '회원관리'
    ],
    
    // 게시판관리
    '300' => [
        'icon' => 'fa-list-alt',
        'active_icon' => 'fa-list-alt',
        'color' => '#6610f2',
        'active_color' => '#6f42c1',
        'title' => '게시판관리'
    ],
    
    // 쇼핑몰관리 1부
    '400' => [
        'icon' => 'fa-shopping-cart',
        'active_icon' => 'fa-shopping-cart',
        'color' => '#fd7e14',
        'active_color' => '#e83e8c',
        'title' => '상품/주문관리'
    ],
    
    // 쇼핑몰관리 2부 (통계)
    '500' => [
        'icon' => 'fa-chart-bar',
        'active_icon' => 'fa-chart-line',
        'color' => '#20c997',
        'active_color' => '#17a2b8',
        'title' => '매출/통계'
    ],
    
    // SMS관리
    '900' => [
        'icon' => 'fa-sms',
        'active_icon' => 'fa-sms',
        'color' => '#e83e8c',
        'active_color' => '#dc3545',
        'title' => 'SMS관리'
    ]
];

/**
 * 메뉴 아이콘 정보 반환
 * @param string $menu_code 메뉴 코드 (100, 180, 190 등)
 * @param bool $is_active 활성 상태 여부
 * @return array 아이콘 정보
 */
function dmk_get_menu_icon($menu_code, $is_active = false) {
    global $dmk_menu_icons;
    
    // 메뉴 코드에서 앞 3자리 추출 (190100 -> 190)
    $menu_key = substr($menu_code, 0, 3);
    
    if (!isset($dmk_menu_icons[$menu_key])) {
        // 기본 아이콘 반환
        return [
            'icon' => 'fa-circle',
            'color' => '#6c757d',
            'title' => '메뉴'
        ];
    }
    
    $icon_config = $dmk_menu_icons[$menu_key];
    
    return [
        'icon' => $is_active ? $icon_config['active_icon'] : $icon_config['icon'],
        'color' => $is_active ? $icon_config['active_color'] : $icon_config['color'],
        'title' => $icon_config['title']
    ];
}

/**
 * FontAwesome 아이콘 HTML 생성
 * @param string $menu_code 메뉴 코드
 * @param bool $is_active 활성 상태 여부
 * @param string $additional_classes 추가 CSS 클래스
 * @return string 아이콘 HTML
 */
function dmk_render_menu_icon($menu_code, $is_active = false, $additional_classes = '') {
    $icon_info = dmk_get_menu_icon($menu_code, $is_active);
    
    $classes = 'fa ' . $icon_info['icon'];
    if ($additional_classes) {
        $classes .= ' ' . $additional_classes;
    }
    
    $style = 'color: ' . $icon_info['color'] . ';';
    
    return '<i class="' . $classes . '" style="' . $style . '" title="' . $icon_info['title'] . '"></i>';
}

/**
 * 메뉴 버튼용 아이콘 HTML 생성 (텍스트 숨김 없이)
 * @param string $menu_code 메뉴 코드
 * @param bool $is_active 활성 상태 여부
 * @param string $menu_title 메뉴 제목
 * @return string 버튼 내용 HTML
 */
function dmk_render_menu_button_content($menu_code, $is_active = false, $menu_title = '') {
    $icon_info = dmk_get_menu_icon($menu_code, $is_active);
    
    $icon_classes = 'fa ' . $icon_info['icon'] . ' dmk-menu-icon';
    $icon_style = 'color: ' . $icon_info['color'] . ';';
    
    $html = '<i class="' . $icon_classes . '" style="' . $icon_style . '"></i>';
    
    // 작은 화면에서는 텍스트 표시
    if ($menu_title) {
        $html .= '<span class="dmk-menu-text">' . $menu_title . '</span>';
    }
    
    return $html;
}

/**
 * 모든 메뉴 아이콘 설정 반환 (디버깅/설정용)
 * @return array 전체 메뉴 아이콘 설정
 */
function dmk_get_all_menu_icons() {
    global $dmk_menu_icons;
    return $dmk_menu_icons;
}
?>