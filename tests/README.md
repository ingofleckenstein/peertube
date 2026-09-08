# Regressionstests

Mit PHP 8.2+ und cURL aus dem Modulverzeichnis ausführen:

```sh
php tests/run.php
php tests/bootstrap-compat.php
```

Die Tests verwenden einen kontrollierbaren Cache und ersetzen API-Aufrufe sowie HumHub/Yii-Abhängigkeiten durch Testdoubles. Geprüft werden Cache-Treffer und Ablauf, Fehler-Wartezeit, konkurrierende Abrufe, leere und gefüllte Live-Beschreibungen, Passwortschutz, frische Metadatenprüfung, Bildadress- und Inhaltsgrenzen sowie Zugriff auf bereits gecachte Bilder. Zusätzlich prüfen sie die sichere WebVTT-Auswertung, Zeitmarken und die Ablehnung fremder Untertitelpfade. `bootstrap-compat.php` simuliert eine ältere HumHub-Modulkonfiguration und stellt sicher, dass Modul- und Eventklasse nur einmal deklariert werden. Es werden keine echten Netzwerkzugriffe oder Datenbankänderungen ausgeführt.

Vor Deployment zusätzlich auf HumHub CE 1.18.5 / PeerTube 8.2.4 prüfen: Livequelle ohne Beschreibung erzeugen; Vorschaubilder in Mediathek, Profil, Stream und geteilten Beiträgen laden; Zugriff als Gast und nach Rechteentzug ablehnen; Bild nach Bearbeitung erneuern; wiederholte Seitenaufrufe anhand der PeerTube-Zugriffslogs vergleichen. Ein neues Video öffnen, den Status „Transkript wird erstellt.“ prüfen, nach Abschluss das Aufklappfeld, Zeitmarken, Live-Hervorhebung und den Kopierknopf testen sowie einen nur im Transkript vorkommenden Begriff in der globalen HumHub-Suche finden. Bei PeerTube-Ausfall sollen vorhandene Cache-Bilder verfügbar bleiben und neue fehlgeschlagene Abrufe pro Schlüssel höchstens einmal je Minute erfolgen.
