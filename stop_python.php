<?php
// 1. 定義要清除內容的檔案路徑
$txt_path = "card_number.txt";

// 2. 清除 card_number.txt 檔案內容
// 使用 file_put_contents() 將一個空字串寫入檔案，這會覆蓋原有的內容。
if (file_put_contents($txt_path, "") !== FALSE) {
    // 雖然 sendBeacon 不會顯示輸出，但寫入日誌或返回訊息是個好習慣
    // error_log("卡號檔案內容已清除。"); 
} else {
    // error_log("錯誤：無法清除卡號檔案內容，請檢查權限。");
}

// 3. 處理 Python 腳本停止（清除 Flag 檔案）
// 這會告知背景執行的 Python 腳本（read_card.py）它應該結束或暫停讀卡循環。
$flag_file = "run.flag";
if (file_exists($flag_file)) {
    unlink($flag_file); // 刪除 flag，Python 會自動停止
}
?>