<?php
session_start();

$mysqli = new mysqli('localhost', 'root', '123456', 'parking_db');

if ($mysqli->connect_error) {
    die("MySQL 連線失敗: " . $mysqli->connect_error);
}

$student_id = $_POST['student_id'] ?? '';
$password = $_POST['password'] ?? '';

if ($student_id && $password) {
    $stmt = $mysqli->prepare("SELECT user_id, student_id, password FROM user WHERE student_id=?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // 登入成功
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['student_id'] = $user['student_id'];
        header("Location: member.php");
        exit;
    } else {
        // 登入失敗
        echo "<p style='color:red; font-weight:bold; background-color:black; padding:5px;'>帳號或密碼錯誤</p>";
        echo '<script>setTimeout(()=>{window.location="login.html"},3000);</script>';
    }

    $stmt->close();
}

$mysqli->close();
?>