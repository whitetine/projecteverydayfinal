<?php
// 防止重複包含
if (!isset($pdo_included)) {
    $pdo_included = true;
    
    $conn=new PDO("mysql:host=localhost;dbname=projecteverydays","root","");
    // 現在密碼是預設的，如果有改資料庫密碼要記得改
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (!function_exists('query')) {
        function query($res){
            global $conn;
            return $conn->query($res);
        }
    }
    
    if (!function_exists('fetch')) {
        function fetch($res){
            return $res->fetch(2);
        }
    }
    
    if (!function_exists('fetchAll')) {
        function fetchAll($res){
            return $res->fetchAll(2);
        }
    }
    
    if (!function_exists('rowCount')) {
        function rowCount($res){
            return $res->rowCount();
        }
    }
}
?>