<?php
/**
 * 서버 프로세스 관리
 * kb_server_processes 테이블 기반 프로세스 목록 관리
 */

$sub_menu = "180200";
include_once('./_common.php');

auth_check('180200', 'r');

$g5['title'] = '서버 프로세스 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 검색 조건
$where = " WHERE 1=1 ";
$sql_search = "";

if($stx) {
    $sql_search .= " AND (p.process_name LIKE '%{$stx}%' OR s.server_name LIKE '%{$stx}%') ";
}

if($server_id) {
    $sql_search .= " AND p.server_id = '{$server_id}' ";
}

if($status) {
    $sql_search .= " AND p.status = '{$status}' ";
}

if($process_type) {
    $sql_search .= " AND p.process_type = '{$process_type}' ";
}

$where .= $sql_search;

// 서버 목록 조회 (선택박스용)
$server_list = [];
$sql = " SELECT server_id, server_name FROM kb_servers ORDER BY priority ASC, server_name ASC ";
$result_servers = sql_query($sql);
while($row = sql_fetch_array($result_servers)) {
    $server_list[] = $row;
}

// 전체 레코드 수
$sql = " SELECT COUNT(*) as cnt 
         FROM kb_server_processes p 
         LEFT JOIN kb_servers s ON p.server_id = s.server_id 
         $where ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

// 페이징
$rows = 20;
$total_page = ceil($total_count / $rows);
if(!$page) $page = 1;
$start = ($page - 1) * $rows;

// 프로세스 목록 조회
$sql = " SELECT p.*, s.server_name, s.server_host
         FROM kb_server_processes p 
         LEFT JOIN kb_servers s ON p.server_id = s.server_id 
         $where 
         ORDER BY p.server_id ASC, p.process_name ASC 
         LIMIT $start, $rows ";
$result = sql_query($sql);

?>
<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">총 프로세스 수</span><span class="ov_num"><?=number_format($total_count)?></span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<fieldset>
    <legend>프로세스 검색</legend>
    <select name="server_id">
        <option value="">전체 서버</option>
        <?php foreach($server_list as $sv): ?>
        <option value="<?=$sv['server_id']?>" <?php echo $server_id==$sv['server_id']?'selected':'';?>><?=get_text($sv['server_name'])?></option>
        <?php endforeach; ?>
    </select>
    <select name="process_type">
        <option value="">전체 타입</option>
        <option value="live" <?php echo $process_type=='live'?'selected':'';?>>운영</option>
        <option value="test" <?php echo $process_type=='test'?'selected':'';?>>테스트</option>
    </select>
    <select name="status">
        <option value="">전체 상태</option>
        <option value="running" <?php echo $status=='running'?'selected':'';?>>실행중</option>
        <option value="stopped" <?php echo $status=='stopped'?'selected':'';?>>중지됨</option>
        <option value="starting" <?php echo $status=='starting'?'selected':'';?>>시작중</option>
        <option value="stopping" <?php echo $status=='stopping'?'selected':'';?>>중지중</option>
        <option value="error" <?php echo $status=='error'?'selected':'';?>>오류</option>
        <option value="crashed" <?php echo $status=='crashed'?'selected':'';?>>크래시</option>
    </select>
    <input type="text" name="stx" value="<?php echo $stx?>" id="stx" class="frm_input" placeholder="프로세스명/서버명 검색">
    <input type="submit" class="btn_submit" value="검색">
</fieldset>
</form>

<div class="local_desc01 local_desc">
    <p>카카오봇 서버 프로세스를 관리합니다. 각 프로세스의 상태와 리소스 사용량을 모니터링할 수 있습니다.</p>
</div>

<<<<<<< HEAD
<div class="btn_fixed_top">
    <a href="./server_process_form.php" id="process_add" class="btn btn_01">프로세스 등록</a>
=======
<div class="btn_add01 btn_add">
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
</div>

<form name="fprocesslist" id="fprocesslist">
<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">
            <label for="chkall" class="sound_only">프로세스 전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col">ID</th>
        <th scope="col">프로세스명</th>
        <th scope="col">서버</th>
        <th scope="col">타입</th>
        <th scope="col">포트</th>
        <th scope="col">PID</th>
        <th scope="col">상태</th>
        <th scope="col">리소스 사용량</th>
        <th scope="col">마지막 하트비트</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 상태별 클래스와 텍스트
        $status_class = '';
        $status_text = '';
        switch($row['status']) {
            case 'running':
                $status_class = 'online';
                $status_text = '<span class="fc_2677d9">실행중</span>';
                break;
            case 'stopped':
                $status_class = 'offline';
                $status_text = '<span class="fc_6c757d">중지됨</span>';
                break;
            case 'starting':
                $status_class = 'warn';
                $status_text = '<span class="fc_28a745">시작중</span>';
                break;
            case 'stopping':
                $status_class = 'warn';
                $status_text = '<span class="fc_ffc107">중지중</span>';
                break;
            case 'error':
                $status_class = 'error';
                $status_text = '<span class="fc_dc3545">오류</span>';
                break;
            case 'crashed':
                $status_class = 'error';
                $status_text = '<span class="fc_dc3545">크래시</span>';
                break;
        }
        
        // 프로세스 타입 표시
        $type_text = $row['process_type'] == 'live' ? '<span class="fc_dc3545">운영</span>' : '<span class="fc_28a745">테스트</span>';
        
        // 하트비트 시간 계산
        $heartbeat_text = '';
        if($row['last_heartbeat']) {
            $heartbeat_time = strtotime($row['last_heartbeat']);
            $diff = time() - $heartbeat_time;
            if($diff < 60) {
                $heartbeat_text = $diff.'초 전';
            } elseif($diff < 3600) {
                $heartbeat_text = floor($diff/60).'분 전';
            } else {
                $heartbeat_text = substr($row['last_heartbeat'], 5, 11);
            }
        } else {
            $heartbeat_text = '-';
        }
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo $row['process_name']?> 선택</label>
            <input type="checkbox" name="chk[]" value="<?php echo $row['process_id']?>" id="chk_<?php echo $i; ?>">
        </td>
        <td class="td_num"><?php echo $row['process_id']?></td>
        <td class="td_left">
            <a href="./server_process_form.php?w=u&amp;process_id=<?php echo $row['process_id']?>">
                <?php echo get_text($row['process_name'])?>
            </a>
        </td>
        <td><?php echo get_text($row['server_name'])?><br><small><?php echo $row['server_host']?></small></td>
        <td><?php echo $type_text?></td>
        <td class="td_num"><?php echo $row['port']?></td>
        <td class="td_num"><?php echo $row['pid'] ? $row['pid'] : '-'?></td>
        <td class="<?php echo $status_class?>"><?php echo $status_text?></td>
        <td class="td_num">
            <?php if($row['cpu_usage']): ?>
                CPU: <?php echo $row['cpu_usage']?>%<br>
            <?php endif; ?>
            <?php if($row['memory_usage']): ?>
                MEM: <?php echo number_format($row['memory_usage'], 1)?>MB
            <?php endif; ?>
            <?php if(!$row['cpu_usage'] && !$row['memory_usage']): ?>
                -
            <?php endif; ?>
        </td>
        <td class="td_datetime"><?php echo $heartbeat_text?></td>
        <td class="td_mng td_mng_s">
            <a href="./server_process_form.php?w=u&amp;process_id=<?php echo $row['process_id']?>" class="btn btn_03">수정</a>
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
    <a href="./server_process_form.php" id="process_add" class="btn btn_01">프로세스 등록</a>
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
        if (!confirm("선택한 프로세스를 정말 삭제하시겠습니까?\\n\\n한번 삭제한 자료는 복구할 수 없습니다.\\n\\n그래도 삭제하시겠습니까?")) {
            return;
        }
        f.action = "./server_process_list_delete.php";
    }

    f.submit();
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>