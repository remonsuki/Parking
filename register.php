<?php
// 1. 取得腳本的絕對路徑
$python_script = __DIR__ . "/read_card.py";
$flag_file = __DIR__ . "/run.flag"; // **確保 flag 檔案也使用絕對路徑**

// 2. 構建命令
// 使用 start /B 確保在 Windows 上建立一個獨立的背景進程
// 使用 cmd /c 來確保命令被完整執行
// 確保 NUL (或 /dev/null) 和 2>&1 之間有空格
$command = "start /B python \"$python_script\" > NUL 2>&1";

// 3. 執行命令
// 使用 pclose(popen(...)) 是在 PHP 中實現 Windows/Linux 跨平台背景執行的最可靠方法之一。
// 它能確保 PHP 不會等待子進程。
pclose(popen($command, 'r'));


// 4. 建立 flag 檔案 (也使用絕對路徑)
file_put_contents($flag_file, "1");

// 預設停車場代號
$LOT_ID = 1;

// 自動讀取 card_number.txt 的內容 (用於 PHP 首次載入時的初始化值)
$card_id = "";
$txt_path = "card_number.txt";

if (file_exists($txt_path)) {
    $card_id = trim(file_get_contents($txt_path));
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>💳 用戶註冊 - 停車區管理系統</title>
    <style>
        /* ================================================= */
        /* --- 全域樣式與佈局 --- */
        /* ================================================= */
        body {
            font-family: 'Noto Sans TC', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background: linear-gradient(135deg, #e6f3ff 0%, #cceeff 100%);
            /* 淺藍色漸變背景 */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #2c3e50;
            padding: 20px;
        }

        /* --- 容器 (Container) --- */
        .container {
            width: 100%;
            max-width: 480px;
            /* 寬度略為增加 */
            padding: 40px;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            /* 更立體的陰影 */
            transition: transform 0.3s ease-in-out;
        }

        .container:hover {
            transform: translateY(-3px);
        }

        /* --- 標題 (Heading) --- */
        h2 {
            text-align: center;
            color: #007bff;
            margin-bottom: 30px;
            font-size: 2em;
            font-weight: 700;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
            display: inline-block;
            margin-left: auto;
            margin-right: auto;
        }

        /* ================================================= */
        /* --- 表單元素 --- */
        /* ================================================= */
        label {
            display: block;
            margin-top: 20px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #34495e;
            /* 深藍灰色文字 */
        }

        input[type=text],
        input[type=password],
        input[type=email],
        select {
            width: 100%;
            padding: 14px 18px;
            margin: 5px 0 15px 0;
            font-size: 17px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            box-sizing: border-box;
            background-color: #f8f9fa;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
            background-color: white;
            outline: none;
        }

        /* 檔案上傳樣式優化 */
        input[type=file] {
            width: 100%;
            padding: 10px 0;
            margin: 5px 0 15px 0;
            font-size: 16px;
            border: none;
            background-color: transparent;
        }

        /* --- 提交按鈕 --- */
        input[type=submit] {
            width: 100%;
            padding: 15px;
            margin-top: 30px;
            background: #28a745;
            /* 註冊使用綠色，強調成功和開始 */
            color: white;
            border: none;
            border-radius: 50px;
            /* 膠囊形狀 */
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            transition: background-color 0.3s, transform 0.2s, box-shadow 0.3s;
        }

        input[type=submit]:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.5);
        }

        /* ================================================= */
        /* --- 讀卡區專屬樣式 --- */
        /* ================================================= */
        .card-reader-section {
            margin-top: 25px;
            padding: 20px;
            background-color: #f8f9fa;
            /* 淺灰色背景分隔 */
            border: 1px solid #dee2e6;
            border-radius: 10px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        /* 唯讀顯示輸入框的樣式 */
        #card_display {
            background-color: #e9ecef;
            /* 初始灰色背景 */
            color: #6c757d;
            /* 初始文字顏色 */
            font-size: 1.2em;
            font-weight: bold;
            text-align: center;
            letter-spacing: 1px;
            border: 2px dashed #007bff;
            /* 藍色虛線強調 */
        }

        /* JavaScript 成功讀卡時會設定的樣式 */
        .card-success {
            background-color: #d4edda !important;
            border-color: #28a745 !important;
            color: #155724 !important;
        }

        .note {
            color: #007bff;
            font-size: 14px;
            text-align: center;
            margin-top: 10px;
            font-style: italic;
        }

        /* --- 回首頁連結 --- */
        .back-link {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
            padding: 8px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .back-link:hover {
            background-color: #f0f8ff;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>💳 使用者註冊</h2>

        <form action="register_process.php" method="POST" enctype="multipart/form-data">
            <label for="name">👤 姓名:</label>
            <input type="text" id="name" name="name" required placeholder="請輸入您的姓名">

            <label for="student_id">🎓 學號/帳號:</label>
            <input type="text" id="student_id" name="student_id" required placeholder="請輸入您的學號">

            <label for="password">🔑 密碼:</label>
            <input type="password" id="password" name="password" required placeholder="請設定登入密碼">

            <label for="mail">📧 信箱:</label>
            <input type="email" id="mail" name="mail" required placeholder="請輸入電子郵件信箱">

            <label for="plate_id">🏍️ 車牌號碼:</label>
            <input type="text" id="plate_id" name="plate_id" required placeholder="例如: ABC-1234">

            <label for="photo">📸 機車照片:</label>
            <input type="file" id="photo" name="photo" accept="image/*" required>

            <hr style="margin: 25px 0; border-top: 1px solid #e0e0e0;">

            <div class="card-reader-section">
                <label>💳 卡號綁定區 (請將卡片放上讀卡機)：</label>

                <input type="text" name="card_display" id="card_display" value="等待卡號讀取..." readonly placeholder="等待卡號讀取...">

                <input type="hidden" name="card_id" id="card_id_hidden" value="<?php echo htmlspecialchars($card_id); ?>">

                <p class="note">＊讀取成功後，卡號將自動填入並鎖定，請直接提交表單。</p>
            </div>

            <div id="debug_card_id" style="display:none; text-align: right; color: #adb5bd; font-size: 0.8em; margin-top: 5px;"></div>

            <input type="submit" value="✅ 確認註冊">
        </form>

        <a href="index.html" class="back-link">← 回首頁</a>
    </div>
</body>


<script>
    // 檢查卡號的函式
    function checkCardID() {
        const cardDisplayInput = document.getElementById('card_display');
        const cardIDHiddenInput = document.getElementById('card_id_hidden');
        const debugDisplay = document.getElementById('debug_card_id'); // 偵錯顯示 (維持原樣)

        // 1. 發送 AJAX 請求給伺服器 (使用 check_card.php)
        fetch('check_card.php')
            .then(response => response.text())
            .then(data => {
                const cardID = data.trim();
                const hiddenValue = cardIDHiddenInput.value.trim();

                // 2. 判斷是否取得有效卡號
                // 這裡的邏輯需要確保只有實際卡號才算成功，並避免重複設定
                if (cardID && cardID !== '讀取中' && cardID !== '無卡號' && cardID !== '0') {

                    // *** 讀取到新卡號時 (或第一次讀取到卡號時) ***
                    if (cardID !== hiddenValue) {

                        // A. 更新隱藏欄位：儲存實際卡號，用於提交給後端
                        cardIDHiddenInput.value = cardID;

                        // B. 更新顯示欄位：顯示友善提示和成功樣式
                        cardDisplayInput.value = `讀取成功！`; // 僅顯示部分卡號
                        cardDisplayInput.classList.add('card-success'); // 添加成功樣式

                        console.log("已讀取到卡號：" + cardID);
                        debugDisplay.textContent = 'Last Read: ' + cardID;
                    }

                } else {
                    // *** 讀取中/無卡號/初始化狀態 ***

                    // 如果目前顯示的是成功狀態，但現在卡片被移走了，則恢復狀態
                    if (cardDisplayInput.classList.contains('card-success')) {
                        cardDisplayInput.value = '等待卡號讀取...';
                        cardIDHiddenInput.value = ''; // 清空隱藏欄位
                        cardDisplayInput.classList.remove('card-success'); // 移除成功樣式
                        debugDisplay.textContent = 'Last Read: Removed';
                    } else if (cardIDDisplay.value !== '等待卡號讀取...') {
                        // 確保在沒有卡片時，顯示等待狀態
                        cardDisplayInput.value = '等待卡號讀取...';
                    }

                    debugDisplay.textContent = cardID === '讀取中' ? '讀取中' : '未讀取';
                }
            })
            .catch(error => console.error('Error fetching card ID:', error));
    }

    // 設定定時器：每 1000 毫秒 (1 秒) 檢查一次卡號
    const intervalID = setInterval(checkCardID, 1000);

    // 當頁面關閉時，清除定時器
    window.addEventListener("beforeunload", function () {
        clearInterval(intervalID);
        // 透過 sendBeacon 通知 PHP 刪除 flag
        navigator.sendBeacon("stop_python.php");
    });
</script>


</html>