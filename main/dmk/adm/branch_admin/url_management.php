<?php
$sub_menu = "600900";
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// 권한 체크
dmk_authenticate_admin(DMK_MB_TYPE_DISTRIBUTOR);

$g5['title'] = '지점 URL 관리';

// 액션 처리
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action) {
    switch ($action) {
        case 'add':
            $br_id = clean_xss_tags($_POST['br_id']);
            $url_code = clean_xss_tags($_POST['url_code']);
            $active = (int)$_POST['active'];
            $priority = (int)$_POST['priority'];
            $description = clean_xss_tags($_POST['description']);
            
            // 유효성 검사
            if (!$br_id || !$url_code) {
                alert('지점 ID와 URL 코드는 필수입니다.');
            }
            
            // URL 코드 형식 검사
            if (!preg_match('/^[a-z0-9_-]+$/', $url_code)) {
                alert('URL 코드는 영문 소문자, 숫자, 하이픈(-), 언더스코어(_)만 사용 가능합니다.');
            }
            
            // 중복 검사
            $check_sql = "SELECT bu_id FROM dmk_branch_url WHERE br_url_code = '" . sql_escape_string($url_code) . "'";
            if (sql_fetch($check_sql)) {
                alert('이미 사용 중인 URL 코드입니다.');
            }
            
            // 지점 존재 확인
            $branch_sql = "SELECT br_id FROM dmk_branch WHERE br_id = '" . sql_escape_string($br_id) . "'";
            if (!sql_fetch($branch_sql)) {
                alert('존재하지 않는 지점 ID입니다.');
            }
            
            // 추가
            $insert_sql = "
            INSERT INTO dmk_branch_url (br_id, br_url_code, br_url_active, bu_priority, bu_description) 
            VALUES (
                '" . sql_escape_string($br_id) . "',
                '" . sql_escape_string($url_code) . "',
                $active,
                $priority,
                '" . sql_escape_string($description) . "'
            )";
            
            if (sql_query($insert_sql)) {
                alert('URL 매핑이 추가되었습니다.', $_SERVER['PHP_SELF']);
            } else {
                alert('추가 중 오류가 발생했습니다.');
            }
            break;
            
        case 'update':
            $bu_id = (int)$_POST['bu_id'];
            $active = (int)$_POST['active'];
            $priority = (int)$_POST['priority'];
            $description = clean_xss_tags($_POST['description']);
            
            $update_sql = "
            UPDATE dmk_branch_url SET 
                br_url_active = $active,
                bu_priority = $priority,
                bu_description = '" . sql_escape_string($description) . "'
            WHERE bu_id = $bu_id";
            
            if (sql_query($update_sql)) {
                alert('수정되었습니다.', $_SERVER['PHP_SELF']);
            } else {
                alert('수정 중 오류가 발생했습니다.');
            }
            break;
            
        case 'delete':
            $bu_id = (int)$_POST['bu_id'];
            
            $delete_sql = "DELETE FROM dmk_branch_url WHERE bu_id = $bu_id";
            
            if (sql_query($delete_sql)) {
                alert('삭제되었습니다.', $_SERVER['PHP_SELF']);
            } else {
                alert('삭제 중 오류가 발생했습니다.');
            }
            break;
    }
}

// 목록 조회
$sql = " SELECT u.*, b.br_name, a.ag_name
         FROM dmk_branch_url u
         LEFT JOIN dmk_branch b ON u.br_id = b.br_id
         LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id
         ORDER BY u.bu_priority DESC, u.br_url_code ASC ";
$result = sql_query($sql);

// 지점 목록 조회 (셀렉트박스용)
$branch_sql = " SELECT b.br_id, b.br_name, a.ag_name
                FROM dmk_branch b
                LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id
                WHERE b.br_status = 1
                ORDER BY a.ag_name, b.br_name ";
$branch_result = sql_query($branch_sql);

include_once(G5_DMK_PATH.'/adm/admin.head.php');
?>

<div class="admin-container">
    <div class="admin-header">
        <h1><?php echo $g5['title'] ?></h1>
        <p>지점별 친화적 URL을 관리합니다.</p>
    </div>

    <!-- URL 추가 폼 -->
    <div class="admin-section">
        <h2>새 URL 매핑 추가</h2>
        <form method="post" class="admin-form">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label>지점 선택 *</label>
                <select name="br_id" required>
                    <option value="">지점을 선택하세요</option>
                    <?php while ($branch = sql_fetch_array($branch_result)) { ?>
                    <option value="<?php echo $branch['br_id'] ?>">
                        <?php echo htmlspecialchars($branch['br_name']) ?> 
                        (<?php echo htmlspecialchars($branch['ag_name'] ?: '직영') ?>)
                    </option>
                    <?php } ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>URL 코드 *</label>
                <input type="text" name="url_code" required placeholder="예: haeundae, gangnam">
                <small>영문 소문자, 숫자, 하이픈(-), 언더스코어(_)만 사용 가능</small>
            </div>
            
            <div class="form-group">
                <label>우선순위</label>
                <input type="number" name="priority" value="10" min="0" max="100">
                <small>높을수록 우선 적용됩니다</small>
            </div>
            
            <div class="form-group">
                <label>활성 상태</label>
                <select name="active">
                    <option value="1">활성</option>
                    <option value="0">비활성</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>설명</label>
                <input type="text" name="description" placeholder="예: 해운대점, 부산 지역">
            </div>
            
            <button type="submit" class="btn btn-primary">추가</button>
        </form>
    </div>

    <!-- URL 목록 -->
    <div class="admin-section">
        <h2>URL 매핑 목록</h2>
        
        <?php if (sql_num_rows($result) > 0) { ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>URL 코드</th>
                        <th>지점</th>
                        <th>대리점</th>
                        <th>우선순위</th>
                        <th>상태</th>
                        <th>설명</th>
                        <th>테스트</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = sql_fetch_array($result)) { ?>
                    <tr>
                        <td>
                            <code><?php echo htmlspecialchars($row['br_url_code']) ?></code>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['br_name'] ?: $row['br_id']) ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['ag_name'] ?: '직영') ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="bu_id" value="<?php echo $row['bu_id'] ?>">
                                <input type="hidden" name="active" value="<?php echo $row['br_url_active'] ?>">
                                <input type="hidden" name="description" value="<?php echo htmlspecialchars($row['bu_description']) ?>">
                                <input type="number" name="priority" value="<?php echo $row['bu_priority'] ?>" 
                                       min="0" max="100" style="width: 60px;" onchange="this.form.submit()">
                            </form>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="bu_id" value="<?php echo $row['bu_id'] ?>">
                                <input type="hidden" name="priority" value="<?php echo $row['bu_priority'] ?>">
                                <input type="hidden" name="description" value="<?php echo htmlspecialchars($row['bu_description']) ?>">
                                <select name="active" onchange="this.form.submit()">
                                    <option value="1"<?php echo $row['br_url_active'] ? ' selected' : '' ?>>활성</option>
                                    <option value="0"<?php echo !$row['br_url_active'] ? ' selected' : '' ?>>비활성</option>
                                </select>
                            </form>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="bu_id" value="<?php echo $row['bu_id'] ?>">
                                <input type="hidden" name="active" value="<?php echo $row['br_url_active'] ?>">
                                <input type="hidden" name="priority" value="<?php echo $row['bu_priority'] ?>">
                                <input type="text" name="description" value="<?php echo htmlspecialchars($row['bu_description']) ?>" 
                                       style="width: 150px;" onblur="this.form.submit()">
                            </form>
                        </td>
                        <td>
                            <a href="<?php echo G5_URL ?>/<?php echo $row['br_url_code'] ?>" 
                               target="_blank" class="btn btn-sm btn-info">테스트</a>
                        </td>
                        <td>
                            <form method="post" style="display: inline;" 
                                  onsubmit="return confirm('정말 삭제하시겠습니까?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="bu_id" value="<?php echo $row['bu_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">삭제</button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } else { ?>
        <p class="no-data">등록된 URL 매핑이 없습니다.</p>
        <?php } ?>
    </div>

    <!-- 사용 가이드 -->
    <div class="admin-section">
        <h2>사용 가이드</h2>
        <div class="guide-content">
            <h3>URL 패턴</h3>
            <ul>
                <li><code>domaeka.com/{url_code}</code> - 친화적 URL</li>
                <li><code>domaeka.com/shop/{url_code}</code> - 쇼핑 URL</li>
                <li><code>domaeka.com/order/{url_code}</code> - 주문 URL</li>
            </ul>
            
            <h3>URL 코드 규칙</h3>
            <ul>
                <li>영문 소문자, 숫자, 하이픈(-), 언더스코어(_)만 사용</li>
                <li>3~50자 이내 권장</li>
                <li>기억하기 쉽고 의미 있는 이름 사용</li>
                <li>지역명이나 지점명 활용 권장</li>
            </ul>
            
            <h3>우선순위</h3>
            <ul>
                <li>같은 지점에 여러 URL이 있을 경우 높은 우선순위가 우선 적용</li>
                <li>0~100 범위에서 설정</li>
                <li>기본값: 10</li>
            </ul>
        </div>
    </div>
</div>

<style>
.admin-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.admin-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
}

.admin-section {
    background: #fff;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.admin-form .form-group {
    margin-bottom: 20px;
}

.admin-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.admin-form input, .admin-form select, .admin-form textarea {
    width: 100%;
    max-width: 400px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.admin-form small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.admin-table th, .admin-table td {
    padding: 12px 8px;
    border: 1px solid #ddd;
    text-align: left;
}

.admin-table th {
    background: #f8f9fa;
    font-weight: bold;
}

.admin-table code {
    background: #f1f3f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 13px;
}

.btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.btn-primary { background: #007bff; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-sm { padding: 4px 8px; font-size: 12px; }

.no-data {
    text-align: center;
    padding: 40px;
    color: #666;
}

.guide-content h3 {
    margin-top: 20px;
    margin-bottom: 10px;
    color: #333;
}

.guide-content ul {
    margin-bottom: 20px;
}

.guide-content code {
    background: #f1f3f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}
</style>

<?php include_once(G5_DMK_PATH.'/adm/admin.tail.php'); ?>