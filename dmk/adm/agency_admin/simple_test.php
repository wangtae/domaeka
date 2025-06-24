<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>도매까 대리점 관리 (간단 버전)</h1>";

// 그누보드 로드
$paths = ['../../../../_common.php', '../../../_common.php', '../../_common.php'];
foreach ($paths as $path) {
    if (file_exists($path)) {
        include_once $path;
        echo "<p>✅ 그누보드 로드: {$path}</p>";
        break;
    }
}

if (!$member['mb_id']) {
    echo "<p>로그인 필요</p>";
    exit;
}

echo "<p>사용자: {$member['mb_id']} ({$member['mb_name']})</p>";

// 대리점 조회
$sql = "SELECT * FROM dmk_agency ORDER BY ag_datetime DESC";
$result = sql_query($sql);

echo "<h2>대리점 목록</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>대리점명</th><th>대표자</th><th>관리자</th><th>상태</th></tr>";

$count = 0;
while ($row = sql_fetch_array($result)) {
    $count++;
    echo "<tr>";
    echo "<td>{$row['ag_id']}</td>";
    echo "<td>{$row['ag_name']}</td>";
    echo "<td>{$row['ag_ceo_name']}</td>";
    echo "<td>{$row['ag_mb_id']}</td>";
    echo "<td>".($row['ag_status'] ? '활성' : '비활성')."</td>";
    echo "</tr>";
}

if ($count == 0) {
    echo "<tr><td colspan='5'>데이터 없음</td></tr>";
}

echo "</table>";
echo "<p>총 {$count}개</p>";
?> 