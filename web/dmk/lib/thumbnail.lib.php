<?php
/**
 * 썸네일 생성 라이브러리
 * Base64 이미지를 받아서 썸네일을 생성하고 Base64로 반환
 */

if (!defined('_GNUBOARD_')) exit;

/**
 * Base64 이미지에서 썸네일 생성
 * 
 * @param string $base64_image Base64 인코딩된 원본 이미지
 * @param int $width 썸네일 너비
 * @param int $height 썸네일 높이
 * @param int $quality JPEG 품질 (1-100)
 * @return string|false Base64 인코딩된 썸네일 또는 실패시 false
 */
function create_thumbnail_from_base64($base64_image, $width = 80, $height = 80, $quality = 80) {
    try {
        // Base64 디코딩
        $image_data = base64_decode($base64_image);
        if ($image_data === false) {
            return false;
        }
        
        // 이미지 리소스 생성
        $src_image = imagecreatefromstring($image_data);
        if ($src_image === false) {
            return false;
        }
        
        // 원본 이미지 크기
        $src_width = imagesx($src_image);
        $src_height = imagesy($src_image);
        
        // 비율 계산
        $ratio_orig = $src_width / $src_height;
        $ratio_thumb = $width / $height;
        
        // 크롭 영역 계산 (중앙 크롭)
        if ($ratio_orig > $ratio_thumb) {
            // 원본이 더 넓음 - 좌우를 크롭
            $new_width = $src_height * $ratio_thumb;
            $new_height = $src_height;
            $x_offset = ($src_width - $new_width) / 2;
            $y_offset = 0;
        } else {
            // 원본이 더 높음 - 상하를 크롭
            $new_width = $src_width;
            $new_height = $src_width / $ratio_thumb;
            $x_offset = 0;
            $y_offset = ($src_height - $new_height) / 2;
        }
        
        // 썸네일 이미지 생성
        $thumb_image = imagecreatetruecolor($width, $height);
        
        // 백그라운드 흰색으로 채우기
        $white = imagecolorallocate($thumb_image, 255, 255, 255);
        imagefill($thumb_image, 0, 0, $white);
        
        // 리샘플링으로 고품질 썸네일 생성
        imagecopyresampled(
            $thumb_image, $src_image,
            0, 0, $x_offset, $y_offset,
            $width, $height, $new_width, $new_height
        );
        
        // 메모리에서 JPEG 생성
        ob_start();
        imagejpeg($thumb_image, null, $quality);
        $thumbnail_data = ob_get_clean();
        
        // 메모리 해제
        imagedestroy($src_image);
        imagedestroy($thumb_image);
        
        // Base64 인코딩하여 반환
        return base64_encode($thumbnail_data);
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 이미지 배열에서 썸네일 배열 생성
 * 
 * @param array $images_array JSON 디코딩된 이미지 배열
 * @param int $width 썸네일 너비
 * @param int $height 썸네일 높이
 * @param int $quality JPEG 품질
 * @return array 썸네일 배열
 */
function create_thumbnails_from_array($images_array, $width = 80, $height = 80, $quality = 80) {
    $thumbnails = array();
    
    if (!is_array($images_array)) {
        return $thumbnails;
    }
    
    foreach ($images_array as $idx => $image) {
        if (isset($image['base64']) && !empty($image['base64'])) {
            $thumbnail_base64 = create_thumbnail_from_base64($image['base64'], $width, $height, $quality);
            if ($thumbnail_base64 !== false) {
                $thumbnails[] = array(
                    'base64' => $thumbnail_base64,
                    'original_idx' => $idx
                );
            }
        }
    }
    
    return $thumbnails;
}

/**
 * 스케줄 데이터의 모든 이미지에 대한 썸네일 생성
 * 
 * @param array $schedule 스케줄 데이터 (message_images_1, message_images_2 포함)
 * @return array 썸네일 데이터 (message_thumbnails_1, message_thumbnails_2)
 */
function create_schedule_thumbnails($schedule) {
    $result = array(
        'message_thumbnails_1' => null,
        'message_thumbnails_2' => null
    );
    
    // 이미지 그룹 1 썸네일 생성
    if (!empty($schedule['message_images_1'])) {
        $images_1 = json_decode($schedule['message_images_1'], true);
        if ($images_1) {
            $thumbnails_1 = create_thumbnails_from_array($images_1);
            if (!empty($thumbnails_1)) {
                $result['message_thumbnails_1'] = json_encode($thumbnails_1);
            }
        }
    }
    
    // 이미지 그룹 2 썸네일 생성
    if (!empty($schedule['message_images_2'])) {
        $images_2 = json_decode($schedule['message_images_2'], true);
        if ($images_2) {
            $thumbnails_2 = create_thumbnails_from_array($images_2);
            if (!empty($thumbnails_2)) {
                $result['message_thumbnails_2'] = json_encode($thumbnails_2);
            }
        }
    }
    
    return $result;
}