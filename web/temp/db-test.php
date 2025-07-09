<?php
// test_db.php 파일 생성
$host = 'domaeka-mariadb';
$username = 'domaeka';
$password = '!domaekaservice@.';
$database = 'domaeka';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    echo "DB 연결 성공!";
} catch(PDOException $e) {
    echo "DB 연결 실패: " . $e->getMessage();
}
?>
