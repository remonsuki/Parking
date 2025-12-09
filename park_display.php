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
    $message = "離場成功！";
    $time_label = "離場時間";
} else {
    $message = "錯誤或未定義的動作";
    $time_label = "時間";
}

// 確保連線在腳本結束時關閉，儘管在這個頁面中不是必須的。
if ($mysqli->connect_error) {
    // 雖然連線失敗，頁面邏輯仍可運行，但應記錄錯誤。
    error_log("MySQL 連線失敗: " . $mysqli->connect_error);
}
$mysqli->close();

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>✅ 停車成功</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <meta http-equiv="refresh" content="5;url=parkinout.php" />
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            /* 柔和淺藍灰背景 */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .success-box {
            background: white;
            padding: 40px;
            border-radius: 16px;
            /* 更圓潤 */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            /* 柔和陰影 */
            text-align: center;
            width: 90%;
            max-width: 480px;
            border-top: 5px solid #28a745;
            /* 頂部增加強調綠色邊框 */
            transition: transform 0.3s ease;
        }

        .success-box:hover {
            transform: translateY(-5px);
        }

        h1 {
            color: #28a745;
            /* 成功綠 */
            font-size: 36px;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .welcome-msg {
            font-size: 20px;
            color: #333;
            margin: 0 0 25px 0;
            font-weight: 600;
        }

        strong {
            color: #1877f2;
            /* 藍色強調使用者資訊 */
            font-weight: 700;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 15px;
            background-color: #f0f8ff;
            /* 輕微藍色背景區分資訊區塊 */
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
            border: 1px solid #e0f0ff;
        }

        .info-grid p {
            margin: 0;
            font-size: 16px;
            padding: 5px 0;
        }

        .info-label {
            color: #6c757d;
            /* 灰色標籤 */
            font-weight: 400;
        }

        .info-value {
            color: #333;
            font-weight: 600;
        }

        .timer {
            font-size: 14px;
            color: #6c757d;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        .return-btn {
            display: inline-block;
            background-color: #1877f2;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
            box-shadow: 0 4px 10px rgba(24, 119, 242, 0.4);
        }

        .return-btn:hover {
            background-color: #135fbf;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="success-box">
        <h1>✅ <?php echo htmlspecialchars($message); ?></h1>
        <p class="welcome-msg">歡迎您，**<?php echo htmlspecialchars($user_name); ?>**！</p>

        <div class="info-grid">
            <div>
                <p class="info-label">學號 / ID</p>
                <p class="info-value"><?php echo htmlspecialchars($student_id); ?></p>
            </div>
            <div>
                <p class="info-label"><?php echo htmlspecialchars($time_label); ?></p>
                <p class="info-value"><?php echo htmlspecialchars($display_time); ?></p>
            </div>
        </div>

        <p class="timer">（本頁面將於 5 秒後自動跳轉回感應介面）</p>
        <a href="parkinout.php" class="return-btn">立即返回</a>
    </div>
</body>

</html>