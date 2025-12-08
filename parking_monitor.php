<?php
// 設置 PHP 執行時間無限 (適用於 CLI 環境)
set_time_limit(0);

// ★ 修正 1: 設定 HTTP Content-Type 為 HTML
header('Content-Type: text/html; charset=utf-8');
// ★ 修正 2: 開始 <pre> 標籤以保留換行和間距
echo "<pre>";

// 確保腳本使用正確的路徑執行
$SCRIPT_DIR = __DIR__;
$SENDMAIL_SCRIPT = $SCRIPT_DIR . '/sendmail.php';

// 設定違規的時間限制 (3 天，單位為秒)
// 3 天 * 24 小時 * 60 分鐘 * 60 秒 = 259200 秒
$VIOLATION_THRESHOLD_SECONDS = 180; // 測試用 180 秒

// 設定清理歷史記錄的門檻 (180 天)
$CLEANUP_THRESHOLD_DAYS = 180; 

// [DB 連線參數]
$DB_CONFIG = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '123456',
    'database' => 'parking_db'
];

// ------------------------------------------------------------------

/**
 * 0. ★ 新增功能：清理超過指定天數的歷史停車記錄
 */
function clean_up_old_records($db_config, $cleanup_days)
{
    $conn = null;
    try {
        $conn = mysqli_connect(
            $db_config['host'],
            $db_config['user'],
            $db_config['password'],
            $db_config['database']
        );

        if (mysqli_connect_errno()) {
            throw new Exception("資料庫連線錯誤: " . mysqli_connect_error());
        }

        // 計算截止日期：當前時間減去 $cleanup_days
        // 條件：exit_time 必須存在 (IS NOT NULL) 且 exit_time 小於 (N 天前)
        $query = "
            DELETE FROM park_record
            WHERE exit_time IS NOT NULL 
            AND exit_time < DATE_SUB(NOW(), INTERVAL ? DAY)
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $cleanup_days);
        $stmt->execute();
        
        $deleted_rows = $stmt->affected_rows;
        $stmt->close();
        
        echo date("Y-m-d H:i:s") . " [CLEANUP] 成功刪除 {$deleted_rows} 筆超過 {$cleanup_days} 天的歷史記錄。\n";
        
    } catch (Exception $e) {
        error_log(date("Y-m-d H:i:s") . " [ERROR] 清理歷史記錄失敗: " . $e->getMessage());
        echo date("Y-m-d H:i:s") . " [ERROR] 清理歷史記錄失敗，詳情請見伺服器錯誤日誌。\n";
    } finally {
        if ($conn)
            $conn->close();
    }
}


/**
 * 1. 連線資料庫並取得違規記錄 (包含 record_id 和 lot_id)
 */
function get_violating_users($db_config, $threshold_seconds)
{
    $violating_records = [];
    $conn = null;

    try {
        // 嘗試連線
        $conn = mysqli_connect(
            $db_config['host'],
            $db_config['user'],
            $db_config['password'],
            $db_config['database']
        );

        if (mysqli_connect_errno()) {
            throw new Exception("資料庫連線錯誤: " . mysqli_connect_error());
        }

        // 修正 SQL：使用聚合函數 MAX() 來解決 ONLY_FULL_GROUP_BY 錯誤
        $query = "
            SELECT 
                user_id, 
                MAX(record_id) AS record_id, 
                MAX(lot_id) AS lot_id 
            FROM park_record 
            WHERE exit_time IS NULL 
            AND TIMESTAMPDIFF(SECOND, entry_time, NOW()) > ?
            GROUP BY user_id
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $threshold_seconds);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $violating_records[] = [
                'user_id' => $row['user_id'],
                'record_id' => $row['record_id'],
                'lot_id' => $row['lot_id']
            ];
        }

        $stmt->close();

    } catch (Exception $e) {
        error_log(date("Y-m-d H:i:s") . " [ERROR] 資料庫操作失敗: " . $e->getMessage());
        echo date("Y-m-d H:i:s") . " [FATAL ERROR] 資料庫操作失敗，詳情請見伺服器錯誤日誌。\n";
        return [];
    } finally {
        if ($conn)
            $conn->close();
    }
    return $violating_records;
}

/**
 * 2. 處理違規資料：寫入 violation_record、更新 user.violation_count
 * 並回傳使用者最新的 violation_count。
 */
function process_violation_and_update_count($violation_data, $db_config)
{
    $conn = null;
    $user_id = $violation_data['user_id'];
    $record_id = $violation_data['record_id'];
    $lot_id = $violation_data['lot_id'];
    $violation_date = date("Y-m-d H:i:s");
    $new_violation_count = -1; 

    try {
        $conn = mysqli_connect(
            $db_config['host'],
            $db_config['user'],
            $db_config['password'],
            $db_config['database']
        );

        if (mysqli_connect_errno()) {
            throw new Exception("資料庫連線錯誤: " . mysqli_connect_error());
        }

        $conn->begin_transaction(); 

        // A. 寫入 violation_record 表格
        $query_insert = "
            INSERT INTO violation_record 
                (user_id, violation_date, lot_id, record_id) 
            VALUES 
                (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                violation_date = violation_date 
        ";
        $stmt_insert = $conn->prepare($query_insert);
        $stmt_insert->bind_param("isii", $user_id, $violation_date, $lot_id, $record_id);
        $stmt_insert->execute();

        // B. 更新 user.violation_count +1 
        $query_update = "
            UPDATE user 
            SET violation_count = violation_count + 1
            WHERE user_id = ?
        ";
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->bind_param("i", $user_id);
        $stmt_update->execute();

        // C. 讀取更新後的值
        $query_select = "
            SELECT violation_count 
            FROM user 
            WHERE user_id = ?
        ";
        $stmt_select = $conn->prepare($query_select);
        $stmt_select->bind_param("i", $user_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();

        if ($row = $result->fetch_assoc()) {
            $new_violation_count = (int) $row['violation_count'];
        }

        $conn->commit();
        echo date("Y-m-d H:i:s") . " [SUCCESS] DB 更新成功：User ID {$user_id} 違規記錄已建立，計數為 {$new_violation_count}。\n";
        return $new_violation_count;

    } catch (Exception $e) {
        if ($conn) {
            $conn->rollback();
        }
        error_log(date("Y-m-d H:i:s") . " [ERROR] DB 處理違規失敗 (User ID: {$user_id}): " . $e->getMessage());
        echo date("Y-m-d H:i:s") . " [ERROR] DB 處理失敗 (User ID: {$user_id})，詳情請見伺服器錯誤日誌。\n";
        return -1; 

    } finally {
        if (isset($stmt_insert)) $stmt_insert->close();
        if (isset($stmt_update)) $stmt_update->close();
        if (isset($stmt_select)) $stmt_select->close();
        if ($conn) $conn->close();
    }
}


/**
 * 3. 執行 sendmail.php 腳本 (確保背景執行且輸出到日誌)
 */
function execute_sendmail_script($user_id, $sendmail_path)
{
    // ... (此函式保持不變)
    $php_cli_path = 'php';
    $log_path = __DIR__ . "/monitor_log.txt";
    $command = "\"$php_cli_path\" \"$sendmail_path\" \"$user_id\" >> \"$log_path\" 2>&1 &";

    echo date("Y-m-d H:i:s") . " [INFO] 執行命令: " . $command . "\n";
    shell_exec($command);
}

// ------------------------------------------------------------------

// --- 主執行區塊 ---
echo date("Y-m-d H:i:s") . " [INFO] 開始執行停車記錄監控...\n";

// A. ★ 步驟 1: 清理歷史記錄 (放在最前面，減少資料庫負擔)
clean_up_old_records($DB_CONFIG, $CLEANUP_THRESHOLD_DAYS);
echo date("Y-m-d H:i:s") . " [INFO] --- 歷史記錄清理完成 ---\n";


// B. 步驟 2: 處理違規記錄
echo date("Y-m-d H:i:s") . " [INFO] 開始檢測超時停車記錄...\n";
$violating_records = get_violating_users($DB_CONFIG, $VIOLATION_THRESHOLD_SECONDS);

if (!empty($violating_records)) {
    echo date("Y-m-d H:i:s") . " [WARNING] 偵測到 " . count($violating_records) . " 筆違規記錄需要處理！\n";

    // 針對每筆違規記錄進行資料庫處理和寄信
    foreach ($violating_records as $record) {
        $user_id = $record['user_id'];

        // 處理資料庫 (寫入記錄並增加計數)，並獲取新的違規次數
        $new_violation_count = process_violation_and_update_count($record, $DB_CONFIG);

        // 判斷是否寄信：只有在 violation_count 為 1 時才寄信
        if ($new_violation_count === 1) {
            echo date("Y-m-d H:i:s") . " [ACTION] User ID: {$user_id} 首次違規，正在執行 sendmail.php...\n";
            execute_sendmail_script($user_id, $SENDMAIL_SCRIPT);
        } else if ($new_violation_count > 1) {
            echo date("Y-m-d H:i:s") . " [INFO] User ID: {$user_id} (計數: {$new_violation_count}) 已收到過通知，跳過寄信。\n";
        } else {
            echo date("Y-m-d H:i:s") . " [ERROR] User ID: {$user_id} 資料庫處理失敗，跳過寄信。\n";
        }
    }

} else {
    echo date("Y-m-d H:i:s") . " [INFO] 無違規記錄。\n";
}

echo date("Y-m-d H:i:s") . " [INFO] 監控執行結束。\n";

// 結束 <pre> 標籤
echo "</pre>";
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>手動檢測違規</title>
</head>
</html>