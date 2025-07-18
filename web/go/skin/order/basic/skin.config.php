<?php
/**
 * 기본 주문 페이지 스킨 설정
 */

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 스킨 정보
$skin_info = array(
    'name' => '기본 스킨',
    'version' => '1.0',
    'author' => '도매까',
    'description' => '1열, 2열, 3열 레이아웃을 지원하는 기본 스킨입니다.',
    'options' => array(
        'layout' => array(
            'label' => '레이아웃',
            'type' => 'select',
            'default' => '1col',
            'values' => array(
                '1col' => '1열',
                '2col' => '2열',
                '3col' => '3열'
            )
        )
    )
);
?>