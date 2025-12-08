<?php
// 載入 PHPMailer 函式庫
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ------------------------------------------------------------------
require __DIR__ . '/vendor/autoload.php';
// ------------------------------------------------------------------

// --- 設定與參數 ---
// [Email 發送參數] 
$SENDER_EMAIL = "frank940626@gmail.com";
$SMTP_PASSWORD = "fuyw tjvn ywih xgal";
$SMTP_SERVER = "smtp.gmail.com";
$SMTP_PORT = 587;

// [DB 連線參數]
$DB_CONFIG = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '123456',
    'database' => 'parking_db'
];

// ------------------------------------------------------------------

/**
 * 1. 資料庫查詢函式：根據 user_id 取得 Email、名稱和最近的 lot_id
 *
 * ★ 假設 user 表格中包含 'name' 欄位。
 * ★ 查詢 park_record 取得該使用者目前未離場 (exit_time IS NULL) 的最新停車場 ID (lot_id)。
 */
function get_user_violation_details($user_id, $db_config)
{
    $conn = null;
    $details = null;

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

        // 結合查詢：取得使用者的 mail, name (從 user 表)，以及最新的 lot_id (從 park_record 表)
        $query = "
            SELECT 
                u.mail, 
                u.name, 
                MAX(pr.lot_id) AS lot_id 
            FROM user u
            LEFT JOIN park_record pr ON u.user_id = pr.user_id AND pr.exit_time IS NULL
            WHERE u.user_id = ?
            GROUP BY u.user_id, u.mail, u.name 
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // 確保 lot_id 有值，如果該使用者有多筆未離場紀錄，MAX(lot_id) 會選最大的。
            // 由於 parking_monitor 也是使用 MAX(lot_id)，這裡保持一致性。
            if (empty($row['lot_id'])) {
                echo "❌ 警告: 找不到 User ID {$user_id} 未離場的 lot_id，使用預設值。\n";
                $lot_id = "未知";
            } else {
                $lot_id = $row['lot_id'];
            }

            $details = [
                'mail' => $row['mail'],
                'name' => $row['name'] ?? "使用者", // 如果 name 為 NULL，使用 "使用者"
                'lot_id' => $lot_id
            ];

        } else {
            echo "找不到 user_id {$user_id} 的詳細資訊\n";
        }

    } catch (Exception $e) {
        echo "資料庫錯誤: " . $e->getMessage() . "\n";
    } finally {
        if (isset($stmt))
            $stmt->close();
        if (isset($conn))
            $conn->close();
    }
    return $details;
}

/**
 * 2. Email 寄送函式
 */
function send_violation_notification($recipient, $subject, $body, $smtp_config)
{
    if (!$recipient) {
        return;
    }

    $mail = new PHPMailer(true);

    try {
        // SMTP 配置
        $mail->isSMTP();
        $mail->Host = $smtp_config['server'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['sender_email'];
        $mail->Password = $smtp_config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_config['port'];

        // 收件人
        $mail->setFrom($smtp_config['sender_email']);
        $mail->addAddress($recipient);

        // 內容
        $mail->isHTML(false);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        echo "✅ 郵件成功寄出到 {$recipient}。\n";

    } catch (Exception $e) {
        echo "❌ 寄送郵件失敗: Mailer Error: {$mail->ErrorInfo}\n";
    }
}

// ------------------------------------------------------------------

// --- 主執行區塊 ---

// 檢查 $argv[1] 是否存在 (這是傳遞的 user_id 參數)
if (!isset($argv[1])) {
    echo "❌ 錯誤：請提供 User ID 作為命令列參數。\n";
    exit(1);
}

// 1. 取得命令列傳入的 User ID
$VIOLATING_USER_ID = (int) $argv[1];

// 2. 取得詳細資訊 (包含 email, name, lot_id)
$details = get_user_violation_details($VIOLATING_USER_ID, $DB_CONFIG);

if ($details && $details['mail']) {
    $recipient_email = $details['mail'];
    $user_name = $details['name'];
    $lot_id = $details['lot_id'];

    echo "✅ 成功取得違規使用者 (ID:{$VIOLATING_USER_ID}) 的詳細資訊，寄件 Email: {$recipient_email}\n";

    // 3. 定義信件內容
    $email_subject = "【提醒】停車超時"; // ★ 主旨修改
    $email_body = (
        "親愛的{$user_name}同學\n\n" // ★ 內文包含使用者名稱
        . "雲科大停車系統通知您，您的機車在第{$lot_id}停車場裡停車時間已超過服務條款規定的三天限制。\n" // ★ 內文包含 lot_id
        . "為了避免進一步的處罰或鎖定帳號，請您立即回校區將機車移出。\n\n"
        . "此致\n"
        . "系統管理團隊"
    );

    // 4. 呼叫 Email 寄送函式
    $SMTP_CONFIG = [
        'server' => $SMTP_SERVER,
        'sender_email' => $SENDER_EMAIL,
        'password' => $SMTP_PASSWORD,
        'port' => $SMTP_PORT
    ];

    send_violation_notification($recipient_email, $email_subject, $email_body, $SMTP_CONFIG);

} else {
    echo "❌ 無法取得 Email 或詳細資訊，無法寄送通知信給 User ID: {$VIOLATING_USER_ID}。\n";
}
?>