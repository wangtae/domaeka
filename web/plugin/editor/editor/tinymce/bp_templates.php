<?php
include_once "./_common.php";
header('Content-Type: application/json; charset=UTF-8');
$url =  G5_PLUGIN_URL . "/editor/{$config['cf_editor']}/template/";
?>
[
{"title": "BS4 Jumbotron", "description": "Bootstrap Jumbotron Template", "url": "<?php echo $url . "/jumbotron.html"; ?>"},
{"title": "SECTION", "description": "섹션", "url": "<?php echo $url . "/section.html"; ?>"},
{"title": "BS4 Alert Info", "description": "Bootstrap Alert info Template", "url": "<?php echo $url . "/alert-info.html"; ?>"},
{"title": "BS4 Alert Danger", "description": "Bootstrap Alert danger Template", "url": "<?php echo $url . "/alert-danger.html"; ?>"},
{"title": "BS4 Card", "description": "Bootstrap Card Template", "url":"<?php echo $url . "/card.html"; ?>"},
{"title": "BS4 Card-image", "description": "Bootstrap Card-image Template", "url":"<?php echo $url . "/card-image.html"; ?>"},
{"title": "BS4 Card-header", "description": "Bootstrap Card-header Template", "url":"<?php echo $url . "/card-header.html"; ?>"}
]