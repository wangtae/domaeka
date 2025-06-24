<?php
include_once "./_common.php";
/***************************************************
 * Only these origins are allowed to upload images *
 ***************************************************/
$accepted_origins = BP_TINYMCE_DOMAIN;

/*********************************************
 * Change this line to set the upload folder *
 *********************************************/
$imageFolder = G5_EDITOR_PATH . DIRECTORY_SEPARATOR . $config['cf_editor'] . DIRECTORY_SEPARATOR . 'images/';
$imageurl = G5_EDITOR_URL . DIRECTORY_SEPARATOR . $config['cf_editor'] . DIRECTORY_SEPARATOR . 'images/';
@mkdir($imageFolder, G5_DIR_PERMISSION);

reset($_FILES);
$temp = current($_FILES);
if (isset($temp['tmp_name']) && is_uploaded_file($temp['tmp_name'])) {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        // same-origin requests won't set an origin. If the origin is set, it must be valid.
        if (in_array($_SERVER['HTTP_ORIGIN'], $accepted_origins)) {
            header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        } else {
            header("HTTP/1.1 403 Origin Denied");
            return;
        }
    }

    // Don't attempt to process the upload on an OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        return;
    }

    // Sanitize input
    if (preg_match("/([^\w\s\d\-_~,;:\[\]\(\).])|([\.]{2,})/", $temp['name'])) {
        header("HTTP/1.1 400 Invalid file name.");
        return;
    }

    // Verify extension
    if (!in_array(strtolower(pathinfo($temp['name'], PATHINFO_EXTENSION)), array("gif", "jpg", "png", 'webp'))) {
        header("HTTP/1.1 400 Invalid extension.");
        return;
    }

    //파일명 변경
    $upload = cut_str(md5(sha1($_SERVER['REMOTE_ADDR'])), 5, '-') . uniqid() . '-' . replace_filename($temp['name']);

    //이미지 SERVER PATH
    $filetowrite = $imageFolder . $upload;
    //이미지 URL
    $imageurl_full = $imageurl . $upload;
    move_uploaded_file($temp['tmp_name'], $filetowrite);
    //bp_image_orientation_fix($filetowrite, 85);

    // Respond to the successful upload with JSON.
    // Use a location key to specify the path to the saved image resource.
    // { location : '/your/uploaded/image/file'}
    echo json_encode(array('location' => $imageurl_full));
} else {
    // Notify editor that the upload failed
    header("HTTP/1.1 500 Server Error");
}
