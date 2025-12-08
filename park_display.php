<?php
// 設置資料庫連線參數
$host = "localhost";
$user = "root";
$password = "123456";
$db_name = "parking_db";
$mysqli = new mysqli($host, $user, $password, $db_name);

//抓取資料
$user_id = $_GET['user_id'] ?? 'N/A';
$record_id = $_GET['record_id'] ?? 'N/A';
$action_status = $_GET['status'] ?? 'N/A';
$user_name = $_GET['name'] ?? 'N/A';
$student_id = $_GET['student_id'] ?? 'N/A';
$display_time = 'N/A';

if ($action_status === 'ENTRY') {
    $display_time = $_GET['entry_time'] ?? 'N/A';
    $message = "進場成功！";
    $time_label = "進場時間";
} elseif ($action_status === 'EXIT') {
    $display_time = $_GET['exit_time'] ?? 'N/A';
    $message = "出場成功！";
    $time_label = "出場時間";
} else {
    $message = "錯誤或未定義的動作";
    $time_label = "時間";
}

// if ($user_id > 0 && $record_id > 0) {
//     // 查詢用戶資訊
//     $sql_user = "SELECT name, student_id FROM user WHERE user_id = ?";
//     if ($stmt_user = $mysqli->prepare($sql_user)) {
//         $stmt_user->bind_param("i", $user_id);
//         $stmt_user->execute();
//         $result_user = $stmt_user->get_result();
//         if ($data_user = $result_user->fetch_assoc()) {
//             $user_name = htmlspecialchars($data_user['name']);
//             $student_id = htmlspecialchars($data_user['student_id']);
//         }
//         $stmt_user->close();
//     }

//     // 查詢停車時間
//     $sql_record = "SELECT entry_time FROM park_record WHERE record_id = ?";
//     if ($stmt_record = $mysqli->prepare($sql_record)) {
//         $stmt_record->bind_param("i", $record_id);
//         $stmt_record->execute();
//         $result_record = $stmt_record->get_result();
//         if ($data_record = $result_record->fetch_assoc()) {
//             $entry_time = htmlspecialchars($data_record['entry_time']);
//         }
//         $stmt_record->close();
//     }
// }

// $mysqli->close();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>✅ 停車成功</title>
    <meta http-equiv="refresh" content="5;url=parkinout.php" />
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #e6ffed;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .success-box {
            background: white;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            width: 450px;
            border: 3px solid #28a745;
        }

        h1 {
            color: #28a745;
            font-size: 32px;
        }

        p {
            font-size: 18px;
            margin: 15px 0;
        }

        strong {
            color: #1877f2;
        }

        .timer {
            font-size: 14px;
            color: #dc3545;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="success-box">
        <h1>✅ <?php echo htmlspecialchars($message); ?></h1>
        <p>歡迎您，**<?php echo $user_name; ?>**！</p>
        <hr>
        <p>學號: <strong><?php echo $student_id; ?></strong></p>
        <p><?php echo htmlspecialchars($time_label); ?>: <?php echo htmlspecialchars($display_time); ?></p>
        <p class="timer">（本頁面將於 5 秒後自動跳轉回感應介面）</p>
        <a href="parkinout.php">立即返回</a>
    </div>
</body>

</html>