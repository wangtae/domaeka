<?php
// 테스트용 품절 체크 스크립트
define('_GNUBOARD_', true);
include_once(dirname(dirname(__FILE__)).'/common.php');
include_once(G5_DMK_PATH . '/lib/order.status.hook.php');

// 테스트할 상품 ID와 주문 ID
$test_item_id = '1752022423'; // 해남에서 만든 반시 고구마말랭이 (재고 0개)
$test_order_id = '20250722002540'; // 최근 주문

echo "품절 체크 테스트 시작...\n";
echo "상품 ID: $test_item_id\n";
echo "주문 ID: $test_order_id\n\n";

// 품절 체크 함수 호출
dmk_check_stock_warning_simple($test_item_id, $test_order_id);

echo "\n테스트 완료. 로그를 확인하세요.\n";
?>