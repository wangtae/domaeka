<?php
/**
 * 서버 프로세스 등록/수정
 */

include_once('./_common.php');

auth_check('180200', 'w');

$w = $_GET['w'];
$process_id = $_GET['process_id'];

if ($w == 'u' && $process_id) {
    $sql = " SELECT * FROM kb_server_processes WHERE process_id = '$process_id' ";
    $pr = sql_fetch($sql);
    if (!$pr['process_id']) {
        alert('등록된 프로세스가 아닙니다.');
    }
} else {
    $pr = array();
}

$g5['title'] = '서버 프로세스 '.($w==''?'등록':'수정');
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 서버 목록 조회
$server_list = [];
$sql = " SELECT server_id, server_name FROM kb_servers WHERE status != 'failed' ORDER BY priority ASC, server_name ASC ";
$result_servers = sql_query($sql);
while($row = sql_fetch_array($result_servers)) {
    $server_list[] = $row;
}
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
        <th scope="row"><label for="server_id">서버 선택<strong class="sound_only">필수</strong></label></th>
        <td>
            <select name="server_id" id="server_id" required class="frm_input">
                <option value="">서버를 선택하세요</option>
                <?php foreach($server_list as $server): ?>
                <option value="<?php echo $server['server_id']?>" <?php echo ($pr['server_id']==$server['server_id'])?'selected':'';?>><?php echo $server['server_name']?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="process_name">프로세스명<strong class="sound_only">필수</strong></label></th>
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
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="auto_restart">자동 재시작</label></th>
        <td>
            <input type="radio" name="auto_restart" value="Y" id="auto_restart_y" <?php echo ($pr['auto_restart']=='Y')?'checked':'';?>>
            <label for="auto_restart_y">예</label>
            <input type="radio" name="auto_restart" value="N" id="auto_restart_n" <?php echo ($pr['auto_restart']=='N' || !$pr['auto_restart'])?'checked':'';?>>
            <label for="auto_restart_n">아니오</label>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="status">상태</label></th>
        <td>
            <select name="status" id="status" class="frm_input">
                <option value="inactive" <?php echo ($pr['status']=='inactive' || !$pr['status'])?'selected':'';?>>비활성</option>
                <option value="active" <?php echo ($pr['status']=='active')?'selected':'';?>>활성</option>
                <option value="failed" <?php echo ($pr['status']=='failed')?'selected':'';?>>실패</option>
                <option value="maintenance" <?php echo ($pr['status']=='maintenance')?'selected':'';?>>점검중</option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="description">설명</label></th>
        <td><textarea name="description" id="description" class="frm_input" rows="4" cols="50"><?php echo $pr['description']?></textarea></td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="./server_process_list.php" class="btn btn_02">목록</a>
    <input type="submit" name="btn_submit" value="확인" id="btn_submit" accesskey="s" class="btn_submit btn">
</div>

</form>

<script>
function fprocess_submit(f)
{
    if (!f.server_id.value) {
        alert("서버를 선택해주세요.");
        f.server_id.focus();
        return false;
    }
    
    if (!f.process_name.value) {
        alert("프로세스명을 입력해주세요.");
        f.process_name.focus();
        return false;
    }
    
    if (!f.process_type.value) {
        alert("프로세스 유형을 선택해주세요.");
        f.process_type.focus();
        return false;
    }
    
    if (f.port.value && (f.port.value < 1024 || f.port.value > 65535)) {
        alert("포트 번호는 1024~65535 범위여야 합니다.");
        f.port.focus();
        return false;
    }
    
    return true;
}
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>