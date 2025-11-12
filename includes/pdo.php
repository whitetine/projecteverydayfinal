<?php
$conn=new PDO("mysql:host=localhost;dbname=projecteverydays","root","");
// 現在密碼是預設的，如果有改資料庫密碼要記得改
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function query($res){
    global $conn;
    return $conn->query($res);
}
function fetch($res){
    return $res->fetch(2);
}
function fetchAll($res){
    return $res->fetchAll(2);
}
function rowCount($res){
    return $res->rowCount();
}

?>