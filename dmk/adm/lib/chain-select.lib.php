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
 * 페이지 모드 정의
 */
define('DMK_CHAIN_MODE_LIST', 'list');     // 목록 페이지 - 선택박스로 표시
define('DMK_CHAIN_MODE_FORM_NEW', 'form_new'); // 등록 폼 - 선택박스로 표시
define('DMK_CHAIN_MODE_FORM_EDIT', 'form_edit'); // 수정 폼 - readonly input으로 표시

/**
 * 관리자 권한에 따른 선택박스 표시 레벨 결정
 * 
 * @param array $dmk_auth 관리자 권한 정보
 * @param string $page_type 페이지 유형 (full, distributor_agency, distributor_only)
 * @param string $page_mode 페이지 모드 (list, form_new, form_edit)
 * @return array 표시할 선택박스 정보
 */
function dmk_get_chain_select_config($dmk_auth, $page_type = DMK_CHAIN_SELECT_FULL, $page_mode = DMK_CHAIN_MODE_LIST) {
    $config = [
        'show_distributor' => false,
        'show_agency' => false,
        'show_branch' => false,
        'distributor_readonly' => false,
        'agency_readonly' => false,
        'branch_readonly' => false,
        'initial_distributor' => '',
        'initial_agency' => '',
        'initial_branch' => '',
        'page_mode' => $page_mode,
        'render_as_input' => ($page_mode === DMK_CHAIN_MODE_FORM_EDIT), // form_edit 모드에서는 input으로 렌더링
        'show_hierarchy_info' => true // 하위 계층이 상위 계층 정보를 볼 수 있는지 여부
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
                // 총판 관리자는 대리점만 선택 가능 (총판 선택박스 숨김)
                $config['show_distributor'] = false; // 총판 선택박스 숨김
                $config['show_agency'] = true;
                $config['initial_distributor'] = $dmk_auth['dt_id'] ?? '';
                break;
            case DMK_CHAIN_SELECT_FULL:
            default:
                // 총판 관리자도 총판-대리점-지점 모두 선택 가능 (서브관리자 등록 시)
                $config['show_distributor'] = true; // 총판 선택박스 표시
                $config['show_agency'] = true;
                $config['show_branch'] = true;
                $config['initial_distributor'] = $dmk_auth['dt_id'] ?? $dmk_auth['mb_id'] ?? '';
                break;
        }
    }
    // 대리점 관리자인 경우
    else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
        switch ($page_type) {
            case DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY:
                // 대리점 관리자는 총판만 선택하는 페이지에서는 선택박스 표시 안함
                break;
            case DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY:
                // 대리점 관리자는 대리점 선택 가능 (총판 선택박스 숨김)
                $config['show_distributor'] = false; // 총판 선택박스 숨김
                $config['show_agency'] = true; // 대리점 선택박스 표시
                $config['initial_distributor'] = $dmk_auth['dt_id'] ?? '';
                break;
            case DMK_CHAIN_SELECT_FULL:
            default:
                // 대리점 관리자는 대리점-지점 선택박스 표시 (총판 선택박스 숨김)
                $config['show_distributor'] = false; // 총판 선택박스 숨김
                $config['show_agency'] = true; // 대리점 선택박스 표시
                $config['show_branch'] = true; // 지점 선택박스 표시
                $config['initial_distributor'] = $dmk_auth['dt_id'] ?? '';
                $config['initial_agency'] = $dmk_auth['ag_id'] ?? '';
                break;
        }
    }
    // 지점 관리자인 경우
    else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
        // 지점 관리자는 모든 페이지에서 선택박스 표시 안함 (모든 값이 고정)
        $config['show_distributor'] = false; // 총판 선택박스 숨김
        $config['show_agency'] = false; // 대리점 선택박스 숨김
        $config['show_branch'] = false; // 지점 선택박스 숨김
        $config['initial_distributor'] = $dmk_auth['dt_id'] ?? '';
        $config['initial_agency'] = $dmk_auth['ag_id'] ?? '';
        $config['initial_branch'] = $dmk_auth['br_id'] ?? '';
        $config['show_hierarchy_info'] = false; // 지점 관리자는 상위 계층 정보 숨김
    }
    
    // 하위 계층에서 상위 계층 정보 숨김 처리
    if (!$dmk_auth['is_super']) {
        // 총판 관리자는 자신의 총판 ID만 알 수 있음
        if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
            $config['show_hierarchy_info'] = false; // 상위 계층 정보 숨김
        }
        // 대리점 관리자는 자신의 총판/대리점 ID만 알 수 있음  
        else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
            $config['show_hierarchy_info'] = false; // 상위 계층 정보 숨김
        }
        // 지점 관리자는 자신의 총판/대리점/지점 ID만 알 수 있음
        else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
            $config['show_hierarchy_info'] = false; // 상위 계층 정보 숨김
        }
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
 * 대리점 목록 조회 (대리점 관리자가 자신의 대리점을 선택할 수 있도록)
 * 
 * @param array $dmk_auth 관리자 권한 정보
 * @return array 대리점 목록
 */
function dmk_get_agencies_for_select($dmk_auth) {
    global $g5;
    
    $agencies = [];
    
    if ($dmk_auth['is_super']) {
        // 최고관리자: 모든 대리점 조회
        $sql = "SELECT a.ag_id, m.mb_nick AS ag_name 
                FROM dmk_agency a
                JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id
                WHERE a.ag_status = 1 
                ORDER BY m.mb_nick ASC";
        $result = sql_query($sql);
        while($row = sql_fetch_array($result)) {
            $agencies[] = [
                'id' => $row['ag_id'],
                'name' => $row['ag_name']
            ];
        }
    } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR && !empty($dmk_auth['dt_id'])) {
        // 총판 관리자: 자신의 총판에 속한 대리점들
        $sql = "SELECT a.ag_id, m.mb_nick AS ag_name 
                FROM dmk_agency a
                JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id
                WHERE a.dt_id = '".sql_escape_string($dmk_auth['dt_id'])."' AND a.ag_status = 1 
                ORDER BY m.mb_nick ASC";
        $result = sql_query($sql);
        while($row = sql_fetch_array($result)) {
            $agencies[] = [
                'id' => $row['ag_id'],
                'name' => $row['ag_name']
            ];
        }
    } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY && !empty($dmk_auth['ag_id'])) {
        // 대리점 관리자: 자신의 대리점만
        $agencies[] = [
            'id' => $dmk_auth['ag_id'],
            'name' => $dmk_auth['ag_name'] ?? $dmk_auth['ag_id']
        ];
    }
    
    return $agencies;
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
        'page_mode' => DMK_CHAIN_MODE_LIST, // 기본값
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
        'debug' => true // 기본적으로 디버그 모드 ON (개발 중)
    ];
    
    // 사용자 옵션과 기본 옵션 병합 (사용자 옵션이 우선)
    $options = array_merge($default_options, $options);
    
    // 관리자 권한에 따른 자동 설정
    $config = dmk_get_chain_select_config($options['dmk_auth'], $options['page_type'], $options['page_mode']);
    
    $html = '';
    
    // CSS/JS 에셋 포함
    $html .= dmk_include_chain_select_assets();
    
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

        
        // 라벨 추가
        if ($options['show_labels']) {
            $html .= '<label for="' . $field_name . '" class="dmk-chain-select-label">' . $options['labels']['distributor'] . '</label>';
        } else {
            $html .= '<label for="' . $field_name . '" class="' . $options['css_classes']['label'] . '">' . $options['labels']['distributor'] . '</label>';
        }
        
        // form_edit 모드에서는 readonly input으로 렌더링
        if ($config['render_as_input']) {
            $display_value = '';
            if ($selected_dt_id) {
                foreach ($distributors as $distributor) {
                    if ($distributor['dt_id'] == $selected_dt_id) {
                        $display_value = $config['show_hierarchy_info'] ? 
                            $distributor['dt_name'] . ' (' . $distributor['dt_id'] . ')' : 
                            $distributor['dt_name'];
                        break;
                    }
                }
            }
            $html .= '<input type="text" name="' . $field_name . '_display" id="' . $field_name . '_display" class="' . $options['css_classes']['select'] . '" value="' . htmlspecialchars($display_value) . '" readonly style="margin-right:3px;display:none;">';
            $html .= '<input type="hidden" name="' . $field_name . '" id="' . $field_name . '" value="' . htmlspecialchars($selected_dt_id) . '">';
        } else {
            // 기존 select 박스 렌더링
            $readonly_attr = $config['distributor_readonly'] ? 'readonly' : '';
            $html .= '<select name="' . $field_name . '" id="' . $field_name . '" class="dmk-chain-select ' . $options['css_classes']['select'] . '" ' . $readonly_attr . '>';
            $html .= '<option value="">' . $options['placeholders']['distributor'] . '</option>';
            
            foreach ($distributors as $distributor) {
                $selected = ($selected_dt_id == $distributor['dt_id']) ? 'selected' : '';
                $option_text = $config['show_hierarchy_info'] ? 
                    $distributor['dt_name'] . ' (' . $distributor['dt_id'] . ')' : 
                    $distributor['dt_name'];
                $html .= '<option value="' . htmlspecialchars($distributor['dt_id']) . '" ' . $selected . '>';
                $html .= htmlspecialchars($option_text);
                $html .= '</option>';
            }
            
            $html .= '</select>';
        }
    }
    
    // 대리점 선택박스
    if ($config['show_agency']) {
        $selected_ag_id = $config['initial_agency'] ?: (isset($options['current_values']['sag_id']) ? $options['current_values']['sag_id'] : (isset($options['current_values']['ag_id']) ? $options['current_values']['ag_id'] : ''));
        $readonly = $config['agency_readonly'] ? 'readonly' : '';
        $field_name = $options['field_names']['agency'] ?? 'sag_id';
        
        // 라벨 추가
        if ($options['show_labels']) {
            $html .= '<label for="' . $field_name . '" class="dmk-chain-select-label">' . $options['labels']['agency'] . '</label>';
        } else {
            $html .= '<label for="' . $field_name . '" class="' . $options['css_classes']['label'] . '">' . $options['labels']['agency'] . '</label>';
        }
        
        // form_edit 모드에서는 readonly input으로 렌더링
        if ($config['render_as_input']) {
            $display_value = '';
            if ($selected_ag_id) {
                // 대리점 관리자인 경우, 자신의 대리점 정보 사용
                if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY && !empty($dmk_auth['ag_id']) && $selected_ag_id == $dmk_auth['ag_id']) {
                    $ag_name = dmk_get_member_name($dmk_auth['ag_id']);
                    $display_value = $config['show_hierarchy_info'] ? 
                        $ag_name . ' (' . $dmk_auth['ag_id'] . ')' : 
                        $ag_name;
                } else {
                    // 다른 경우는 AJAX를 통해 데이터를 가져와야 할 수도 있지만, 수정 모드에서는 현재 값만 표시
                    $display_value = $config['show_hierarchy_info'] ? 
                        '대리점 (' . $selected_ag_id . ')' : 
                        '대리점';
                }
            }
            $html .= '<input type="text" name="' . $field_name . '_display" id="' . $field_name . '_display" class="' . $options['css_classes']['select'] . '" value="' . htmlspecialchars($display_value) . '" readonly  style="margin-right:3px;display:none;">';
            $html .= '<input type="hidden" name="' . $field_name . '" id="' . $field_name . '" value="' . htmlspecialchars($selected_ag_id) . '">';
        } else {
            // 기존 select 박스 렌더링
            $readonly_attr = $config['agency_readonly'] ? 'readonly' : '';
            $html .= '<select name="' . $field_name . '" id="' . $field_name . '" class="dmk-chain-select ' . $options['css_classes']['select'] . '" ' . $readonly_attr . '>';
            $html .= '<option value="">' . $options['placeholders']['agency'] . '</option>';
            
            // 대리점 목록 추가
            $agencies = dmk_get_agencies_for_select($options['dmk_auth']);
            foreach ($agencies as $agency) {
                $selected = ($selected_ag_id == $agency['id']) ? 'selected' : '';
                $option_text = $config['show_hierarchy_info'] ? 
                    $agency['name'] . ' (' . $agency['id'] . ')' : 
                    $agency['name'];
                $html .= '<option value="' . htmlspecialchars($agency['id']) . '" ' . $selected . '>';
                $html .= htmlspecialchars($option_text);
                $html .= '</option>';
            }
            $html .= '</select>';
        }
        
    }
    
    // 지점 선택박스
    if ($config['show_branch']) {
        $selected_br_id = $config['initial_branch'] ?: (isset($options['current_values']['sbr_id']) ? $options['current_values']['sbr_id'] : (isset($options['current_values']['br_id']) ? $options['current_values']['br_id'] : ''));
        $readonly = $config['branch_readonly'] ? 'readonly' : '';
        $field_name = $options['field_names']['branch'] ?? 'sbr_id';
        
        // 라벨 추가
        if ($options['show_labels']) {
            $html .= '<label for="' . $field_name . '" class="dmk-chain-select-label">' . $options['labels']['branch'] . '</label>';
        } else {
            $html .= '<label for="' . $field_name . '" class="' . $options['css_classes']['label'] . '">' . $options['labels']['branch'] . '</label>';
        }
        
        // form_edit 모드에서는 readonly input으로 렌더링
        if ($config['render_as_input']) {
            $display_value = '';
            if ($selected_br_id) {
                // 지점 관리자인 경우, 자신의 지점 정보 사용
                if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH && !empty($dmk_auth['br_id']) && $selected_br_id == $dmk_auth['br_id']) {
                    $br_name = dmk_get_member_name($dmk_auth['br_id']);
                    $display_value = $config['show_hierarchy_info'] ? 
                        $br_name . ' (' . $dmk_auth['br_id'] . ')' : 
                        $br_name;
                } else {
                    // 다른 경우는 현재 값만 표시
                    $display_value = $config['show_hierarchy_info'] ? 
                        '지점 (' . $selected_br_id . ')' : 
                        '지점';
                }
            }
            $html .= '<input type="text" name="' . $field_name . '_display" id="' . $field_name . '_display" class="' . $options['css_classes']['select'] . '" value="' . htmlspecialchars($display_value) . '" readonly  style="margin-right:3px;display:none;">';
            $html .= '<input type="hidden" name="' . $field_name . '" id="' . $field_name . '" value="' . htmlspecialchars($selected_br_id) . '">';
        } else {
            // 기존 select 박스 렌더링
            $readonly_attr = $config['branch_readonly'] ? 'readonly' : '';
            $html .= '<select name="' . $field_name . '" id="' . $field_name . '" class="dmk-chain-select ' . $options['css_classes']['select'] . '" ' . $readonly_attr . '>';
            $html .= '<option value="">' . $options['placeholders']['branch'] . '</option>';
            $html .= '</select>';
        }
        
    }


    
    // JavaScript 초기화 코드 (form_edit 모드가 아닐 때만)
    if (!$config['render_as_input'] && ($config['show_distributor'] || $config['show_agency'] || $config['show_branch'])) {
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
            'debug' => $options['debug']
        ];
        
        // 콜백 함수 추가
        if (isset($options['callbacks'])) {
            if (isset($options['callbacks']['onDistributorChange'])) {
                $js_config['onDistributorChange'] = $options['callbacks']['onDistributorChange'];
            }
            if (isset($options['callbacks']['onAgencyChange'])) {
                $js_config['onAgencyChange'] = $options['callbacks']['onAgencyChange'];
            }
            if (isset($options['callbacks']['onBranchChange'])) {
                $js_config['onBranchChange'] = $options['callbacks']['onBranchChange'];
            }
        }
        
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
    $html .= '<link rel="stylesheet" href="' . G5_URL . '/dmk/adm/css/chain-select.css' . $cache_buster . '">';
    
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

/**
 * 편의 함수들 - 간단한 사용을 위한 래퍼 함수들
 */

/**
 * 목록 페이지용 체인 선택박스 (list 모드)
 * 
 * @param array $current_values 현재 선택된 값들
 * @param string $page_type 페이지 타입
 * @param array $additional_options 추가 옵션
 * @return string HTML 코드
 */
function dmk_chain_select_for_list($current_values = [], $page_type = DMK_CHAIN_SELECT_FULL, $additional_options = []) {
    $options = array_merge([
        'page_mode' => DMK_CHAIN_MODE_LIST,
        'page_type' => $page_type,
        'current_values' => $current_values,
        'auto_submit' => true
    ], $additional_options);
    
    return dmk_render_chain_select($options);
}

/**
 * 등록 폼용 체인 선택박스 (form_new 모드)
 * 
 * @param array $current_values 현재 선택된 값들
 * @param string $page_type 페이지 타입
 * @param array $additional_options 추가 옵션
 * @return string HTML 코드
 */
function dmk_chain_select_for_form_new($current_values = [], $page_type = DMK_CHAIN_SELECT_FULL, $additional_options = []) {
    $options = array_merge([
        'page_mode' => DMK_CHAIN_MODE_FORM_NEW,
        'page_type' => $page_type,
        'current_values' => $current_values,
        'auto_submit' => false
    ], $additional_options);
    
    return dmk_render_chain_select($options);
}

/**
 * 수정 폼용 체인 선택박스 (form_edit 모드 - readonly input으로 표시)
 * 
 * @param array $current_values 현재 선택된 값들
 * @param string $page_type 페이지 타입
 * @param array $additional_options 추가 옵션
 * @return string HTML 코드
 */
function dmk_chain_select_for_form_edit($current_values = [], $page_type = DMK_CHAIN_SELECT_FULL, $additional_options = []) {
    $options = array_merge([
        'page_mode' => DMK_CHAIN_MODE_FORM_EDIT,
        'page_type' => $page_type,
        'current_values' => $current_values,
        'auto_submit' => false,
        'show_labels' => true
    ], $additional_options);
    
    return dmk_render_chain_select($options);
}
?>