<?php
/**
 * 기본 스킨 - 상품 목록 템플릿
 * 
 * 사용 가능한 변수:
 * - $items: 상품 목록 배열
 * - $categories: 카테고리 목록 배열
 * - $branch: 지점 정보
 * - $skin_config: 스킨 설정 (layout 등)
 */

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 레이아웃 설정 가져오기
$layout = isset($skin_config['layout']) ? $skin_config['layout'] : '1col';

// 디버그
echo "<!-- Skin Debug: Layout = {$layout} -->";

// 레이아웃별 CSS 클래스 설정
$grid_class = '';
$item_class = '';

switch($layout) {
    case '2col':
        $grid_class = 'grid grid-cols-1 md:grid-cols-2 gap-4';
        $item_class = 'border rounded-lg p-4';
        break;
    case '3col':
        $grid_class = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4';
        $item_class = 'border rounded-lg p-4';
        break;
    default: // 1col
        $grid_class = '';
        $item_class = 'product-card';
        break;
}
?>

<div class="product-list <?php echo ($layout != '1col') ? 'px-4 py-2' : ''; ?>">
    <?php
    $has_products = false;
    $filtered_items = array();
    
    // 카테고리별로 상품 그룹화
    while ($item = sql_fetch_array($items_result)) {
        $has_products = true;
        $ca_id = $item['ca_id'];
        if (!isset($filtered_items[$ca_id])) {
            $filtered_items[$ca_id] = array();
        }
        $filtered_items[$ca_id][] = $item;
    }
    ?>
    
    <?php if ($has_products) { ?>
        <?php if ($layout == '1col') { ?>
            <!-- 1열 레이아웃 (기존 스타일) -->
            <?php foreach ($filtered_items as $ca_id => $category_items) { ?>
                <?php foreach ($category_items as $item) { ?>
                    <?php
                    $item_img = '';
                    if ($item['it_img1']) {
                        $item_img = G5_DATA_URL . '/item/' . $item['it_img1'];
                    }
                    ?>
                    <div class="<?php echo $item_class; ?>">
                        <div class="w-full flex items-center space-x-6">
                            <?php if ($item_img) { ?>
                            <div class="w-20 h-20 flex-shrink-0">
                                <img src="<?php echo $item_img; ?>" alt="<?php echo htmlspecialchars($item['it_name']); ?>" 
                                     class="w-full h-full object-cover rounded-lg">
                            </div>
                            <?php } ?>
                            <div class="flex-1">
                                <div class="space-y-1">
                                    <div class="flex">
                                        <p class="text-sm min-w-[60px] text-gray-500">상품명</p>
                                        <p class="text-sm font-medium"><?php echo htmlspecialchars($item['it_name']) ?></p>
                                    </div>
                                    <div class="flex">
                                        <p class="text-sm min-w-[60px] text-gray-500">가격</p>
                                        <p class="text-sm font-medium"><?php echo number_format($item['it_price']) ?>원</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium"><?php echo $item['it_stock_qty'] ?><span class="text-sm min-w-[60px] text-gray-500">개 재고</span></p>
                                        <?php 
                                        $warning_qty = isset($branch['br_stock_warning_qty']) ? $branch['br_stock_warning_qty'] : 10;
                                        if($item['it_stock_qty'] <= $warning_qty && $item['it_stock_qty'] > 0): 
                                        ?>
                                        <span style="background-color: #fed7aa; color: #c2410c; padding: 2px 8px; border-radius: 9999px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;">
                                            <svg style="width: 12px; height: 12px;" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            품절임박
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1 mt-5">
                            <div class="w-full flex space-x-3">
                                <button type="button" class="border flex items-center justify-center rounded-lg font-medium px-4 text-sm" 
                                        onclick="showProductDetail('<?php echo $item['it_id'] ?>')"
                                        data-product-id="<?php echo $item['it_id'] ?>"
                                        data-product-name="<?php echo htmlspecialchars($item['it_name']) ?>"
                                        data-product-price="<?php echo number_format($item['it_price']) ?>"
                                        data-product-img="<?php echo $item_img ?>"
                                        data-product-basic="<?php echo htmlspecialchars($item['it_basic']) ?>"
                                        data-product-explan="<?php echo htmlspecialchars($item['it_explan']) ?>">상세보기</button>
                                <div class="border flex-1 flex items-center justify-center rounded-lg">
                                    <button type="button" 
                                            class="quantity-btn" 
                                            onclick="updateQuantity('<?php echo $item['it_id'] ?>', -1)"
                                            disabled>-</button>
                                    <div class="flex items-center justify-center font-bold text-lg px-4">
                                        <span id="qty_<?php echo $item['it_id'] ?>">0</span>개
                                    </div>
                                    <button type="button" 
                                            class="quantity-btn" 
                                            onclick="updateQuantity('<?php echo $item['it_id'] ?>', 1)">+</button>
                                </div>
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][qty]" value="0" id="input_<?php echo $item['it_id'] ?>">
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][price]" value="<?php echo $item['it_price'] ?>">
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][name]" value="<?php echo htmlspecialchars($item['it_name']) ?>">
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][stock]" value="<?php echo $item['it_stock_qty'] ?>">
                            </div>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        <?php } else { ?>
            <!-- 2열/3열 레이아웃 -->
            <div class="<?php echo $grid_class; ?>">
                <?php foreach ($filtered_items as $ca_id => $category_items) { ?>
                    <?php foreach ($category_items as $item) { ?>
                        <?php
                        $item_img = '';
                        if ($item['it_img1']) {
                            $item_img = G5_DATA_URL . '/item/' . $item['it_img1'];
                        }
                        ?>
                        <div class="<?php echo $item_class; ?> flex flex-col">
                            <?php if ($item_img) { ?>
                            <div class="w-full h-48 mb-4">
                                <img src="<?php echo $item_img; ?>" alt="<?php echo htmlspecialchars($item['it_name']); ?>" 
                                     class="w-full h-full object-cover rounded-lg">
                            </div>
                            <?php } ?>
                            <div class="flex-1">
                                <h3 class="font-medium text-base mb-2"><?php echo htmlspecialchars($item['it_name']) ?></h3>
                                <p class="text-lg font-bold text-primary mb-1"><?php echo number_format($item['it_price']) ?>원</p>
                                <p class="text-sm text-gray-500 mb-4"><?php echo $item['it_stock_qty'] ?>개 남았습니다.</p>
                            </div>
                            <div class="flex flex-col space-y-2">
                                <button type="button" 
                                        class="w-full border border-gray-300 rounded-lg py-2 text-sm font-medium hover:bg-gray-50"
                                        onclick="showProductDetail('<?php echo $item['it_id'] ?>')"
                                        data-product-id="<?php echo $item['it_id'] ?>"
                                        data-product-name="<?php echo htmlspecialchars($item['it_name']) ?>"
                                        data-product-price="<?php echo number_format($item['it_price']) ?>"
                                        data-product-img="<?php echo $item_img ?>"
                                        data-product-basic="<?php echo htmlspecialchars($item['it_basic']) ?>"
                                        data-product-explan="<?php echo htmlspecialchars($item['it_explan']) ?>">상세보기</button>
                                <div class="border border-gray-300 rounded-lg flex items-center justify-between overflow-hidden">
                                    <button type="button" 
                                            class="quantity-btn w-12 h-10 flex-shrink-0" 
                                            onclick="updateQuantity('<?php echo $item['it_id'] ?>', -1)"
                                            disabled>-</button>
                                    <div class="flex items-center justify-center font-bold text-base flex-1">
                                        <span id="qty_<?php echo $item['it_id'] ?>">0</span>개
                                    </div>
                                    <button type="button" 
                                            class="quantity-btn w-12 h-10 flex-shrink-0" 
                                            onclick="updateQuantity('<?php echo $item['it_id'] ?>', 1)">+</button>
                                </div>
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][qty]" value="0" id="input_<?php echo $item['it_id'] ?>">
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][price]" value="<?php echo $item['it_price'] ?>">
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][name]" value="<?php echo htmlspecialchars($item['it_name']) ?>">
                                <input type="hidden" name="items[<?php echo $item['it_id'] ?>][stock]" value="<?php echo $item['it_stock_qty'] ?>">
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        <?php } ?>
    <?php } else { ?>
        <!-- 상품이 없을 때 -->
        <div class="<?php echo ($layout == '1col') ? 'product-card' : 'border rounded-lg p-8'; ?> text-center py-10">
            <div class="text-gray-500">
                <i class="fas fa-box-open text-4xl mb-4"></i>
                <p class="text-lg font-medium">해당 날짜에 주문 가능한 상품이 없습니다</p>
                <p class="text-sm">다른 날짜를 선택하시거나 나중에 다시 확인해주세요</p>
            </div>
        </div>
    <?php } ?>
</div>