<?php
/**
 * 클라이언트 봇 관리
 * kb_bot_devices 테이블 기반 봇 디바이스 관리
 */

$sub_menu = "180300";
include_once('./_common.php');

auth_check('180300', 'r');

$g5['title'] = '클라이언트 봇 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 검색 조건
$where = " WHERE 1=1 ";
$sql_search = "";

if($stx) {
    $sql_search .= " AND (bot_name LIKE '%{$stx}%' OR device_id LIKE '%{$stx}%' OR ip_address LIKE '%{$stx}%') ";
}

if($status) {
    $sql_search .= " AND status = '{$status}' ";
}

if($client_type) {
    $sql_search .= " AND client_type LIKE '%{$client_type}%' ";
}

$where .= $sql_search;

// 전체 레코드 수
$sql = " SELECT COUNT(*) as cnt FROM kb_bot_devices $where ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

// 페이징
$rows = 20;
$total_page = ceil($total_count / $rows);
if(!$page) $page = 1;
$start = ($page - 1) * $rows;

// 봇 디바이스 목록 조회
$sql = " SELECT * FROM kb_bot_devices $where ORDER BY created_at DESC LIMIT $start, $rows ";
$result = sql_query($sql);

// 상태별 통계
$stats_sql = " SELECT status, COUNT(*) as cnt FROM kb_bot_devices GROUP BY status ";
$stats_result = sql_query($stats_sql);
$stats = [];
while($row = sql_fetch_array($stats_result)) {
    $stats[$row['status']] = $row['cnt'];
}

?>
<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">총 디바이스</span><span class="ov_num"><?=number_format($total_count)?></span></span>
    <span class="btn_ov01"><span class="ov_txt">승인됨</span><span class="ov_num"><?=number_format($stats['approved'] ?? 0)?></span></span>
    <span class="btn_ov01"><span class="ov_txt">대기중</span><span class="ov_num"><?=number_format($stats['pending'] ?? 0)?></span></span>
    <span class="btn_ov01"><span class="ov_txt">거부됨</span><span class="ov_num"><?=number_format($stats['denied'] ?? 0)?></span></span>
</div>

<form name="fsearch" id="fsearch" class="local_sch01 local_sch" method="get">
<fieldset>
    <legend>봇 디바이스 검색</legend>
    <select name="status">
        <option value="">전체 상태</option>
        <option value="pending" <?php echo $status=='pending'?'selected':'';?>>승인 대기</option>
        <option value="approved" <?php echo $status=='approved'?'selected':'';?>>승인됨</option>
        <option value="denied" <?php echo $status=='denied'?'selected':'';?>>거부됨</option>
        <option value="revoked" <?php echo $status=='revoked'?'selected':'';?>>취소됨</option>
        <option value="blocked" <?php echo $status=='blocked'?'selected':'';?>>차단됨</option>
    </select>
    <select name="client_type">
        <option value="">전체 타입</option>
        <option value="MessengerBotR" <?php echo $client_type=='MessengerBotR'?'selected':'';?>>MessengerBotR</option>
        <option value="AutoReply" <?php echo $client_type=='AutoReply'?'selected':'';?>>AutoReply</option>
    </select>
    <input type="text" name="stx" value="<?php echo $stx?>" id="stx" class="frm_input" placeholder="봇명/디바이스ID/IP 검색">
    <input type="submit" class="btn_submit" value="검색">
</fieldset>
</form>

<div class="local_desc01 local_desc">
    <p>카카오톡 봇 클라이언트 디바이스를 관리합니다. 새로운 디바이스의 승인/거부 및 기존 디바이스의 상태를 관리할 수 있습니다.</p>
</div>

<form name="fdevicelist" id="fdevicelist">
<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">
            <label for="chkall" class="sound_only">디바이스 전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col">ID</th>
        <th scope="col">봇명</th>
        <th scope="col">디바이스 ID</th>
        <th scope="col">IP 주소</th>
        <th scope="col">클라이언트</th>
        <th scope="col">상태</th>
        <th scope="col">등록일</th>
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
            case 'pending':
                $status_class = 'warn';
                $status_text = '<span class="fc_ffc107">승인 대기</span>';
                break;
            case 'approved':
                $status_class = 'online';
                $status_text = '<span class="fc_28a745">승인됨</span>';
                break;
            case 'denied':
                $status_class = 'offline';
                $status_text = '<span class="fc_dc3545">거부됨</span>';
                break;
            case 'revoked':
                $status_class = 'offline';
                $status_text = '<span class="fc_6c757d">취소됨</span>';
                break;
            case 'blocked':
                $status_class = 'error';
                $status_text = '<span class="fc_dc3545">차단됨</span>';
                break;
        }
        
        // 디바이스 ID 마스킹
        $masked_device_id = substr($row['device_id'], 0, 8) . '***' . substr($row['device_id'], -4);
        
        // 클라이언트 정보 파싱
        $client_info = '';
        if($row['client_type']) {
            $client_info = $row['client_type'];
            if($row['client_version']) {
                $client_info .= '<br><small>v'.$row['client_version'].'</small>';
            }
        }
    ?>
    <tr class="<?php echo $bg; ?>">
        <td class="td_chk">
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo $row['bot_name']?> 선택</label>
            <input type="checkbox" name="chk[]" value="<?php echo $row['id']?>" id="chk_<?php echo $i; ?>">
        </td>
        <td class="td_num"><?php echo $row['id']?></td>
        <td class="td_left">
            <a href="./bot_device_form.php?w=u&amp;device_id=<?php echo $row['id']?>">
                <?php echo get_text($row['bot_name'])?>
            </a>
        </td>
        <td class="td_monospace"><?php echo $masked_device_id?></td>
        <td><?php echo $row['ip_address']?></td>
        <td><?php echo $client_info?></td>
        <td class="<?php echo $status_class?>"><?php echo $status_text?></td>
        <td class="td_datetime"><?php echo substr($row['created_at'], 0, 16)?></td>
        <td class="td_mng td_mng_s">
            <?php if($row['status'] == 'pending'): ?>
                <a href="./bot_device_approve.php?id=<?php echo $row['id']?>&amp;action=approve" class="btn btn_01" onclick="return confirm('이 디바이스를 승인하시겠습니까?')">승인</a>
                <a href="./bot_device_approve.php?id=<?php echo $row['id']?>&amp;action=deny" class="btn btn_02" onclick="return confirm('이 디바이스를 거부하시겠습니까?')">거부</a>
            <?php else: ?>
                <a href="./bot_device_form.php?w=u&amp;device_id=<?php echo $row['id']?>" class="btn btn_03">수정</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php
    }
    ?>
    </tbody>
    </table>
</div>

<div class="btn_list01 btn_list">
    <input type="button" name="btn_submit" value="선택 승인" onclick="btn_check(this.form, 'approve')" class="btn btn_01">
    <input type="button" name="btn_submit" value="선택 거부" onclick="btn_check(this.form, 'deny')" class="btn btn_02">
    <input type="button" name="btn_submit" value="선택 삭제" onclick="btn_check(this.form, 'delete')" class="btn btn_02">
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

    if (act == "approve") {
        if (!confirm("선택한 디바이스를 승인하시겠습니까?")) {
            return;
        }
        f.action = "./bot_device_bulk_action.php?action=approve";
    } else if (act == "deny") {
        if (!confirm("선택한 디바이스를 거부하시겠습니까?")) {
            return;
        }
        f.action = "./bot_device_bulk_action.php?action=deny";
    } else if (act == "delete") {
        if (!confirm("선택한 디바이스를 정말 삭제하시겠습니까?\\n\\n한번 삭제한 자료는 복구할 수 없습니다.\\n\\n그래도 삭제하시겠습니까?")) {
            return;
        }
        f.action = "./bot_device_list_delete.php";
    }

    f.submit();
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>