# Ergebnis – 7. September 2026

- Modulstand 2.8.7: Mediathek-Vorschaubilder werden lokal im HumHub-Cache für 24 Stunden gespeichert und nur nach aktueller Zugriffsprüfung ausgeliefert.
- Metadaten zum Ergänzen fehlender Vorschaubildadressen werden fünf Minuten gecacht; Fehler bremsen Wiederholungen für 60 Sekunden. Gleichzeitige identische Abrufe auf einem Server werden gesperrt.
- Ursache des Livefehlers: PeerTube lehnt `description=""` beim Anlegen der Livequelle ab. Leere Beschreibungen werden jetzt weggelassen; Passwortschutz bleibt erhalten.
- 29 isolierte Regressionstests bestanden; PHP-Syntax geprüft. Keine Datenbankmigration erforderlich.
- Noch nicht auf einer laufenden Instanz installiert oder dort abgenommen. Dateien aktualisieren, HumHub-Cache leeren und Livequelle ohne Beschreibung sowie Vorschaubilder mit unterschiedlichen Berechtigungen prüfen.
- Die HTTP-429-Meldungen aus local-link-preview betreffen ein anderes Modul. Dessen Linkabrufe werden durch diesen Mediathek-Cache nicht verändert.
