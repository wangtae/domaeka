<?php
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

header('Content-Type: application/json');

// 디버깅을 위한 로그 함수
function debug_log($message) {
    error_log("[DMK AJAX DEBUG] " . $message);
}

try {
    // GET 또는 POST 방식 모두 지원
    $owner_type = isset($_REQUEST['owner_type']) ? clean_xss_tags($_REQUEST['owner_type']) : '';
    $parent_id = isset($_REQUEST['parent_id']) ? clean_xss_tags($_REQUEST['parent_id']) : '';

    debug_log("Request - owner_type: $owner_type, parent_id: $parent_id");

    $result = [];

    switch ($owner_type) {
        case 'distributor':
        case DMK_OWNER_TYPE_DISTRIBUTOR:
            // 총판 선택 시, 모든 총판 목록 반환
            debug_log("Getting distributors list");
            $distributors = dmk_get_distributors();
            debug_log("Found " . count($distributors) . " distributors");
            foreach ($distributors as $distributor) {
                $result[] = ['id' => $distributor['mb_id'], 'name' => $distributor['mb_name']];
            }
            break;
            
        case 'agency':
        case DMK_OWNER_TYPE_AGENCY:
            // 대리점 선택 시, 현재 관리자에 따라 대리점 목록 반환
            debug_log("Getting agencies for parent_id: $parent_id");
            $dmk_auth = dmk_get_admin_auth();
            
            if ($dmk_auth['is_super']) {
                // 최고관리자는 모든 대리점
                $agencies = dmk_get_agencies();
            } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
                // 총판은 자신의 대리점만
                $agencies = dmk_get_agencies($dmk_auth['mb_id']);
            } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
                // 대리점은 자신의 대리점만
                $agencies = [['ag_id' => $dmk_auth['ag_id'], 'ag_name' => $dmk_auth['ag_name']]];
            } else {
                $agencies = [];
            }
            
            debug_log("Found " . count($agencies) . " agencies");
            foreach ($agencies as $agency) {
                $result[] = ['id' => $agency['ag_id'], 'name' => $agency['ag_name']];
            }
            break;
            
        case 'branch':
        case DMK_OWNER_TYPE_BRANCH:
            // 지점 선택 시, 현재 관리자에 따라 지점 목록 반환
            debug_log("Getting branches for parent_id: $parent_id");
            $dmk_auth = dmk_get_admin_auth();
            
            if ($dmk_auth['is_super']) {
                // 최고관리자는 모든 지점
                $branches = dmk_get_branches();
            } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) {
                // 총판은 자신의 총판 하위 모든 지점
                $branches = dmk_get_branches_for_distributor($dmk_auth['mb_id']);
                // 브랜치 정보 가져오기
                $branch_data = [];
                foreach ($branches as $br_id) {
                    $sql = "SELECT br_id, br_name FROM dmk_branch WHERE br_id = '" . sql_escape_string($br_id) . "'";
                    $row = sql_fetch($sql);
                    if ($row) {
                        $branch_data[] = $row;
                    }
                }
                $branches = $branch_data;
            } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY) {
                // 대리점은 자신의 지점만
                $branches = dmk_get_branches($dmk_auth['ag_id']);
            } else if ($dmk_auth['mb_type'] == DMK_MB_TYPE_BRANCH) {
                // 지점은 자신의 지점만
                $branches = [['br_id' => $dmk_auth['br_id'], 'br_name' => $dmk_auth['br_name']]];
            } else {
                $branches = [];
            }
            
            debug_log("Found " . count($branches) . " branches");
            foreach ($branches as $branch) {
                $result[] = ['id' => $branch['br_id'], 'name' => $branch['br_name']];
            }
            break;
            
        default:
            debug_log("Unknown owner_type: $owner_type");
            break;
    }

    debug_log("Returning " . count($result) . " items");
    echo json_encode(['success' => true, 'data' => $result]);

} catch (Exception $e) {
    debug_log("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 