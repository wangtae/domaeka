<?php
if (!defined('_GNUBOARD_')) exit;

$g5_debug['php']['begin_time'] = $begin_time = get_microtime();

$files = glob(G5_ADMIN_PATH.'/css/admin_extend_*');
if (is_array($files)) {
    foreach ((array) $files as $k=>$css_file) {
        
        $fileinfo = pathinfo($css_file);
        $ext = $fileinfo['extension'];
        
        if( $ext !== 'css' ) continue;
        
        $css_file = str_replace(G5_ADMIN_PATH, G5_ADMIN_URL, $css_file);
        add_stylesheet('<link rel="stylesheet" href="'.$css_file.'">', $k);
    }
}

include_once(G5_PATH.'/head.sub.php');

// 도매까 통합 권한 시스템 로드
if (file_exists(G5_PATH . '/dmk/include/dmk_unified_auth.php')) {
    include_once(G5_PATH . '/dmk/include/dmk_unified_auth.php');
}

// 도매까 체인 선택박스 에셋 포함 (특정 페이지에서만)
if (defined('G5_DMK_PATH')) {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    $chain_select_pages = ['member_list.php', 'distributor_list.php', 'agency_list.php', 'branch_list.php'];
    
    if (in_array($current_script, $chain_select_pages)) {
        include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
        echo dmk_include_chain_select_assets();
    }
    
    // 도매까 메뉴 아이콘 시스템 포함
    include_once(G5_PATH.'/dmk/include/dmk_menu_icons.php');
    
    // FontAwesome CDN 추가
    add_stylesheet('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">', 100);
    
    // FontAwesome 메뉴 스타일 추가
    add_stylesheet('<link rel="stylesheet" href="'.G5_ADMIN_URL.'/css/admin_dmk_fontawesome.css">', 999);
}

function print_menu1($key, $no='')
{
    global $menu;

    $str = print_menu2($key, $no);

    return $str;
}

function print_menu2($key, $no='')
{
    global $menu, $auth_menu, $is_admin, $auth, $g5, $sub_menu, $member;

    $str = "<ul>";
    for($i=1; $i<count($menu[$key]); $i++)
    {
        if( ! isset($menu[$key][$i]) ){
            continue;
        }

        $menu_code = $menu[$key][$i][0];
        $menu_key = isset($menu[$key][$i][3]) ? $menu[$key][$i][3] : '';
        
        // 모든 메뉴에 대해 통합된 dmk_auth_check_menu_display 함수를 사용하여 권한을 확인
        if (function_exists('dmk_auth_check_menu_display') && !dmk_auth_check_menu_display($menu_code, $menu_key)) {
            continue;
        }
        
        $gnb_grp_div = $gnb_grp_style = '';

        if (isset($menu[$key][$i][4])){
            if (($menu[$key][$i][4] == 1 && $gnb_grp_style == false) || ($menu[$key][$i][4] != 1 && $gnb_grp_style == true)) $gnb_grp_div = 'gnb_grp_div';

            if ($menu[$key][$i][4] == 1) $gnb_grp_style = 'gnb_grp_style';
        }

        $current_class = '';

        if ($menu[$key][$i][0] == $sub_menu){
            $current_class = ' on';
        }

        $str .= '<li data-menu="'.$menu[$key][$i][0].'"><a href="'.$menu[$key][$i][2].'" class="gnb_2da '.$gnb_grp_style.' '.$gnb_grp_div.$current_class.'">'.$menu[$key][$i][1].'</a></li>';

        $auth_menu[$menu[$key][$i][0]] = $menu[$key][$i][1];
    }
    $str .= "</ul>";

    return $str;
}

$adm_menu_cookie = array(
'container' => '',
'gnb'       => '',
'btn_gnb'   => '',
);

if( ! empty($_COOKIE['g5_admin_btn_gnb']) ){
    $adm_menu_cookie['container'] = 'container-small';
    $adm_menu_cookie['gnb'] = 'gnb_small';
    $adm_menu_cookie['btn_gnb'] = 'btn_gnb_open';
}
?>

<script>
var tempX = 0;
var tempY = 0;

function imageview(id, w, h)
{

    menu(id);

    var el_id = document.getElementById(id);

    //submenu = eval(name+".style");
    submenu = el_id.style;
    submenu.left = tempX - ( w + 11 );
    submenu.top  = tempY - ( h / 2 );

    selectBoxVisible();

    if (el_id.style.display != 'none')
        selectBoxHidden(id);
}
</script>

<div id="to_content"><a href="#container">본문 바로가기</a></div>

<header id="hd">
    <h1><?php echo $config['cf_title'] ?></h1>
    <div id="hd_top">
        <button type="button" id="btn_gnb" class="btn_gnb_close <?php echo $adm_menu_cookie['btn_gnb'];?>">메뉴</button>
       <div id="logo"><a href="<?php echo correct_goto_url(G5_ADMIN_URL); ?>"><img src="<?php echo G5_ADMIN_URL ?>/img/logo.png" alt="<?php echo get_text($config['cf_title']); ?> 관리자"></a></div>

        <div id="tnb">
            <ul>
                <!--
                <li class="tnb_li"><a href="<?php echo G5_SHOP_URL ?>/" class="tnb_shop" target="_blank" title="쇼핑몰 바로가기">쇼핑몰 바로가기</a></li>
                <li class="tnb_li"><a href="<?php echo G5_URL ?>/" class="tnb_community" target="_blank" title="커뮤니티 바로가기">커뮤니티 바로가기</a></li>
                <li class="tnb_li"><a href="<?php echo G5_ADMIN_URL ?>/service.php" class="tnb_service">부가서비스</a></li>-->
                <li class="tnb_li"><button type="button" class="tnb_mb_btn">
                    <?php
                    $dmk_auth = dmk_get_admin_auth();
                    $admin_type_text = '';
                    if ($dmk_auth) {
                        if ($dmk_auth['is_super']) {
                            $admin_type_text = '본사';
                        } else {
                            switch ($dmk_auth['mb_type']) {
                                case DMK_MB_TYPE_DISTRIBUTOR:
                                    $admin_type_text = '총판';
                                    break;
                                case DMK_MB_TYPE_AGENCY:
                                    $admin_type_text = '대리점';
                                    break;
                                case DMK_MB_TYPE_BRANCH:
                                    $admin_type_text = '지점';
                                    break;
                                default:
                                    $admin_type_text = '일반';
                                    break;
                            }
                        }
                    }
                    echo $admin_type_text ? '<i class="fa fa-gear"></i> ' . $admin_type_text . '' : '';
                    ?>
                    <span class="./img/btn_gnb.png">메뉴열기</span></button>
                    <ul class="tnb_mb_area">
   
                        <li id="tnb_logout"><a href="<?php echo G5_BBS_URL ?>/logout.php">로그아웃</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
    <nav id="gnb" class="gnb_large <?php echo $adm_menu_cookie['gnb']; ?>">
        <h2>관리자 주메뉴</h2>
        <ul class="gnb_ul">
            <?php
            $jj = 1;
            foreach($amenu as $key=>$value) {
                $href1 = $href2 = '';

                if (isset($menu['menu'.$key][0][2]) && $menu['menu'.$key][0][2]) {
                    $href1 = '<a href="'.$menu['menu'.$key][0][2].'" class="gnb_1da">';
                    $href2 = '</a>';
                } else {
                    continue;
                }

                // 메인 메뉴 아이콘에 대한 권한 검사 추가 (임시로 비활성화)
                $menu_code = $menu['menu'.$key][0][0];
                $menu_key = isset($menu['menu'.$key][0][3]) ? $menu['menu'.$key][0][3] : '';
                
                // DMK 권한 검사 - 메인 메뉴 아이콘 표시 여부 확인
                if (function_exists('dmk_auth_check_menu_display') && !dmk_auth_check_menu_display($menu_code, $menu_key)) {
                    continue;
                }

                $current_class = "";
                $is_active = false;
                
                // 현재 메뉴 활성화 감지 개선
                $current_menu_prefix = substr($menu['menu'.$key][0][0], 0, 3);
                $current_sub_menu_prefix = isset($sub_menu) ? substr($sub_menu, 0, 3) : '';
                
                // 현재 URL 기반 메뉴 감지 (더 정확한 감지를 위해)
                $current_script_path = $_SERVER['SCRIPT_NAME'];
                $is_current_menu = false;
                
                if ($current_menu_prefix == $current_sub_menu_prefix) {
                    $is_current_menu = true;
                } else {
                    // URL 기반으로도 체크
                    switch($current_menu_prefix) {
                        case '180': // 봇 관리
                            if (strpos($current_script_path, '/dmk/adm/bot/') !== false) {
                                $is_current_menu = true;
                            }
                            break;
                        case '190': // 프랜차이즈 관리
                            if (strpos($current_script_path, '/dmk/adm/distributor_admin/') !== false ||
                                strpos($current_script_path, '/dmk/adm/agency_admin/') !== false ||
                                strpos($current_script_path, '/dmk/adm/branch_admin/') !== false ||
                                strpos($current_script_path, '/dmk/adm/statistics/') !== false ||
                                strpos($current_script_path, '/dmk/adm/admin_manager/') !== false ||
                                strpos($current_script_path, '/dmk/adm/logs/') !== false) {
                                $is_current_menu = true;
                            }
                            break;
                        case '200': // 회원관리
                            if (strpos($current_script_path, 'member') !== false) {
                                $is_current_menu = true;
                            }
                            break;
                        case '400': // 상품/주문관리
                            if (strpos($current_script_path, 'item') !== false ||
                                strpos($current_script_path, 'order') !== false ||
                                strpos($current_script_path, 'category') !== false) {
                                $is_current_menu = true;
                            }
                            break;
                        case '500': // 매출/통계
                            if (strpos($current_script_path, 'sale') !== false ||
                                strpos($current_script_path, 'stats') !== false ||
                                strpos($current_script_path, 'chart') !== false) {
                                $is_current_menu = true;
                            }
                            break;
                    }
                }
                
                if ($is_current_menu) {
                    $current_class = " on";
                    $is_active = true;
                }

                $button_title = $menu['menu'.$key][0][1];
                
                // FontAwesome 아이콘 시스템 사용
                $menu_icon_html = '';
                if (function_exists('dmk_render_menu_button_content')) {
                    $menu_icon_html = dmk_render_menu_button_content($key, $is_active, $button_title);
                } else {
                    $menu_icon_html = $button_title; // 폴백
                }
                
                // 디버깅 정보 (개발 환경에서만)
                if (defined('G5_IS_ADMIN') && G5_IS_ADMIN && isset($_GET['debug_menu'])) {
                    echo "<!-- DEBUG: Menu $key, sub_menu: ".($sub_menu ?? 'none').", script: $current_script_path, active: ".($is_current_menu ? 'yes' : 'no')." -->";
                }
            ?>
            <li class="gnb_li<?php echo $current_class;?>">
                <button type="button" class="btn_op dmk-fontawesome-menu menu-<?php echo $key; ?> menu-order-<?php echo $jj; ?>" title="<?php echo $button_title; ?>"><?php echo $menu_icon_html;?></button>
                <div class="gnb_oparea_wr">
                    <div class="gnb_oparea">
                        <h3><?php echo $menu['menu'.$key][0][1];?></h3>
                        <?php echo print_menu1('menu'.$key, 1); ?>
                    </div>
                </div>
            </li>
            <?php
            $jj++;
            }     //end foreach
            ?>
        </ul>
    </nav>

</header>
<script>
jQuery(function($){

    var menu_cookie_key = 'g5_admin_btn_gnb';

    $(".tnb_mb_btn").click(function(){
        $(".tnb_mb_area").toggle();
    });

    $("#btn_gnb").click(function(){
        
        var $this = $(this);

        try {
            if( ! $this.hasClass("btn_gnb_open") ){
                set_cookie(menu_cookie_key, 1, 60*60*24*365);
            } else {
                delete_cookie(menu_cookie_key);
            }
        }
        catch(err) {
        }

        $("#container").toggleClass("container-small");
        $("#gnb").toggleClass("gnb_small");
        $this.toggleClass("btn_gnb_open");

    });

    $(".gnb_ul li .btn_op" ).click(function() {
        $(this).parent().addClass("on").siblings().removeClass("on");
    });
    
    // 서브 메뉴 클릭 이벤트
    $(document).on('click', '.gnb_oparea a', function(e) {
        // 모든 서브 메뉴에서 on 클래스 제거
        $('.gnb_oparea a').removeClass('on');
        // 클릭한 서브 메뉴에 on 클래스 추가
        $(this).addClass('on');
    });
    
    // 페이지 로드시 현재 URL 기반으로 올바른 메뉴 활성화
    $(document).ready(function() {
        // PHP에서 전달된 메뉴 정보
        var currentSubMenu = '<?php echo isset($sub_menu) ? $sub_menu : ""; ?>';
        var currentPath = window.location.pathname;
        var currentMenu = null;
        
        // 메뉴 코드를 기반으로 상위 메뉴 자동 감지
        if (currentSubMenu) {
            var menuGroup = currentSubMenu.substring(0, 3); // 예: "190900" -> "190"
            currentMenu = '.menu-' + menuGroup;
        } else {
            // sub_menu가 없는 경우 URL 기반으로 감지 (기존 로직 유지)
            if (currentPath.indexOf('/dmk/adm/bot/') !== -1) {
                currentMenu = '.menu-180'; // 봇 관리
            } else if (currentPath.indexOf('/dmk/adm/distributor_admin/') !== -1 ||
                       currentPath.indexOf('/dmk/adm/agency_admin/') !== -1 ||
                       currentPath.indexOf('/dmk/adm/branch_admin/') !== -1 ||
                       currentPath.indexOf('/dmk/adm/statistics/') !== -1 ||
                       currentPath.indexOf('/dmk/adm/admin_manager/') !== -1 ||
                       currentPath.indexOf('/dmk/adm/logs/') !== -1) {
                currentMenu = '.menu-190'; // 프랜차이즈 관리
            } else if (currentPath.indexOf('member') !== -1) {
                currentMenu = '.menu-200'; // 회원관리
            } else if (currentPath.indexOf('board') !== -1 || currentPath.indexOf('bbs') !== -1) {
                currentMenu = '.menu-300'; // 게시판관리
            } else if (currentPath.indexOf('item') !== -1 || currentPath.indexOf('order') !== -1 || currentPath.indexOf('category') !== -1 || currentPath.indexOf('inorder') !== -1) {
                currentMenu = '.menu-400'; // 상품/주문관리
            } else if (currentPath.indexOf('sale') !== -1 || currentPath.indexOf('chart') !== -1 || currentPath.indexOf('stats') !== -1 || currentPath.indexOf('wishlist') !== -1 || currentPath.indexOf('rank') !== -1 || currentPath.indexOf('print') !== -1) {
                currentMenu = '.menu-500'; // 매출/통계
            } else if (currentPath.indexOf('sms') !== -1) {
                currentMenu = '.menu-900'; // SMS관리
            } else {
                currentMenu = '.menu-100'; // 기본값: 환경설정
            }
        }
        
        // 모든 메뉴에서 on 클래스 제거
        $(".gnb_ul li").removeClass("on");
        
        // 감지된 메뉴에 on 클래스 추가
        if (currentMenu) {
            var targetMenuButton = $(".gnb_ul .btn_op" + currentMenu);
            if (targetMenuButton.length > 0) {
                var targetMenuLi = targetMenuButton.closest('li');
                targetMenuLi.addClass("on");
                
                // 디버깅용 로그
                console.log('Menu activated: ' + currentMenu + ' for path: ' + currentPath);
            } else {
                // 폴백: PHP에서 설정된 on 클래스가 있으면 그대로 유지, 없으면 첫 번째 메뉴
                var hasActiveMenu = $(".gnb_ul li.on").length > 0;
                if (!hasActiveMenu) {
                    var firstVisibleMenu = $(".gnb_ul li:visible:first");
                    if (firstVisibleMenu.length > 0) {
                        firstVisibleMenu.addClass("on");
                    }
                }
                console.log('Menu fallback activated for path: ' + currentPath);
            }
        }
        
        // 서브 메뉴 활성화 (PHP에서 전달된 메뉴 코드 우선 사용)
        activateSubmenu(currentPath, currentSubMenu);
    });
    
    // 서브 메뉴 활성화 함수
    function activateSubmenu(currentPath, currentSubMenu) {
        // 모든 서브 메뉴에서 on 클래스 제거
        $('.gnb_oparea a').removeClass('on');
        
        var activeSubmenuId = null;
        
        // PHP에서 전달된 메뉴 코드 우선 사용
        if (currentSubMenu) {
            activeSubmenuId = currentSubMenu;
        } else {
            // 폴백: URL 기반 매핑 (기존 하드코딩 유지)
            var submenuMap = {
                // 봇 관리 서브 메뉴
                '/bot/server_list.php': '180100',
                '/bot/server_process_list.php': '180200', 
                '/bot/bot_device_list.php': '180300',
                '/bot/ping_monitor_list.php': '180400',
                '/bot/room_list.php': '180500',
                '/bot/bot_schedule_list.php': '180600',
                '/bot/bot_schedule_log_list.php': '180610',
                '/bot/chat_log_list.php': '180700',
                
                // 프랜차이즈 관리 서브 메뉴
                '/distributor_admin/distributor_list.php': '190100',
                '/agency_admin/agency_list.php': '190200',
                '/branch_admin/branch_list.php': '190300',
                '/statistics/statistics_dashboard.php': '190400',
                '/admin_manager/admin_list.php': '190600',
                '/admin_manager/dmk_auth_list.php': '190700',
                '/logs/action_log_list.php': '190900',
                '/admin_manager/menu_config.php': '190800',
                
                // 쇼핑몰 관리 서브 메뉴
                '/shop_admin/orderlist.php': '400400',
                '/shop_admin/categorylist.php': '400200',
                '/shop_admin/itemlist.php': '400300',
                '/shop_admin/itemstocklist.php': '400620',
                '/shop_admin/itemtypelist.php': '400610',
                '/shop_admin/optionstocklist.php': '400500',
                '/shop_admin/inorderlist.php': '400410',
                
                // 쇼핑몰현황/기타 서브 메뉴
                '/shop_admin/sale1.php': '500110',
                '/shop_admin/itemsellrank.php': '500100',
                '/shop_admin/orderprint.php': '500120',
                '/shop_admin/wishlist.php': '500140'
            };
            
            // 현재 경로에 해당하는 서브 메뉴 찾기
            for (var path in submenuMap) {
                if (currentPath.indexOf(path) !== -1) {
                    activeSubmenuId = submenuMap[path];
                    break;
                }
            }
        }
        
        // 해당하는 서브 메뉴에 on 클래스 추가
        if (activeSubmenuId) {
            var submenuLink = $('.gnb_oparea li[data-menu="' + activeSubmenuId + '"] a');
            
            if (submenuLink.length > 0) {
                submenuLink.addClass('on');
                console.log('Submenu activated: ' + activeSubmenuId + ' (source: ' + (currentSubMenu ? 'PHP' : 'URL mapping') + ')');
            } else {
                console.log('No submenu found for ID: ' + activeSubmenuId);
            }
        }
    }

});
</script>


<div id="wrapper">

    <div id="container" class="<?php echo $adm_menu_cookie['container']; ?>">

        <h1 id="container_title"><?php echo $g5['title'] ?></h1>
        <div class="container_wr">