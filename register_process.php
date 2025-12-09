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
// 1. 處理檔案上傳 (仍必須暫時儲存圖片，才能將其導入 GridFS)
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

    // A. 檢查 plate_id 是否重複 (新增的檢查邏輯)
    $sql_check_plate = "SELECT plate_id FROM vehicle WHERE plate_id = ?";
    if ($stmt_check = $mysqli->prepare($sql_check_plate)) {
        $stmt_check->bind_param("s", $plate_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $success = false;
            error_log("Vehicle 插入失敗: 車牌 {$plate_id} 已註冊。");
        }
        $stmt_check->close();
    } else {
        $success = false;
        error_log("Vehicle 檢查預處理失敗: " . $mysqli->error);
    }
    
    // B. 插入 user 資料
    if ($success) {
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
    }


    // C. 插入 vehicle 資料
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
        // 如果 MySQL 失敗，則不需要寫入 MongoDB
        $mysqli->close();
        
        // 如果錯誤發生在 MySQL 插入前，圖片可能還在地端，需要清理
        if ($save_path && file_exists($save_path)) {
             unlink($save_path);
        }
        die("❌ 註冊失敗，資料已復原。請檢查錯誤日誌。");
    }
    $mysqli->close();
}


// ----------------------------------------------------
// 3. 寫入 MongoDB (使用 GridFS 儲存圖片 ID)
// ----------------------------------------------------

if ($success && $save_path) {

    require __DIR__ . '/vendor/autoload.php';
    $gridfs_file_id = null; 

    try {
        $mongoClient = new MongoDB\Client('mongodb://localhost:27017');
        $database = $mongoClient->parkingNoSqldb;
        
        // 1. 取得 GridFS Bucket (預設會使用 fs.files 和 fs.chunks)
        $bucket = $database->selectGridFSBucket(); 

        // 2. 開啟圖片的二進制數據流
        $stream = fopen($save_path, 'r');
        if ($stream === FALSE) {
            throw new Exception("無法開啟圖片檔案流。");
        }

        // 3. 將圖片數據流儲存到 GridFS 中，並取得 ID
        // 在 metadata 中儲存 plate_id，方便在 GridFS 內部查詢
        $options = ['metadata' => ['plate' => $plate_id]];
        
        $gridfs_file_id = $bucket->uploadFromStream(
            $plate_id . "_" . basename($save_path), // GridFS 內部的檔名
            $stream,
            $options
        );
        
        fclose($stream); // 關閉數據流

        if ($gridfs_file_id) {
            
            // ★ 核心步驟：將 GridFS ID 寫入目標 parkingdb Collection
            $mongoCollection = $database->parkingdb;
            
            $documentData = [
                'plate' => $plate_id, // 車牌號碼
                'photo' => $gridfs_file_id, // GridFS 返回的 ObjectId，即圖片的 ID
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
            ];
            
            // 由於您要保持原來的結構，我們將 photo 欄位的值設為 GridFS ID
            $result = $mongoCollection->insertOne($documentData);

            if ($result->getInsertedCount() == 1) {
                // 成功寫入 MongoDB，執行地端檔案刪除
                if (file_exists($save_path)) {
                    unlink($save_path); 
                }

                // 導向登入頁面
                header("Location: login.html");
                exit;
            } else {
                // MongoDB 寫入失敗，但圖片已在 GridFS 中。理論上應回滾 GridFS，但這裡簡化處理。
                error_log("MongoDB ParkingDB 寫入失敗。");
                $success = false;
            }

        } else {
            error_log("GridFS 圖片儲存失敗。");
            $success = false;
        }

    } catch (Exception $e) {
        error_log("MongoDB (GridFS) 連線或寫入錯誤: " . $e->getMessage());
        $success = false;
    }
}

// ----------------------------------------------------
// 4. 最終結果處理
// ----------------------------------------------------

if (!$success) {
    // 如果是 GridFS/MongoDB 失敗，且本地檔案還存在（如前面步驟 3 中沒有被刪除），則清理
    if ($save_path && file_exists($save_path)) {
        unlink($save_path);
    }
    die("❌ 註冊失敗，請檢查錯誤日誌。");
}
?>