<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Before including common.php<br>";

try {
    require_once(__DIR__ . '/../common.php');
    echo "After including common.php<br>";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
} catch (ParseError $e) {
    echo "Parse Error: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . "<br>";
}

echo "Script ended<br>";
?>