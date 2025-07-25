<?php
$sub_menu = "190900"; // 로그 관리 메뉴 코드
include_once './_common.php';

// 도매까 권한 라이브러리 포함
include_once(G5_PATH.'/dmk/adm/lib/admin.auth.lib.php');

// 메뉴 접근 권한 확인
if (!dmk_can_access_menu($sub_menu)) {
    alert('접근 권한이 없습니다.');
}

// 최고관리자가 아니면 도매까 자체 권한 체크 사용
if ($is_admin != 'super') {
    // 도매까 권한 체크를 이미 위에서 했으므로 여기서는 통과
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    alert('잘못된 접근입니다.');
}

// 로그 정보 조회
$sql = " SELECT l.*, m.mb_name, m.mb_nick
         FROM dmk_action_logs l
         LEFT JOIN {$g5['member_table']} m ON l.mb_id = m.mb_id
         WHERE l.id = $id ";
$log = sql_fetch($sql);

if (!$log) {
    alert('해당 로그를 찾을 수 없습니다.');
}

$g5['title'] = '관리자 액션 로그 상세보기';
include_once (G5_ADMIN_PATH.'/admin.head.php');

// 액션 타입별 한글명
$action_type_names = array(
    'CREATE' => '생성',
    'UPDATE' => '수정', 
    'DELETE' => '삭제',
    'LOGIN' => '로그인',
    'LOGOUT' => '로그아웃'
);

// JSON 데이터 파싱 및 포맷팅
function format_json_data($json_str) {
    if (empty($json_str)) return '';
    
    $data = json_decode($json_str, true);
    if (!$data) return $json_str;
    
    $formatted = '';
    foreach ($data as $key => $value) {
        $formatted .= "<strong>{$key}:</strong> " . htmlspecialchars($value) . "<br>\n";
    }
    return $formatted;
}

// 데이터 차이점 비교 함수
function compare_data($old_data, $new_data) {
    $old = json_decode($old_data, true);
    $new = json_decode($new_data, true);
    
    if (!$old || !$new) return '';
    
    $changes = '';
    $all_keys = array_unique(array_merge(array_keys($old), array_keys($new)));
    
    foreach ($all_keys as $key) {
        $old_val = isset($old[$key]) ? $old[$key] : '';
        $new_val = isset($new[$key]) ? $new[$key] : '';
        
        if ($old_val !== $new_val) {
            $changes .= "<tr>";
            $changes .= "<td><strong>{$key}</strong></td>";
            $changes .= "<td style='color:#999;'>" . htmlspecialchars($old_val) . "</td>";
            $changes .= "<td style='color:#0066cc;'>" . htmlspecialchars($new_val) . "</td>";
            $changes .= "</tr>";
        }
    }
    
    return $changes;
}
?>

<div class="local_desc01 local_desc">
    <p>
        <strong>관리자 액션 로그 상세정보</strong><br>
        • 관리자의 액션에 대한 상세 정보와 변경 내역을 확인할 수 있습니다.
    </p>
</div>

<div class="tbl_frm01 tbl_wrap">
    <table>
    <caption>액션 로그 기본 정보</caption>
    <colgroup>
        <col style="width:150px">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row">로그 ID</th>
        <td><?php echo $log['id'] ?></td>
    </tr>
    <tr>
        <th scope="row">관리자 ID</th>
        <td>
            <a href="<?php echo G5_ADMIN_URL ?>/member_form.php?w=u&mb_id=<?php echo $log['mb_id'] ?>" target="_blank">
                <?php echo $log['mb_id'] ?>
            </a>
            <?php if ($log['mb_name'] || $log['mb_nick']) { ?>
            (<?php echo get_text($log['mb_name'] ?: $log['mb_nick']) ?>)
            <?php } ?>
        </td>
    </tr>
    <tr>
        <th scope="row">액션 타입</th>
        <td>
            <span class="txt_<?php echo strtolower($log['action_type']) ?>">
                <?php echo $action_type_names[$log['action_type']] ?: $log['action_type'] ?>
            </span>
        </td>
    </tr>
    <tr>
        <th scope="row">메뉴 코드</th>
        <td><?php echo $log['menu_code'] ?></td>
    </tr>
    <tr>
        <th scope="row">대상 테이블</th>
        <td><?php echo $log['target_table'] ?></td>
    </tr>
    <tr>
        <th scope="row">대상 ID</th>
        <td><?php echo $log['target_id'] ?></td>
    </tr>
    <tr>
        <th scope="row">액션 상세</th>
        <td><?php echo nl2br(get_text($log['action_detail'])) ?></td>
    </tr>
    <tr>
        <th scope="row">IP 주소</th>
        <td><?php echo $log['action_ip'] ?></td>
    </tr>
    <tr>
        <th scope="row">실행 시간</th>
        <td><?php echo $log['log_datetime'] ?></td>
    </tr>
    </tbody>
    </table>
</div>

<?php if ($log['old_data'] || $log['new_data']) { ?>
<div class="tbl_frm01 tbl_wrap" style="margin-top:20px;">
    <table>
    <caption>데이터 변경 내역</caption>
    <colgroup>
        <col style="width:150px">
        <col style="width:40%">
        <col style="width:40%">
    </colgroup>
    <thead>
    <tr>
        <th scope="col">필드명</th>
        <th scope="col">변경 전 (OLD)</th>
        <th scope="col">변경 후 (NEW)</th>
    </tr>
    </thead>
    <tbody>
    <?php 
    $changes = compare_data($log['old_data'], $log['new_data']);
    if ($changes) {
        echo $changes;
    } else {
        echo '<tr><td colspan="3" class="empty_table">변경된 데이터가 없습니다.</td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>
<?php } ?>

<?php if ($log['old_data'] && $log['action_type'] != 'UPDATE') { ?>
<div class="tbl_frm01 tbl_wrap" style="margin-top:20px;">
    <table>
    <caption>변경 전 데이터 (RAW)</caption>
    <colgroup>
        <col>
    </colgroup>
    <tbody>
    <tr>
        <td style="padding:15px; background:#f8f8f8; font-family:monospace; white-space:pre-wrap;"><?php echo htmlspecialchars($log['old_data']) ?></td>
    </tr>
    </tbody>
    </table>
</div>
<?php } ?>

<?php if ($log['new_data']) { ?>
<div class="tbl_frm01 tbl_wrap" style="margin-top:20px;">
    <table>
    <caption>변경 후 데이터 (RAW)</caption>
    <colgroup>
        <col>
    </colgroup>
    <tbody>
    <tr>
        <td style="padding:15px; background:#f0f8ff; font-family:monospace; white-space:pre-wrap;"><?php echo htmlspecialchars($log['new_data']) ?></td>
    </tr>
    </tbody>
    </table>
</div>
<?php } ?>

<div class="btn_fixed_top">
    <a href="./action_log_list.php" class="btn_01 btn">목록으로</a>
    <?php if ($log['id'] > 1) { ?>
    <a href="./action_log_view.php?id=<?php echo $log['id']-1 ?>" class="btn_02 btn">이전</a>
    <?php } ?>
    <?php 
    // 다음 로그 존재 여부 확인
    $next_sql = "SELECT id FROM dmk_action_logs WHERE id > {$log['id']} ORDER BY id ASC LIMIT 1";
    $next_result = sql_fetch($next_sql);
    if ($next_result) {
    ?>
    <a href="./action_log_view.php?id=<?php echo $next_result['id'] ?>" class="btn_02 btn">다음</a>
    <?php } ?>
</div>

<style>
/* 액션 타입별 색상 스타일 */
.txt_create { color: #28a745; font-weight: bold; }
.txt_update { color: #007bff; font-weight: bold; }
.txt_delete { color: #dc3545; font-weight: bold; }
.txt_login { color: #17a2b8; font-weight: bold; }
.txt_logout { color: #6c757d; font-weight: bold; }
</style>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>