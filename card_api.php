<?php
$card_id = $_POST["card_id"] ?? "";

if ($card_id !== "") {
    file_put_contents("last_card.txt", $card_id);
}

echo "OK";
