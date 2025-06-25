<?php
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

header('Content-Type: application/json');

// 디버깅을 위한 로그 함수
function debug_log($message) {
    error_log("[DMK AJAX DEBUG] " . $message);
}

try {
    $owner_type = isset($_GET['owner_type']) ? clean_xss_tags($_GET['owner_type']) : '';
    $parent_id = isset($_GET['parent_id']) ? clean_xss_tags($_GET['parent_id']) : '';

    debug_log("Request - owner_type: $owner_type, parent_id: $parent_id");

    $owner_ids = [];

    switch ($owner_type) {
        case DMK_OWNER_TYPE_DISTRIBUTOR:
            // 총판 선택 시, 모든 총판 목록 반환
            debug_log("Getting distributors list");
            $distributors = dmk_get_distributors();
            debug_log("Found " . count($distributors) . " distributors");
            foreach ($distributors as $distributor) {
                $owner_ids[] = ['id' => $distributor['mb_id'], 'name' => $distributor['mb_name']];
            }
            break;
        case DMK_OWNER_TYPE_AGENCY:
            // 대리점 선택 시, 특정 총판에 속한 대리점 목록 반환
            debug_log("Getting agencies for parent_id: $parent_id");
            $agencies = dmk_get_agencies($parent_id);
            debug_log("Found " . count($agencies) . " agencies");
            foreach ($agencies as $agency) {
                $owner_ids[] = ['id' => $agency['ag_id'], 'name' => $agency['ag_name']];
            }
            break;
        case DMK_OWNER_TYPE_BRANCH:
            // 지점 선택 시, 특정 대리점에 속한 지점 목록 반환
            debug_log("Getting branches for parent_id: $parent_id");
            $branches = dmk_get_branches($parent_id);
            debug_log("Found " . count($branches) . " branches");
            foreach ($branches as $branch) {
                $owner_ids[] = ['id' => $branch['br_id'], 'name' => $branch['br_name']];
            }
            break;
        default:
            debug_log("Unknown owner_type: $owner_type");
            break;
    }

    debug_log("Returning " . count($owner_ids) . " items");
    echo json_encode($owner_ids);

} catch (Exception $e) {
    debug_log("Exception: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?> 