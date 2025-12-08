from smartcard.Exceptions import CardConnectionException
from smartcard.CardMonitoring import CardMonitor, CardObserver
from smartcard.util import toHexString, toBytes
from smartcard.System import readers
import os
import time
import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

# ★ 讀卡號的 APDU（你提供的）
SelectAPDU = [0x00, 0xA4, 0x04, 0x00, 0x10, 0xD1, 0x58, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x11, 0x00]
ReadProfileAPDU = [0x00, 0xca, 0x11, 0x00, 0x02, 0x00, 0x00]


class CardListener(CardObserver):
    def update(self, observable, actions):
        added, removed = actions

        # 卡片插入
        for card in added:
            print("卡片插入")
            atr_hex = toHexString(card.atr)
            print("ATR(hex)：", atr_hex)

            try:
                # 連線讀卡機
                r = readers()[0]
                connection = r.createConnection()
                connection.connect()

                # ★ 發送 APDU，讀取卡號
                data, sw1, sw2 = connection.transmit(SelectAPDU)
                data, sw1, sw2 = connection.transmit(ReadProfileAPDU)

                # 將 byte 陣列轉成字串（例如：P124563785）
                uid = ''.join(chr(i) for i in data[36:40])
                print("CARDID :", uid)

                # ★ 寫入 txt（你可以選擇寫卡號或四碼）
                txt_path = r"card_number.txt"
                with open(txt_path, "w", encoding="utf-8") as f:
                    f.write(uid)

                print("卡號已寫入 card_number.txt")

            except CardConnectionException:
                print("無法連線到讀卡機")

        # 卡片移除
        for card in removed:
            print("卡片移除")


monitor = CardMonitor()
observer = CardListener()
monitor.addObserver(observer)

print("等待卡片插入... 按 Ctrl+C 結束")

# while True:
#     time.sleep(1)


FLAG_FILE_PATH = r"run.flag"
print("等待 run.flag 存在...")
# 修改檢查條件，使用絕對路徑
while os.path.exists(FLAG_FILE_PATH):
    time.sleep(1)  # 每秒檢查一次 flag 檔案
