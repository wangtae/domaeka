<?php
/**
 * 기본 스킨 (3열) - 상품 목록 템플릿
 * 
 * 사용 가능한 변수:
 * - $items: 상품 목록 배열
 * - $categories: 카테고리 목록 배열
 * - $branch: 지점 정보
 */

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
?>

<div class="product-list px-3 py-2">
    <div class="grid grid-cols-3 gap-2">
        <?php
        $has_products = false;
        while ($item = sql_fetch_array($items_result)) {
            $has_products = true;
            
            // 상품 이미지 경로 설정
            $item_img = '';
            if ($item['it_img1']) {
                if (strpos($item['it_img1'], '/') !== false) {
                    $item_img = G5_DATA_URL.'/item/'.$item['it_img1'];
                } else {
                    $item_img = G5_DATA_URL.'/item/'.$item['it_id'].'/'.$item['it_img1'];
                }
            }
        ?>
        <div class="border rounded-lg p-2 flex flex-col" data-category="<?php echo $item['ca_id'] ?>" data-delivery-type="<?php echo htmlspecialchars($item['dmk_delivery_type']) ?>">
            <?php if ($item_img) { ?>
            <div class="w-full aspect-square mb-2">
                <img src="<?php echo $item_img ?>" 
                     alt="<?php echo htmlspecialchars($item['it_name']) ?>" 
                     class="w-full h-full object-cover rounded"
                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjFGNUY5Ii8+CjxwYXRoIGQ9Ik01MCA2NUw2MCA0NUg0MEw1MCA2NVoiIGZpbGw9IiM2NDc0OEIiLz4KPC9zdmc+'" />
            </div>
            <?php } else { ?>
            <div class="w-full aspect-square mb-2 bg-gray-100 rounded flex items-center justify-center">
                <i class="fas fa-image text-gray-400 text-2xl"></i>
            </div>
            <?php } ?>
            
            <div class="flex-1">
                <h3 class="font-medium text-xs mb-1 line-clamp-2 leading-4"><?php echo htmlspecialchars($item['it_name']) ?></h3>
                <p class="text-sm font-bold text-green-600"><?php echo number_format($item['it_price']) ?>원</p>
                <p class="text-xs text-gray-500">재고: <?php echo $item['it_stock_qty'] ?></p>
            </div>
            
            <div class="mt-2 space-y-1">
                <button type="button" 
                        class="w-full border border-gray-300 rounded py-1 text-xs hover:bg-gray-50"
                        onclick="showProductDetail('<?php echo $item['it_id'] ?>')"
                        data-product-id="<?php echo $item['it_id'] ?>"
                        data-product-name="<?php echo htmlspecialchars($item['it_name']) ?>"
                        data-product-price="<?php echo number_format($item['it_price']) ?>"
                        data-product-img="<?php echo $item_img ?>"
                        data-product-basic="<?php echo htmlspecialchars($item['it_basic']) ?>"
                        data-product-explan="<?php echo htmlspecialchars($item['it_explan']) ?>">상세</button>
                <div class="flex items-center border border-gray-300 rounded overflow-hidden">
                    <button type="button" 
                            class="quantity-btn-3col flex-1 py-1 text-xs" 
                            onclick="updateQuantity('<?php echo $item['it_id'] ?>', -1)"
                            disabled>-</button>
                    <div class="flex-1 text-center">
                        <span id="qty_<?php echo $item['it_id'] ?>" class="text-xs font-bold">0</span>
                    </div>
                    <button type="button" 
                            class="quantity-btn-3col flex-1 py-1 text-xs" 
                            onclick="updateQuantity('<?php echo $item['it_id'] ?>', 1)">+</button>
                </div>
                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][qty]" value="0" id="input_<?php echo $item['it_id'] ?>">
                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][price]" value="<?php echo $item['it_price'] ?>">
                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][name]" value="<?php echo htmlspecialchars($item['it_name']) ?>">
                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][stock]" value="<?php echo $item['it_stock_qty'] ?>">
            </div>
        </div>
        <?php } ?>
    </div>
    
    <?php if (!$has_products) { ?>
    <div class="border rounded-lg p-8 text-center">
        <div class="text-gray-500">
            <i class="fas fa-box-open text-4xl mb-4"></i>
            <p class="text-lg font-medium">해당 날짜에 주문 가능한 상품이 없습니다</p>
            <p class="text-sm">다른 날짜를 선택하시거나 나중에 다시 확인해주세요</p>
        </div>
    </div>
    <?php } ?>
</div>