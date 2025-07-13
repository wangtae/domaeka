<?php
/**
 * 서버 관리
 * kb_servers 테이블 기반 서버 목록 관리
 */

$sub_menu = "180100";
include_once('./_common.php');

// 권한 체크 (본사만 접근 가능)
auth_check('180100', 'r');

$g5['title'] = '서버 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 검색 조건
$where = " WHERE 1=1 ";
$sql_search = "";

if($stx) {
    $sql_search .= " AND server_name LIKE '%{$stx}%' ";
}

if($status) {
    $sql_search .= " AND status = '{$status}' ";
}

$where .= $sql_search;

// 전체 레코드 수
$sql = " SELECT COUNT(*) as cnt FROM kb_servers $where ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

// 페이징
$rows = 20;
$total_page = ceil($total_count / $rows);
if(!$page) $page = 1;
$start = ($page - 1) * $rows;

// 서버 목록 조회
$sql = " SELECT * FROM kb_servers $where ORDER BY priority ASC, server_id DESC LIMIT $start, $rows ";
$result = sql_query($sql);

?>
<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">총 서버 수</span><span class="ov_num"><?=number_format($total_count)?></span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<fieldset>
    <legend>서버 검색</legend>
    <label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
    <select name="status">
        <option value="">전체 상태</option>
        <option value="healthy" <?php echo $status=='healthy'?'selected':'';?>>정상</option>
        <option value="degraded" <?php echo $status=='degraded'?'selected':'';?>>저하됨</option>
        <option value="maintenance" <?php echo $status=='maintenance'?'selected':'';?>>점검중</option>
        <option value="failed" <?php echo $status=='failed'?'selected':'';?>>실패</option>
    </select>
    <input type="text" name="stx" value="<?php echo $stx?>" id="stx" class="frm_input" placeholder="서버명 검색">
    <input type="submit" class="btn_submit" value="검색">
</fieldset>
</form>

<div class="local_desc01 local_desc">
    <p>카카오봇 서버 목록을 관리합니다. 서버의 상태와 연결된 봇 수를 모니터링할 수 있습니다.</p>
</div>

<<<<<<< HEAD
<div class="btn_fixed_top">
    <a href="./server_form.php" id="server_add" class="btn btn_01">서버 등록</a>
=======
<div class="btn_add01 btn_add">
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
</div>

<form name="fserverlist" id="fserverlist">
<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">
            <label for="chkall" class="sound_only">서버 전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col">ID</th>
        <th scope="col">서버명</th>
        <th scope="col">호스트</th>
        <th scope="col">우선순위</th>
        <th scope="col">상태</th>
        <th scope="col">봇 수</th>
        <th scope="col">등록일</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 상태별 클래스
        $status_class = '';
        $status_text = '';
        switch($row['status']) {
            case 'healthy':
                $status_class = 'online';
                $status_text = '<span class="fc_2677d9">정상</span>';
                break;
            case 'degraded':
                $status_class = 'warn';
                $status_text = '<span class="fc_eb5a00">저하됨</span>';
                break;
            case 'maintenance':
                $status_class = 'maint';
                $status_text = '<span class="fc_6c757d">점검중</span>';
                break;
            case 'failed':
                $status_class = 'offline';
                $status_text = '<span class="fc_dc3545">실패</span>';
                break;
        }
        
        $usage_percent = 0;
        if($row['max_bots'] > 0) {
            $usage_percent = round(($row['current_bots'] / $row['max_bots']) * 100, 1);
        }
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo $row['server_name']?> 선택</label>
            <input type="checkbox" name="chk[]" value="<?php echo $row['server_id']?>" id="chk_<?php echo $i; ?>">
        </td>
        <td class="td_num"><?php echo $row['server_id']?></td>
        <td class="td_left">
            <a href="./server_form.php?w=u&amp;server_id=<?php echo $row['server_id']?>">
                <?php echo get_text($row['server_name'])?>
            </a>
        </td>
        <td><?php echo $row['server_host']?></td>
        <td class="td_num"><?php echo $row['priority']?></td>
        <td class="<?php echo $status_class?>"><?php echo $status_text?></td>
        <td class="td_num">
            <?php echo $row['current_bots']?><?php if($row['max_bots']) echo ' / '.$row['max_bots']?>
            <?php if($row['max_bots'] > 0): ?>
                <br><small>(<?php echo $usage_percent?>%)</small>
            <?php endif; ?>
        </td>
        <td class="td_datetime"><?php echo substr($row['created_at'], 0, 10)?></td>
        <td class="td_mng td_mng_s">
            <a href="./server_form.php?w=u&amp;server_id=<?php echo $row['server_id']?>" class="btn btn_03">수정</a>
        </td>
    </tr>
    <?php
    }
    ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="button" name="btn_submit" value="선택삭제" onclick="btn_check(this.form, 'delete')" class="btn btn_02">
    <a href="./server_form.php" id="server_add" class="btn btn_01">서버 등록</a>
</div>

</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, '?'.$qstr.'&amp;page='); ?>

<script>
function btn_check(f, act)
{
    if (!is_checked("chk[]")) {
        alert("하나 이상 선택하세요.");
        return;
    }

    if (act == "delete") {
        if (!confirm("선택한 서버를 정말 삭제하시겠습니까?\\n\\n한번 삭제한 자료는 복구할 수 없습니다.\\n\\n그래도 삭제하시겠습니까?")) {
            return;
        }
        f.action = "./server_list_delete.php";
    }

    f.submit();
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>