<?php
$sub_menu = '180300';
include_once('./_common.php');

auth_check($auth[$sub_menu], "r");

$g5['title'] = '스케줄링 발송 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// dmk_bot_schedule 테이블에서 데이터를 가져옵니다.
$sql_common = " FROM kb_schedule ";
$sql_search = " WHERE 1 ";

// 권한에 따른 데이터 필터링 (본사, 총판, 대리점, 지점)
// 본사 관리자는 모든 스케줄 조회
// 총판은 자신의 하위 대리점/지점 스케줄 조회
// 대리점은 자신의 하위 지점 스케줄 조회
// 지점은 자신의 스케줄만 조회

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

<div class="btn_fixed_top">
    <a href="./bot_schedule_form.php" class="btn_01 btn">스케줄 등록</a>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">ID</th>
        <th scope="col">대상 지점</th>
        <th scope="col">메시지</th>
        <th scope="col">발송 시간</th>
        <th scope="col">발송 요일</th>
        <th scope="col">상태</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
    ?>
    <tr>
        <td><?php echo $row['id']; ?></td>
        <td><?php echo $row['target_branch_id']; ?></td>
        <td><?php echo $row['message_content']; ?></td>
        <td><?php echo $row['send_time']; ?></td>
        <td><?php echo $row['send_days']; ?></td>
        <td><?php echo $row['status']; ?></td>
        <td>
            <a href="./bot_schedule_form.php?w=u&id=<?php echo $row['id']; ?>" class="btn_03">수정</a>
            <a href="./bot_schedule_form_update.php?w=d&id=<?php echo $row['id']; ?>" onclick="return confirm('정말 삭제하시겠습니까?');" class="btn_02">삭제</a>
        </td>
    </tr>
    <?php
    }
    if ($i == 0)
        echo '<tr><td colspan="7" class="empty_table">데이터가 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page='); ?>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>