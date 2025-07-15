<?php
/**
 * 채팅방 수정
 * 상태와 설명만 수정 가능
 */

$sub_menu = "180500";
include_once('./_common.php');

auth_check('180500', 'w');

$w = $_GET['w'];
$room_id = $_GET['room_id'];

if (!$room_id) {
    alert('방 ID가 없습니다.');
}

$sql = " SELECT * FROM kb_rooms WHERE room_id = '$room_id' ";
$room = sql_fetch($sql);
if (!$room['room_id']) {
    alert('등록된 채팅방이 아닙니다.');
}

$g5['title'] = '채팅방 수정';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 방장 정보 파싱
$owners_info = '';
if($room['room_owners']) {
    $owners = json_decode($room['room_owners'], true);
    if($owners && is_array($owners)) {
        $owners_info = implode(', ', $owners);
    }
}

// 로그 설정 파싱
$log_settings = [];
if($room['log_settings']) {
    $log_settings = json_decode($room['log_settings'], true);
}
?>

<form name="froom" id="froom" action="./room_form_update.php" onsubmit="return froom_submit(this);" method="post">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="room_id" value="<?php echo $room_id ?>">
<input type="hidden" name="token" value="<?php echo get_admin_token() ?>">

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?></caption>
    <colgroup>
        <col class="grid_4">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row">방 ID</th>
        <td><?php echo substr($room['room_id'], 0, 10) . '...'?></td>
    </tr>
    <tr>
        <th scope="row">채팅방명</th>
        <td><?php echo get_text($room['room_name'])?></td>
    </tr>
    <tr>
        <th scope="row">봇명</th>
        <td><?php echo get_text($room['bot_name'])?></td>
    </tr>
    <tr>
        <th scope="row">동시실행수</th>
        <td><?php echo $room['room_concurrency']?></td>
    </tr>
    <tr>
        <th scope="row">방장 정보</th>
        <td><?php echo $owners_info ? get_text($owners_info) : '정보 없음'?></td>
    </tr>
    <tr>
        <th scope="row">등록일시</th>
        <td><?php echo $room['created_at']?></td>
    </tr>
    <tr>
        <th scope="row">최종 업데이트</th>
        <td><?php echo $room['updated_at'] ? $room['updated_at'] : '수정 이력 없음'?></td>
    </tr>
    <?php
    // 관리자 권한 체크 - 본사와 총판만 지점 배정 가능
    $user_info = dmk_get_admin_auth($member['mb_id']);
    if ($user_info['type'] == 'super' || $user_info['type'] == 'distributor'):
    ?>
    <tr>
        <th scope="row"><label for="owner_type">배정 지점<strong class="sound_only">필수</strong></label></th>
        <td>
            <?php if ($user_info['type'] == 'super'): ?>
            <!-- 본사는 총판부터 선택 -->
            <select name="dt_id" id="dt_id" class="frm_input" onchange="loadAgencies(this.value)">
                <option value="">총판 선택</option>
                <?php
                $sql = " SELECT dt_id, dt_name FROM g5_dmk_distributors ORDER BY dt_name ";
                $result_dt = sql_query($sql);
                while($dt = sql_fetch_array($result_dt)) {
                    $selected = '';
                    if ($room['owner_type'] == 'distributor' && $room['owner_id'] == $dt['dt_id']) {
                        $selected = 'selected';
                    }
                    echo '<option value="'.$dt['dt_id'].'" '.$selected.'>'.$dt['dt_name'].'</option>';
                }
                ?>
            </select>
            <?php else: ?>
            <!-- 총판은 자신의 ID를 hidden으로 -->
            <input type="hidden" name="dt_id" id="dt_id" value="<?php echo $user_info['key']?>">
            <?php echo $user_info['name']?> (총판)
            <?php endif; ?>
            
            <select name="ag_id" id="ag_id" class="frm_input" onchange="loadBranches(this.value)" style="margin-left:5px;">
                <option value="">대리점 선택</option>
                <?php if ($room['owner_type'] == 'agency' || $room['owner_type'] == 'branch'): ?>
                <?php
                // 기존 대리점 정보 로드
                $dt_id_for_agency = '';
                if ($room['owner_type'] == 'agency') {
                    $sql = " SELECT dt_id FROM g5_dmk_agencies WHERE ag_id = '{$room['owner_id']}' ";
                    $ag_info = sql_fetch($sql);
                    $dt_id_for_agency = $ag_info['dt_id'];
                } else if ($room['owner_type'] == 'branch') {
                    $sql = " SELECT a.dt_id FROM g5_dmk_agencies a 
                             JOIN g5_dmk_branches b ON a.ag_id = b.ag_id 
                             WHERE b.br_id = '{$room['owner_id']}' ";
                    $br_info = sql_fetch($sql);
                    $dt_id_for_agency = $br_info['dt_id'];
                }
                
                if ($dt_id_for_agency) {
                    $sql = " SELECT ag_id, ag_name FROM g5_dmk_agencies WHERE dt_id = '$dt_id_for_agency' ORDER BY ag_name ";
                    $result_ag = sql_query($sql);
                    while($ag = sql_fetch_array($result_ag)) {
                        $selected = '';
                        if ($room['owner_type'] == 'agency' && $room['owner_id'] == $ag['ag_id']) {
                            $selected = 'selected';
                        } else if ($room['owner_type'] == 'branch') {
                            $sql2 = " SELECT ag_id FROM g5_dmk_branches WHERE br_id = '{$room['owner_id']}' ";
                            $br_ag = sql_fetch($sql2);
                            if ($br_ag['ag_id'] == $ag['ag_id']) {
                                $selected = 'selected';
                            }
                        }
                        echo '<option value="'.$ag['ag_id'].'" '.$selected.'>'.$ag['ag_name'].'</option>';
                    }
                }
                ?>
                <?php endif; ?>
            </select>
            
            <select name="br_id" id="br_id" class="frm_input" style="margin-left:5px;">
                <option value="">지점 선택</option>
                <?php if ($room['owner_type'] == 'branch'): ?>
                <?php
                // 기존 지점 정보 로드
                $sql = " SELECT ag_id FROM g5_dmk_branches WHERE br_id = '{$room['owner_id']}' ";
                $br_info = sql_fetch($sql);
                if ($br_info['ag_id']) {
                    $sql = " SELECT br_id, br_name FROM g5_dmk_branches WHERE ag_id = '{$br_info['ag_id']}' ORDER BY br_name ";
                    $result_br = sql_query($sql);
                    while($br = sql_fetch_array($result_br)) {
                        $selected = ($room['owner_id'] == $br['br_id']) ? 'selected' : '';
                        echo '<option value="'.$br['br_id'].'" '.$selected.'>'.$br['br_name'].'</option>';
                    }
                }
                ?>
                <?php endif; ?>
            </select>
            
            <div class="frm_info">
                채팅방을 관리할 지점을 선택하세요. 지점을 선택하지 않으면 상위 레벨(대리점/총판)이 관리합니다.
            </div>
        </td>
    </tr>
    <?php endif; ?>
    <tr>
        <th scope="row"><label for="status">상태<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="status" id="status" required class="frm_input">
                <option value="pending" <?php echo ($room['status']=='pending')?'selected':'';?>>승인 대기</option>
                <option value="approved" <?php echo ($room['status']=='approved')?'selected':'';?>>승인됨</option>
                <option value="denied" <?php echo ($room['status']=='denied')?'selected':'';?>>거부됨</option>
                <option value="revoked" <?php echo ($room['status']=='revoked')?'selected':'';?>>취소됨</option>
                <option value="blocked" <?php echo ($room['status']=='blocked')?'selected':'';?>>차단됨</option>
            </select>
            <div class="frm_info">
                채팅방의 승인 상태를 변경할 수 있습니다.
            </div>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="description">설명/메모</label></th>
        <td>
            <textarea name="description" id="description" class="frm_input" rows="5" cols="50" placeholder="채팅방에 대한 설명이나 메모를 입력하세요"><?php echo get_text($room['descryption'])?></textarea>
            <div class="frm_info">
                관리자용 메모입니다. 채팅방 관리에 필요한 정보를 기록하세요.
            </div>
        </td>
    </tr>
    <?php if($room['status'] == 'denied' || $room['status'] == 'blocked'): ?>
    <tr>
        <th scope="row"><label for="rejection_reason">거부/차단 사유</label></th>
        <td>
            <textarea name="rejection_reason" id="rejection_reason" class="frm_input" rows="3" cols="50" placeholder="거부 또는 차단 사유를 입력하세요"><?php echo isset($room['rejection_reason']) ? get_text($room['rejection_reason']) : ''?></textarea>
        </td>
    </tr>
    <?php endif; ?>
    
    <tr>
        <th scope="row" colspan="2" style="background-color: #f8f9fa; text-align: center; font-weight: bold;">로그 설정</th>
    </tr>
    <tr>
        <th scope="row"><label for="log_enabled">로그 활성화</label></th>
        <td>
            <input type="checkbox" name="log_enabled" id="log_enabled" value="1" <?php echo (isset($log_settings['enabled']) && $log_settings['enabled']) ? 'checked' : '';?>>
            <label for="log_enabled">채팅 로그를 수집합니다</label>
            <div class="frm_info">
                체크하면 이 채팅방의 메시지가 데이터베이스에 저장됩니다.
            </div>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="log_retention_days">로그 보관일수</label></th>
        <td>
            <input type="number" name="log_retention_days" id="log_retention_days" class="frm_input" min="1" max="365" value="<?php echo $log_settings['retention_days'] ?? 30?>">
            <span>일</span>
            <div class="frm_info">
                설정한 일수가 지난 로그는 자동으로 삭제됩니다. (기본값: 30일)
            </div>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./room_list.php" class="btn btn_02">목록</a>
    <input type="submit" name="btn_submit" value="확인" id="btn_submit" accesskey="s" class="btn_submit btn">
    <?php if($room['status'] != 'blocked'): ?>
    <a href="./room_block.php?room_id=<?php echo urlencode($room_id)?>" class="btn btn_01" onclick="return confirm('이 채팅방을 차단하시겠습니까?')">차단</a>
    <?php endif; ?>
</div>

</form>

<script>
function loadAgencies(dt_id) {
    var ag_select = document.getElementById('ag_id');
    var br_select = document.getElementById('br_id');
    
    // 대리점, 지점 선택 초기화
    ag_select.innerHTML = '<option value="">대리점 선택</option>';
    br_select.innerHTML = '<option value="">지점 선택</option>';
    
    if (!dt_id) return;
    
    // AJAX로 대리점 목록 로드
    var xhr = new XMLHttpRequest();
    xhr.open('GET', './ajax.get_agencies.php?dt_id=' + dt_id, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var agencies = JSON.parse(xhr.responseText);
                agencies.forEach(function(agency) {
                    var option = document.createElement('option');
                    option.value = agency.ag_id;
                    option.textContent = agency.ag_name;
                    ag_select.appendChild(option);
                });
            } catch (e) {
                console.error('대리점 목록 로드 실패:', e);
            }
        }
    };
    xhr.send();
}

function loadBranches(ag_id) {
    var br_select = document.getElementById('br_id');
    
    // 지점 선택 초기화
    br_select.innerHTML = '<option value="">지점 선택</option>';
    
    if (!ag_id) return;
    
    // AJAX로 지점 목록 로드
    var xhr = new XMLHttpRequest();
    xhr.open('GET', './ajax.get_branches.php?ag_id=' + ag_id, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var branches = JSON.parse(xhr.responseText);
                branches.forEach(function(branch) {
                    var option = document.createElement('option');
                    option.value = branch.br_id;
                    option.textContent = branch.br_name;
                    br_select.appendChild(option);
                });
            } catch (e) {
                console.error('지점 목록 로드 실패:', e);
            }
        }
    };
    xhr.send();
}

function froom_submit(f)
{
    if (!f.status.value) {
        alert("상태를 선택해주세요.");
        f.status.focus();
        return false;
    }
    
    // 거부나 차단으로 변경하는 경우 확인
    if ((f.status.value == 'denied' || f.status.value == 'blocked') && 
        ('<?php echo $room['status']?>' != 'denied' && '<?php echo $room['status']?>' != 'blocked')) {
        if (!confirm('채팅방을 ' + (f.status.value == 'denied' ? '거부' : '차단') + ' 상태로 변경하시겠습니까?')) {
            return false;
        }
    }
    
    return true;
}

// 페이지 로드 시 기존 값이 있으면 하위 목록 로드
<?php if ($user_info['type'] == 'super' && $room['owner_type'] && $room['owner_id']): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($room['owner_type'] == 'distributor'): ?>
    // 총판이 선택된 경우
    loadAgencies('<?php echo $room['owner_id']?>');
    <?php elseif ($room['owner_type'] == 'agency'): ?>
    // 대리점이 선택된 경우
    <?php
    $sql = " SELECT dt_id FROM g5_dmk_agencies WHERE ag_id = '{$room['owner_id']}' ";
    $ag_info = sql_fetch($sql);
    ?>
    document.getElementById('dt_id').value = '<?php echo $ag_info['dt_id']?>';
    loadAgencies('<?php echo $ag_info['dt_id']?>');
    setTimeout(function() {
        document.getElementById('ag_id').value = '<?php echo $room['owner_id']?>';
        loadBranches('<?php echo $room['owner_id']?>');
    }, 500);
    <?php elseif ($room['owner_type'] == 'branch'): ?>
    // 지점이 선택된 경우
    <?php
    $sql = " SELECT a.dt_id, b.ag_id FROM g5_dmk_agencies a 
             JOIN g5_dmk_branches b ON a.ag_id = b.ag_id 
             WHERE b.br_id = '{$room['owner_id']}' ";
    $br_info = sql_fetch($sql);
    ?>
    document.getElementById('dt_id').value = '<?php echo $br_info['dt_id']?>';
    loadAgencies('<?php echo $br_info['dt_id']?>');
    setTimeout(function() {
        document.getElementById('ag_id').value = '<?php echo $br_info['ag_id']?>';
        loadBranches('<?php echo $br_info['ag_id']?>');
        setTimeout(function() {
            document.getElementById('br_id').value = '<?php echo $room['owner_id']?>';
        }, 500);
    }, 500);
    <?php endif; ?>
});
<?php endif; ?>
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>