<?php
$sub_menu = "200100";
require_once './_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

dmk_auth_check_menu($auth, $sub_menu, 'r');

$sql_common = " from {$g5['member_table']} m 
                LEFT JOIN dmk_branch b ON m.dmk_br_id = b.br_id 
                LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id 
                LEFT JOIN {$g5['member_table']} mb_br ON b.br_id = mb_br.mb_id 
                LEFT JOIN {$g5['member_table']} mb_ag ON a.ag_id = mb_ag.mb_id 
                ";

$sql_search = " where (1) ";

// 관리자 계정 필터링 (mb_level 4 이상은 관리자)
// 총판 관리자가 하위 모든 회원을 조회할 수 있도록 dmk_mb_type 및 mb_level 필터링은 제거함.
// 필요한 경우 별도의 필터링 옵션으로 제공할 수 있음.
// $sql_search .= " AND m.dmk_mb_type = 0 AND m.mb_level < 4 "; // dmk_mb_type이 0인 일반 회원만 표시

// 도매까 관리자 권한 정보 조회
$dmk_auth = dmk_get_admin_auth();

// 계층별 회원 필터링 <i class="fa fa-sitemap dmk-updated-icon" title="개조"></i>
if (!$dmk_auth['is_super']) {
    if ($dmk_auth['mb_type'] == 3) {
        // 지점 관리자: 자신의 지점 회원만 조회
        $sql_search .= " AND m.dmk_br_id = '".sql_escape_string($dmk_auth['br_id'])."' ";
    } elseif ($dmk_auth['mb_type'] == 2) {
        // 대리점 관리자: 소속 지점들의 회원 조회
        $sql_search .= " AND m.dmk_br_id IN (SELECT br_id FROM dmk_branch WHERE ag_id = '".sql_escape_string($dmk_auth['ag_id'])."') ";
    } elseif ($dmk_auth['mb_type'] == 1) {
        // 총판 관리자: 소속 대리점들의 지점 회원 조회
        $sql_search .= " AND m.dmk_br_id IN (
            SELECT b.br_id FROM dmk_branch b 
            JOIN dmk_agency a ON b.ag_id = a.ag_id 
            JOIN dmk_distributor d ON a.dt_id = d.dt_id 
            WHERE d.dt_id = '".sql_escape_string($dmk_auth['mb_id'])."'
        ) ";
    }
}

// 대리점, 지점 필터링 (GET 파라미터) <i class="fa fa-filter dmk-new-icon" title="NEW"></i>
$filter_dt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$filter_ag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$filter_br_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

// 기존 파라미터 호환성 지원
if (!$filter_ag_id && isset($_GET['ag_id'])) {
    $filter_ag_id = clean_xss_tags($_GET['ag_id']);
}
if (!$filter_br_id && isset($_GET['br_id'])) {
    $filter_br_id = clean_xss_tags($_GET['br_id']);
}

if ($filter_dt_id) {
    $sql_search .= " AND EXISTS (
        SELECT 1 FROM dmk_branch b2 
        JOIN dmk_agency a2 ON b2.ag_id = a2.ag_id 
        JOIN dmk_distributor d2 ON a2.dt_id = d2.dt_id 
        WHERE b2.br_id = m.dmk_br_id AND d2.dt_id = '".sql_escape_string($filter_dt_id)."'
    ) ";
}
if ($filter_ag_id) {
    $sql_search .= " AND a.ag_id = '".sql_escape_string($filter_ag_id)."' ";
}
if ($filter_br_id) {
    $sql_search .= " AND m.dmk_br_id = '".sql_escape_string($filter_br_id)."' ";
}

if ($stx) {
    $sql_search .= " and ( ";
    switch ($sfl) {
        case 'mb_point':
            $sql_search .= " ({$sfl} >= '{$stx}') ";
            break;
        case 'mb_level':
            $sql_search .= " ({$sfl} = '{$stx}') ";
            break;
        case 'mb_tel':
        case 'mb_hp':
            $sql_search .= " ({$sfl} like '%{$stx}') ";
            break;
        default:
            $sql_search .= " ({$sfl} like '{$stx}%') ";
            break;
    }
    $sql_search .= " ) ";
}

//if ($is_admin != 'super') {
    $sql_search .= " and m.mb_level = '2' ";
//}

if (!$sst) {
    $sst = "m.mb_datetime";
    $sod = "desc";
}

$sql_order = " order by {$sst} {$sod} ";

$sql = " select count(*) as cnt {$sql_common} {$sql_search} {$sql_order} ";


$row = sql_fetch($sql);
$total_count = isset($row['cnt']) ? (int)$row['cnt'] : 0;

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) {
    $page = 1; // 페이지가 없으면 첫 페이지 (1 페이지)
}
$from_record = ($page - 1) * $rows; // 시작 열을 구함

// 탈퇴회원수
$sql = " select count(*) as cnt {$sql_common} {$sql_search} and m.mb_leave_date <> '' {$sql_order} ";
$row = sql_fetch($sql);
$leave_count = isset($row['cnt']) ? (int)$row['cnt'] : 0;

// 차단회원수
$sql = " select count(*) as cnt {$sql_common} {$sql_search} and m.mb_intercept_date <> '' {$sql_order} ";
$row = sql_fetch($sql);
$intercept_count = isset($row['cnt']) ? (int)$row['cnt'] : 0;

$listall = '<a href="' . $_SERVER['SCRIPT_NAME'] . '" class="ov_listall">전체목록</a>';

$g5['title'] = '회원관리 <i class="fa fa-users dmk-updated-icon" title="개조"></i>';

// URL 쿼리 스트링 생성
$qstr = 'sst='.$sst.'&amp;sod='.$sod.'&amp;sfl='.$sfl.'&amp;stx='.$stx;
if ($filter_dt_id) {
    $qstr .= '&amp;sdt_id='.$filter_dt_id;
}
if ($filter_ag_id) {
    $qstr .= '&amp;sag_id='.$filter_ag_id;
}
if ($filter_br_id) {
    $qstr .= '&amp;sbr_id='.$filter_br_id;
}

require_once './admin.head.php';

$sql = " select m.*, mb_br.mb_nick as br_name, mb_ag.mb_nick as ag_name {$sql_common} {$sql_search} {$sql_order} limit {$from_record}, {$rows} ";
$result = sql_query($sql);

$colspan = 17;
?>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">총회원수 </span><span class="ov_num"> <?php echo number_format($total_count) ?>명 </span></span>
    <a href="?sst=mb_intercept_date&amp;sod=desc&amp;sfl=<?php echo $sfl ?>&amp;stx=<?php echo $stx ?>" class="btn_ov01" data-tooltip-text="차단된 순으로 정렬합니다.&#xa;전체 데이터를 출력합니다."> <span class="ov_txt">차단 </span><span class="ov_num"><?php echo number_format($intercept_count) ?>명</span></a>
    <a href="?sst=mb_leave_date&amp;sod=desc&amp;sfl=<?php echo $sfl ?>&amp;stx=<?php echo $stx ?>" class="btn_ov01" data-tooltip-text="탈퇴된 순으로 정렬합니다.&#xa;전체 데이터를 출력합니다."> <span class="ov_txt">탈퇴 </span><span class="ov_num"><?php echo number_format($leave_count) ?>명</span></a>
</div>

<form id="fsearch" name="fsearch" class="local_sch01 local_sch" method="get">

    <!-- 도매까 계층 선택박스 (NEW) -->
    <?php
    // 도매까 체인 선택박스 포함
    include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
    
    // 현재 선택된 계층 값들
    $current_dt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
    $current_ag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
    $current_br_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';
    
    // 기존 ag_id, br_id 파라미터도 지원 (호환성)
    if (!$current_ag_id && isset($_GET['ag_id'])) {
        $current_ag_id = clean_xss_tags($_GET['ag_id']);
    }
    if (!$current_br_id && isset($_GET['br_id'])) {
        $current_br_id = clean_xss_tags($_GET['br_id']);
    }
    
    echo dmk_render_chain_select([
        'page_type' => DMK_CHAIN_SELECT_FULL,
        'auto_submit' => true,
        'form_id' => 'fsearch',
        'field_names' => [
            'distributor' => 'sdt_id',
            'agency' => 'sag_id', 
            'branch' => 'sbr_id'
        ],
        'current_values' => [
            'sdt_id' => $current_dt_id,
            'sag_id' => $current_ag_id,
            'sbr_id' => $current_br_id
        ],
        'placeholders' => [
            'distributor' => '전체 총판',
            'agency' => '전체 대리점',
            'branch' => '전체 지점'
        ]
    ]);
    ?>
    <!-- //도매까 계층 선택박스 -->

    <label for="sfl" class="sound_only">검색대상</label>
    <select name="sfl" id="sfl">
        <option value="mb_id" <?php echo get_selected($sfl, "mb_id"); ?>>회원아이디</option>
        <option value="mb_nick" <?php echo get_selected($sfl, "mb_nick"); ?>>닉네임</option>
        <option value="mb_name" <?php echo get_selected($sfl, "mb_name"); ?>>이름</option>
        <option value="mb_level" <?php echo get_selected($sfl, "mb_level"); ?>>권한</option>
        <option value="mb_email" <?php echo get_selected($sfl, "mb_email"); ?>>E-MAIL</option>
        <option value="mb_tel" <?php echo get_selected($sfl, "mb_tel"); ?>>전화번호</option>
        <option value="mb_hp" <?php echo get_selected($sfl, "mb_hp"); ?>>휴대폰번호</option>
        <option value="mb_point" <?php echo get_selected($sfl, "mb_point"); ?>>포인트</option>
        <option value="mb_datetime" <?php echo get_selected($sfl, "mb_datetime"); ?>>가입일시</option>
        <option value="mb_ip" <?php echo get_selected($sfl, "mb_ip"); ?>>IP</option>
        <option value="mb_recommend" <?php echo get_selected($sfl, "mb_recommend"); ?>>추천인</option>
    </select>
    <label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
    <input type="text" name="stx" value="<?php echo $stx ?>" id="stx" required class="required frm_input">
    <input type="submit" class="btn_submit" value="검색">

</form>

<div class="local_desc01 local_desc">
    <p>
        회원자료 삭제 시 다른 회원이 기존 회원아이디를 사용하지 못하도록 회원아이디, 이름, 닉네임은 삭제하지 않고 영구 보관합니다.
    </p>
</div>


<form name="fmemberlist" id="fmemberlist" action="./member_list_update.php" onsubmit="return fmemberlist_submit(this);" method="post">
    <input type="hidden" name="sst" value="<?php echo $sst ?>">
    <input type="hidden" name="sod" value="<?php echo $sod ?>">
    <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
    <input type="hidden" name="stx" value="<?php echo $stx ?>">
    <input type="hidden" name="page" value="<?php echo $page ?>">
    <input type="hidden" name="sdt_id" value="<?php echo $filter_dt_id ?>">
    <input type="hidden" name="sag_id" value="<?php echo $filter_ag_id ?>">
    <input type="hidden" name="sbr_id" value="<?php echo $filter_br_id ?>">
    <input type="hidden" name="token" value="">

    <div class="tbl_head01 tbl_wrap">
        <table>
            <caption><?php echo $g5['title']; ?> 목록</caption>
            <thead>
                <tr>
                    <th scope="col" id="mb_list_chk" rowspan="2">
                        <label for="chkall" class="sound_only">회원 전체</label>
                        <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
                    </th>
                    <th scope="col" id="mb_list_id" colspan="2"><?php echo subject_sort_link('mb_id') ?>아이디</a></th>
                    <th scope="col" rowspan="2" id="mb_list_cert"><?php echo subject_sort_link('mb_certify', '', 'desc') ?>본인확인</a></th>
                    <th scope="col" id="mb_list_mailc"><?php echo subject_sort_link('mb_email_certify', '', 'desc') ?>메일인증</a></th>
                    <th scope="col" id="mb_list_open"><?php echo subject_sort_link('mb_open', '', 'desc') ?>정보공개</a></th>
                    <th scope="col" id="mb_list_mailr"><?php echo subject_sort_link('mb_mailling', '', 'desc') ?>메일수신</a></th>
                    <th scope="col" id="mb_list_auth">상태</th>
                    <th scope="col" id="mb_list_mobile">휴대폰</th>
                    <th scope="col" id="mb_list_lastcall"><?php echo subject_sort_link('mb_today_login', '', 'desc') ?>최종접속</a></th>
                    <th scope="col" id="mb_list_grp">접근그룹</th>
                    <th scope="col" rowspan="2" id="mb_list_branch">소속 지점 <i class="fa fa-building dmk-new-icon" title="NEW"></i></th>
                    <th scope="col" rowspan="2" id="mb_list_mng">관리</th>
                </tr>
                <tr>
                    <th scope="col" id="mb_list_name"><?php echo subject_sort_link('mb_name') ?>이름</a></th>
                    <th scope="col" id="mb_list_nick"><?php echo subject_sort_link('mb_nick') ?>닉네임</a></th>
                    <th scope="col" id="mb_list_sms"><?php echo subject_sort_link('mb_sms', '', 'desc') ?>SMS수신</a></th>
                    <th scope="col" id="mb_list_adultc"><?php echo subject_sort_link('mb_adult', '', 'desc') ?>성인인증</a></th>
                    <th scope="col" id="mb_list_auth"><?php echo subject_sort_link('mb_intercept_date', '', 'desc') ?>접근차단</a></th>
                    <th scope="col" id="mb_list_deny"><?php echo subject_sort_link('mb_level', '', 'desc') ?>권한</a></th>
                    <th scope="col" id="mb_list_tel">전화번호</th>
                    <th scope="col" id="mb_list_join"><?php echo subject_sort_link('mb_datetime', '', 'desc') ?>가입일</a></th>
                    <th scope="col" id="mb_list_point"><?php echo subject_sort_link('mb_point', '', 'desc') ?> 포인트</a></th>
                </tr>
            </thead>
            <tbody>
                <?php
                for ($i = 0; $row = sql_fetch_array($result); $i++) {
                    // 접근가능한 그룹수
                    $sql2 = " select count(*) as cnt from {$g5['group_member_table']} where mb_id = '{$row['mb_id']}' ";
                    $row2 = sql_fetch($sql2);
                    $group = '';
                    if ($row2['cnt']) {
                        $group = '<a href="./boardgroupmember_form.php?mb_id=' . $row['mb_id'] . '">' . $row2['cnt'] . '</a>';
                    }

                    if ($is_admin == 'group') {
                        $s_mod = '';
                    } else {
                        $s_mod = '<a href="./member_form.php?' . $qstr . '&amp;w=u&amp;mb_id=' . $row['mb_id'] . '" class="btn btn_03">수정</a>';
                    }
                    $s_grp = '<a href="./boardgroupmember_form.php?mb_id=' . $row['mb_id'] . '" class="btn btn_02">그룹</a>';

                    $leave_date = $row['mb_leave_date'] ? $row['mb_leave_date'] : date('Ymd', G5_SERVER_TIME);
                    $intercept_date = $row['mb_intercept_date'] ? $row['mb_intercept_date'] : date('Ymd', G5_SERVER_TIME);

                    $mb_nick = get_sideview($row['mb_id'], get_text($row['mb_nick']), $row['mb_email'], $row['mb_homepage']);

                    $mb_id = $row['mb_id'];
                    $leave_msg = '';
                    $intercept_msg = '';
                    $intercept_title = '';
                    if ($row['mb_leave_date']) {
                        $mb_id = $mb_id;
                        $leave_msg = '<span class="mb_leave_msg">탈퇴함</span>';
                    } elseif ($row['mb_intercept_date']) {
                        $mb_id = $mb_id;
                        $intercept_msg = '<span class="mb_intercept_msg">차단됨</span>';
                        $intercept_title = '차단해제';
                    }
                    if ($intercept_title == '') {
                        $intercept_title = '차단하기';
                    }

                    $address = $row['mb_zip1'] ? print_address($row['mb_addr1'], $row['mb_addr2'], $row['mb_addr3'], $row['mb_addr_jibeon']) : '';

                    $bg = 'bg' . ($i % 2);

                    switch ($row['mb_certify']) {
                        case 'hp':
                            $mb_certify_case = '휴대폰';
                            $mb_certify_val = 'hp';
                            break;
                        case 'ipin':
                            $mb_certify_case = '아이핀';
                            $mb_certify_val = '';
                            break;
                        case 'simple':
                            $mb_certify_case = '간편인증';
                            $mb_certify_val = '';
                            break;
                        case 'admin':
                            $mb_certify_case = '관리자';
                            $mb_certify_val = 'admin';
                            break;
                        default:
                            $mb_certify_case = '&nbsp;';
                            $mb_certify_val = 'admin';
                            break;
                    }
                ?>

                    <tr class="<?php echo $bg; ?>">
                        <td headers="mb_list_chk" class="td_chk" rowspan="2">
                            <input type="hidden" name="mb_id[<?php echo $i ?>]" value="<?php echo $row['mb_id'] ?>" id="mb_id_<?php echo $i ?>">
                            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo get_text($row['mb_name']); ?> <?php echo get_text($row['mb_nick']); ?>님</label>
                            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i ?>">
                        </td>
                        <td headers="mb_list_id" colspan="2" class="td_name sv_use">
                            <?php echo $mb_id ?>
                            <?php
                            //소셜계정이 있다면
                            if (function_exists('social_login_link_account')) {
                                if ($my_social_accounts = social_login_link_account($row['mb_id'], false, 'get_data')) {
                                    echo '<div class="member_social_provider sns-wrap-over sns-wrap-32">';
                                    foreach ((array) $my_social_accounts as $account) {     //반복문
                                        if (empty($account) || empty($account['provider'])) {
                                            continue;
                                        }

                                        $provider = strtolower($account['provider']);
                                        $provider_name = social_get_provider_service_name($provider);

                                        echo '<span class="sns-icon sns-' . $provider . '" title="' . $provider_name . '">';
                                        echo '<span class="ico"></span>';
                                        echo '<span class="txt">' . $provider_name . '</span>';
                                        echo '</span>';
                                    }
                                    echo '</div>';
                                }
                            }
                            ?>
                        </td>
                        <td headers="mb_list_cert" rowspan="2" class="td_mbcert">
                            <input type="radio" name="mb_certify[<?php echo $i; ?>]" value="simple" id="mb_certify_sa_<?php echo $i; ?>" <?php echo $row['mb_certify'] == 'simple' ? 'checked' : ''; ?>>
                            <label for="mb_certify_sa_<?php echo $i; ?>">간편인증</label><br>
                            <input type="radio" name="mb_certify[<?php echo $i; ?>]" value="hp" id="mb_certify_hp_<?php echo $i; ?>" <?php echo $row['mb_certify'] == 'hp' ? 'checked' : ''; ?>>
                            <label for="mb_certify_hp_<?php echo $i; ?>">휴대폰</label><br>
                            <input type="radio" name="mb_certify[<?php echo $i; ?>]" value="ipin" id="mb_certify_ipin_<?php echo $i; ?>" <?php echo $row['mb_certify'] == 'ipin' ? 'checked' : ''; ?>>
                            <label for="mb_certify_ipin_<?php echo $i; ?>">아이핀</label>
                        </td>
                        <td headers="mb_list_mailc"><?php echo preg_match('/[1-9]/', $row['mb_email_certify']) ? '<span class="txt_true">Yes</span>' : '<span class="txt_false">No</span>'; ?></td>
                        <td headers="mb_list_open">
                            <label for="mb_open_<?php echo $i; ?>" class="sound_only">정보공개</label>
                            <input type="checkbox" name="mb_open[<?php echo $i; ?>]" <?php echo $row['mb_open'] ? 'checked' : ''; ?> value="1" id="mb_open_<?php echo $i; ?>">
                        </td>
                        <td headers="mb_list_mailr">
                            <label for="mb_mailling_<?php echo $i; ?>" class="sound_only">메일수신</label>
                            <input type="checkbox" name="mb_mailling[<?php echo $i; ?>]" <?php echo $row['mb_mailling'] ? 'checked' : ''; ?> value="1" id="mb_mailling_<?php echo $i; ?>">
                        </td>
                        <td headers="mb_list_auth" class="td_mbstat">
                            <?php
                            if ($leave_msg || $intercept_msg) {
                                echo $leave_msg . ' ' . $intercept_msg;
                            } else {
                                echo "정상";
                            }
                            ?>
                        </td>
                        <td headers="mb_list_mobile" class="td_tel"><?php echo get_text($row['mb_hp']); ?></td>
                        <td headers="mb_list_lastcall" class="td_date"><?php echo substr($row['mb_today_login'], 2, 8); ?></td>
                        <td headers="mb_list_grp" class="td_numsmall"><?php echo $group ?></td>
                        <td headers="mb_list_branch" rowspan="2" class="td_left">
                            <?php 
                            if ($row['br_name']) {
                                echo '지점명: ' . $row['br_name'] . '<br/>(ID: ' . $row['dmk_br_id'] . ')';
                            } else {
                                echo '-';
                            }
                            if ($row['ag_name']) {
                                echo '<br/>대리점명: ' . $row['ag_name'] . '<br/>(ID: ' . $row['ag_id'] . ')';
                            }
                            ?>
                        </td>
                        <td headers="mb_list_mng" rowspan="2" class="td_mng td_mng_s"><?php echo $s_mod ?><?php echo $s_grp ?></td>
                    </tr>
                    <tr class="<?php echo $bg; ?>">
                        <td headers="mb_list_name" class="td_mbname"><?php echo get_text($row['mb_name']); ?></td>
                        <td headers="mb_list_nick" class="td_name sv_use">
                            <div><?php echo $mb_nick ?></div>
                        </td>

                        <td headers="mb_list_sms">
                            <label for="mb_sms_<?php echo $i; ?>" class="sound_only">SMS수신</label>
                            <input type="checkbox" name="mb_sms[<?php echo $i; ?>]" <?php echo $row['mb_sms'] ? 'checked' : ''; ?> value="1" id="mb_sms_<?php echo $i; ?>">
                        </td>
                        <td headers="mb_list_adultc">
                            <label for="mb_adult_<?php echo $i; ?>" class="sound_only">성인인증</label>
                            <input type="checkbox" name="mb_adult[<?php echo $i; ?>]" <?php echo $row['mb_adult'] ? 'checked' : ''; ?> value="1" id="mb_adult_<?php echo $i; ?>">
                        </td>
                        <td headers="mb_list_deny">
                            <?php if (empty($row['mb_leave_date'])) { ?>
                                <input type="checkbox" name="mb_intercept_date[<?php echo $i; ?>]" <?php echo $row['mb_intercept_date'] ? 'checked' : ''; ?> value="<?php echo $intercept_date ?>" id="mb_intercept_date_<?php echo $i ?>" title="<?php echo $intercept_title ?>">
                                <label for="mb_intercept_date_<?php echo $i; ?>" class="sound_only">접근차단</label>
                            <?php } ?>
                        </td>
                        <td headers="mb_list_auth" class="td_mbstat">
                            <?php echo get_member_level_select("mb_level[$i]", 1, $member['mb_level'], $row['mb_level']) ?>
                        </td>
                        <td headers="mb_list_tel" class="td_tel"><?php echo get_text($row['mb_tel']); ?></td>
                        <td headers="mb_list_join" class="td_date"><?php echo substr($row['mb_datetime'], 2, 8); ?></td>
                        <td headers="mb_list_point" class="td_num"><a href="point_list.php?sfl=mb_id&amp;stx=<?php echo $row['mb_id'] ?>"><?php echo number_format($row['mb_point']) ?></a></td>

                    </tr>

                <?php
                }
                if ($i == 0) {
                    echo "<tr><td colspan=\"" . $colspan . "\" class=\"empty_table\">자료가 없습니다.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="btn_fixed_top">
        <input type="submit" name="act_button" value="선택수정" onclick="document.pressed=this.value" class="btn btn_02">
        <input type="submit" name="act_button" value="선택삭제" onclick="document.pressed=this.value" class="btn btn_02">
        <?php 
        // 회원 추가 권한 체크: 기존 super admin 또는 DMK 관리자
        $can_add_member = false;
        if ($is_admin == 'super') {
            $can_add_member = true;
        } elseif ($dmk_auth && $dmk_auth['admin_type'] === 'main') {
            // DMK main 관리자들도 회원 추가 가능
            $can_add_member = true;
        }
        
        if ($can_add_member) { ?>
            <a href="./member_form.php" id="member_add" class="btn btn_01">회원추가</a>
        <?php } ?>

    </div>


</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, '?' . $qstr . '&amp;page='); ?>

<script>
    function fmemberlist_submit(f) {
        if (!is_checked("chk[]")) {
            alert(document.pressed + " 하실 항목을 하나 이상 선택하세요.");
            return false;
        }

        if (document.pressed == "선택삭제") {
            if (!confirm("선택한 자료를 정말 삭제하시겠습니까?")) {
                return false;
            }
        }

        return true;
    }

    // 대리점 선택 시 지점 옵션 업데이트
    function updateBranchOptions() {
        var agencySelect = document.getElementById('ag_id');
        var branchSelect = document.getElementById('br_id');
        
        if (!agencySelect || !branchSelect) return;
        
        var selectedAgencyId = agencySelect.value;
        var branchOptions = branchSelect.getElementsByTagName('option');
        
        // 첫 번째 옵션(전체 지점)은 항상 표시
        for (var i = 1; i < branchOptions.length; i++) {
            var option = branchOptions[i];
            var optionAgencyId = option.getAttribute('data-ag-id');
            
            if (!selectedAgencyId || optionAgencyId === selectedAgencyId) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        }
        
        // 선택된 지점이 현재 대리점에 속하지 않으면 초기화
        var currentBranchOption = branchSelect.options[branchSelect.selectedIndex];
        if (currentBranchOption && currentBranchOption.getAttribute('data-ag-id') && 
            selectedAgencyId && currentBranchOption.getAttribute('data-ag-id') !== selectedAgencyId) {
            branchSelect.value = '';
        }
    }

    // 대리점 선택 후 지점 옵션 업데이트하고 폼 제출
    function updateBranchOptionsAndSubmit() {
        updateBranchOptions();
        document.getElementById('fsearch').submit();
    }

    // 페이지 로드 시 지점 옵션 초기화
    document.addEventListener('DOMContentLoaded', function() {
        updateBranchOptions();
    });
</script>

<?php
require_once './admin.tail.php';
