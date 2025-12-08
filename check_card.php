<?php
// check_card.php
$txt_path = "card_number.txt";

if (file_exists($txt_path)) {
    // 讀取並直接輸出檔案內容給前端
    $card_id = trim(file_get_contents($txt_path));
    // 如果檔案內容為空，回傳一個友善的提示
    echo ($card_id === '') ? '無卡號' : $card_id;
} else {
    // 檔案不存在，表示尚未讀取或發生錯誤
    echo '讀取中';
}

// 確保沒有任何多餘的輸出（如空格或換行符）
exit; 
?>