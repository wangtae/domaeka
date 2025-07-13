<?php
/**
 * 서버 프로세스 등록/수정
 */

<<<<<<< HEAD
$sub_menu = "180200";
=======
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
include_once('./_common.php');

auth_check('180200', 'w');

$w = $_GET['w'];
$process_id = $_GET['process_id'];

if ($w == 'u' && $process_id) {
<<<<<<< HEAD
    $sql = " SELECT p.*, s.server_name FROM kb_server_processes p 
             LEFT JOIN kb_servers s ON p.server_id = s.server_id 
             WHERE p.process_id = '$process_id' ";
=======
    $sql = " SELECT * FROM kb_server_processes WHERE process_id = '$process_id' ";
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
    $pr = sql_fetch($sql);
    if (!$pr['process_id']) {
        alert('등록된 프로세스가 아닙니다.');
    }
} else {
    $pr = array();
}

<<<<<<< HEAD
// 서버 목록 조회
$server_list = array();
$sql = " SELECT server_id, server_name FROM kb_servers WHERE status = 'healthy' ORDER BY server_name ";
$result = sql_query($sql);
while($row = sql_fetch_array($result)) {
    $server_list[] = $row;
}

$g5['title'] = '서버 프로세스 '.($w==''?'등록':'수정');
include_once (G5_ADMIN_PATH.'/admin.head.php');
=======
$g5['title'] = '서버 프로세스 '.($w==''?'등록':'수정');
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 서버 목록 조회
$server_list = [];
$sql = " SELECT server_id, server_name FROM kb_servers WHERE status != 'failed' ORDER BY priority ASC, server_name ASC ";
$result_servers = sql_query($sql);
while($row = sql_fetch_array($result_servers)) {
    $server_list[] = $row;
}
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
?>

<form name="fprocess" id="fprocess" action="./server_process_form_update.php" onsubmit="return fprocess_submit(this);" method="post" enctype="multipart/form-data">
<input type="hidden" name="w" value="<?php echo $w ?>">
<input type="hidden" name="process_id" value="<?php echo $process_id ?>">
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
<<<<<<< HEAD
        <th scope="row"><label for="server_id">서버<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="server_id" id="server_id" required class="frm_input required">
                <option value="">서버를 선택하세요</option>
                <?php foreach($server_list as $server): ?>
                <option value="<?php echo $server['server_id']?>" <?php echo ($pr['server_id']==$server['server_id'])?'selected':'';?>><?php echo get_text($server['server_name'])?></option>
                <?php endforeach; ?>
            </select>
            <span class="frm_info">프로세스가 실행될 서버를 선택하세요.</span>
=======
        <th scope="row"><label for="server_id">서버 선택<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="server_id" id="server_id" required class="frm_input">
                <option value="">서버를 선택하세요</option>
                <?php foreach($server_list as $server): ?>
                <option value="<?php echo $server['server_id']?>" <?php echo ($pr['server_id']==$server['server_id'])?'selected':'';?>><?php echo $server['server_name']?></option>
                <?php endforeach; ?>
            </select>
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="process_name">프로세스명<strong class="sound_only">필수</strong></label></th>
<<<<<<< HEAD
        <td>
            <input type="text" name="process_name" value="<?php echo get_text($pr['process_name']) ?>" id="process_name" required class="frm_input required" size="40" maxlength="100">
            <span class="frm_info">프로세스의 고유한 이름을 입력하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="process_type">프로세스 타입<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="process_type" id="process_type" required class="frm_input required">
                <option value="">타입을 선택하세요</option>
                <option value="bot" <?php echo ($pr['process_type']=='bot')?'selected':'';?>>Bot Process</option>
                <option value="api" <?php echo ($pr['process_type']=='api')?'selected':'';?>>API Server</option>
                <option value="worker" <?php echo ($pr['process_type']=='worker')?'selected':'';?>>Worker Process</option>
                <option value="scheduler" <?php echo ($pr['process_type']=='scheduler')?'selected':'';?>>Scheduler</option>
            </select>
            <span class="frm_info">프로세스의 타입을 선택하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="port">포트</label></th>
        <td>
            <input type="number" name="port" value="<?php echo $pr['port'] ?>" id="port" class="frm_input" min="1024" max="65535">
            <span class="frm_info">프로세스가 사용할 포트 번호 (1024-65535, 선택사항)</span>
=======
        <td><input type="text" name="process_name" value="<?php echo $pr['process_name']?>" id="process_name" required class="frm_input" size="50" maxlength="100" placeholder="예: server-test-01"></td>
    </tr>
    <tr>
        <th scope="row"><label for="process_type">프로세스 유형<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="process_type" id="process_type" required class="frm_input">
                <option value="">유형을 선택하세요</option>
                <option value="main" <?php echo ($pr['process_type']=='main')?'selected':'';?>>메인 서버</option>
                <option value="worker" <?php echo ($pr['process_type']=='worker')?'selected':'';?>>워커 프로세스</option>
                <option value="monitor" <?php echo ($pr['process_type']=='monitor')?'selected':'';?>>모니터링</option>
                <option value="backup" <?php echo ($pr['process_type']=='backup')?'selected':'';?>>백업 서버</option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="port">포트 번호</label></th>
        <td><input type="number" name="port" value="<?php echo $pr['port']?>" id="port" class="frm_input" min="1024" max="65535" placeholder="1490"></td>
    </tr>
    <tr>
        <th scope="row"><label for="mode">실행 모드</label></th>
        <td>
            <select name="mode" id="mode" class="frm_input">
                <option value="test" <?php echo ($pr['mode']=='test')?'selected':'';?>>테스트</option>
                <option value="prod" <?php echo ($pr['mode']=='prod')?'selected':'';?>>운영</option>
                <option value="dev" <?php echo ($pr['mode']=='dev')?'selected':'';?>>개발</option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="log_level">로그 레벨</label></th>
        <td>
            <select name="log_level" id="log_level" class="frm_input">
                <option value="DEBUG" <?php echo ($pr['log_level']=='DEBUG')?'selected':'';?>>DEBUG</option>
                <option value="INFO" <?php echo ($pr['log_level']=='INFO')?'selected':'';?>>INFO</option>
                <option value="WARNING" <?php echo ($pr['log_level']=='WARNING')?'selected':'';?>>WARNING</option>
                <option value="ERROR" <?php echo ($pr['log_level']=='ERROR')?'selected':'';?>>ERROR</option>
            </select>
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="auto_restart">자동 재시작</label></th>
        <td>
<<<<<<< HEAD
            <input type="radio" name="auto_restart" value="1" id="auto_restart_y" <?php echo ($pr['auto_restart']=='1')?'checked':'';?>>
            <label for="auto_restart_y">사용</label>
            <input type="radio" name="auto_restart" value="0" id="auto_restart_n" <?php echo ($pr['auto_restart']=='0')?'checked':'';?>>
            <label for="auto_restart_n">미사용</label>
            <?php if(!$pr['auto_restart']) echo '<input type="radio" name="auto_restart" value="1" checked style="display:none;">'; ?>
            <span class="frm_info">프로세스 종료 시 자동으로 재시작할지 설정합니다.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="max_memory_mb">최대 메모리 (MB)</label></th>
        <td>
            <input type="number" name="max_memory_mb" value="<?php echo $pr['max_memory_mb'] ?>" id="max_memory_mb" class="frm_input" min="128" max="8192">
            <span class="frm_info">프로세스가 사용할 수 있는 최대 메모리 (MB 단위, 선택사항)</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="config_json">설정 JSON</label></th>
        <td>
            <textarea name="config_json" id="config_json" rows="8" class="frm_input" style="width:100%;"><?php echo get_text($pr['config_json']) ?></textarea>
            <span class="frm_info">프로세스 설정을 JSON 형태로 입력하세요. (선택사항)</span>
=======
            <input type="radio" name="auto_restart" value="Y" id="auto_restart_y" <?php echo ($pr['auto_restart']=='Y')?'checked':'';?>>
            <label for="auto_restart_y">예</label>
            <input type="radio" name="auto_restart" value="N" id="auto_restart_n" <?php echo ($pr['auto_restart']=='N' || !$pr['auto_restart'])?'checked':'';?>>
            <label for="auto_restart_n">아니오</label>
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="status">상태</label></th>
        <td>
            <select name="status" id="status" class="frm_input">
<<<<<<< HEAD
                <option value="stopped" <?php echo ($pr['status']=='stopped')?'selected':'';?>>중지됨</option>
                <option value="running" <?php echo ($pr['status']=='running')?'selected':'';?>>실행중</option>
                <option value="error" <?php echo ($pr['status']=='error')?'selected':'';?>>오류</option>
                <option value="maintenance" <?php echo ($pr['status']=='maintenance')?'selected':'';?>>점검중</option>
            </select>
            <span class="frm_info">프로세스의 현재 상태를 설정하세요.</span>
=======
                <option value="inactive" <?php echo ($pr['status']=='inactive' || !$pr['status'])?'selected':'';?>>비활성</option>
                <option value="active" <?php echo ($pr['status']=='active')?'selected':'';?>>활성</option>
                <option value="failed" <?php echo ($pr['status']=='failed')?'selected':'';?>>실패</option>
                <option value="maintenance" <?php echo ($pr['status']=='maintenance')?'selected':'';?>>점검중</option>
            </select>
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="description">설명</label></th>
<<<<<<< HEAD
        <td>
            <textarea name="description" id="description" rows="4" class="frm_input" style="width:100%;"><?php echo get_text($pr['description']) ?></textarea>
            <span class="frm_info">프로세스에 대한 설명을 입력하세요.</span>
        </td>
=======
        <td><textarea name="description" id="description" class="frm_input" rows="4" cols="50"><?php echo $pr['description']?></textarea></td>
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
<<<<<<< HEAD
    <input type="submit" value="확인" class="btn btn_01" accesskey="s">
    <a href="./server_process_list.php" class="btn btn_02">목록</a>
</div>
=======
    <a href="./server_process_list.php" class="btn btn_02">목록</a>
    <input type="submit" name="btn_submit" value="확인" id="btn_submit" accesskey="s" class="btn_submit btn">
</div>

>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
</form>

<script>
function fprocess_submit(f)
{
    if (!f.server_id.value) {
<<<<<<< HEAD
        alert("서버를 선택하세요.");
=======
        alert("서버를 선택해주세요.");
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
        f.server_id.focus();
        return false;
    }
    
    if (!f.process_name.value) {
<<<<<<< HEAD
        alert("프로세스명을 입력하세요.");
=======
        alert("프로세스명을 입력해주세요.");
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
        f.process_name.focus();
        return false;
    }
    
    if (!f.process_type.value) {
<<<<<<< HEAD
        alert("프로세스 타입을 선택하세요.");
=======
        alert("프로세스 유형을 선택해주세요.");
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
        f.process_type.focus();
        return false;
    }
    
<<<<<<< HEAD
    // JSON 검증
    if (f.config_json.value) {
        try {
            JSON.parse(f.config_json.value);
        } catch (e) {
            alert("설정 JSON 형식이 올바르지 않습니다.");
            f.config_json.focus();
            return false;
        }
=======
    if (f.port.value && (f.port.value < 1024 || f.port.value > 65535)) {
        alert("포트 번호는 1024~65535 범위여야 합니다.");
        f.port.focus();
        return false;
>>>>>>> 1b78be4e05d9071d6b3e8e2dce02c4be897303cc
    }
    
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>