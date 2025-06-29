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
                // 총판 관리자는 총판만 선택하는 페이지에서는 총판 선택박스를 읽기 전용으로 표시
                $config['show_distributor'] = true;
                $config['distributor_readonly'] = true;
                $config['initial_distributor'] = $dmk_auth['dt_id'] ?? '';
                break;
            case DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY:
                // 총판 관리자는 대리점만 선택 가능 (총판은 고정)
                $config['show_distributor'] = true; // 총판 선택박스 노출
                $config['distributor_readonly'] = true; // 총판 선택박스를 읽기 전용으로 설정
                $config['show_agency'] = true;
                $config['initial_distributor'] = $dmk_auth['dt_id'] ?? '';
                break;
            case DMK_CHAIN_SELECT_FULL:
            default:
                // 총판 관리자는 대리점-지점 선택 가능 (총판은 고정)
                $config['show_distributor'] = true; // 총판 선택박스 노출
                $config['distributor_readonly'] = true; // 총판 선택박스를 읽기 전용으로 설정
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
                // 대리점 관리자는 자신의 대리점 선택박스를 보고, 지점 선택 가능 (총판은 고정)
                $config['show_agency'] = true; // 대리점 선택박스 노출
                $config['agency_readonly'] = true; // 대리점 선택박스를 읽기 전용으로 설정
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
 * 계층별 선택박스 HTML 렌더링 (간소화된 버전)
 * 
 * @param array $options 렌더링 옵션 (최소한의 필수 옵션만)
 * @return string HTML 코드
 */
function dmk_render_chain_select($options = []) {
    global $g5;
    
    // 자동으로 관리자 권한 가져오기
    $dmk_auth = dmk_get_admin_auth();
    
    // 기본 옵션 설정 (대부분 자동화)
    $default_options = [
        'dmk_auth' => $dmk_auth,
        'page_type' => DMK_CHAIN_SELECT_FULL, // 기본값
        'current_values' => [
            'sdt_id' => '',
            'sag_id' => '',
            'sbr_id' => '',
            'dt_id' => '',
            'ag_id' => '',
            'br_id' => ''
        ],
        'form_id' => 'fsearch', // 표준 폼 ID
        'auto_submit' => true, // 기본적으로 자동 제출
        'ajax_endpoints' => [
            'agencies' => G5_URL . '/dmk/adm/_ajax/get_agencies.php',
            'branches' => G5_URL . '/dmk/adm/_ajax/get_branches.php'
        ],
        'css_classes' => [
            'select' => 'frm_input', // 표준 CSS 클래스
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
        'field_names' => [
            'distributor' => 'sdt_id',
            'agency' => 'sag_id',
            'branch' => 'sbr_id'
        ],
        'show_labels' => false, // 기본적으로 목록 페이지용으로 라벨 숨김
        'container_class' => '', // 추가 컨테이너 클래스
        'debug' => true, // 기본적으로 디버그 모드 ON (개발 중)
        'include_hidden_fields' => true, // dmk_dt_id, dmk_ag_id, dmk_br_id hidden 필드 포함 여부
        'hidden_field_names' => [
            'dt_id' => 'dmk_dt_id',
            'ag_id' => 'dmk_ag_id', 
            'br_id' => 'dmk_br_id'
        ]
    ];
    
    // 사용자 옵션과 기본 옵션 병합 (사용자 옵션이 우선)
    $options = array_merge($default_options, $options);
    
    // 관리자 권한에 따른 자동 설정
    $config = dmk_get_chain_select_config($options['dmk_auth'], $options['page_type']);
    
    $html = '';
    
    // 컨테이너 시작
    $container_classes = 'dmk-chain-select-container';
    if (!empty($options['container_class'])) {
        $container_classes .= ' ' . $options['container_class'];
    }
    
    // 총판 선택박스
    if ($config['show_distributor']) {
        $distributors = dmk_get_distributors_for_select($options['dmk_auth']);
        $selected_dt_id = $config['initial_distributor'] ?: (isset($options['current_values']['sdt_id']) ? $options['current_values']['sdt_id'] : (isset($options['current_values']['dt_id']) ? $options['current_values']['dt_id'] : ''));
        $readonly = $config['distributor_readonly'] ? 'readonly' : '';
        $field_name = $options['field_names']['distributor'] ?? 'sdt_id';

        // 디버깅: $distributors와 $selected_dt_id 값 확인
        error_log("DMK_CHAIN_SELECT: Distributors Data: " . print_r($distributors, true));
        error_log("DMK_CHAIN_SELECT: Selected Distributor ID: " . $selected_dt_id);

        
        if ($options['show_labels']) {
            $html .= '<label for="' . $field_name . '" class="dmk-chain-select-label">' . $options['labels']['distributor'] . '</label>';
        } else {
            $html .= '<label for="' . $field_name . '" class="' . $options['css_classes']['label'] . '">' . $options['labels']['distributor'] . '</label>';
        }
        $html .= '<select name="' . $field_name . '" id="' . $field_name . '" class="dmk-chain-select ' . $options['css_classes']['select'] . '" ' . $readonly . '>';
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
        $selected_ag_id = $config['initial_agency'] ?: (isset($options['current_values']['sag_id']) ? $options['current_values']['sag_id'] : (isset($options['current_values']['ag_id']) ? $options['current_values']['ag_id'] : ''));
        $readonly = $config['agency_readonly'] ? 'readonly' : '';
        $field_name = $options['field_names']['agency'] ?? 'sag_id';
        
        if ($options['show_labels']) {
            $html .= '<label for="' . $field_name . '" class="dmk-chain-select-label">' . $options['labels']['agency'] . '</label>';
        } else {
            $html .= '<label for="' . $field_name . '" class="' . $options['css_classes']['label'] . '">' . $options['labels']['agency'] . '</label>';
        }
        $html .= '<select name="' . $field_name . '" id="' . $field_name . '" class="dmk-chain-select ' . $options['css_classes']['select'] . '" ' . $readonly . '>';
        $html .= '<option value="">' . $options['placeholders']['agency'] . '</option>';
        
        // 대리점 관리자인 경우, 자신의 대리점만 옵션으로 추가
        if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY && !empty($dmk_auth['ag_id'])) {
            $ag_name = dmk_get_member_name($dmk_auth['ag_id']); // mb_id로 mb_name (mb_nick) 가져오는 함수 활용
            $selected = ($selected_ag_id == $dmk_auth['ag_id']) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($dmk_auth['ag_id']) . '" ' . $selected . '>' . htmlspecialchars($ag_name) . ' (' . htmlspecialchars($dmk_auth['ag_id']) . ')</option>';
        } else {
             // 그 외의 경우 (예: 최고관리자, 총판관리자), AJAX로 동적 로드를 위해 빈 상태 유지 또는 초기값만 설정
             // (이미 chain-select.js에서 AJAX로 로드하므로 여기서는 별도 처리 없음)
        }
        $html .= '</select>';
        
    }
    
    // 지점 선택박스
    if ($config['show_branch']) {
        $selected_br_id = $config['initial_branch'] ?: (isset($options['current_values']['sbr_id']) ? $options['current_values']['sbr_id'] : (isset($options['current_values']['br_id']) ? $options['current_values']['br_id'] : ''));
        $readonly = $config['branch_readonly'] ? 'readonly' : '';
        $field_name = $options['field_names']['branch'] ?? 'sbr_id';
        
        if ($options['show_labels']) {
            $html .= '<label for="' . $field_name . '" class="dmk-chain-select-label">' . $options['labels']['branch'] . '</label>';
        } else {
            $html .= '<label for="' . $field_name . '" class="' . $options['css_classes']['label'] . '">' . $options['labels']['branch'] . '</label>';
        }
        $html .= '<select name="' . $field_name . '" id="' . $field_name . '" class="dmk-chain-select ' . $options['css_classes']['select'] . '" ' . $readonly . '>';
        $html .= '<option value="">' . $options['placeholders']['branch'] . '</option>';
        $html .= '</select>';
        
    }

    // Hidden fields 추가 (dmk_dt_id, dmk_ag_id, dmk_br_id)
    if ($options['include_hidden_fields']) {
        $dt_id_value = $config['initial_distributor'] ?: (isset($options['current_values']['dt_id']) ? $options['current_values']['dt_id'] : '');
        $ag_id_value = $config['initial_agency'] ?: (isset($options['current_values']['ag_id']) ? $options['current_values']['ag_id'] : '');
        $br_id_value = $config['initial_branch'] ?: (isset($options['current_values']['br_id']) ? $options['current_values']['br_id'] : '');
        
        $html .= '<input type="hidden" name="' . $options['hidden_field_names']['dt_id'] . '" id="' . $options['hidden_field_names']['dt_id'] . '" value="' . htmlspecialchars($dt_id_value) . '">';
        $html .= '<input type="hidden" name="' . $options['hidden_field_names']['ag_id'] . '" id="' . $options['hidden_field_names']['ag_id'] . '" value="' . htmlspecialchars($ag_id_value) . '">';
        $html .= '<input type="hidden" name="' . $options['hidden_field_names']['br_id'] . '" id="' . $options['hidden_field_names']['br_id'] . '" value="' . htmlspecialchars($br_id_value) . '">';
    }

    
    // JavaScript 초기화 코드
    if ($config['show_distributor'] || $config['show_agency'] || $config['show_branch']) {
        $js_config = [
            'distributorSelectId' => $options['field_names']['distributor'] ?? 'sdt_id',
            'agencySelectId' => $options['field_names']['agency'] ?? 'sag_id',
            'branchSelectId' => $options['field_names']['branch'] ?? 'sbr_id',
            'agencyEndpoint' => $options['ajax_endpoints']['agencies'],
            'branchEndpoint' => $options['ajax_endpoints']['branches'],
            'initialDistributor' => $config['initial_distributor'] ?: (isset($options['current_values']) && isset($options['current_values'][$options['field_names']['distributor'] ?? 'sdt_id']) ? $options['current_values'][$options['field_names']['distributor'] ?? 'sdt_id'] : ''),
            'initialAgency' => $config['initial_agency'] ?: (isset($options['current_values']) && isset($options['current_values'][$options['field_names']['agency'] ?? 'sag_id']) ? $options['current_values'][$options['field_names']['agency'] ?? 'sag_id'] : ''),
            'initialBranch' => $config['initial_branch'] ?: (isset($options['current_values']) && isset($options['current_values'][$options['field_names']['branch'] ?? 'sbr_id']) ? $options['current_values'][$options['field_names']['branch'] ?? 'sbr_id'] : ''),
            'autoSubmit' => $options['auto_submit'],
            'formId' => $options['form_id'],
            'debug' => $options['debug'],
            // Hidden fields 설정 추가
            'includeHiddenFields' => $options['include_hidden_fields'],
            'hiddenFieldNames' => $options['hidden_field_names']
        ];
        
        // 디버깅을 위해 autoSubmit 값 출력
        $html .= '<!-- DEBUG: autoSubmit value in js_config: ' . json_encode($js_config['autoSubmit']) . ' -->';
        $html .= '<!-- DEBUG: AJAX endpoints: ' . json_encode($js_config['agencyEndpoint']) . ', ' . json_encode($js_config['branchEndpoint']) . ' -->';
        $html .= '<!-- DEBUG: G5_URL: ' . G5_URL . ' -->';
        $html .= '<!-- DEBUG: Full js_config: ' . json_encode($js_config, JSON_PRETTY_PRINT) . ' -->';

        $html .= '<script>';
        $html .= 'document.addEventListener("DOMContentLoaded", function() {';
        if ($options['debug']) {
            $html .= 'console.log("체인 선택박스 초기화 설정:", ' . json_encode($js_config) . ');';
        }
        $html .= 'if (typeof DmkChainSelect !== "undefined") {';
        $html .= 'new DmkChainSelect(' . json_encode($js_config) . ');';
        $html .= '} else {';
        if ($options['debug']) {
            $html .= 'console.error("DmkChainSelect JavaScript 라이브러리가 로드되지 않았습니다.");';
        }
        $html .= '}';
        $html .= '});';
        $html .= '</script>';
    }
    
    return $html;
}

/**
 * 체인 선택박스 관련 에셋(JS, CSS) 포함
 * 
 * @return string HTML 코드
 */
function dmk_include_chain_select_assets() {
    // 중복 로드 방지를 위한 전역 변수 체크
    global $dmk_chain_select_assets_included;
    if ($dmk_chain_select_assets_included) {
        return '';
    }
    $dmk_chain_select_assets_included = true;
    
    $cache_buster = dmk_get_cache_buster();
    
    $html = '';
    
    // CSS 파일 포함
    $html .= '<link rel="stylesheet" href="' . G5_URL . '/dmk/adm/js/chain-select.css' . $cache_buster . '">';
    
    // JavaScript 파일 포함
    $html .= '<script src="' . G5_URL . '/dmk/adm/js/chain-select.js' . $cache_buster . '"></script>';
    
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

/**
 * 개발자 IP인지 확인하는 함수
 * 
 * @return bool 개발자 IP 여부
 */
function dmk_is_developer_ip() {
    // 환경 변수로 개발자 모드 설정 확인
    if (defined('DMK_DEVELOPER_MODE') && constant('DMK_DEVELOPER_MODE')) {
        return true;
    }
    
    // 환경 변수 DMK_DEVELOPER_IPS가 설정된 경우
    if (defined('DMK_DEVELOPER_IPS')) {
        $developer_ips = explode(',', constant('DMK_DEVELOPER_IPS'));
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if (in_array($client_ip, $developer_ips)) {
            return true;
        }
    }
    
    // 기본 개발자 IP 목록
    $developer_ips = [
        '127.0.0.1',      // localhost
        '::1',            // localhost IPv6
        '192.168.1.100',  // 예시 개발자 IP
        '192.168.0.100',  // 예시 개발자 IP
        '124.62.66.233'
    ];
    
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // 개발자 IP 목록에 포함되어 있거나 localhost인 경우
    if (in_array($client_ip, $developer_ips) || 
        strpos($client_ip, '192.168.') === 0 || 
        strpos($client_ip, '10.') === 0) {
        return true;
    }
    
    return false;
}

/**
 * 개발자 IP인 경우 캐싱 방지를 위한 타임스탬프 파라미터 생성
 * 
 * @return string 캐싱 방지 파라미터
 */
function dmk_get_cache_buster() {
    if (dmk_is_developer_ip()) {
        return '?t=' . time();
    }
    return '';
}
?>