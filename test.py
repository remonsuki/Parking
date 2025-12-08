from smartcard.System import readers
from smartcard.util import toHexString
from smartcard.CardMonitoring import CardMonitor, CardObserver
from smartcard.CardType import AnyCardType

class CardListener(CardObserver):
    def update(self, observable, actions):
        (addedcards, removedcards) = actions
        
        for card in addedcards:
            print("卡片插入：", card.atr)
            print("ATR(hex)：", toHexString(card.atr))

        for card in removedcards:
            print("卡片移除")

monitor = CardMonitor()
observer = CardListener()
monitor.addObserver(observer)

print("等待卡片插入... 按 Ctrl+C 結束")

import time
while True:
    time.sleep(1)
