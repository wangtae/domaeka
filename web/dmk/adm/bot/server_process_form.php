<?php
/**
 * 서버 프로세스 등록/수정
 */

$sub_menu = "180200";
include_once('./_common.php');

auth_check('180200', 'w');

$w = $_GET['w'];
$process_id = $_GET['process_id'];

if ($w == 'u' && $process_id) {
    $sql = " SELECT p.*, s.server_name FROM kb_server_processes p 
             LEFT JOIN kb_servers s ON p.server_id = s.server_id 
             WHERE p.process_id = '$process_id' ";
    $pr = sql_fetch($sql);
    if (!$pr['process_id']) {
        alert('등록된 프로세스가 아닙니다.');
    }
} else {
    $pr = array();
}

// 서버 목록 조회
$server_list = array();
$sql = " SELECT server_id, server_name FROM kb_servers WHERE status = 'healthy' ORDER BY server_name ";
$result = sql_query($sql);
while($row = sql_fetch_array($result)) {
    $server_list[] = $row;
}

$g5['title'] = '서버 프로세스 '.($w==''?'등록':'수정');
include_once (G5_ADMIN_PATH.'/admin.head.php');
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
        <th scope="row"><label for="server_id">서버<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="server_id" id="server_id" required class="frm_input required">
                <option value="">서버를 선택하세요</option>
                <?php foreach($server_list as $server): ?>
                <option value="<?php echo $server['server_id']?>" <?php echo ($pr['server_id']==$server['server_id'])?'selected':'';?>><?php echo get_text($server['server_name'])?></option>
                <?php endforeach; ?>
            </select>
            <span class="frm_info">프로세스가 실행될 서버를 선택하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="process_name">프로세스명<strong class="sound_only">필수</strong></label></th>
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
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="auto_restart">자동 재시작</label></th>
        <td>
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
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="status">상태</label></th>
        <td>
            <select name="status" id="status" class="frm_input">
                <option value="stopped" <?php echo ($pr['status']=='stopped')?'selected':'';?>>중지됨</option>
                <option value="running" <?php echo ($pr['status']=='running')?'selected':'';?>>실행중</option>
                <option value="error" <?php echo ($pr['status']=='error')?'selected':'';?>>오류</option>
                <option value="maintenance" <?php echo ($pr['status']=='maintenance')?'selected':'';?>>점검중</option>
            </select>
            <span class="frm_info">프로세스의 현재 상태를 설정하세요.</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="description">설명</label></th>
        <td>
            <textarea name="description" id="description" rows="4" class="frm_input" style="width:100%;"><?php echo get_text($pr['description']) ?></textarea>
            <span class="frm_info">프로세스에 대한 설명을 입력하세요.</span>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="submit" value="확인" class="btn btn_01" accesskey="s">
    <a href="./server_process_list.php" class="btn btn_02">목록</a>
</div>
</form>

<script>
function fprocess_submit(f)
{
    if (!f.server_id.value) {
        alert("서버를 선택하세요.");
        f.server_id.focus();
        return false;
    }
    
    if (!f.process_name.value) {
        alert("프로세스명을 입력하세요.");
        f.process_name.focus();
        return false;
    }
    
    if (!f.process_type.value) {
        alert("프로세스 타입을 선택하세요.");
        f.process_type.focus();
        return false;
    }
    
    // JSON 검증
    if (f.config_json.value) {
        try {
            JSON.parse(f.config_json.value);
        } catch (e) {
            alert("설정 JSON 형식이 올바르지 않습니다.");
            f.config_json.focus();
            return false;
        }
    }
    
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>