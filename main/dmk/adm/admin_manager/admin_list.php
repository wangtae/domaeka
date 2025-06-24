<?php
$sub_menu = "190600";
require_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/admin_manager/_common.php');

$g5['title'] = '관리자 관리';

// 현재 관리자 권한 확인
$dmk_auth = dmk_get_admin_auth();

// 검색 조건 설정
$sql_search = " WHERE mb_level >= 4 "; // 관리자 레벨만 조회

// 권한별 관리자 필터링
if (!$dmk_auth['is_super']) {
    if ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR) {
        // 총판: 총판, 대리점, 지점 관리자 조회
        $sql_search .= " AND mb_level >= 4 AND mb_level <= 8 ";
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY) {
        // 대리점: 대리점, 지점 관리자 조회
        $sql_search .= " AND mb_level >= 4 AND mb_level <= 6 ";
    } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH) {
        // 지점: 지점 관리자만 조회
        $sql_search .= " AND mb_level = 4 ";
    }
}

// 검색 기능
$sfl = isset($_GET['sfl']) ? clean_xss_tags($_GET['sfl']) : '';
$stx = isset($_GET['stx']) ? clean_xss_tags($_GET['stx']) : '';

if ($stx) {
    $sql_search .= " AND ( ";
    switch ($sfl) {
        case 'mb_level':
            $sql_search .= " mb_level = '{$stx}' ";
            break;
        case 'dmk_mb_type':
            $sql_search .= " dmk_mb_type = '{$stx}' ";
            break;
        default:
            $sql_search .= " {$sfl} LIKE '{$stx}%' ";
            break;
    }
    $sql_search .= " ) ";
}

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rows = 20;

$sql_common = " FROM {$g5['member_table']} ";
$sql_order = " ORDER BY mb_level DESC, mb_datetime DESC ";

// 전체 개수
$sql = " SELECT COUNT(*) as cnt {$sql_common} {$sql_search} ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

$total_page = ceil($total_count / $rows);
if ($page < 1) $page = 1;
$from_record = ($page - 1) * $rows;

// 목록 조회
$sql = " SELECT * {$sql_common} {$sql_search} {$sql_order} LIMIT {$from_record}, {$rows} ";
$result = sql_query($sql);

// URL 쿼리 스트링 생성
$qstr = 'sfl='.$sfl.'&amp;stx='.$stx.'&amp;page='.$page;

require_once G5_ADMIN_PATH.'/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01">
        <span class="ov_txt">총 관리자수 </span>
        <span class="ov_num"><?php echo number_format($total_count) ?>명</span>
    </span>
</div>

<form name="fsearch" class="local_sch01 local_sch" method="get">
    <label for="sfl" class="sound_only">검색대상</label>
    <select name="sfl" id="sfl">
        <option value="mb_id"<?php echo get_selected($sfl, "mb_id"); ?>>관리자ID</option>
        <option value="mb_name"<?php echo get_selected($sfl, "mb_name"); ?>>이름</option>
        <option value="mb_nick"<?php echo get_selected($sfl, "mb_nick"); ?>>닉네임</option>
        <option value="mb_level"<?php echo get_selected($sfl, "mb_level"); ?>>권한레벨</option>
        <option value="dmk_mb_type"<?php echo get_selected($sfl, "dmk_mb_type"); ?>>관리자유형</option>
    </select>
    <label for="stx" class="sound_only">검색어</label>
    <input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input">
    <input type="submit" class="btn_submit" value="검색">
</form>

<div class="btn_add01 btn_add">
    <?php if ($dmk_auth['is_super'] || $dmk_auth['mb_level'] >= DMK_MB_LEVEL_BRANCH) { ?>
    <a href="./admin_form.php" id="admin_add" class="btn btn_01">관리자 추가</a>
    <?php } ?>
</div>

<form name="flist" id="flist" method="post">
<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">관리자ID</th>
        <th scope="col">이름</th>
        <th scope="col">닉네임</th>
        <th scope="col">권한레벨</th>
        <th scope="col">관리자유형</th>
        <th scope="col">가입일</th>
        <th scope="col">최종접속</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $bg = 'bg'.($i%2);
        
        // 관리자 유형 표시
        $admin_type_text = '';
        switch ($row['mb_level']) {
            case 10: $admin_type_text = '<span class="label label-primary">본사</span>'; break;
            case 8: $admin_type_text = '<span class="label label-info">총판</span>'; break;
            case 6: $admin_type_text = '<span class="label label-success">대리점</span>'; break;
            case 4: $admin_type_text = '<span class="label label-warning">지점</span>'; break;
            default: $admin_type_text = '<span class="label label-default">일반</span>'; break;
        }
        
        // 수정 권한 체크
        $can_modify = false;
        if ($dmk_auth['is_super']) {
            $can_modify = true;
        } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_DISTRIBUTOR && $row['mb_level'] <= 8) {
            $can_modify = true;
        } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_AGENCY && $row['mb_level'] <= 6) {
            $can_modify = true;
        } elseif ($dmk_auth['mb_level'] == DMK_MB_LEVEL_BRANCH && $row['mb_level'] == 4) {
            $can_modify = true;
        }
    ?>
    <tr class="<?php echo $bg; ?>">
        <td><?php echo $row['mb_id'] ?></td>
        <td><?php echo $row['mb_name'] ?></td>
        <td><?php echo $row['mb_nick'] ?></td>
        <td class="td_num"><?php echo $row['mb_level'] ?></td>
        <td class="td_mng"><?php echo $admin_type_text ?></td>
        <td class="td_datetime"><?php echo substr($row['mb_datetime'], 0, 10) ?></td>
        <td class="td_datetime"><?php echo $row['mb_today_login'] ? substr($row['mb_today_login'], 0, 10) : '-' ?></td>
        <td class="td_mng td_mng_s">
            <?php if ($can_modify) { ?>
            <a href="./admin_form.php?w=u&amp;mb_id=<?php echo $row['mb_id'] ?>" class="btn btn_03">수정</a>
            <?php } else { ?>
            <span class="btn btn_02">수정불가</span>
            <?php } ?>
        </td>
    </tr>
    <?php
    }
    if ($i == 0) {
        echo '<tr><td colspan="8" class="empty_table">자료가 없습니다.</td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>
</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page='); ?>

<script>
$(function(){
    $("#admin_add").click(function(){
        location.href = "./admin_form.php";
    });
});
</script>

<?php
require_once G5_ADMIN_PATH.'/admin.tail.php';
?> 