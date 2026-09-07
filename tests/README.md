# Regressionstests

Mit PHP 8.2+ und cURL aus dem Modulverzeichnis ausführen:

```sh
php tests/run.php
```

Die Tests verwenden einen kontrollierbaren Cache und ersetzen API-Aufrufe sowie HumHub/Yii-Abhängigkeiten durch Testdoubles. Geprüft werden Cache-Treffer und Ablauf, Fehler-Wartezeit, konkurrierende Abrufe, leere und gefüllte Live-Beschreibungen, Passwortschutz, frische Metadatenprüfung, Bildadress- und Inhaltsgrenzen sowie Zugriff auf bereits gecachte Bilder. Es werden keine echten Netzwerkzugriffe oder Datenbankänderungen ausgeführt.

Vor Deployment zusätzlich auf HumHub CE 1.18.5 / PeerTube 8.2.4 prüfen: Livequelle ohne Beschreibung erzeugen; Vorschaubilder in Mediathek, Profil, Stream und geteilten Beiträgen laden; Zugriff als Gast und nach Rechteentzug ablehnen; Bild nach Bearbeitung erneuern; wiederholte Seitenaufrufe anhand der PeerTube-Zugriffslogs vergleichen. Bei PeerTube-Ausfall sollen vorhandene Cache-Bilder verfügbar bleiben und neue fehlgeschlagene Abrufe pro Schlüssel höchstens einmal je Minute erfolgen.
