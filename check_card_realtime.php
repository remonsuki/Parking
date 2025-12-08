<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// 資料庫連線配置
$host = "localhost";
$user = "root";
$password = "123456";
$db_name = "parking_db";
$mysqli = new mysqli($host, $user, $password, $db_name);

if ($mysqli->connect_errno) {
    echo json_encode(['status' => 'ERROR', 'message' => "DB連線失敗: " . $mysqli->connect_error]);
    exit();
}

// 取得停車場代號 (預設為 1)
$lot_id = isset($_GET['lot_id']) ? (int) $_GET['lot_id'] : 1;
$txt_path = "card_number.txt";

// =======================================================
// 1. 容量檢查邏輯 (無論是否有卡，都需要執行)
// =======================================================

// 查詢總容量 (total_capacity)
$sql_capacity = "SELECT total_capacity FROM parkinglot WHERE lot_id = ?";
$stmt_capacity = $mysqli->prepare($sql_capacity);
$stmt_capacity->bind_param("i", $lot_id);
$stmt_capacity->execute();
$capacity_result = $stmt_capacity->get_result();
$capacity_data = $capacity_result->fetch_assoc();
$total_capacity = $capacity_data['total_capacity'] ?? 0;
$stmt_capacity->close();

// 查詢目前已佔用車位數 (exit_time IS NULL)
$sql_occupied = "SELECT COUNT(*) AS occupied_count FROM park_record WHERE lot_id = ? AND exit_time IS NULL";
$stmt_occupied = $mysqli->prepare($sql_occupied);
$stmt_occupied->bind_param("i", $lot_id);
$stmt_occupied->execute();
$occupied_result = $stmt_occupied->get_result();
$occupied_data = $occupied_result->fetch_assoc();
$occupied_count = $occupied_data['occupied_count'] ?? 0;
$stmt_occupied->close();

// 處理前端獨立請求容量資訊
if (isset($_GET['action']) && $_GET['action'] === 'check_capacity') {
    $mysqli->close();
    echo json_encode([
        'status' => 'CAPACITY_INFO',
        'total_capacity' => $total_capacity,
        'occupied_count' => $occupied_count
    ]);
    exit();
}

// =======================================================
// 2. 讀取卡號並處理進出場邏輯
// =======================================================

$card_id = file_exists($txt_path) ? trim(file_get_contents($txt_path)) : '';

// 檢查是否讀到有效卡號
if (empty($card_id) || $card_id === '') {
    $mysqli->close();
    echo json_encode(['status' => 'NO_CARD']);
    exit();
}

// 查詢 user ID、佔用狀態和姓名
$sql_check = "
    SELECT 
        user_id,
        is_occupied,
        name,
        student_id
    FROM 
        user
    WHERE 
        card_id = ?
    LIMIT 1";

$stmt = $mysqli->prepare($sql_check);
$stmt->bind_param("s", $card_id);
$stmt->execute();
$result = $stmt->get_result();

if ($user_data = $result->fetch_assoc()) {
    $user_id = $user_data['user_id'];
    $user_name = $user_data['name'];
    $student_id = $user_data['student_id'];
    $is_occupied = $user_data['is_occupied'];
    $current_time = date('Y-m-d H:i:s');
    $action_status = '';

    $record_id = null;
    $entry_time_found = null;

    $mysqli->begin_transaction();
    $success = false;

    // =======================================================
    // 3. 判斷進場 (is_occupied = 0)
    // =======================================================
    if ($is_occupied == 0) {

        $remaining_capacity = $total_capacity - $occupied_count;

        // 停車場已滿，回傳 'FULL' 狀態
        if ($remaining_capacity <= 0) {
            $mysqli->close();
            // 💡 滿位時不清空檔案，讓前端顯示完畢後等待下一次感應
            echo json_encode([
                'status' => 'FULL',
                'message' => "停車場 {$lot_id} 已滿！",
                'total_capacity' => $total_capacity,
                'occupied_count' => $occupied_count
            ]);
            file_put_contents($txt_path, '');
            exit();
        }

        // A. 寫入 park_record (進場紀錄)
        $sql_insert = "INSERT INTO park_record (user_id, entry_time, lot_id) VALUES (?, ?, ?)";
        $stmt_insert = $mysqli->prepare($sql_insert);
        $stmt_insert->bind_param("isi", $user_id, $current_time, $lot_id);

        if ($stmt_insert->execute()) {
            // B. 更新 user.is_occupied 狀態為 1 (已佔用)
            $sql_update_user = "UPDATE user SET is_occupied = 1 WHERE user_id = ?";
            $stmt_update_user = $mysqli->prepare($sql_update_user);
            $stmt_update_user->bind_param("i", $user_id);

            if ($stmt_update_user->execute()) {
                $success = true;
                $action_status = 'ENTRY';
                $record_id = $mysqli->insert_id;
            }
            $stmt_update_user->close();
        }
        $stmt_insert->close();

        // =======================================================
        // 4. 判斷出場 (is_occupied = 1)
        // =======================================================
    } else if ($is_occupied == 1) {

        // 1. 查詢最新未結算紀錄
        $sql_select_record = "
            SELECT record_id, entry_time
            FROM park_record 
            WHERE user_id = ? AND exit_time IS NULL 
            ORDER BY entry_time DESC 
            LIMIT 1";

        $stmt_select = $mysqli->prepare($sql_select_record);
        $stmt_select->bind_param("i", $user_id);
        $stmt_select->execute();
        $record_result = $stmt_select->get_result();

        if ($record_data = $record_result->fetch_assoc()) {
            $record_id = $record_data['record_id'];
            $entry_time_found = $record_data['entry_time'];

            // A. 更新 park_record (填入 exit_time)
            $sql_update_record = "
                UPDATE park_record 
                SET exit_time = ?
                WHERE record_id = ?";

            $stmt_update_record = $mysqli->prepare($sql_update_record);
            $stmt_update_record->bind_param("si", $current_time, $record_id);

            if ($stmt_update_record->execute()) {
                // B. 更新 user.is_occupied 狀態為 0
                $sql_update_user = "UPDATE user SET is_occupied = 0 WHERE user_id = ?";
                $sql_update_user = "UPDATE user SET violation_count = 0 WHERE user_id = ?";
                $stmt_update_user = $mysqli->prepare($sql_update_user);
                $stmt_update_user->bind_param("i", $user_id);

                if ($stmt_update_user->execute()) {
                    $success = true;
                    $action_status = 'EXIT';
                }
                $stmt_update_user->close();
            }
            $stmt_update_record->close();
        } else {
            $success = false;
        }
        $stmt_select->close();
    }

    // =======================================================
    // 5. 處理交易結果與回傳 (成功/失敗)
    // =======================================================
    if ($success) {
        $mysqli->commit();
        // 交易成功，清空卡號
        file_put_contents($txt_path, '');
        $mysqli->close();

        $entry_time_to_send = ($action_status == 'ENTRY') ? $current_time : $entry_time_found;
        $exit_time_to_send = ($action_status == 'EXIT') ? $current_time : null;

        echo json_encode([
            'status' => $action_status,
            'user_id' => $user_id,
            'record_id' => $record_id,
            'user_name' => $user_name,
            'student_id' => $student_id,
            'entry_time' => $entry_time_to_send,
            'exit_time' => $exit_time_to_send,
            'message' => ($action_status == 'ENTRY' ? '進場成功' : '出場成功')
        ]);
    } else {
        $mysqli->rollback();
        $mysqli->close();
        echo json_encode(['status' => 'ERROR', 'message' => '交易失敗，狀態未更新或無未出場紀錄']);
    }

} else {
    // 找不到卡號對應的用戶 (NOT_REGISTERED)

    // ✨ 修正：清空卡號檔案，避免卡號持續停留
    file_put_contents($txt_path, '');

    $mysqli->close();
    echo json_encode(['status' => 'NOT_REGISTERED']);
}
?>