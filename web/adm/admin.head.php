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

// 도매까 체인 선택박스 에셋 포함 (특정 페이지에서만)
if (defined('G5_DMK_PATH')) {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    $chain_select_pages = ['member_list.php', 'distributor_list.php', 'agency_list.php', 'branch_list.php'];
    
    if (in_array($current_script, $chain_select_pages)) {
        include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
        echo dmk_include_chain_select_assets();
    }
    
    // 도매까 메뉴 아이콘 시스템 포함
    include_once(G5_ADMIN_PATH.'/dmk/include/dmk_menu_icons.php');
    
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
        if (!dmk_auth_check_menu_display($menu_code, $menu_key)) {
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

                // 메인 메뉴 아이콘에 대한 권한 검사 추가
                $menu_code = $menu['menu'.$key][0][0];
                $menu_key = isset($menu['menu'.$key][0][3]) ? $menu['menu'.$key][0][3] : '';
                
                // DMK 권한 검사 - 메인 메뉴 아이콘 표시 여부 확인
                if (!dmk_auth_check_menu_display($menu_code, $menu_key)) {
                    continue;
                }

                $current_class = "";
                $is_active = false;
                if (isset($sub_menu) && (substr($sub_menu, 0, 3) == substr($menu['menu'.$key][0][0], 0, 3))) {
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
    
    // 페이지 로드시 첫 번째 메뉴를 자동으로 선택된 상태로 표시
    $(document).ready(function() {
        // 현재 선택된 메뉴가 있는지 확인
        var hasActiveMenu = $(".gnb_ul li.on").length > 0;
        
        // 선택된 메뉴가 없으면 첫 번째 보이는 메뉴를 선택
        if (!hasActiveMenu) {
            var firstVisibleMenu = $(".gnb_ul li:visible:first");
            if (firstVisibleMenu.length > 0) {
                firstVisibleMenu.addClass("on");
            }
        }
    });

});
</script>


<div id="wrapper">

    <div id="container" class="<?php echo $adm_menu_cookie['container']; ?>">

        <h1 id="container_title"><?php echo $g5['title'] ?></h1>
        <div class="container_wr">