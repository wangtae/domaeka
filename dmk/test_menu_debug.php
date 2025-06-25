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

// 권한 체크
dmk_auth_check_menu($auth, "dmk", "r");

$g5['title'] = "도매까 메뉴 권한 디버그";
include_once G5_ADMIN_PATH.'/admin.head.php';

// 도매까 권한 정보 가져오기
if (function_exists('dmk_get_admin_auth')) {
    $dmk_auth = dmk_get_admin_auth();
} else {
    $dmk_auth = null;
}

// 현재 사용자 타입 확인
$user_type = 'none';
if (function_exists('dmk_get_current_user_type')) {
    $user_type = dmk_get_current_user_type();
}

// 테스트할 메뉴 코드들
$test_menu_codes = array(
    '190000' => '프랜차이즈 관리',
    '190100' => '총판관리',
    '190200' => '대리점관리',
    '190300' => '지점관리',
    '190400' => '통계분석',
    '190600' => '서브관리자관리',
    '190700' => '서브관리자권한설정',
    '190800' => '계층별메뉴권한설정',
    '200000' => '회원관리',
    '200100' => '회원목록',
    '400000' => '쇼핑몰관리',
    '400010' => '쇼핑몰현황',
    '400200' => '분류관리',
    '400300' => '상품관리',
    '400400' => '주문관리',
    '400410' => '미완료주문',
    '400500' => '상품옵션재고관리',
    '400610' => '상품유형관리',
    '400620' => '재고관리',
    '500000' => '쇼핑몰현황/기타',
    '500100' => '상품판매순위',
    '500110' => '매출현황',
    '500120' => '주문내역출력',
    '500140' => '보관함현황'
);
?>

<div class="local_desc01 local_desc">
    <p>
        현재 로그인한 관리자의 도매까 권한 정보와 메뉴 접근 권한을 확인할 수 있습니다.<br>
        이 페이지는 디버깅 목적으로 사용됩니다.
    </p>
    </div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption>기본 권한 정보</caption>
    <thead>
    <tr>
        <th scope="col">항목</th>
        <th scope="col">값</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>회원 ID</td>
        <td><?php echo $member['mb_id']; ?></td>
    </tr>
    <tr>
        <td>회원 이름</td>
        <td><?php echo $member['mb_name']; ?></td>
    </tr>
    <tr>
        <td>관리자 등급</td>
        <td><?php echo $is_admin; ?></td>
    </tr>
    <tr>
        <td>현재 사용자 타입</td>
        <td><?php echo $user_type; ?></td>
    </tr>
    <?php if ($dmk_auth): ?>
    <tr>
        <td>도매까 회원 유형 (mb_type)</td>
        <td>
        <?php
            switch($dmk_auth['mb_type']) {
                case 0: echo "일반 회원 (0)"; break;
                case 1: echo "총판 관리자 (1)"; break;
                case 2: echo "대리점 관리자 (2)"; break;
                case 3: echo "지점 관리자 (3)"; break;
                default: echo "알 수 없음 ({$dmk_auth['mb_type']})";
            }
        ?>
        </td>
    </tr>
    <tr>
        <td>관리자 타입 (admin_type)</td>
        <td><?php echo $dmk_auth['admin_type'] ?: '설정되지 않음'; ?></td>
    </tr>
    <tr>
        <td>회원 레벨 (mb_level)</td>
        <td><?php echo $dmk_auth['mb_level']; ?></td>
    </tr>
    <tr>
        <td>소속 대리점</td>
        <td><?php echo $dmk_auth['ag_id'] ? $dmk_auth['ag_id'] . ' (' . $dmk_auth['ag_name'] . ')' : '없음'; ?></td>
    </tr>
    <tr>
        <td>소속 지점</td>
        <td><?php echo $dmk_auth['br_id'] ? $dmk_auth['br_id'] . ' (' . $dmk_auth['br_name'] . ')' : '없음'; ?></td>
    </tr>
    <tr>
        <td>최고 관리자 여부</td>
        <td><?php echo $dmk_auth['is_super'] ? '예' : '아니오'; ?></td>
    </tr>
    <?php else: ?>
    <tr>
        <td colspan="2">도매까 권한 정보를 가져올 수 없습니다.</td>
    </tr>
    <?php endif; ?>
    </tbody>
    </table>
    </div>

<div class="tbl_head01 tbl_wrap" style="margin-top: 30px;">
        <table>
    <caption>메뉴 접근 권한 테스트</caption>
            <thead>
                <tr>
        <th scope="col">메뉴 코드</th>
        <th scope="col">메뉴 이름</th>
        <th scope="col">전역 설정 권한</th>
        <th scope="col">실제 접근 권한</th>
        <th scope="col">개별 권한 (Sub용)</th>
                </tr>
            </thead>
            <tbody>
    <?php foreach ($test_menu_codes as $menu_code => $menu_name): ?>
                <tr>
                    <td><?php echo $menu_code; ?></td>
                    <td><?php echo $menu_name; ?></td>
        <td>
            <?php 
            if (function_exists('dmk_is_menu_allowed') && $user_type !== 'none') {
                $global_allowed = dmk_is_menu_allowed($menu_code, $user_type);
                echo $global_allowed ? '<span style="color: green;">허용</span>' : '<span style="color: red;">차단</span>';
            } else {
                echo '확인불가';
            }
            ?>
        </td>
        <td>
            <?php 
            if (function_exists('dmk_auth_check_menu_display')) {
                $actual_allowed = dmk_auth_check_menu_display($menu_code, $menu_code);
                echo $actual_allowed ? '<span style="color: green;">허용</span>' : '<span style="color: red;">차단</span>';
            } else {
                echo '확인불가';
            }
            ?>
        </td>
        <td>
                <?php
            if ($dmk_auth && function_exists('dmk_check_individual_menu_permission')) {
                $individual_allowed = dmk_check_individual_menu_permission($dmk_auth['mb_id'], $menu_code);
                echo $individual_allowed ? '<span style="color: green;">허용</span>' : '<span style="color: red;">차단</span>';
            } else {
                echo '확인불가';
                }
                ?>
        </td>
    </tr>
    <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php if ($dmk_auth && isset($auth)): ?>
<div class="tbl_head01 tbl_wrap" style="margin-top: 30px;">
            <table>
    <caption>기존 권한 배열 ($auth)</caption>
                <thead>
                    <tr>
        <th scope="col">메뉴 코드</th>
        <th scope="col">권한 문자열</th>
                    </tr>
                </thead>
                <tbody>
    <?php foreach ($auth as $menu_code => $auth_str): ?>
                    <tr>
        <td><?php echo $menu_code; ?></td>
        <td><?php echo $auth_str; ?></td>
                    </tr>
    <?php endforeach; ?>
                </tbody>
            </table>
</div>
<?php endif; ?>

        <?php
include_once G5_ADMIN_PATH.'/admin.tail.php';
?> 