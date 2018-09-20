<?php
session_start();
$result = explode('-', $_POST['token']);
$value = $_SESSION[$result[1]];
if ($value && $value == $result[2]) {
    session_destroy();
    echo json_encode(array(
        "code" => 1,
        "msg" => "验证通过"
    ));
} else {
    echo json_encode(array(
        "code" => 0,
        "msg" => "验证失败"
    ));
}