# Ergebnis: Livestream-Fehlercodes (2.8.9)

Das aktuelle app.log meldet PeerTube HTTP 403 mit `max_user_lives_limit_reached`.
Dafür erscheint jetzt `PT-LIVE-403-USER-LIMIT` mit konkreter Erklärung und einer
Referenz zum bereinigten Logeintrag. Weitere HTTP-Fehler sowie unbekannte Ursachen
haben ebenfalls Fehlercodes. Das Startformular zeigt die geladenen Diagnoseversionen
und eine eigene Fehlerzusammenfassung.

Das technische PeerTube-Konto hat sein Livequellen-Limit erreicht. Die Administration
muss vorhandene Quellen und das Kontolimit prüfen. Das Update verändert das Limit
nicht und wurde nicht auf dem produktiven Server installiert.

Isolierte Regressionstests und LiveForm-Validierung prüfen die Änderung; ein echter
Livestream auf dem betroffenen Server bleibt nach Behebung des Limits abzunehmen.

Bei Limitfehlern führt ein Link zur PeerTube-Videoverwaltung (`/my-library/videos`,
gegen PeerTube 8.2.4 geprüft). Dort mit dem technischen Konto anmelden und
nicht mehr benötigte Livequellen über deren Menü entfernen. Der Link selbst
löscht nichts. Eine entfernte Quelle verliert ihre Streaming-Zugangsdaten.
Die Verwaltung des gemeinsamen technischen Kontos ist der Administration
vorbehalten; der Link überträgt keine Anmeldung oder Zugangsdaten.
