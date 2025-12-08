<?php
session_start();

// 1. 驗證登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

// 2. 資料庫連線 (MySQL)
$mysqli = new mysqli('localhost', 'root', '123456', 'parking_db');

if ($mysqli->connect_error) {
    die("MySQL 連線失敗: " . $mysqli->connect_error);
}

$user_id = $_SESSION['user_id'];
$update_message = '';

// 3. 處理表單 POST 提交 (密碼/信箱修改) - 此邏輯保持不變
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['password'] ?? '';
    $new_mail = $_POST['mail'] ?? '';
    $update_fields = [];
    $update_params = [];
    $param_types = '';

    if (!empty($new_password)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update_fields[] = "password=?";
        $update_params[] = $hashed;
        $param_types .= 's';
    }

    if (!empty($new_mail)) {
        $update_fields[] = "mail=?";
        $update_params[] = $new_mail;
        $param_types .= 's';
    }

    if (!empty($update_fields)) {
        $sql = "UPDATE user SET " . implode(", ", $update_fields) . " WHERE user_id=?";
        $stmt = $mysqli->prepare($sql);

        $update_params[] = $user_id;
        $param_types .= 'i';

        $stmt->bind_param($param_types, ...$update_params);

        if ($stmt->execute()) {
            $update_message = "<p style='color:green; font-weight:bold;'>✅ 資訊已成功更新！</p>";
        } else {
            $update_message = "<p style='color:red;'>❌ 更新失敗：" . htmlspecialchars($stmt->error) . "</p>";
        }
        $stmt->close();
    }
}

// 4. 獲取使用者所有資訊 (MySQL)
$sql_fetch = "
    SELECT 
        u.name, 
        u.student_id, 
        u.mail, 
        v.plate_id 
    FROM 
        user u
    LEFT JOIN 
        vehicle v ON u.user_id = v.user_id 
    WHERE 
        u.user_id = ?
";

$stmt_fetch = $mysqli->prepare($sql_fetch);
$stmt_fetch->bind_param("i", $user_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
$user_info = $result->fetch_assoc();
$stmt_fetch->close();

if (!$user_info) {
    session_destroy();
    header("Location: login.html");
    exit;
}

// 5. 連線到 MongoDB 並讀取照片
$photo_base64_uri = null;
$plate_id = $user_info['plate_id'] ?? null;

if (!empty($plate_id)) {
    // 從當前檔案 member.php 的位置，向上尋找 vendor 資料夾
    require __DIR__ . '/vendor/autoload.php';
    try {
        $mongoClient = new MongoDB\Client('mongodb://localhost:27017');
        // 使用者提供的資料庫和集合名稱
        $mongoCollection = $mongoClient->parkingNoSqldb->parkingdb;

        // 查詢 MongoDB，尋找 plate 欄位匹配的文檔
        $mongo_result = $mongoCollection->findOne(['plate' => $plate_id]);

        if ($mongo_result && isset($mongo_result['photo'])) {
            // 假設 'photo' 欄位儲存了 Base64 編碼的圖片字串
            $photo_base64 = (string) $mongo_result['photo'];

            // 處理 Base64 數據並組合成 Data URI
            // 假設圖片類型為 image/jpeg，如果圖片包含 MIME 資訊，則優先解析
            $mime_type = 'image/jpeg';
            $photo_data = $photo_base64;

            // 檢查 Base64 字串是否包含 Data URI 前綴 (如 data:image/jpeg;base64,...)
            if (preg_match('/^data:(image\/(?:png|jpeg|gif|bmp|webp));base64,(.*)$/', $photo_base64, $matches)) {
                $mime_type = $matches[1];
                $photo_data = $matches[2]; // 只取 Base64 數據部分
            }

            $photo_base64_uri = "data:{$mime_type};base64,{$photo_data}";
        }
    } catch (Exception $e) {
        error_log("MongoDB Error: " . $e->getMessage());
        $update_message .= "<p style='color:orange; font-size:14px;'>⚠️ MongoDB 連線或讀取失敗。</p>";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>🌟 會員頁面 - 資訊修改</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            margin: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #1877f2;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }

        p {
            font-size: 16px;
            margin: 10px 0;
        }

        strong {
            color: #333;
        }

        /* 針對照片顯示區塊的樣式 */
        .photo-display {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 6px;
        }

        .photo-display img {
            max-width: 100%;
            height: auto;
            border: 2px solid #ccc;
            border-radius: 4px;
        }

        input[type="text"],
        input[type="password"] {
            width: 98%;
            padding: 10px;
            margin: 5px 0 15px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        input[type="submit"]:hover {
            background-color: #1e7e34;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            font-size: 16px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>👤 會員資訊與修改</h2>

        <?php echo $update_message; // 顯示更新成功或失敗訊息 ?>

        <p><strong>姓名:</strong> <?php echo htmlspecialchars($user_info['name'] ?? 'N/A'); ?></p>
        <p><strong>學號:</strong> <?php echo htmlspecialchars($user_info['student_id'] ?? 'N/A'); ?></p>
        <p><strong>車牌號碼:</strong> <?php echo htmlspecialchars($plate_id ?? 'N/A'); ?></p>

        <?php if (!empty($photo_base64_uri)): ?>
            <div class="photo-display">
                <label style="font-weight: bold; color: #1877f2;">🏍️ 機車照片:</label>
                <img src="<?php echo $photo_base64_uri; ?>" alt="機車照片">
            </div>
        <?php else: ?>
            <p style="color:#dc3545; font-size:14px; text-align: center;">(未找到車輛照片或 MongoDB 中無對應紀錄)</p>
        <?php endif; ?>
        <hr>

        <form method="post" action="">

            <label>📧 信箱（可修改）:</label>
            <input type="text" name="mail" value="<?php echo htmlspecialchars($user_info['mail'] ?? ''); ?>" required>

            <label>🔑 密碼（留空則不修改）:</label>
            <input type="password" name="password" placeholder="請輸入新密碼">
            <p style="font-size:12px; color:#999; margin-top:-10px;">* 密碼若留空，則保持不變。</p>

            <input type="submit" value="💾 儲存修改">
        </form>

        <a href="logout.php" class="logout-btn">登出</a>
    </div>
</body>

</html>

<?php
$mysqli->close();
?>