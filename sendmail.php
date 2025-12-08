<?php
// 載入 PHPMailer 函式庫
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ------------------------------------------------------------------
// ★ 請確保使用絕對路徑載入 Composer 的 Autoloader，以避免在排程環境中找不到檔案
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
 * 1. 資料庫查詢函式：根據 user_id 取得 Email
 */
function get_user_email($user_id, $db_config)
{
    // ... (此函式保持不變)
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

        $query = "SELECT mail FROM user WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['mail'];
        } else {
            echo "找不到 user_id {$user_id} 的 email\n";
            return null;
        }

    } catch (Exception $e) {
        echo "資料庫錯誤: " . $e->getMessage() . "\n";
        return null;
    } finally {
        if (isset($stmt))
            $stmt->close();
        if (isset($conn))
            $conn->close();
    }
}

/**
 * 2. Email 寄送函式
 */
function send_violation_notification($recipient, $subject, $body, $smtp_config)
{
    // ... (此函式保持不變)
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

// 2. 取得 Email
$recipient_email = get_user_email($VIOLATING_USER_ID, $DB_CONFIG);

if ($recipient_email) {
    echo "✅ 成功取得違規使用者 (ID:{$VIOLATING_USER_ID}) 的 Email: {$recipient_email}\n";

    // 3. 定義信件內容
    $email_subject = "【最終警告】您的停車記錄已超限";
    $email_body = (
        "親愛的使用者 (ID: {$VIOLATING_USER_ID})：\n\n"
        . "我們通知您，您的機車停車時間已超過服務條款規定的三天限制。\n"
        . "為了避免進一步的處罰或鎖定帳號，請您立即回校區將機車移出。\n\n"
        . "此致，\n"
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
    echo "❌ 無法取得 Email，無法寄送通知信給 User ID: {$VIOLATING_USER_ID}。\n";
}
?>