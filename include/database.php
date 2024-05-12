<?php
$servername = "127.0.0.1";
$username = "root";
$password = "";
$database = "EZK3";

    $conn = new mysqli($servername, $username, $password, $database);

    // Проверка соединения
    if ($conn->connect_error) {
        die("Ошибка подключения: " . $conn->connect_error);
    }
 