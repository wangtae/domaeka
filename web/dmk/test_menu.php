<?php
include_once './_common.php';

// 권한 체크
auth_check_menu($auth, "dmk", "r");

$g5['title'] = "도매까 메뉴 테스트";
include_once G5_ADMIN_PATH.'/admin.head.php';

// 도매까 권한 정보 가져오기
if (function_exists('dmk_get_admin_auth')) {
    $dmk_auth = dmk_get_admin_auth();
} else {
    $dmk_auth = null;
}
?>

<div class="local_desc01 local_desc">
    <p>
        도매까 메뉴 시스템이 정상적으로 통합되었습니다.<br>
        현재 관리자의 도매까 권한 정보를 확인할 수 있습니다.
    </p>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption>도매까 권한 정보</caption>
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
    <?php if ($dmk_auth): ?>
    <tr>
        <td>도매까 회원 유형</td>
        <td>
            <?php 
            switch($dmk_auth['mb_type']) {
                case 0: echo "일반 회원"; break;
                case 1: echo "본사 관리자"; break;
                case 2: echo "대리점 관리자"; break;
                case 3: echo "지점 관리자"; break;
                default: echo "알 수 없음 ({$dmk_auth['mb_type']})";
            }
            ?>
        </td>
    </tr>
    <tr>
        <td>소속 대리점</td>
        <td><?php echo $dmk_auth['ag_id'] ? $dmk_auth['ag_id'] : '없음'; ?></td>
    </tr>
    <tr>
        <td>소속 지점</td>
        <td><?php echo $dmk_auth['br_id'] ? $dmk_auth['br_id'] : '없음'; ?></td>
    </tr>
    <tr>
        <td>최고 관리자 여부</td>
        <td><?php echo $dmk_auth['is_super'] ? '예' : '아니오'; ?></td>
    </tr>
    <?php else: ?>
    <tr>
        <td colspan="2">도매까 권한 정보를 불러올 수 없습니다.</td>
    </tr>
    <?php endif; ?>
    </tbody>
    </table>
</div>

<div class="btn_confirm01 btn_confirm">
    <a href="<?php echo G5_ADMIN_URL; ?>">관리자 메인으로</a>
    <a href="<?php echo G5_URL; ?>/dmk/adm/agency_admin/agency_list.php">대리점 관리</a>
    <a href="<?php echo G5_URL; ?>/dmk/adm/branch_admin/branch_list.php">지점 관리</a>
</div>

<?php
include_once G5_ADMIN_PATH.'/admin.tail.php';
?> 