<?php
/**
 * 도매까 계층별 선택박스 공통 라이브러리
 * 총판-대리점-지점 체인 선택박스 구현
 */

if (!defined('_GNUBOARD_')) exit;

/**
 * 계층별 선택박스 렌더링 옵션 정의
 */
define('DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY', 'distributor_only');
define('DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY', 'distributor_agency');
define('DMK_CHAIN_SELECT_FULL', 'full'); // 총판-대리점-지점

/**
 * 관리자 권한에 따른 선택박스 표시 레벨 결정
 * 
 * @param array $dmk_auth 관리자 권한 정보
 * @param string $page_type 페이지 유형 (full, distributor_agency, distributor_only)
 * @return array 표시할 선택박스 정보
 */
function dmk_get_chain_select_config($dmk_auth, $page_type = DMK_CHAIN_SELECT_FULL) {
    $config = [
        'show_distributor' => false,
        'show_agency' => false,
        'show_branch' => false,
        'distributor_readonly' => false,
        'agency_readonly' => false,
        'branch_readonly' => false,
        'initial_distributor' => '',
        'initial_agency' => '',
        'initial_branch' => ''
    ];
    
    // 최고관리자인 경우
    if ($dmk_auth['is_super']) {
        switch ($page_type) {
            case DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY:
                $config['show_distributor'] = true;
                break;
            case DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY:
                $config['show_distributor'] = true;
                $config['show_agency'] = true;
                break;
            case DMK_CHAIN_SELECT_FULL:
            default:
                $config['show_distributor'] = true;
                $config['show_agency'] = true;
                $config['show_branch'] = true;
                break;
        }
    }
    // 총판 관리자인 경우
    else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
        switch ($page_type) {
            case DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY:
                // 총판 관리자는 총판만 선택하는 페이지에서는 선택박스 표시 안함
                break;
            case DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY:
                // 총판 관리자는 대리점만 선택 가능 (총판은 고정)
                $config['show_agency'] = true;
                $config['initial_distributor'] = $dmk_auth['dt_id'] ?? '';
                break;
            case DMK_CHAIN_SELECT_FULL:
            default:
                // 총판 관리자는 대리점-지점 선택 가능 (총판은 고정)
                $config['show_agency'] = true;
                $config['show_branch'] = true;
                $config['initial_distributor'] = $dmk_auth['dt_id'] ?? '';
                break;
        }
    }
    // 대리점 관리자인 경우
    else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        switch ($page_type) {
            case DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY:
            case DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY:
                // 대리점 관리자는 총판/대리점 선택하는 페이지에서는 선택박스 표시 안함
                break;
            case DMK_CHAIN_SELECT_FULL:
            default:
                // 대리점 관리자는 지점만 선택 가능 (총판/대리점은 고정)
                $config['show_branch'] = true;
                $config['initial_distributor'] = $dmk_auth['dt_id'] ?? '';
                $config['initial_agency'] = $dmk_auth['ag_id'] ?? '';
                break;
        }
    }
    // 지점 관리자인 경우
    else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
        // 지점 관리자는 모든 페이지에서 선택박스 표시 안함 (모든 값이 고정)
        $config['initial_distributor'] = $dmk_auth['dt_id'] ?? '';
        $config['initial_agency'] = $dmk_auth['ag_id'] ?? '';
        $config['initial_branch'] = $dmk_auth['br_id'] ?? '';
    }
    
    return $config;
}

/**
 * 총판 목록 조회
 * 
 * @param array $dmk_auth 관리자 권한 정보
 * @return array 총판 목록
 */
function dmk_get_distributors_for_select($dmk_auth) {
    global $g5;
    
    $distributors = [];
    
    if ($dmk_auth['is_super']) {
        // 최고관리자: 모든 총판 조회
        $sql = "SELECT d.dt_id, m.mb_nick AS dt_name 
                FROM dmk_distributor d
                JOIN {$g5['member_table']} m ON d.dt_id = m.mb_id
                WHERE d.dt_status = 1 
                ORDER BY m.mb_nick ASC";
        $result = sql_query($sql);
        while($row = sql_fetch_array($result)) {
            $distributors[] = $row;
        }
    } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR && !empty($dmk_auth['dt_id'])) {
        // 총판 관리자: 자신의 총판만
        $distributors[] = [
            'dt_id' => $dmk_auth['dt_id'],
            'dt_name' => $dmk_auth['dt_name'] ?? $dmk_auth['dt_id']
        ];
    } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY && !empty($dmk_auth['dt_id'])) {
        // 대리점 관리자: 소속 총판
        $distributors[] = [
            'dt_id' => $dmk_auth['dt_id'],
            'dt_name' => $dmk_auth['dt_name'] ?? $dmk_auth['dt_id']
        ];
    } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH && !empty($dmk_auth['dt_id'])) {
        // 지점 관리자: 소속 총판
        $distributors[] = [
            'dt_id' => $dmk_auth['dt_id'],
            'dt_name' => $dmk_auth['dt_name'] ?? $dmk_auth['dt_id']
        ];
    }
    
    return $distributors;
}

/**
 * 계층별 선택박스 HTML 렌더링
 * 
 * @param array $options 렌더링 옵션
 * @return string HTML 코드
 */
function dmk_render_chain_select($options = []) {
    global $g5;
    
    // 기본 옵션 설정
    $default_options = [
        'dmk_auth' => dmk_get_admin_auth(),
        'page_type' => DMK_CHAIN_SELECT_FULL,
        'current_values' => [
            'sdt_id' => '',
            'sag_id' => '',
            'sbr_id' => ''
        ],
        'form_id' => 'fsearch',
        'auto_submit' => true,
        'ajax_endpoints' => [
            'agencies' => './get_agencies.php',
            'branches' => './get_branches.php'
        ],
        'css_classes' => [
            'select' => 'frm_input',
            'label' => 'sound_only'
        ],
        'labels' => [
            'distributor' => '총판 선택',
            'agency' => '대리점 선택',
            'branch' => '지점 선택'
        ],
        'placeholders' => [
            'distributor' => '전체 총판',
            'agency' => '전체 대리점',
            'branch' => '전체 지점'
        ],
        'debug' => false
    ];
    
    $options = array_merge($default_options, $options);
    $config = dmk_get_chain_select_config($options['dmk_auth'], $options['page_type']);
    
    $html = '';
    
    // 총판 선택박스
    if ($config['show_distributor']) {
        $distributors = dmk_get_distributors_for_select($options['dmk_auth']);
        $selected_dt_id = $config['initial_distributor'] ?: $options['current_values']['sdt_id'];
        $readonly = $config['distributor_readonly'] ? 'readonly' : '';
        
        $html .= '<label for="sdt_id" class="' . $options['css_classes']['label'] . '">' . $options['labels']['distributor'] . '</label>';
        $html .= '<select name="sdt_id" id="sdt_id" class="' . $options['css_classes']['select'] . '" ' . $readonly . '>';
        $html .= '<option value="">' . $options['placeholders']['distributor'] . '</option>';
        
        foreach ($distributors as $distributor) {
            $selected = ($selected_dt_id == $distributor['dt_id']) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($distributor['dt_id']) . '" ' . $selected . '>';
            $html .= htmlspecialchars($distributor['dt_name']) . ' (' . htmlspecialchars($distributor['dt_id']) . ')';
            $html .= '</option>';
        }
        
        $html .= '</select>';
    }
    
    // 대리점 선택박스
    if ($config['show_agency']) {
        $selected_ag_id = $config['initial_agency'] ?: $options['current_values']['sag_id'];
        $readonly = $config['agency_readonly'] ? 'readonly' : '';
        
        $html .= '<label for="sag_id" class="' . $options['css_classes']['label'] . '">' . $options['labels']['agency'] . '</label>';
        $html .= '<select name="sag_id" id="sag_id" class="' . $options['css_classes']['select'] . '" ' . $readonly . '>';
        $html .= '<option value="">' . $options['placeholders']['agency'] . '</option>';
        $html .= '</select>';
    }
    
    // 지점 선택박스
    if ($config['show_branch']) {
        $selected_br_id = $config['initial_branch'] ?: $options['current_values']['sbr_id'];
        $readonly = $config['branch_readonly'] ? 'readonly' : '';
        
        $html .= '<label for="sbr_id" class="' . $options['css_classes']['label'] . '">' . $options['labels']['branch'] . '</label>';
        $html .= '<select name="sbr_id" id="sbr_id" class="' . $options['css_classes']['select'] . '" ' . $readonly . '>';
        $html .= '<option value="">' . $options['placeholders']['branch'] . '</option>';
        $html .= '</select>';
    }
    
    // JavaScript 초기화 코드
    if ($config['show_distributor'] || $config['show_agency'] || $config['show_branch']) {
        $js_config = [
            'agencyEndpoint' => $options['ajax_endpoints']['agencies'],
            'branchEndpoint' => $options['ajax_endpoints']['branches'],
            'initialDistributor' => $config['initial_distributor'] ?: $options['current_values']['sdt_id'],
            'initialAgency' => $config['initial_agency'] ?: $options['current_values']['sag_id'],
            'initialBranch' => $config['initial_branch'] ?: $options['current_values']['sbr_id'],
            'autoSubmit' => $options['auto_submit'],
            'formId' => $options['form_id'],
            'debug' => $options['debug']
        ];
        
        $html .= '<script>';
        $html .= 'document.addEventListener("DOMContentLoaded", function() {';
        $html .= 'if (typeof DmkChainSelect !== "undefined") {';
        $html .= 'new DmkChainSelect(' . json_encode($js_config) . ');';
        $html .= '} else {';
        $html .= 'console.error("DmkChainSelect 라이브러리가 로드되지 않았습니다.");';
        $html .= '}';
        $html .= '});';
        $html .= '</script>';
    }
    
    return $html;
}

/**
 * 계층별 선택박스 JavaScript/CSS 파일 포함
 * 
 * @param string $base_path 기본 경로
 * @return string HTML 코드
 */
function dmk_include_chain_select_assets($base_path = '') {
    if (empty($base_path)) {
        $base_path = G5_DMK_URL . '/adm';
    }
    
    $html = '';
    $html .= '<script src="' . $base_path . '/js/chain-select.js"></script>';
    $html .= '<link rel="stylesheet" href="' . $base_path . '/css/chain-select.css">';
    
    return $html;
}

/**
 * 공통 AJAX 엔드포인트 파일 생성 (get_agencies.php, get_branches.php가 없는 경우)
 * 
 * @param string $target_dir 대상 디렉토리
 */
function dmk_create_chain_select_endpoints($target_dir) {
    // get_agencies.php 생성
    $agencies_content = '<?php
include_once \'../../../adm/_common.php\';
include_once(G5_DMK_PATH.\'/adm/lib/admin.auth.lib.php\');

header(\'Content-Type: application/json\');

$dmk_auth = dmk_get_admin_auth();
$dt_id = isset($_GET[\'dt_id\']) ? clean_xss_tags($_GET[\'dt_id\']) : \'\';

$agencies = [];

if ($dmk_auth[\'is_super\'] || ($dmk_auth[\'mb_level\'] == DMK_MB_LEVEL_DISTRIBUTOR && $dmk_auth[\'dt_id\'] == $dt_id)) {
    $ag_sql_where = \'\';
    if (!empty($dt_id)) {
        $ag_sql_where = " WHERE a.dt_id = \'".sql_escape_string($dt_id)."\' AND a.ag_status = 1 ";
    } else {
        $ag_sql_where = " WHERE a.ag_status = 1 ";
    }

    $ag_sql = "SELECT a.ag_id, m.mb_nick AS ag_name 
               FROM dmk_agency a
               JOIN {$g5[\'member_table\']} m ON a.ag_id = m.mb_id
               ". $ag_sql_where ." ORDER BY m.mb_nick ASC";
    $ag_res = sql_query($ag_sql);
    while($ag_row = sql_fetch_array($ag_res)) {
        $agencies[] = array(
            \'id\' => $ag_row[\'ag_id\'],
            \'name\' => $ag_row[\'ag_name\']
        );
    }
}

echo json_encode($agencies);
?>';

    // get_branches.php 생성
    $branches_content = '<?php
include_once \'../../../adm/_common.php\';
include_once(G5_DMK_PATH.\'/adm/lib/admin.auth.lib.php\');

header(\'Content-Type: application/json\');

$dmk_auth = dmk_get_admin_auth();
$ag_id = isset($_GET[\'ag_id\']) ? clean_xss_tags($_GET[\'ag_id\']) : \'\';

$branches = [];

if ($dmk_auth[\'is_super\'] || 
    ($dmk_auth[\'mb_level\'] == DMK_MB_LEVEL_DISTRIBUTOR) ||
    ($dmk_auth[\'mb_level\'] == DMK_MB_LEVEL_AGENCY && $dmk_auth[\'ag_id\'] == $ag_id)
) {
    $br_sql_where = \'\';
    if (!empty($ag_id)) {
        $br_sql_where = " WHERE b.ag_id = \'".sql_escape_string($ag_id)."\' AND b.br_status = 1 ";
    } else {
        $br_sql_where = " WHERE b.br_status = 1 ";
    }

    $br_sql = "SELECT b.br_id, m.mb_nick AS br_name 
               FROM dmk_branch b
               JOIN {$g5[\'member_table\']} m ON b.br_id = m.mb_id
               ". $br_sql_where ." ORDER BY m.mb_nick ASC";
    $br_res = sql_query($br_sql);
    while($br_row = sql_fetch_array($br_res)) {
        $branches[] = array(
            \'id\' => $br_row[\'br_id\'],
            \'name\' => $br_row[\'br_name\']
        );
    }
}

echo json_encode($branches);
?>';

    if (!file_exists($target_dir . '/get_agencies.php')) {
        file_put_contents($target_dir . '/get_agencies.php', $agencies_content);
    }
    
    if (!file_exists($target_dir . '/get_branches.php')) {
        file_put_contents($target_dir . '/get_branches.php', $branches_content);
    }
}
?> 