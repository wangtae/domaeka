<?php
/**
 * 도매까 관리자 권한 디버깅 페이지
 */

// 관리자 페이지 접근을 위해 _common.php를 포함하지 않고 직접 구현
require_once '../common.php';
require_once 'adm/lib/admin.auth.lib.php';

// HTML 헤더
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>도매까 권한 디버깅</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .info-box { border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; color: #856404; }
    </style>
</head>
<body>
    <h1>도매까 관리자 권한 디버깅</h1>
    
    <?php if (!$is_member): ?>
        <div class="info-box error">
            <h3>로그인 필요</h3>
            <p>이 페이지를 보려면 먼저 로그인해야 합니다.</p>
            <p><a href="<?php echo G5_BBS_URL ?>/login.php">로그인 페이지로 이동</a></p>
        </div>
    <?php else: ?>
        
        <div class="info-box">
            <h3>현재 로그인 정보</h3>
            <ul>
                <li><strong>회원 ID:</strong> <?php echo htmlspecialchars($member['mb_id']); ?></li>
                <li><strong>회원 이름:</strong> <?php echo htmlspecialchars($member['mb_name']); ?></li>
                <li><strong>회원 레벨:</strong> <?php echo $member['mb_level']; ?></li>
                <li><strong>그누보드 관리자 권한:</strong> <?php echo $is_admin ? $is_admin : '없음'; ?></li>
            </ul>
        </div>

        <?php
        // 도매까 관리자 권한 확인
        $dmk_auth = dmk_get_admin_auth();
        ?>
        
        <div class="info-box <?php echo $dmk_auth['mb_type'] > 0 ? 'success' : 'warning'; ?>">
            <h3>도매까 관리자 권한</h3>
            <ul>
                <li><strong>관리자 유형:</strong> 
                    <?php 
                    switch($dmk_auth['mb_type']) {
                        case 0: echo '일반 회원'; break;
                        case 1: echo '총판 관리자'; break;
                        case 2: echo '대리점 관리자'; break;
                        case 3: echo '지점 관리자'; break;
                        default: echo '알 수 없음';
                    }
                    ?> (<?php echo $dmk_auth['mb_type']; ?>)
                </li>
                <li><strong>최고 관리자:</strong> <?php echo $dmk_auth['is_super'] ? '예' : '아니오'; ?></li>
                <li><strong>총판 ID:</strong> <?php echo $dmk_auth['dt_id'] ?: '없음'; ?></li>
                <li><strong>대리점 ID:</strong> <?php echo $dmk_auth['ag_id'] ?: '없음'; ?></li>
                <li><strong>지점 ID:</strong> <?php echo $dmk_auth['br_id'] ?: '없음'; ?></li>
            </ul>
        </div>

        <div class="info-box">
            <h3>권한 테스트</h3>
            
            <h4>1. 기본 권한 체크 (dmk_auth_check)</h4>
            <?php
            ob_start();
            $auth_error = dmk_auth_check('', 'r', true);
            ob_end_clean();
            
            if ($auth_error) {
                echo "<div class='error'>❌ 실패: " . htmlspecialchars($auth_error) . "</div>";
            } else {
                echo "<div class='success'>✅ 성공: 읽기 권한 있음</div>";
            }
            ?>
            
            <h4>2. 메뉴 접근 권한 체크</h4>
            <?php
            $test_menus = [
                '200100' => '회원목록',
                '190100' => '도매까 총판관리',
                '190200' => '도매까 대리점관리',
                '190300' => '도매까 지점관리'
            ];
            
            foreach ($test_menus as $menu_code => $menu_name) {
                $can_access = dmk_auth_check_menu_display($menu_code, $menu_code);
                if ($can_access) {
                    echo "<div class='success'>✅ {$menu_name} ({$menu_code}): 접근 가능</div>";
                } else {
                    echo "<div class='warning'>⚠️ {$menu_name} ({$menu_code}): 접근 불가</div>";
                }
            }
            ?>
        </div>

        <div class="info-box">
            <h3>관리자 페이지 링크</h3>
            <?php if ($is_admin || $dmk_auth['mb_type'] > 0): ?>
                <p><a href="<?php echo G5_ADMIN_URL; ?>" target="_blank">관리자 페이지로 이동</a></p>
            <?php else: ?>
                <p class="error">관리자 권한이 없어 관리자 페이지에 접근할 수 없습니다.</p>
            <?php endif; ?>
        </div>

        <div class="info-box">
            <h3>데이터베이스 조회 결과</h3>
            
            <?php
            // 현재 사용자가 도매까 관리자인지 DB에서 직접 확인
            $mb_id = sql_escape_string($member['mb_id']);
            
            echo "<h4>총판 관리자 여부:</h4>";
            $sql = "SELECT dt_id, dt_name FROM dmk_distributor WHERE dt_mb_id = '{$mb_id}'";
            $result = sql_fetch($sql);
            if ($result) {
                echo "<div class='success'>✅ 총판 관리자입니다: {$result['dt_name']} ({$result['dt_id']})</div>";
            } else {
                echo "<div class='warning'>총판 관리자가 아닙니다.</div>";
            }
            
            echo "<h4>대리점 관리자 여부:</h4>";
            $sql = "SELECT ag_id, ag_name, dt_id FROM dmk_agency WHERE ag_mb_id = '{$mb_id}'";
            $result = sql_fetch($sql);
            if ($result) {
                echo "<div class='success'>✅ 대리점 관리자입니다: {$result['ag_name']} ({$result['ag_id']}, 소속총판: {$result['dt_id']})</div>";
            } else {
                echo "<div class='warning'>대리점 관리자가 아닙니다.</div>";
            }
            
            echo "<h4>지점 관리자 여부:</h4>";
            $sql = "SELECT br_id, br_name, ag_id FROM dmk_branch WHERE br_mb_id = '{$mb_id}'";
            $result = sql_fetch($sql);
            if ($result) {
                echo "<div class='success'>✅ 지점 관리자입니다: {$result['br_name']} ({$result['br_id']}, 소속대리점: {$result['ag_id']})</div>";
            } else {
                echo "<div class='warning'>지점 관리자가 아닙니다.</div>";
            }
            ?>
        </div>

    <?php endif; ?>
    
    <div class="info-box">
        <h3>작업 완료 후</h3>
        <p>디버깅이 완료되면 보안상 이 파일을 삭제하는 것을 권장합니다.</p>
    </div>
    
</body>
</html> 