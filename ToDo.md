# Ergebnis – Livestream-Korrektur 2.8.8

Die Korrektur 2.8.7 war bereits auf GitHub (5b59850). Das neue Log zeigt andere Fehler: Beschreibung „d“ ist zu kurz; später ist das Live-Limit des technischen PeerTube-Kontos erreicht.

2.8.8 validiert Live-Beschreibungen als leer oder 3–10.000 Zeichen und erklärt die Live-Limits im Formular. Regressionstests sowie zusätzliche Prüfungen mit dem echten Yii-Validator sind vorhanden. Das erreichte PeerTube-Limit muss administrativ auf PeerTube geprüft werden; eine lokale Codeänderung hebt es nicht auf. Keine Livequelle wurde gelöscht und kein Serverlimit geändert.
