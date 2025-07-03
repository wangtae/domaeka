<?php
$sub_menu = '180200';
include_once('../../_common.php');

auth_check($auth[$sub_menu], "r");

$g5['title'] = '클라이언트 봇 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 본사 관리자만 접근 가능
if (!$dmk_auth['is_super']) {
    alert('본사 관리자만 접근할 수 있습니다.');
}

?>

<div class="local_desc01 local_desc">
    <p>
        <strong>클라이언트 봇 관리</strong><br>
        실제 지점별 톡방에 참여하여 활동하는 클라이언트 봇의 상태를 관리하고 제어하는 메뉴입니다.
    </p>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">지점 ID</th>
        <th scope="col">봇 상태</th>
        <th scope="col">마지막 활동</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>branch_001</td>
        <td><span class="txt_true">온라인</span></td>
        <td>2023-10-27 10:05:00</td>
        <td>
            <button type="button" class="btn_03">재시작</button>
            <button type="button" class="btn_02">중지</button>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>