<?php
/**
 * 도매까 메뉴 권한 시스템 디버그 페이지
 * 
 * 이 페이지는 계층별 메뉴 권한이 올바르게 작동하는지 확인하기 위한 디버그 페이지입니다.
 * 관리자 로그인 후 /dmk/test_menu_debug.php 로 접속하여 확인할 수 있습니다.
 */

// 그누보드 common.php 포함
require_once '../common.php';

// 관리자만 접근 가능
if (!$is_admin) {
    alert('관리자만 접근 가능합니다.');
}

// 도매까 전역 설정 포함
include_once(G5_PATH . '/dmk/dmk_global_settings.php');
include_once(G5_PATH . '/dmk/adm/lib/admin.auth.lib.php');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>도매까 메뉴 권한 디버그</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { border: 1px solid #ccc; padding: 15px; margin: 10px 0; }
        .debug-title { font-weight: bold; color: #333; margin-bottom: 10px; }
        .debug-info { background: #f5f5f5; padding: 10px; margin: 5px 0; }
        .allowed { color: green; }
        .denied { color: red; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>도매까 메뉴 권한 시스템 디버그</h1>
    
    <div class="debug-section">
        <div class="debug-title">1. 현재 사용자 정보</div>
        <div class="debug-info">
            <strong>회원 ID:</strong> <?php echo $member['mb_id'] ?? 'none'; ?><br>
            <strong>is_admin:</strong> <?php echo $is_admin; ?><br>
            <strong>mb_level:</strong> <?php echo $member['mb_level'] ?? 'none'; ?>
        </div>
    </div>

    <div class="debug-section">
        <div class="debug-title">2. DMK 관리자 정보</div>
        <?php
        $dmk_auth = dmk_get_admin_auth();
        if ($dmk_auth) {
        ?>
            <div class="debug-info">
                <strong>DMK mb_type:</strong> <?php echo $dmk_auth['mb_type']; ?><br>
                <strong>admin_type:</strong> <?php echo $dmk_auth['admin_type']; ?><br>
                <strong>is_super:</strong> <?php echo $dmk_auth['is_super'] ? 'true' : 'false'; ?><br>
                <strong>ag_id:</strong> <?php echo $dmk_auth['ag_id'] ?? 'none'; ?><br>
                <strong>br_id:</strong> <?php echo $dmk_auth['br_id'] ?? 'none'; ?>
            </div>
        <?php
        } else {
        ?>
            <div class="debug-info">DMK 관리자 정보가 없습니다.</div>
        <?php
        }
        ?>
    </div>

    <div class="debug-section">
        <div class="debug-title">3. 계층 타입</div>
        <div class="debug-info">
            <strong>User Type:</strong> <?php echo dmk_get_current_user_type(); ?>
        </div>
    </div>

    <div class="debug-section">
        <div class="debug-title">4. 메뉴 권한 테스트</div>
        <table>
            <thead>
                <tr>
                    <th>메뉴 코드</th>
                    <th>메뉴 이름</th>
                    <th>권한 여부</th>
                    <th>사용자별 제목</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $user_type = dmk_get_current_user_type();
                $test_menus = array(
                    '100000' => '환경설정',
                    '200000' => '도매까 관리',
                    '300000' => '회원관리',
                    '400000' => '게시판관리',
                    '500000' => '쇼핑몰관리',
                    '600000' => '쇼핑몰현황/기타',
                    '700000' => 'SMS관리'
                );
                
                foreach ($test_menus as $menu_code => $menu_name) {
                    $is_allowed = dmk_is_menu_allowed($menu_code, $user_type);
                    $custom_title = dmk_get_menu_title($menu_code, $user_type);
                    $status_class = $is_allowed ? 'allowed' : 'denied';
                    $status_text = $is_allowed ? '허용' : '거부';
                    $display_title = $custom_title ? $custom_title : $menu_name;
                ?>
                <tr>
                    <td><?php echo $menu_code; ?></td>
                    <td><?php echo $menu_name; ?></td>
                    <td class="<?php echo $status_class; ?>"><?php echo $status_text; ?></td>
                    <td><?php echo $display_title; ?></td>
                </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="debug-section">
        <div class="debug-title">5. 계층별 서브메뉴 테스트 (도매까 관리)</div>
        <?php
        $sub_menus = dmk_get_sub_menus('200000', $user_type);
        if ($sub_menus) {
        ?>
            <table>
                <thead>
                    <tr>
                        <th>서브메뉴 코드</th>
                        <th>서브메뉴 이름</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sub_menus as $sub_code => $sub_name) { ?>
                    <tr>
                        <td><?php echo $sub_code; ?></td>
                        <td><?php echo $sub_name; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php
        } else {
        ?>
            <div class="debug-info">이 계층에서는 도매까 관리 서브메뉴가 설정되지 않았습니다.</div>
        <?php
        }
        ?>
    </div>

    <div class="debug-section">
        <div class="debug-title">6. 메뉴 설정 정보</div>
        <div class="debug-info">
            <strong>DMK_MENU_CONFIG 변수 존재:</strong> <?php echo isset($DMK_MENU_CONFIG) ? 'true' : 'false'; ?><br>
            <?php if (isset($DMK_MENU_CONFIG)) { ?>
                <strong>설정된 계층:</strong> <?php echo implode(', ', array_keys($DMK_MENU_CONFIG)); ?>
            <?php } ?>
        </div>
    </div>

    <p><a href="<?php echo G5_ADMIN_URL; ?>">관리자 페이지로 돌아가기</a></p>

</body>
</html> 