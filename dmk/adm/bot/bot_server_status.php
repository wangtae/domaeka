<?php
$sub_menu = '180100';
include_once('../../_common.php');

auth_check($auth[$sub_menu], "r");

$g5['title'] = '봇 서버 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 본사 관리자만 접근 가능
if (!$dmk_auth['is_super']) {
    alert('본사 관리자만 접근할 수 있습니다.');
}

?>

<div class="local_desc01 local_desc">
    <p>
        <strong>봇 서버 관리</strong><br>
        카카오봇 서버(Python) 프로그램의 프로세스를 관리하는 메뉴입니다. (프로세스 시작/중지/재시작 등)
    </p>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">서버 상태</th>
        <th scope="col">프로세스 ID</th>
        <th scope="col">마지막 업데이트</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td><span class="txt_true">실행 중</span></td>
        <td>12345</td>
        <td>2023-10-27 10:00:00</td>
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