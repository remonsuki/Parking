<?php
// 設置資料庫連線參數
$host = "localhost";
$user = "root";
$password = "123456";
$db_name = "parking_db";

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
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>🚗 停車場進出管理系統</title>
    <style>
        /* ================================================= */
        /* BASE STYLES */
        /* ================================================= */
        body {
            font-family: 'Noto Sans TC', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #e9ecef;
            /* 柔和淺灰色背景 */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            flex-direction: column;
            margin: 0;
            padding: 20px;
        }

        /* ================================================= */
        /* CARD CONTAINER */
        /* ================================================= */
        .card {
            background: linear-gradient(145deg, #ffffff, #f0f0f0);
            padding: 45px;
            border-radius: 15px;
            /* 圓角更大 */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.6) inset;
            /* 立體感強的陰影 */
            text-align: center;
            width: 450px;
            /* 寬度略微增加 */
            max-width: 90%;
            transition: transform 0.3s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        h2 {
            color: #007bff;
            /* 主題藍色 */
            margin-bottom: 25px;
            font-weight: 700;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
            display: inline-block;
        }

        /* ================================================= */
        /* SELECT & CAPACITY */
        /* ================================================= */
        #lot_selector {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            font-size: 16px;
            margin-bottom: 25px;
            appearance: none;
            /* 隱藏原生箭頭 */
            background-color: #f8f9fa;
            transition: border-color 0.3s;
        }

        #lot_selector:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }

        #capacity-display {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: left;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            border: 1px solid #e2e6ea;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        /* ================================================= */
        /* STATUS DISPLAY */
        /* ================================================= */
        #status-display {
            font-size: 26px;
            font-weight: bold;
            padding: 30px 20px;
            border: 2px solid #ddd;
            border-radius: 10px;
            margin-top: 20px;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            box-sizing: border-box;
            transition: all 0.4s ease-in-out;
        }

        .waiting {
            color: #6c757d;
            border-color: #ffc107;
            background-color: #fffbe6;
            animation: pulse-waiting 2s infinite;
        }

        /* 視覺優化：等待狀態動畫 */
        @keyframes pulse-waiting {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4);
            }

            70% {
                box-shadow: 0 0 0 15px rgba(255, 193, 7, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
            }
        }

        .success {
            color: white;
            background-color: #28a745;
            border-color: #28a745;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
        }

        .error {
            color: white;
            background-color: #dc3545;
            border-color: #dc3545;
            box-shadow: 0 0 20px rgba(220, 53, 69, 0.5);
        }

        /* 其他提示文字 */
        .note {
            margin-top: 25px;
            color: #adb5bd;
            font-size: 0.9em;
            font-style: italic;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>🅿️ 停車感應區</h2>

        <label for="lot_selector" style="display: block; text-align: left; margin-bottom: 5px; font-weight: bold; color: #495057;">請選擇停車場代號：</label>
        <select id="lot_selector">
            <option value="1">1</option>
            <option value="2">2</option>
        </select>

        <div id="capacity-display">
            車位資訊：<span id="remaining-count">...</span> / <span id="total-count">...</span>
        </div>
        <div id="status-display" class="waiting">
            請將卡片放置於讀卡機上...
        </div>

        <p class="note">系統正在等待讀卡機的感應信號...</p>
    </div>

    <script>
        const statusDisplay = document.getElementById('status-display');
        const lotSelector = document.getElementById('lot_selector');
        const remainingCount = document.getElementById('remaining-count');
        const totalCount = document.getElementById('total-count');
        let cardCheckIntervalID; // 檢查卡片定時器
        let capacityUpdateIntervalID; // 容量更新定時器

        // --- 獨立函式：查詢並更新車位數量 ---
        function updateCapacityDisplay(lotId) {
            fetch('check_card_realtime.php?lot_id=' + lotId + '&action=check_capacity')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'CAPACITY_INFO' && data.total_capacity !== undefined) {
                        const remaining = data.total_capacity - (data.occupied_count || 0);

                        // 根據剩餘數量設定顏色和文字
                        remainingCount.textContent = remaining;
                        totalCount.textContent = data.total_capacity;

                        if (remaining <= 5 && remaining > 0) {
                            // 車位緊張，顯示橘色
                            remainingCount.style.color = '#ffc107';
                        } else if (remaining <= 0) {
                            // 車位已滿，顯示紅色
                            remainingCount.style.color = '#dc3545';
                        } else {
                            // 正常情況，顯示綠色
                            remainingCount.style.color = '#28a745';
                        }
                    } else if (data.status === 'ERROR' || data.status === 'NOT_FOUND') {
                        remainingCount.textContent = `載入錯誤: ${data.message || '找不到停車場資料'}`;
                        totalCount.textContent = 'N/A';
                        remainingCount.style.color = '#dc3545';
                    }
                })
                .catch(error => {
                    console.error('Capacity Load Error:', error);
                    remainingCount.textContent = '連線失敗';
                    totalCount.textContent = 'N/A';
                    remainingCount.style.color = '#dc3545';
                });
        }
        // --- 獨立函式結束 ---


        function checkCardAndProcess() {
            const lotId = lotSelector.value;

            fetch('check_card_realtime.php?lot_id=' + lotId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {



                    if (data.status === 'ENTRY' || data.status === 'EXIT') {

                        clearInterval(cardCheckIntervalID);
                        clearInterval(capacityUpdateIntervalID);

                        const actionMessage = data.status === 'ENTRY' ? '進場成功！' : '出場成功！';
                        statusDisplay.textContent = `✅ ${actionMessage} 正在跳轉...`;
                        statusDisplay.className = 'success';

                        let redirectURL = `park_display.php?status=${data.status}&user_id=${data.user_id}`;
                        if (data.student_id) { redirectURL += `&student_id=${data.student_id}`; }
                        if (data.user_name) { redirectURL += `&name=${encodeURIComponent(data.user_name)}`; } else { redirectURL += `&name=N/A`; }
                        if (data.record_id) { redirectURL += `&record_id=${data.record_id}`; }
                        if (data.entry_time) { redirectURL += `&entry_time=${data.entry_time}`; }
                        if (data.status === 'EXIT' && data.exit_time) { redirectURL += `&exit_time=${data.exit_time}`; }

                        setTimeout(() => {
                            window.location.href = redirectURL;
                        }, 500);    //顯示時間

                    } else if (data.status === 'NO_CARD') {
                        statusDisplay.textContent = "請將卡片放置於讀卡機上...";
                        statusDisplay.className = 'waiting';

                    } else if (data.status === 'NOT_REGISTERED') {
                        statusDisplay.textContent = "❌ 錯誤：卡號未註冊！";
                        statusDisplay.className = 'error';
                        // 後端已處理清空 card_number.txt 的動作
                        setTimeout(() => {
                            statusDisplay.textContent = "請將卡片放置於讀卡機上...";
                            statusDisplay.className = 'waiting';
                        }, 5000);

                    } else if (data.status === 'FULL') {
                        statusDisplay.textContent = `❌ 停車場已滿！(${data.message})`;
                        statusDisplay.className = 'error';
                        setTimeout(() => {
                            statusDisplay.textContent = "請將卡片放置於讀卡機上...";
                            statusDisplay.className = 'waiting';
                        }, 5000);
                    } else if (data.status === 'ERROR') {
                        statusDisplay.textContent = `⚠️ 系統錯誤：${data.message || '處理失敗'}。`;
                        statusDisplay.className = 'error';
                        setTimeout(() => {
                            statusDisplay.textContent = "請將卡片放置於讀卡機上...";
                            statusDisplay.className = 'waiting';
                        }, 5000);
                    }
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    statusDisplay.textContent = `⚠️ 連線或資料處理錯誤：${error.message || '請檢查主機連線。'}`;
                    statusDisplay.className = 'error';
                    setTimeout(() => {
                        statusDisplay.textContent = "請將卡片放置於讀卡機上...";
                        statusDisplay.className = 'waiting';
                    }, 5000);
                });
        }

        // 1. 頁面載入時，立即載入車位資訊
        updateCapacityDisplay(lotSelector.value);

        // 2. 啟動定時器：卡片檢查 (每 1000ms)
        cardCheckIntervalID = setInterval(checkCardAndProcess, 1000);

        // 3. 啟動定時器：容量更新 (每 5000ms 更新一次容量即可)
        capacityUpdateIntervalID = setInterval(() => {
            updateCapacityDisplay(lotSelector.value);
        }, 5000);

        // 4. 監聽下拉選單變更事件
        lotSelector.addEventListener('change', () => {
            updateCapacityDisplay(lotSelector.value);
            statusDisplay.textContent = "已切換停車場，請將卡片放置於讀卡機上...";
            statusDisplay.className = 'waiting';
        });

        // 5. 頁面關閉時，清除所有定時器
        window.addEventListener("beforeunload", function () {
            clearInterval(cardCheckIntervalID);
            clearInterval(capacityUpdateIntervalID);
            navigator.sendBeacon("stop_python.php");
        });
    </script>
</body>

</html>