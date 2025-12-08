<?php
// 1. 移除背景執行與輸出重導向 (用於除錯)
$python_script_path = __DIR__ . "/read_card.py";
$command_debug = "python $python_script_path 2>&1"; // 捕捉標準輸出和錯誤

$output = [];
$return_var = 0;

// 執行命令，PHP 會等待 Python 結束
exec($command_debug, $output, $return_var);

// 2. 輸出除錯資訊到網頁
echo "<h2>Python 啟動除錯資訊 🚨</h2>";
echo "Command: " . htmlspecialchars($command_debug) . "<br>";
echo "Return Code (0=Success): " . $return_var . "<br>";
echo "Output:
<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";