<?php
// 設置錯誤報告 (僅供開發環境使用)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ----------------------------------------------------
// 0. 變數檢查與初始化
// ----------------------------------------------------

// 檢查必要的 POST 變數
if (empty($_POST["name"]) || empty($_POST["password"]) || empty($_POST["card_id"]) || empty($_POST["plate_id"])) {
    die("❌ 錯誤：缺少必要的註冊資訊。");
}

$name = $_POST["name"];
$password = $_POST["password"];
$card_id = $_POST["card_id"];
$student_id = $_POST["student_id"];
$plate_id = $_POST["plate_id"];
$mail = $_POST["mail"];
$hashed = password_hash($password, PASSWORD_DEFAULT);

$user_id = null; // 儲存新建立的使用者 ID
$save_path = null; // 儲存上傳圖片的路徑
$success = true;

// ----------------------------------------------------
// 1. 處理檔案上傳 (必須先完成，才能取得路徑供後續使用)
// ----------------------------------------------------

// 確保有收到檔案且無錯誤
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {

    $file = $_FILES['photo'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_filename = uniqid("img_", true) . "." . $ext;
        $save_path = $upload_dir . $new_filename;

        // 將圖片從暫存區移到資料夾
        if (!move_uploaded_file($file['tmp_name'], $save_path)) {
            $success = false;
            error_log("❌ 圖片儲存失敗。");
        }
    } else {
        $success = false;
        error_log("❌ 不允許的檔案格式。");
    }
} else {
    // 即使沒有圖片，MySQL 交易也可能繼續，但我們需要設定 $success=false 來阻止寫入 MongoDB
    // 根據您的業務邏輯決定是否必須上傳圖片。這裡假設圖片是必須的。
    $success = false;
    error_log("❌ 未收到圖片或上傳失敗。");
}

// ----------------------------------------------------
// 2. MySQL 交易：插入 user & vehicle
// ----------------------------------------------------

if ($success) { // 僅在檔案上傳成功後才進行資料庫操作
    $mysqli = new mysqli("localhost", "root", "123456", "parking_db");
    if ($mysqli->connect_errno) {
        die("MySQL 連線失敗: " . $mysqli->connect_error);
    }

    $mysqli->begin_transaction();

    // A. 插入 user 資料
    $sql_user = "INSERT INTO user (name, password, card_id, student_id, mail) VALUES (?, ?, ?, ?, ?)";
    if ($stmt_user = $mysqli->prepare($sql_user)) {
        $stmt_user->bind_param("sssss", $name, $hashed, $card_id, $student_id, $mail);
        if ($stmt_user->execute()) {
            $user_id = $mysqli->insert_id;
        } else {
            $success = false;
            error_log("User 插入失敗: " . $stmt_user->error);
        }
        $stmt_user->close();
    } else {
        $success = false;
        error_log("User 預處理失敗: " . $mysqli->error);
    }

    // B. 插入 vehicle 資料
    if ($success && $user_id) {
        $sql_vehicle = "INSERT INTO vehicle (user_id, plate_id) VALUES (?, ?)";
        if ($stmt_vehicle = $mysqli->prepare($sql_vehicle)) {
            $stmt_vehicle->bind_param("is", $user_id, $plate_id);
            if (!$stmt_vehicle->execute()) {
                $success = false;
                error_log("Vehicle 插入失敗: " . $stmt_vehicle->error);
            }
            $stmt_vehicle->close();
        } else {
            $success = false;
            error_log("Vehicle 預處理失敗: " . $mysqli->error);
        }
    }

    // 處理 MySQL 交易結果
    if ($success) {
        $mysqli->commit();
    } else {
        $mysqli->rollback();
        // 如果 MySQL 失敗，則不需要寫入 MongoDB，直接回傳錯誤
        $mysqli->close();
        die("❌ 註冊失敗，資料已復原。請檢查錯誤日誌。");
    }
    $mysqli->close();
}


// ----------------------------------------------------
// 3. 寫入 MongoDB (必須在 MySQL 成功後，且有檔案路徑)
// ----------------------------------------------------

if ($success && $save_path) {

    require __DIR__ . '/vendor/autoload.php';
    try {
        $mongoClient = new MongoDB\Client('mongodb://localhost:27017');
        $mongoCollection = $mongoClient->parkingNoSqldb->parkingdb;

        // ✨ 核心修正：將在地端儲存的圖片轉換為 Base64
        $photoBase64Data = null;
        if (file_exists($save_path)) {
            $imageData = file_get_contents($save_path);
            if ($imageData !== FALSE) {
                // 將圖片二進制數據編碼為 Base64 字串
                $photoBase64Data = base64_encode($imageData);
            }
        }

        $documentData = [
            'plate' => $plate_id, // 使用車牌號碼作為查詢鍵
            'photo' => $photoBase64Data, // 儲存 Base64 數據
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
        ];

        $result = $mongoCollection->insertOne($documentData);

        if ($result->getInsertedCount() == 1) {
            // MongoDB 寫入成功，註冊流程結束
            // 可選：成功寫入 MongoDB 後，刪除伺服器上的原始圖片檔案
            // unlink($save_path); 

            // 導向登入頁面
            header("Location: login.html");
            exit;

        } else {
            error_log("MongoDB 寫入失敗。");
            $success = false;
        }

    } catch (Exception $e) {
        error_log("MongoDB 連線或寫入錯誤: " . $e->getMessage());
        $success = false;
    }
}

// ----------------------------------------------------
// 4. 最終結果處理
// ----------------------------------------------------

if (!$success) {
    // 檔案上傳或 MongoDB 失敗
    if ($save_path && file_exists($save_path)) {
        // 如果檔案還在，將其刪除以保持伺服器清潔
        unlink($save_path);
    }
    die("❌ 註冊失敗，請檢查錯誤日誌。");
}
?>