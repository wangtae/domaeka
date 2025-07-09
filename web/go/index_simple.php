<?php
include_once(__DIR__ . '/../common.php');

// get_yoil 함수 정의
if (!function_exists('get_yoil')) {
    function get_yoil($date) {
        $yoil = array('일', '월', '화', '수', '목', '금', '토');
        return $yoil[date('w', strtotime($date))];
    }
}

// URL에서 코드 추출
$request_uri = $_SERVER['REQUEST_URI'];
$path_info = parse_url($request_uri, PHP_URL_PATH);

// /main/go/ 이후의 경로 추출
$pattern = '/\/main\/go\/([^\/\?]+)/';
if (preg_match($pattern, $path_info, $matches)) {
    $br_url_code = $matches[1];
} else {
    goto_url(G5_URL);
}

// br_url_code 검증 및 정리
$br_url_code = preg_replace('/[^a-zA-Z0-9_-]/', '', $br_url_code);

if (!$br_url_code) {
    alert('유효하지 않은 URL 코드입니다.', G5_URL);
}

// dmk_branch_url 테이블에서 br_url_code를 사용하여 실제 br_id 조회
$br_url_code_safe = sql_real_escape_string($br_url_code);
$url_mapping_sql = " SELECT br_id FROM dmk_branch_url WHERE br_url_code = '$br_url_code_safe' AND br_url_active = 1 ";
$url_mapping = sql_fetch($url_mapping_sql);

if (!$url_mapping) {
    alert('유효하지 않은 URL 코드입니다.', G5_URL);
}

$br_id = $url_mapping['br_id'];

// 지점 정보 조회
$branch_sql = " SELECT b.*, a.ag_name, d.dt_name
                FROM dmk_branch b 
                LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id 
                LEFT JOIN dmk_distributor d ON a.dt_id = d.dt_id
                WHERE b.br_id = '$br_id' AND b.br_status = 1 ";
$branch = sql_fetch($branch_sql);

if (!$branch) {
    alert('유효하지 않은 지점입니다.', G5_URL);
}

$g5['title'] = $branch['br_name'] . ' 주문페이지';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $g5['title'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto bg-white min-h-screen">
        <!-- Header -->
        <header class="bg-green-600 text-white p-4">
            <h1 class="text-xl font-bold"><?php echo htmlspecialchars($branch['br_name']) ?></h1>
            <p class="text-sm opacity-90"><?php echo htmlspecialchars($branch['ag_name'] ?: '직영') ?></p>
        </header>

        <!-- Content -->
        <div class="p-6">
            <h2 class="text-2xl font-bold mb-4">주문 페이지</h2>
            
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            ✅ <strong>URL 단축 기능이 성공적으로 작동합니다!</strong>
                        </p>
                        <p class="text-sm text-blue-600 mt-1">
                            현재 URL: <code class="bg-white px-2 py-1 rounded"><?php echo htmlspecialchars($_SERVER['REQUEST_URI']) ?></code>
                        </p>
                        <p class="text-sm text-blue-600">
                            지점 코드: <code class="bg-white px-2 py-1 rounded"><?php echo htmlspecialchars($br_url_code) ?></code> → 
                            지점 ID: <code class="bg-white px-2 py-1 rounded"><?php echo htmlspecialchars($br_id) ?></code>
                        </p>
                    </div>
                </div>
            </div>

            <!-- 지점 정보 -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h3 class="font-semibold mb-2">📍 매장 정보</h3>
                <div class="text-sm text-gray-600 space-y-1">
                    <p><strong>매장:</strong> <?php echo htmlspecialchars($branch['br_name']) ?></p>
                    <p><strong>소속:</strong> <?php echo htmlspecialchars($branch['ag_name'] ?: '직영') ?></p>
                    <?php if ($branch['br_phone']) { ?>
                    <p><strong>전화:</strong> <?php echo htmlspecialchars($branch['br_phone']) ?></p>
                    <?php } ?>
                    <?php if ($branch['br_address']) { ?>
                    <p><strong>주소:</strong> <?php echo htmlspecialchars($branch['br_address']) ?></p>
                    <?php } ?>
                </div>
            </div>

            <!-- 주문 기능 -->
            <div class="space-y-4">
                <button class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-green-700 transition-colors">
                    주문하기
                </button>
                
                <a href="<?php echo G5_DMK_URL ?>/adm/branch_admin/orderlist.php?br_id=<?php echo $br_id ?>" 
                   class="block w-full bg-gray-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-gray-700 transition-colors text-center">
                    주문내역 보기
                </a>
            </div>

            <!-- 테스트 정보 -->
            <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-semibold text-yellow-800 mb-2">🎉 구현 완료!</h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>✅ URL 형식: <code>/main/go/{code}</code></li>
                    <li>✅ 데이터베이스 조회: <code>dmk_branch_url</code> 테이블</li>
                    <li>✅ 지점 정보 표시: <?php echo htmlspecialchars($branch['br_name']) ?></li>
                    <li>✅ PHP만으로 구현 (Nginx 설정 불필요)</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
