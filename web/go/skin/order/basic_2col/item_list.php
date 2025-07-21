<?php
/**
 * 기본 스킨 (2열) - 상품 목록 템플릿
 * 
 * 사용 가능한 변수:
 * - $items: 상품 목록 배열
 * - $categories: 카테고리 목록 배열
 * - $branch: 지점 정보
 */

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
?>

<div class="product-list px-4 py-2">
    <div class="grid grid-cols-2 gap-3">
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
        <div class="border rounded-lg p-3 flex flex-col" data-category="<?php echo $item['ca_id'] ?>" data-delivery-type="<?php echo htmlspecialchars($item['dmk_delivery_type']) ?>">
            <?php if ($item_img) { ?>
            <div class="w-full aspect-square mb-3">
                <img src="<?php echo $item_img ?>" 
                     alt="<?php echo htmlspecialchars($item['it_name']) ?>" 
                     class="w-full h-full object-cover rounded-lg"
                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjFGNUY5Ii8+CjxwYXRoIGQ9Ik01MCA2NUw2MCA0NUg0MEw1MCA2NVoiIGZpbGw9IiM2NDc0OEIiLz4KPC9zdmc+'" />
            </div>
            <?php } else { ?>
            <div class="w-full aspect-square mb-3 bg-gray-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-image text-gray-400 text-3xl"></i>
            </div>
            <?php } ?>
            
            <div class="flex-1 flex flex-col">
                <h3 class="font-medium text-sm mb-2 line-clamp-2"><?php echo htmlspecialchars($item['it_name']) ?></h3>
                <p class="text-base font-bold text-green-600 mb-1"><?php echo number_format($item['it_price']) ?>원</p>
                <div class="flex items-center gap-2 mb-3">
                    <p class="text-xs text-gray-500"><?php echo $item['it_stock_qty'] ?>개 남았습니다.</p>
                    <?php 
                    $warning_qty = isset($branch['br_stock_warning_qty']) ? $branch['br_stock_warning_qty'] : 10;
                    if($item['it_stock_qty'] <= $warning_qty && $item['it_stock_qty'] > 0): 
                    ?>
                    <span style="background-color: #fed7aa; color: #c2410c; padding: 2px 6px; border-radius: 9999px; font-size: 0.625rem; display: inline-flex; align-items: center; gap: 2px;">
                        <svg style="width: 10px; height: 10px;" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        품절임박
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex flex-col space-y-2">
                <button type="button" 
                        class="w-full border border-gray-300 rounded-lg py-1.5 text-xs font-medium hover:bg-gray-50"
                        onclick="showProductDetail('<?php echo $item['it_id'] ?>')"
                        data-product-id="<?php echo $item['it_id'] ?>"
                        data-product-name="<?php echo htmlspecialchars($item['it_name']) ?>"
                        data-product-price="<?php echo number_format($item['it_price']) ?>"
                        data-product-img="<?php echo $item_img ?>"
                        data-product-basic="<?php echo htmlspecialchars($item['it_basic']) ?>"
                        data-product-explan="<?php echo htmlspecialchars($item['it_explan']) ?>">상세보기</button>
                <div class="border border-gray-300 rounded-lg flex items-center justify-between overflow-hidden">
                    <button type="button" 
                            class="quantity-btn-2col w-10 h-8 flex-shrink-0 text-sm" 
                            onclick="updateQuantity('<?php echo $item['it_id'] ?>', -1)"
                            disabled>-</button>
                    <div class="flex items-center justify-center font-bold text-sm flex-1">
                        <span id="qty_<?php echo $item['it_id'] ?>">0</span>개
                    </div>
                    <button type="button" 
                            class="quantity-btn-2col w-10 h-8 flex-shrink-0 text-sm" 
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