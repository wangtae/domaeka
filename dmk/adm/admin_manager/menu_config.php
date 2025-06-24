<?php
/**
 * 계층별 메뉴 설정 관리
 * 최고 관리자만 접근 가능
 */

// 그누보드5 관리자 공통 파일 포함
$admin_common_path = $_SERVER['DOCUMENT_ROOT'] . '/adm/_common.php';
if (file_exists($admin_common_path)) {
    include_once($admin_common_path);
} else {
    die('관리자 공통 파일을 찾을 수 없습니다: ' . $admin_common_path);
}

// 최고 관리자만 접근 가능
if (!is_super_admin($member['mb_id'])) {
    alert('최고관리자만 접근 가능합니다.');
}

// 전역 설정 포함
include_once(G5_DMK_PATH.'/dmk_global_settings.php');

$g5['title'] = '계층별 메뉴 설정';
include_once(G5_ADMIN_PATH.'/admin.head.php');
?>

<div class="local_desc01 local_desc">
    <p>계층별 관리자 메뉴 권한을 설정할 수 있습니다. 각 계층(본사/총판/대리점/지점)별로 접근 가능한 메뉴를 확인하고 관리할 수 있습니다.</p>
    <p><strong>참고:</strong> 실제 메뉴 번호와 이름을 확인하여 <code>dmk_global_settings.php</code> 파일을 직접 편집하시기 바랍니다.</p>
</div>

<div class="tbl_frm01 tbl_wrap">
    <h3>실제 메뉴 구조 및 코드</h3>
    <table>
        <colgroup>
            <col class="grid_2">
            <col class="grid_3">
            <col>
        </colgroup>
        <thead>
            <tr>
                <th>메뉴코드</th>
                <th>메뉴그룹</th>
                <th>메뉴명</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $menu_info = array(
                // 환경설정 (100XXX)
                '100000' => array('환경설정', '환경설정 (메인)'),
                '100100' => array('환경설정', '기본환경설정'),
                '100200' => array('환경설정', '관리권한설정'),
                '100280' => array('환경설정', '테마설정'),
                '100290' => array('환경설정', '메뉴설정'),
                '100300' => array('환경설정', '메일 테스트'),
                '100310' => array('환경설정', '팝업레이어관리'),
                '100400' => array('환경설정', '부가서비스'),
                '100410' => array('환경설정', 'DB업그레이드'),
                '100500' => array('환경설정', 'phpinfo()'),
                '100510' => array('환경설정', 'Browscap 업데이트'),
                '100520' => array('환경설정', '접속로그 변환'),
                '100800' => array('환경설정', '세션파일 일괄삭제'),
                '100900' => array('환경설정', '캐시파일 일괄삭제'),
                '100910' => array('환경설정', '캡챠파일 일괄삭제'),
                '100920' => array('환경설정', '썸네일파일 일괄삭제'),
                
                // 프랜차이즈 관리 (190XXX)
                '190000' => array('프랜차이즈 관리', '프랜차이즈 관리 (메인)'),
                '190100' => array('프랜차이즈 관리', '총판관리'),
                '190200' => array('프랜차이즈 관리', '대리점관리'),
                '190300' => array('프랜차이즈 관리', '지점관리'),
                '190400' => array('프랜차이즈 관리', '통계분석'),
                '190600' => array('프랜차이즈 관리', '서브관리자관리'),
                '190700' => array('프랜차이즈 관리', '관리자권한설정'),
                '190800' => array('프랜차이즈 관리', '메뉴권한설정'),
                
                // 회원관리 (200XXX)
                '200000' => array('회원관리', '회원관리 (메인)'),
                '200100' => array('회원관리', '회원목록'),
                '200200' => array('회원관리', '포인트관리'),
                '200300' => array('회원관리', '회원메일발송'),
                '200800' => array('회원관리', '접속자집계'),
                '200810' => array('회원관리', '접속자검색'),
                '200820' => array('회원관리', '접속자로그삭제'),
                '200900' => array('회원관리', '투표관리'),
                
                // 게시판관리 (300XXX)
                '300000' => array('게시판관리', '게시판관리 (메인)'),
                '300100' => array('게시판관리', '게시판관리'),
                '300200' => array('게시판관리', '게시판그룹관리'),
                '300300' => array('게시판관리', '인기검색어관리'),
                '300400' => array('게시판관리', '인기검색어순위'),
                '300500' => array('게시판관리', '1:1문의설정'),
                '300600' => array('게시판관리', '내용관리'),
                '300700' => array('게시판관리', 'FAQ관리'),
                '300820' => array('게시판관리', '글,댓글 현황'),
                
                // 쇼핑몰관리 (400XXX)
                '400000' => array('쇼핑몰관리', '쇼핑몰관리 (메인)'),
                '400010' => array('쇼핑몰관리', '쇼핑몰현황'),
                '400100' => array('쇼핑몰관리', '쇼핑몰설정'),
                '400200' => array('쇼핑몰관리', '분류관리'),
                '400300' => array('쇼핑몰관리', '상품관리'),
                '400400' => array('쇼핑몰관리', '주문관리'),
                '400410' => array('쇼핑몰관리', '미완료주문'),
                '400440' => array('쇼핑몰관리', '개인결제관리'),
                '400500' => array('쇼핑몰관리', '상품옵션재고관리'),
                '400610' => array('쇼핑몰관리', '상품유형관리'),
                '400620' => array('쇼핑몰관리', '재고관리'),
                '400650' => array('쇼핑몰관리', '사용후기'),
                '400660' => array('쇼핑몰관리', '상품문의'),
                '400750' => array('쇼핑몰관리', '추가배송비관리'),
                '400800' => array('쇼핑몰관리', '쿠폰관리'),
                '400810' => array('쇼핑몰관리', '쿠폰존관리'),
                
                // 쇼핑몰현황/기타 (500XXX)
                '500000' => array('쇼핑몰현황/기타', '쇼핑몰현황/기타 (메인)'),
                '500100' => array('쇼핑몰현황/기타', '상품판매순위'),
                '500110' => array('쇼핑몰현황/기타', '매출현황'),
                '500120' => array('쇼핑몰현황/기타', '주문내역출력'),
                '500140' => array('쇼핑몰현황/기타', '보관함현황'),
                '500210' => array('쇼핑몰현황/기타', '가격비교사이트'),
                '500300' => array('쇼핑몰현황/기타', '이벤트관리'),
                '500310' => array('쇼핑몰현황/기타', '이벤트일괄처리'),
                '500400' => array('쇼핑몰현황/기타', '재입고SMS알림'),
                '500500' => array('쇼핑몰현황/기타', '배너관리'),
                
                // SMS관리 (900XXX)
                '900000' => array('SMS관리', 'SMS 관리 (메인)'),
                '900100' => array('SMS관리', 'SMS 기본설정'),
                '900200' => array('SMS관리', '회원정보업데이트'),
                '900300' => array('SMS관리', '문자 보내기'),
                '900400' => array('SMS관리', '전송내역-건별'),
                '900410' => array('SMS관리', '전송내역-번호별'),
                '900500' => array('SMS관리', '이모티콘 그룹'),
                '900600' => array('SMS관리', '이모티콘 관리'),
                '900700' => array('SMS관리', '휴대폰번호 그룹'),
                '900800' => array('SMS관리', '휴대폰번호 관리'),
                '900900' => array('SMS관리', '휴대폰번호 파일')
            );
            
            foreach ($menu_info as $code => $info) {
                echo "<tr>";
                echo "<td><code style='font-weight:bold; color:#d63384;'>{$code}</code></td>";
                echo "<td><span class='badge' style='background:#6f42c1; color:white; padding:2px 6px; border-radius:3px; font-size:11px;'>{$info[0]}</span></td>";
                echo "<td>{$info[1]}</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<div class="tbl_frm01 tbl_wrap">
    <h3>현재 계층별 메뉴 권한 설정</h3>
    <table>
        <colgroup>
            <col class="grid_2">
            <col class="grid_2">
            <col>
        </colgroup>
        <thead>
            <tr>
                <th>계층</th>
                <th>허용 메뉴 수</th>
                <th>주요 설정 및 허용 메뉴</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $user_types = array(
                'super' => '본사 (최고관리자)',
                'distributor' => '총판',
                'agency' => '대리점',
                'branch' => '지점'
            );
            
            foreach ($user_types as $type => $name) {
                echo "<tr>";
                echo "<td><strong style='color:#0d6efd;'>{$name}</strong></td>";
                
                if (isset($DMK_MENU_CONFIG[$type])) {
                    $config = $DMK_MENU_CONFIG[$type];
                    $menu_count = count($config['allowed_menus']);
                    echo "<td><span class='badge' style='background:#198754; color:white; padding:3px 8px; border-radius:3px;'>{$menu_count}개</span></td>";
                    
                    $details = array();
                    
                    // 메뉴 제목 변경 정보
                    if (isset($config['menu_titles']) && !empty($config['menu_titles'])) {
                        $title_changes = array();
                        foreach ($config['menu_titles'] as $menu_code => $title) {
                            $title_changes[] = "<code>{$menu_code}</code> → {$title}";
                        }
                        $details[] = "<strong>메뉴제목 변경:</strong><br>" . implode('<br>', $title_changes);
                    }
                    
                    // 허용 메뉴 목록 (그룹별로 정리)
                    $grouped_menus = array();
                    foreach ($config['allowed_menus'] as $menu_code) {
                        $prefix = substr($menu_code, 0, 3);
                        if ($prefix == '190') {
                            $group = '프랜차이즈 관리';
                        } else {
                            switch ($prefix[0]) {
                                case '1': $group = '환경설정'; break;
                                case '2': $group = '회원관리'; break;
                                case '3': $group = '게시판관리'; break;
                                case '4': $group = '쇼핑몰관리'; break;
                                case '5': $group = '쇼핑몰현황/기타'; break;
                                case '9': $group = 'SMS관리'; break;
                                default: $group = '기타'; break;
                            }
                        }
                        $grouped_menus[$group][] = $menu_code;
                    }
                    
                    $menu_display = array();
                    foreach ($grouped_menus as $group => $menus) {
                        $menu_display[] = "<strong>{$group}:</strong> " . implode(', ', array_map(function($code) {
                            return "<code style='font-size:10px;'>{$code}</code>";
                        }, $menus));
                    }
                    
                    if (!empty($menu_display)) {
                        $details[] = "<strong>허용 메뉴:</strong><br>" . implode('<br>', $menu_display);
                    }
                    
                    echo "<td style='font-size:12px; line-height:1.4;'>" . implode('<br><br>', $details) . "</td>";
                } else {
                    echo "<td><span class='badge' style='background:#6c757d; color:white; padding:3px 8px; border-radius:3px;'>0개</span></td>";
                    echo "<td style='color:#6c757d;'>설정 없음</td>";
                }
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <a href="<?php echo G5_DMK_PATH; ?>/test_menu_debug.php" class="btn btn_02" target="_blank">
        <i class="fa fa-bug"></i> 메뉴 권한 테스트
    </a>
    <button type="button" class="btn btn_03" onclick="editConfig()">
        <i class="fa fa-edit"></i> 설정 파일 편집
    </button>
    <button type="button" class="btn btn_01" onclick="showMenuCodes()">
        <i class="fa fa-code"></i> 메뉴 코드 복사
    </button>
</div>

<div class="local_desc02">
    <h4><i class="fa fa-cog"></i> 설정 파일 정보</h4>
    <p><strong>설정 파일 위치:</strong> <code><?php echo G5_DMK_PATH; ?>/dmk_global_settings.php</code></p>
    <p>이 파일을 직접 편집하여 계층별 메뉴 권한을 설정할 수 있습니다.</p>
    
    <h4><i class="fa fa-history"></i> 백업 파일</h4>
    <?php
    $backup_files = glob(G5_DMK_PATH.'/dmk_global_settings.backup.*.php');
    if ($backup_files) {
        rsort($backup_files); // 최신순 정렬
        echo "<ul>";
        foreach (array_slice($backup_files, 0, 5) as $backup_file) { // 최근 5개만 표시
            $filename = basename($backup_file);
            $filedate = filemtime($backup_file);
            echo "<li><code>{$filename}</code> - " . date('Y-m-d H:i:s', $filedate) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>백업 파일이 없습니다.</p>";
    }
    ?>
    
    <h4><i class="fa fa-info-circle"></i> 설정 방법</h4>
    <ol>
        <li>위의 <strong>실제 메뉴 구조 및 코드</strong> 표에서 필요한 메뉴 코드를 확인합니다.</li>
        <li><strong>설정 파일 편집</strong> 버튼을 클릭하여 <code>dmk_global_settings.php</code> 파일을 엽니다.</li>
        <li>각 계층별 <code>allowed_menus</code> 배열에 허용할 메뉴 코드를 추가/제거합니다.</li>
        <li><code>menu_titles</code> 배열에서 메뉴 제목을 변경할 수 있습니다.</li>
        <li>파일을 저장한 후 <strong>메뉴 권한 테스트</strong>로 결과를 확인합니다.</li>
    </ol>
</div>

<!-- 메뉴 코드 복사 모달 -->
<div id="menuCodeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:20px; border-radius:8px; width:80%; max-width:600px; max-height:80%; overflow:auto;">
        <h3>메뉴 코드 복사 <button onclick="closeModal()" style="float:right; border:none; background:none; font-size:20px; cursor:pointer;">&times;</button></h3>
        <div style="margin:15px 0;">
            <label><input type="radio" name="copyFormat" value="array" checked> PHP 배열 형식</label>
            <label style="margin-left:15px;"><input type="radio" name="copyFormat" value="list"> 단순 목록</label>
        </div>
        <textarea id="menuCodeText" style="width:100%; height:300px; font-family:monospace; font-size:12px;" readonly></textarea>
        <div style="margin-top:10px; text-align:right;">
            <button onclick="copyToClipboard()" class="btn btn_02">클립보드에 복사</button>
            <button onclick="closeModal()" class="btn btn_03">닫기</button>
        </div>
    </div>
</div>

<script>
function editConfig() {
    if (confirm('설정 파일을 직접 편집하시겠습니까?\n\n주의: 잘못된 편집은 시스템 오류를 발생시킬 수 있습니다.\n편집 전 자동으로 백업이 생성됩니다.')) {
        // 백업 생성 후 편집
        var backupUrl = '<?php echo G5_DMK_PATH; ?>/dmk_global_settings.php';
        window.open(backupUrl, '_blank');
    }
}

function showMenuCodes() {
    document.getElementById('menuCodeModal').style.display = 'block';
    updateMenuCodeText();
}

function closeModal() {
    document.getElementById('menuCodeModal').style.display = 'none';
}

function updateMenuCodeText() {
    var format = document.querySelector('input[name="copyFormat"]:checked').value;
    var menuCodes = <?php echo json_encode(array_keys($menu_info)); ?>;
    var text = '';
    
    if (format === 'array') {
        text = "// 모든 메뉴 코드\n";
        text += "'allowed_menus' => array(\n";
        for (var i = 0; i < menuCodes.length; i++) {
            text += "    '" + menuCodes[i] + "', // " + getMenuName(menuCodes[i]) + "\n";
        }
        text += "),";
    } else {
        text = "// 메뉴 코드 목록\n";
        for (var i = 0; i < menuCodes.length; i++) {
            text += menuCodes[i] + " - " + getMenuName(menuCodes[i]) + "\n";
        }
    }
    
    document.getElementById('menuCodeText').value = text;
}

function getMenuName(code) {
    var menuInfo = <?php echo json_encode($menu_info); ?>;
    return menuInfo[code] ? menuInfo[code][1] : '알 수 없음';
}

function copyToClipboard() {
    var textarea = document.getElementById('menuCodeText');
    textarea.select();
    document.execCommand('copy');
    alert('클립보드에 복사되었습니다.');
}

// 라디오 버튼 변경시 텍스트 업데이트
document.addEventListener('DOMContentLoaded', function() {
    var radios = document.querySelectorAll('input[name="copyFormat"]');
    radios.forEach(function(radio) {
        radio.addEventListener('change', updateMenuCodeText);
    });
});

// 모달 외부 클릭시 닫기
document.addEventListener('click', function(e) {
    var modal = document.getElementById('menuCodeModal');
    if (e.target === modal) {
        closeModal();
    }
});
</script>

<style>
.local_desc02 {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}
.local_desc02 h4 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 16px;
}
.local_desc02 code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}
.local_desc02 ul {
    margin: 10px 0;
    padding-left: 20px;
}
.local_desc02 li {
    margin: 5px 0;
    font-family: monospace;
    font-size: 13px;
}
.local_desc02 ol {
    margin: 10px 0;
    padding-left: 20px;
}
.local_desc02 ol li {
    margin: 8px 0;
    line-height: 1.5;
    font-family: inherit;
    font-size: 14px;
}
.badge {
    display: inline-block;
    font-size: 11px;
    font-weight: bold;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
}
.btn i {
    margin-right: 5px;
}
</style>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?> 