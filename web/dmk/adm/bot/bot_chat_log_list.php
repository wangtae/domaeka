<?php
$sub_menu = '180500';
include_once('./_common.php');

auth_check($auth[$sub_menu], "r");

$g5['title'] = '채팅 내역 조회';
include_once (G5_ADMIN_PATH.'/admin.head.php');

$sql_common = " FROM kb_chat_log ";
$sql_search = " WHERE 1 ";

// 권한에 따른 데이터 필터링 (본사, 총판, 대리점, 지점)
// 본사 관리자는 모든 채팅 로그 조회
// 총판은 자신의 하위 대리점/지점 채팅 로그 조회
// 대리점은 자신의 하위 지점 채팅 로그 조회
// 지점은 자신의 채팅 로그만 조회

$sql = " SELECT COUNT(*) as cnt {$sql_common} {$sql_search} ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);
if ($page < 1) { $page = 1; }
$from_record = ($page - 1) * $rows;

$sql = " SELECT * {$sql_common} {$sql_search} ORDER BY id DESC LIMIT {$from_record}, {$rows} ";
$result = sql_query($sql);

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">전체 </span><span class="ov_num"> <?php echo number_format($total_count) ?>건</span></span>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">ID</th>
        <th scope="col">지점 ID</th>
        <th scope="col">발신자</th>
        <th scope="col">메시지</th>
        <th scope="col">시간</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
    ?>
    <tr>
        <td><?php echo $row['id']; ?></td>
        <td><?php echo $row['branch_id']; ?></td>
        <td><?php echo $row['sender']; ?></td>
        <td><?php echo $row['message']; ?></td>
        <td><?php echo $row['timestamp']; ?></td>
    </tr>
    <?php
    }
    if ($i == 0)
        echo '<tr><td colspan="5" class="empty_table">데이터가 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page='); ?>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>