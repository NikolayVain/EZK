<?php

//tmp - remove before prod!!!
error_reporting(E_ALL);
ini_set('display_errors', '1');

// get current session data
session_start();

// logout to kill session
if (!empty($_REQUEST['logout'])) {
    session_destroy();
    die('session logout');
}

if (!empty($_SESSION['user'])) {
    $user = $_SESSION['user']; 
} else {
    $user = null;
}

$included = true;

include 'include/database.php';
include 'include/general.php';

if ($user) {
    if ($user['type'] == 'student') {
        include 'include/student.php';
    } else {
        include 'include/teacher.php';
    }
} else {
    include 'include/login.php';
}




