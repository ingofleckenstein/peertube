# Community-Mediathek: PeerTube-Modul für HumHub

**Das Repository `peertube` ist ein HumHub-Modul, kein PeerTube-Server-Plugin.** Der Modulordner gehört nach `protected/modules/peertube`. Es verbindet die HumHub-Community mit einem separat betriebenen PeerTube-Server: Video- und Audio-Uploads, Mediathek mit Ordnern und Themen, Stream-Beiträge, Berechtigungen, Inhaltswarnungen sowie OBS-Livestreams und die Übernahme fertiger Aufzeichnungen. Kommentare und Reaktionen bleiben bei der Umwandlung eines Livebeitrags erhalten.

Das Gegenstück [peertube-plugin](https://github.com/ingofleckenstein/peertube-plugin) wird **auf PeerTube** installiert. Es ergänzt HumHub-Permalinks im Embed-Menü und die abgesicherte Direktupload-Brücke.

## Referenzumgebung und Versionen

Stand: 7. September 2026. HumHub **Community Edition 1.18.5** ist in der Betriebsdokumentation und im lokalen Core bestätigt. PeerTube **8.2.4** wurde am selben Tag über die öffentliche Server-API `/api/v1/config` der bestehenden Installation geprüft. Diese Angaben beschreiben die Referenzumgebung, keine vollständige Kompatibilitätsprüfung sämtlicher Funktionen.

| Bestandteil | Stand / Voraussetzung |
| --- | --- |
| Dieses HumHub-Modul | **2.10.1** laut `module.json` |
| HumHub | Referenz **CE 1.18.5**; Metadatenminimum **1.18.3** |
| PeerTube-Server | Referenz **8.2.4**; bisherige Entwicklung auf 8.2.x ausgerichtet |
| PeerTube-Begleitplugin | **1.2.1**, für Direktupload auf PeerTube installieren |

## Version 2.10.1: Bootstrap-Kompatibilität nach Namespace-Update

Beim Upgrade von 2.9.x auf 2.10.0 konnte HumHubs Modul-Erkennung noch eine zwischengespeicherte ältere Konfiguration verwenden. Dadurch wurde die aktuelle Event-Datei in einzelnen Prozessen über zwei Klassennamen eingebunden. Die Bootstrap-Klassen sind nun gegen eine zweite Einbindung geschützt und stellen für einen solchen Alt-Cache einmalig einen kompatiblen Klassenalias bereit. Der GitHub-Modulmanager registriert bei einem Update ein bereits geladenes Modul nicht mehr erneut im selben Request.

Es werden keine Tabellen, Medien, Live-Sitzungen, Einstellungen oder Zuordnungen verändert. Nach dem Deployment kann `php protected/yii cache/flush-all` sicher ausgeführt werden; danach verwenden neue Requests ausschließlich die aktuelle Konfiguration.

## Version 2.9.0: Transkript, Zeitmarken und Suche

Beim ersten berechtigten Laden eines Videos fordert HumHub bei PeerTube eine automatische Transkription an. Bis PeerTube die zeitcodierten WebVTT-Untertitel bereitstellt, steht direkt unter dem Player **„Transkript wird erstellt.“**. Anschließend klappt dort eine schmale Transkriptansicht mit anklickbaren Zeitmarken auf; der aktuell gesprochene Abschnitt wird bei der Wiedergabe hervorgehoben und das vollständige Transkript kann in die Zwischenablage kopiert werden.

Die Untertitel werden ausschließlich nach der bestehenden Medienberechtigungsprüfung von PeerTube abgerufen und lokal gespeichert. Titel, Beschreibung, Themen und der unsichtbare Transkripttext werden über HumHubs `ContentSearchService` in den normalen Content-Index aufgenommen. Suchtreffer bleiben deshalb die jeweiligen Video-Posts und unterliegen weiterhin den nativen HumHub-Rechte-, Space- und Autorfiltern. Die Migration `m260907_000017_transcript` ist erforderlich. PeerTube muss die automatische Transkription aktiviert haben; manuell hinterlegte WebVTT-Untertitel werden ebenfalls übernommen.

## Version 2.8.10: Wiederherstellung fehlender Livequellen

Wenn die dauerhafte PeerTube-Livequelle eines Mitglieds auf PeerTube nicht mehr existiert, wird sie beim nächsten Livestream-Start neu angelegt. Dadurch werden eine veraltete lokale Stream-URL und ein veralteter Schlüssel nicht weiterverwendet. Eine bereits davon betroffene HumHub-Sitzung wird bei PeerTube-HTTP-404 sofort als fehlgeschlagen markiert und blockiert keinen neuen Start. Die alte PeerTube-Quelle wird nicht gelöscht; sie wurde vom Server bereits nicht mehr gefunden.

## Version 2.8.8: Beschreibung und Live-Limits

Live-Beschreibungen sind optional; ausgefüllte Beschreibungen müssen nach dem Trimmen 3 bis 10.000 Zeichen enthalten. Das Formular prüft dies vor dem PeerTube-Aufruf, der API-Client schützt zusätzlich die Erstellung permanenter Livequellen. Grundlage: [PeerTube 8.2.4, CONSTRAINTS_FIELDS.VIDEOS.DESCRIPTION](https://github.com/Chocobozzz/PeerTube/blob/v8.2.4/server/core/initializers/constants.ts).

Die Fehler `max_user_lives_limit_reached` und `max_instance_lives_limit_reached` erhalten verständliche Meldungen. Alle HumHub-Nutzer verwenden den konfigurierten technischen PeerTube-Zugang; dessen Live-Limit kann daher mehrere Personen oder angebundene Communities betreffen. Ein Modulupdate oder Cache-Leeren hebt dieses serverseitige Limit nicht auf. Vorhandene Livequellen und das Limit müssen auf PeerTube administrativ geprüft werden. Das Modul löscht dafür keine fremden Livequellen und ändert keine Serverlimits.

## Version 2.8.7: Vorschaubild-Cache und Live-Korrektur

HumHub liefert Mediathek-Vorschaubilder jetzt über einen eigenen Endpunkt aus. Die Bilddaten werden 24 Stunden im serverseitigen HumHub-Cache gespeichert; Browser erhalten sie erst nach erneuter Modul- und Medienberechtigungsprüfung (`private, no-store`). Aktualisierte lokale Medien verwenden einen neuen Cache-Schlüssel. Zulässig sind ausschließlich begrenzte Rasterbilder von statischen Thumbnail-/Preview-Pfaden der konfigurierten HTTPS-PeerTube-Adresse; Weiterleitungen werden nicht verfolgt. Ohne verfügbares Bild bleibt die Wiedergabetaste nutzbar.

Die lokale Datenbank bleibt die Grundlage für Titel, Beschreibung, Ordner und Sichtbarkeit. Für das Nachladen fehlender Vorschaubildadressen werden PeerTube-Metadaten fünf Minuten zwischengespeichert. Schreibbestätigungen und Live-Statusprüfungen umgehen diesen Anzeige-Cache. Fehlgeschlagene Abrufe pausieren pro Cache-Schlüssel 60 Sekunden; eine Dateisperre verhindert gleichzeitige identische Abrufe auf demselben HumHub-Server. Bei mehreren HumHub-Servern ist für diese Sperren ein gemeinsames Runtime-Dateisystem nötig. Ein persistenter HumHub-Cache und ein beschreibbares Runtime-Verzeichnis sind erforderlich; ein Cache-Leeren verursacht erneut Erstabrufe. Es werden keine Videodateien oder Livestreams gespiegelt.

Beim Erzeugen einer permanenten Livequelle wird eine leere Beschreibung weggelassen. Dies korrigiert den dokumentierten PeerTube-400-Fehler `Incorrect request parameters: description`. Bestehende Livequellen müssen dafür nicht gelöscht werden. Die separat protokollierten HTTP-429-Antworten des Moduls `local-link-preview` sind von dieser Korrektur nicht abgedeckt.

Prüfung: `php tests/run.php` führt isolierte Regressionstests ohne echte Zugangsdaten aus. Die Tests ersetzen die API und HumHub-Abhängigkeiten durch Testdoubles; eine vollständige HumHub-/PeerTube-Abnahme ist damit nicht ersetzt. Für das Update Dateien austauschen und den HumHub-Cache leeren; keine neue Migration nötig. Anschließend Livequelle ohne Beschreibung, mehrmaliges Laden der Mediathek, private/geteilte Medien und die Anzeige nach Rechteentzug auf der Testinstanz prüfen. Diese Version ist durch die Repository-Änderung noch nicht auf einer laufenden Instanz installiert.

## Voraussetzungen und Einrichtung für andere Communities

Das Modul entstand für Community, ist aber technisch konfigurierbar und nicht auf bestimmte Benutzerkonten beschränkt. Folgende installationsbezogene Vorgaben müssen bei einer Übernahme geprüft werden:

- In der Modulkonfiguration die vorbelegte PeerTube-URL `https://video.community.events` durch die eigene HTTPS-URL ersetzen und einen eigenen technischen PeerTube-Benutzer mit Kennwort sowie dessen Kanal-ID hinterlegen.
- Die voreingestellten Embed-Domains `community.example.org` und `community.example.org` durch die eigenen HumHub-Domains ersetzen. Diese Vorgaben sind konfigurierbar.
- Für Direktuploads einen eigenen zufälligen gemeinsamen Schlüssel mit mindestens 32 Zeichen in beiden Komponenten speichern; im PeerTube-Plugin ausschließlich die eigenen erlaubten HTTPS-Ursprünge konfigurieren.
- HumHubs Queue-Verarbeitung und Cronjobs betreiben: Sammelaktionen, Live-Überwachung und die automatische Übernahme von Aufzeichnungen benötigen Hintergrundverarbeitung.
- Rechte für Nutzung, Uploads, Verwaltung und Livestreams in der eigenen Community festlegen. Inhaltswarnungen können angepasst werden; die Oberfläche ist deutsch.

Zugangsdaten gehören ausschließlich in die Administrationskonfiguration. Das Modul speichert die technischen Zugangsdaten verschlüsselt; der HumHub-Anwendungsschlüssel muss bei Sicherung und Wiederherstellung erhalten bleiben. PHP benötigt unter anderem cURL und OpenSSL für API-Zugriffe und Verschlüsselung. Eine genaue laufende PHP-/Datenbankversion wurde für diese Dokumentation nicht festgestellt.

**Einschätzung:** Für andere Communities grundsätzlich verwendbar, aber mit Einrichtung und Abnahme auf deren eigener Installation. Der Namespace `community\videolibrary` ist eine technische Herkunftsbezeichnung. Eine `LICENSE`-Datei und eine Lizenzangabe in `module.json` fehlen derzeit; eine allgemeine Freigabe zur Weitergabe oder Änderung ist damit im Repository nicht dokumentiert. Dies sollte vor einer öffentlichen Veröffentlichung geklärt werden. Die GitHub-Repositories sind derzeit privat; andere Personen benötigen Zugriff.

## Vorhandene Funktions- und Versionsnotizen

Die folgenden Versionsnotizen beschreiben die Entwicklung bis 2.8.6. Ältere Versionsnummern sind historische Einträge, nicht die aktuelle Modulversion. Hinweise zur Deaktivierung wurden an den aktuellen Code angepasst.

Version 2.6.1 erkennt eine noch offene PeerTube-Live-Sitzung unabhängig von unterschiedlichen Systemzeitzonen als aktives Sendesignal. Das Öffnen beziehungsweise automatische Aktualisieren des Live-Studios startet außerdem eine ausgefallene oder abgelaufene Prüfkette selbstheilend neu; veraltete Queue-Aufträge werden weiterhin über die Prüfgeneration entwertet.

Version 2.6.0 erkennt Start und Ende eines OBS-Streams automatisch anhand der PeerTube-Live-Sitzung. Das Live-Studio aktualisiert seinen Status selbstständig; die manuelle Freischaltung entfällt. Während einer laufenden Sendung wird zeitlich unbegrenzt weiter überwacht, nach dem Ende wartet die bestehende Replay-Verarbeitung auf die fertige Aufzeichnung.

Version 2.5.2 übernimmt beim Erzeugen des Mediathek-Eintrags Autor und Bearbeiter ausdrücklich aus dem ursprünglichen Livebeitrag. Das ist erforderlich, weil HumHub-Queue-Worker keine angemeldete Benutzeridentität besitzen.

Version 2.5.1 entfernt einen von PeerTube 8.2 am Live-Sessions-Endpunkt abgelehnten Sortierwert und sortiert Replay-Sitzungen stattdessen lokal. Eine persistente Prüfgeneration stellt außerdem sicher, dass parallele oder veraltete Queue-Aufträge keine mehrfachen Prüfketten erzeugen. Dafür ist die Migration `m260905_000014_live_poll_generation` erforderlich.

Version 2.5.0 ergänzt die automatische Replay-Verarbeitung. Ein Queue-Auftrag prüft die Sitzungs-Historie der permanenten PeerTube-Livequelle, übernimmt die eindeutig zugehörige fertige Aufzeichnung, versieht sie mit dem Community-Passwort und wandelt den temporären Livestream-Inhalt atomar in ein normales Mediathek-Video um. Content-ID, Kommentare und Reaktionen des ursprünglichen Livebeitrags bleiben erhalten. Dafür ist die Migration `m260905_000013_live_replay` erforderlich.

Version 2.4.3 übermittelt das Video-Passwort bereits beim initialen Erstellen einer permanenten Livequelle. PeerTube verlangt dies zwingend, wenn die Livequelle von Beginn an passwortgeschützt angelegt wird.

Version 2.4.2 verwendet für noch nicht erzeugte Live-Aufzeichnungen PeerTubes zulässige private Replay-Sichtbarkeit. PeerTube 8.2 erlaubt an dieser Stelle noch keine Passwort-Sichtbarkeit; der Livestream selbst bleibt passwortgeschützt.

Version 2.4.1 korrigiert den Namespace des HumHub-Formular-Widgets in der Livestream-Vorbereitung. Dadurch lässt sich „Live gehen“ ohne internen Serverfehler öffnen.

Version 2.4.0 ergänzt die erste Livestream-Ausbaustufe. Pro Benutzer wird eine dauerhaft wiederverwendbare PeerTube-Livequelle angelegt; RTMPS-Adresse, Streamschlüssel und Videopasswort werden verschlüsselt in HumHub gespeichert. Berechtigte Mitglieder können in Profilen oder freigegebenen Spaces eine Sendung vorbereiten, sie nach dem Start in OBS als „LIVE“ kennzeichnen und anschließend beenden. Dazu kommen eine globale Gruppenberechtigung und eine zusätzliche Space-Berechtigung „Livestreams starten“. Nach dem Update ist die Migration `m260904_000012_live_streaming` auszuführen.

Die automatische technische Erkennung von Sendestart und Sendeende sowie die Übernahme der fertigen Aufzeichnung als normal bearbeitbares Mediathek-Video sind bewusst noch nicht Bestandteil dieser ersten Stufe.

Version 2.3.2 behebt den Stream-Abbruch durch den ungültigen Aufruf einer nicht vorhandenen `ContentActiveRecord::canEdit()`-Elternmethode. Zusätzlich berücksichtigt Player, Detailansicht und Passwort-Endpunkt optional die sichere Share-Zugriffsprüfung aus Share content 1.2.0. Ein privates Profilvideo bleibt privat und wird nur für Nutzer abspielbar, die mindestens einen veröffentlichten, nicht archivierten und für sie lesbaren Share besitzen.

Version 2.3.1 behebt einen Fehler beim Abschluss großer Direktuploads: PeerTube darf Titel und Beschreibung zunächst trimmen oder Unicode-normalisieren, ohne dass HumHub das bereits vollständig übertragene Video fälschlich löscht. Die vom PeerTube-Plugin signierte Abschlussbestätigung bleibt die Identitätsprüfung; anschließend setzt HumHub die gewünschten Metadaten und kontrolliert sie normalisiert mit kurzen Wiederholungsversuchen.

Version 2.3.0 ersetzt einen starren Browser-Request-Zeitraum durch einen fortschrittsabhängigen Inaktivitätswächter. Solange Uploaddaten fließen, darf eine große Datei lange übertragen werden. Erst nach 60 Sekunden ohne Fortschritt wird der aktuelle 8-MiB-Block abgebrochen und automatisch erneut gesendet. Das verschlüsselte Upload-Ticket behält aus Sicherheitsgründen seine zweistündige Maximalgültigkeit.

Version 2.2.9 rendert den geschützten Player containerneutral: Bei persönlichen Videos wird die Passwort-Route jetzt über den HumHub-Profilcontainer statt über eine nicht vorhandene Space-Beziehung erzeugt. Außerdem liest die kanonische Medien-URL den optionalen Zeitstempel nur in Web-Anfragen; Queue- und E-Mail-Jobs können Profilvideos dadurch ohne `yii\\console\\Request::get()`-Fehler verarbeiten.

Version 2.2.8 synchronisiert die HumHub-Sichtbarkeit mit der ausdrücklichen Auswahl „Im Stream veröffentlichen“. Dadurch erkennt das optionale Modul „Share content“ neue, bearbeitete und nach der Migration auch bestehende Streamvideos als teilbare HumHub-Inhalte und zeigt seinen normalen „Teilen“-Link neben Kommentaren und Reaktionen an. Reine Mediathek-Einträge bleiben private HumHub-Inhalte; es entsteht weiterhin keine Abhängigkeit vom Share-Modul.

Version 2.2.7 setzt Click-to-Load vollständig um: In Mediathek-Karten und hinter Content Warnings wird vor dem Nutzerklick kein leeres iframe mehr erzeugt. Der ausgewählte PeerTube-Player wird erst nach dem Klick in den DOM eingefügt. Das reduziert Feature-Policy-Warnungen, DOM-Last und vorzeitige Verbindungen.

Version 2.2.6 entfernt die für Click-to-Load nicht benötigten iframe-Freigaben `autoplay`, `encrypted-media` und `clipboard-write`. Vollbild und Picture-in-Picture bleiben freigegeben; Firefox erzeugt dadurch keine Feature-Policy-Warnung pro Videokarte mehr.

Version 2.2.5 synchronisiert für ältere Medieneinträge einmalig den tatsächlichen `thumbnailPath` aus der PeerTube-API und speichert die vollständige URL lokal. PeerTubes Thumbnail-Dateiname wird nicht mehr aus der Video-UUID abgeleitet.

Version 2.2.4 verwendet für ältere Medieneinträge ohne gespeicherten Thumbnail-Pfad PeerTubes aktuellen `/lazy-static/thumbnails/`-Pfad. Dadurch werden die Startbilder in der Click-to-Load-Ansicht wieder geladen.

Version 2.2.3 korrigiert die HumHub-User-Klasse der Uploaderbeziehung. Mediatheken mit vorhandenen Medien können damit den Namen des Uploaders wieder fehlerfrei darstellen.

Version 2.2.2 verhindert, dass alte oder unvollständig verknüpfte Medieneinträge die gesamte Mediathek blockieren. Betroffene Einträge werden protokolliert und Admins erhalten einen verständlichen Hinweis. Die Ordneransicht bleibt außerdem kompatibel, wenn die Migration für frei wählbare Ordnersymbole noch nicht ausgeführt wurde.

Version 2.2.1 stellt die globale Uploadberechtigung standardmäßig auf „Verweigern“. Erst eine Freigabe in der jeweiligen HumHub-Benutzergruppe aktiviert Video-Reiter und Dashboard-Aktion. Direkte Aufrufe des Uploadformulars zeigen statt einer technischen Fehlerseite eine verständliche Erklärung; direkte Upload-API-Aufrufe antworten mit einer eindeutigen JSON-Meldung und HTTP 403.

Version 2.2.0 ergänzt über HumHubs Widget-Events einen sichtbaren „Video hochladen“-Button am kompakten Dashboard-Composer. Im Datei-/Wolkenmenü bleibt der Video-Upload entfernt. Aktivierte Benutzerprofile erhalten außerdem einen eigenen Menüpunkt „Mediathek“. Reiter, Menü, Dashboard-Aktion und Endpunkte folgen derselben zentralen Uploadberechtigung.

Version 2.1.2 korrigiert die benutzerbezogene Abfrage von HumHubs Space-PermissionManager. Version 2.1.1 übergab den Benutzer irrtümlich als Berechtigungsparameter und konnte deshalb beim Öffnen der Mediathek einen internen Fehler auslösen.

Version 2.1.1 führt die Uploadentscheidung vollständig in einer gemeinsamen Berechtigungsregel zusammen. Im Space sind ein aktives Modul, die globale Gruppenberechtigung und die Space-Rollenberechtigung erforderlich. Im eigenen Profil sind ein aktives Profilmodul und die globale Gruppenberechtigung erforderlich. HumHubs sichtbarer Video-Reiter, der Mediathek-Button und alle klassischen sowie direkten Upload-Endpunkte verwenden nun dieselbe Prüfung.

Version 2.1.0 modernisiert die Mediathek-Karten und bündelt ihre Aktionen übersichtlich. Das endgültige Löschen ist nicht mehr Teil der Übersicht, sondern liegt geschützt unter „Weitere Aktionen“ im Bearbeitungsformular. Der Upload erscheint ausschließlich als sichtbarer HumHub-Inhaltstyp „Video“ und nicht zusätzlich im Datei-/Wolkenmenü. Außerdem funktioniert die Uploadberechtigung nun auch im aktivierten Profilmodul für den Profileigentümer; die globale Gruppenfreigabe bleibt dabei erforderlich.

Version 2.0.1 wiederholt vorübergehende PostgreSQL-Serialisierungskonflikte beim Sammelabgleich der Embed-Domains mit exponentiellem Abstand und zufälliger Streuung. Ein später erfolgreicher Abgleich entfernt den vorherigen lokalen Fehlerstatus wieder.

Version 2.0.0 („Stufe 3“) überträgt große Video- und Audiodateien direkt vom Browser zum Videoserver. Der Upload läuft in 8-MiB-Blöcken, zeigt Prozentwert, Datenmenge, Geschwindigkeit und Restzeit, wiederholt vorübergehende Verbindungsfehler und lässt sich ausdrücklich abbrechen. HumHub erhält nur Formulardaten und optionale Vorschaubilder. Ein kurzlebiges, verschlüsseltes Upload-Ticket bindet Dateigröße, HumHub-Ursprung und PeerTube-Uploadsitzung; PeerTube signiert den Abschluss, bevor HumHub den Medieneintrag anlegt.

Version 1.10.0 unterstützt die Community-Mediathek als echtes HumHub-Modul sowohl in Spaces als auch in Benutzerprofilen. Dadurch stehen für beide Ebenen HumHubs vier Standardzustände „Nicht verfügbar“, „Deaktivieren“, „Aktiviert“ und „Immer aktiviert“ zur Verfügung. Bei deaktiviertem oder nicht verfügbarem Profilmodul wird auch kein Video-Upload im Profil- oder Dashboard-Composer angeboten. Persönliche Videos werden über den HumHub-Content-Container des Benutzerprofils gespeichert; Ordner und deren Sichtbarkeiten bleiben eine Space-Funktion.

Die Videoeinträge bleiben eigenständige HumHub-`ContentActiveRecord`-Inhalte mit Standard-Wall-Entry, kanonischer URL, Beschreibung und Berechtigungsprüfung. Damit kann das optionale Modul „Share content“ Videoeinträge wie andere HumHub-Inhalte erkennen und wiedergeben. Es gibt weder eine feste Klassenreferenz noch eine Installationsabhängigkeit zu „Share content“; ohne dieses Modul arbeitet die Mediathek unverändert weiter.

Version 1.9.3 hält PeerTube in der normalen Benutzeroberfläche vollständig im Hintergrund. Mitglieder laden Videos „in die Community“ hoch; technische PeerTube-Bezeichnungen bleiben nur in der Administration und in Serverprotokollen sichtbar. Der native HumHub-Videoanhang bleibt in Spaces, Benutzerprofilen und im Dashboard entfernt. Ist der Community-Upload nicht verfügbar oder nicht erlaubt, wird kein alternativer Video-Upload angeboten.

Version 1.9.2 entfernt HumHubs direkten Videoanhang auch aus Benutzerprofil- und Dashboard-Composer-Menüs. Eine globale HumHub-Gruppenberechtigung steuert zusätzlich, welche Benutzergruppen PeerTube-Uploads verwenden dürfen; die Space-Berechtigung bleibt als zweite notwendige Freigabe bestehen. Die allgemeine Content-Container-Schicht ist für eine spätere Benutzer-Mediathek vorbereitet, Benutzerprofile werden wegen der bislang Space-gebundenen Mediendatenbank aber noch nicht als Modulcontainer freigeschaltet.

Version 1.9.1 überarbeitet die Ordnerverwaltung: Name, Sichtbarkeit und Font-Awesome-Symbol werden gemeinsam gespeichert, Löschen liegt getrennt in einer Gefahrenzone, und Ordnerkacheln zeigen ihre Sichtbarkeit mit Schloss oder Benutzergruppe. Click-to-load verwendet auch bei älteren Medien einen PeerTube-Thumbnail-Fallback und startet nach dem bewussten Klick direkt die Wiedergabe.

Version 1.9.0 ist ein Stabilitäts- und Sicherheitsupdate: Der Video-Link im Composer erscheint nur noch in Spaces mit aktiviertem Modul, der technische PeerTube-Zugang wird verschlüsselt gespeichert, OAuth-Tokens werden kurzzeitig gecacht, Multipart-Aufrufe enden nach fünf Minuten und die Mediathek besitzt eine Seitennavigation mit zwölf Medien pro Seite. „Öffentlich“ heißt nun eindeutig „Alle Community-Mitglieder“; Gäste erhalten weiterhin keinen Zugriff. Sammeländerungen laufen über die HumHub-Queue. Upload und Bearbeitung legen zuerst einen lokalen Vorgang an, prüfen danach den PeerTube-Stand und versuchen bei Fehlern beide Seiten zurückzusetzen. Nicht vollständig rücksetzbare Vorgänge werden in der Modulkonfiguration als Admin-Alarm angezeigt.

Version 1.8.0 übergibt dem ergänzenden PeerTube-Plugin den HumHub-Permalink des Medieneintrags. Permalinks mit `t=Sekunden` starten den eingebetteten Player an dieser Stelle. Der iFrame erhält ausschließlich für die benutzerinitiierte Kopierfunktion die Berechtigung `clipboard-write`.

Version 1.7.1 unterstützt mehrere erlaubte HumHub-Domains für PeerTube-Einbettungen. Die Modulkonfiguration enthält eine Domainliste sowie eine Aktion, welche die Freigabe auf alle vorhandenen PeerTube-Videos überträgt. Neue und bearbeitete Videos erhalten die Liste automatisch.

Version 1.7.0 reduziert Upload- und Bearbeitungsformulare auf die wichtigsten Felder. Themen, Ordner, Content Warning und Vorschaubild befinden sich standardmäßig geschlossen in aufklappbaren Bereichen. Eigene JPG-, PNG- und WebP-Vorschaubilder können beim Upload und beim Bearbeiten an PeerTube übertragen werden. Beim neuen Video-Upload kann alternativ ein Zeitpunkt angegeben werden; der Browser erzeugt daraus vor dem Absenden ein Vorschaubild. Ohne Auswahl bleibt PeerTubes bisheriges Standardverhalten unverändert.

Version 1.6.0 verwendet für Medien dieselben globalen und Space-Themen wie HumHub. Die Auswahl erfolgt über HumHubs Topic-Picker, wird am Content- und damit am Stream-Eintrag gespeichert und steht zugleich als Mediathekfilter zur Verfügung. Themen aus älteren Modulversionen bleiben sichtbar, bis das Medium mit einer Auswahl aus dem HumHub-Topic-Picker neu gespeichert wird.

Version 1.5.4 überträgt bei Uploads und Änderungen keine leeren Beschreibungsparameter mehr, die PeerTube mit HTTP 400 ablehnt.

Version 1.5.3 verhindert einen HTTP-500-Fehler, wenn die Migration für die Sichtbarkeit nach dem Dateiaustausch noch aussteht.

Version 1.5.2 vereinheitlicht die Popup-Bedienung, verschiebt die Ordnerverwaltung in die Kopfzeile und ergänzt die Sichtbarkeit für Videos ohne Ordner.

Version 1.5.0 ergänzt den Uploader-Filter und die Ordnerverwaltung mit Umbenennen, Löschen und Sichtbarkeit für Mitglieder oder Öffentlichkeit.

Version 1.4.1 ordnet die Mediathek mit Ordner-Kacheln neu und macht Suche, Filter und Ordnererstellung ausklappbar.

Version 1.4.0 ergänzt freie Themen-Tags, Themenfilter, Freitextsuche und Space-Ordner für Videoreihen.

Version 1.3.3 erlaubt HumHub-Systemadministratoren und Benutzern mit der globalen Berechtigung „Alle Inhalte verwalten“, sämtliche Medien zu bearbeiten und zu löschen. Version 1.3.2 sendet Änderungen und den nachträglichen Passwortschutz im von PeerTube erwarteten Multipart-Format. Version 1.3.1 korrigierte die Registrierung der Space-Berechtigungen und verhindert einen HTTP-500-Fehler beim Öffnen der Mediathek, falls die neue Verwaltungs-Migration nach einem manuellen Dateiaustausch noch nicht ausgeführt wurde.

## Funktionen

- Video-/Audio-Upload aus aktivierten Spaces zu PeerTube
- Eigener „Video“-Eintrag im Stream-Composer und bestehende Mediathek
- HumHubs „Video anhängen“ wird ausschließlich in aktivierten Spaces durch „Video hochladen“ ersetzt; in Spaces ohne aktiviertes Modul wird kein Video-Upload-Link angezeigt
- Checkbox „Im Stream veröffentlichen“, standardmäßig aktiviert
- Normale HumHub-Dateiuploads weisen Video-Dateien ab; Bilder, Dokumente und sonstige Dateien bleiben erlaubt
- PeerTube-Videos mit individuellem, starkem Zufallspasswort (Privacy 5)
- Passwort verschlüsselt mit dem HumHub-Anwendungsschlüssel gespeichert
- Passwortfreigabe nur über eine nicht cachebare, berechtigungsgeprüfte HumHub-Antwort
- Embed immer mit `p2p=0`, `warningTitle=0`, `peertubeLink=0`
- Embed zusätzlich auf die HumHub-Domain beschränkt (PeerTube 8.1+)
- Content Warnings vor dem Laden des Players
- HumHub-ContentModel für Stream, Autor, Kommentare und Space-Sichtbarkeit
- Eindeutige PeerTube-UUID verhindert doppelte lokale Einträge
- Einmaliges Upload-Token verhindert Doppel-Uploads durch Doppelklick oder erneutes Absenden
- Titel, Beschreibung, Content Warnings und Stream-Veröffentlichung können nachträglich bearbeitet werden; Titel und Beschreibung werden mit PeerTube synchronisiert
- Globale und Space-Themen aus HumHub werden am Video-Content gespeichert, im Stream angezeigt und in der Mediathek gefiltert
- Kompakte Formulare mit ausklappbaren Zusatzoptionen sowie eigenen Vorschaubildern; beim Erstupload kann ein Frame per Videozeitpunkt gewählt werden
- Eigene Space-Berechtigungen „Videos hochladen“ und „Videos verwalten“
- Remote-Löschung ist fehlertolerant bei bereits fehlenden Videos; bei anderen PeerTube-Fehlern bleibt der lokale Eintrag für einen erneuten Versuch erhalten
- Synchronisierungsfehler werden lokal markiert, ohne Passwörter oder Tokens im Frontend auszugeben
- Ordner und Videos ohne Ordner können für „Nur Raummitglieder“ oder „Alle Community-Mitglieder“ freigegeben werden; Gäste bleiben ausgeschlossen
- Mediathek-Seitennavigation mit zwölf Medien pro Seite
- Technischer PeerTube-Zugang verschlüsselt gespeichert und OAuth-Zugriffstoken gecacht
- Sammelaktionen für Bestandsschutz und Embed-Domains laufen über die HumHub-Queue
- Direkter, blockweiser Browser-Upload mit Fortschritt, Restzeit, Wiederholungen und Abbruch

## Update von 1.1.0 bis 1.2.3

1. Datenbank und bisherigen Modulordner sichern.
2. Den Ordner `peertube` aus dieser ZIP über `protected/modules/peertube` kopieren.
3. Da HumHub für manuell kopierte Module keinen Update-Knopf anzeigt: **Administration → Module → PeerTube Mediathek → Konfigurieren** öffnen und dort **Datenbank jetzt aktualisieren** anklicken. Dadurch laufen alle noch fehlenden Migrationen.
4. Konfiguration speichern und **Verbindung testen**.
5. Einen Test-Upload in einem Space durchführen und sowohl Mediathek als auch Stream prüfen.

Migration `m260902_000003_protected_stream_content` ergänzt Passwortschutz, Stream-Schalter und Thumbnail. Migration `m260903_000004_management` ergänzt `upload_token`, `sync_status`, `last_sync_error` und den eindeutigen Upload-Token-Index. Migration `m260903_000008_pending_operations` speichert laufende Upload- und Bearbeitungsvorgänge für eine kontrollierte Rücksetzung. Frühere Mediathek-Videos können in der Modulkonfiguration gesammelt über **Bestehende Videos jetzt schützen** umgestellt werden; alternativ geschieht dies beim ersten berechtigten Abspielen. Frühere Videos werden nicht nachträglich als Stream-Posts dupliziert.

Nach dem Update können die neuen Rechte in den Space-Einstellungen unter **Berechtigungen** je Space angepasst werden. Standardmäßig dürfen Mitglieder hochladen; Eigentümer, Administratoren und Moderatoren dürfen alle Medien verwalten. Der jeweilige Uploader darf sein eigenes Medium weiterhin bearbeiten und löschen.

## Neuinstallation

Den Ordner `peertube` nach `protected/modules/` kopieren, das Modul in HumHub installieren, PeerTube-URL, technischen Benutzer und Kanal-ID konfigurieren und anschließend in den gewünschten Spaces aktivieren.

## Direktupload einrichten

In der HumHub-Modulkonfiguration und in den Einstellungen des PeerTube-Plugins `humhub-permalinks` muss derselbe zufällige Schlüssel mit mindestens 32 Zeichen gespeichert werden. Im PeerTube-Plugin werden zusätzlich alle erlaubten HumHub-Ursprünge vollständig mit `https://` eingetragen. Große Mediendateien laufen danach nicht mehr durch HumHub/PHP; dessen Upload-Limit betrifft nur ein optional hochgeladenes Vorschaubild.

Für die beiden Sammelaktionen muss HumHubs Queue-Worker beziehungsweise Queue-Cronjob eingerichtet sein. Ohne laufende Queue bleiben die Aufträge eingeplant, werden aber nicht abgearbeitet.

## Sicherheitsgrenze

Das Passwort steht nicht in Embed-URLs oder im ausgelieferten Seiten-HTML. Ein berechtigter Browser muss es für die Wiedergabe an PeerTube übermitteln; technisch versierte berechtigte Nutzer können es daher in ihren eigenen Netzwerkdaten auslesen. Bildschirmaufnahmen lassen sich ebenfalls nicht verhindern.

## Deinstallation

Beim normalen Deaktivieren bleiben die lokalen Daten erhalten (`Module::disable()`). Nur wenn die administrative Option `allowDataDeletionOnDisable` ausdrücklich gesetzt wurde, wird HumHubs Deinstallationspfad ausgeführt. PeerTube-Dateien werden dabei nicht automatisch massenweise gelöscht. Das Löschen einzelner Medien kann dagegen bei aktivierter Einstellung `deleteRemote` auch die Remote-Datei entfernen. Für Updates das Modul aktiviert lassen, Dateien austauschen und ausstehende Migrationen ausführen.
## Version 2.7.0

- Geplante Livestreams werden sofort im Stream angekündigt.
- Bei aktiviertem Kalender-Modul erscheinen geplante Livestreams automatisch im Kalender; das Kalender-Modul bleibt optional.
- OBS-Start und -Ende werden alle fünf Sekunden statt alle 30 Sekunden geprüft.
- Das Live-Studio aktualisiert nur noch den Status und lädt nicht mehr die ganze Seite neu.
## Version 2.7.1

- Hotfix: Die optionale Kalenderintegration wird nicht mehr global geladen und kann deshalb keine HumHub-Seite blockieren.
- Eine Modul-Deaktivierung bewahrt standardmäßig sämtliche Tabellen, Inhalte und Einstellungen auf.
- Vollständige Datenlöschung ist nur nach ausdrücklicher Aktivierung in der Danger Zone möglich.
## Version 2.7.2

- PeerTube-Instanzen ohne freigegebenen Niedriglatenzmodus fallen automatisch auf den Standardmodus zurück.
- Livestreams können ein eigenes Ankündigungsbild erhalten.
- Derselbe Stream-Beitrag zeigt vor Beginn die Ankündigung, während der Sendung den Live-Player und danach die gespeicherte Aufzeichnung.
- Die Hotfix-Konfiguration enthält keine veralteten Kalender-Callbacks mehr.
## Version 2.7.3

- Leere HumHub-Beitragsformulare lösen nach ihrer dynamischen Initialisierung keine falsche Warnung über ungespeicherte Änderungen mehr aus.
- Tatsächlich eingegebener Text bleibt weiterhin durch die Verlassen-Warnung geschützt.
# Version 2.8.0

- Geplante Livestream-Ankündigungen können über das native Drei-Punkte-Menü bearbeitet werden.
- Titel, Beschreibung, Termin, Dauer und Ankündigungsbild lassen sich vor Streambeginn ändern.
- `[artikelbild]` positioniert Video, Ankündigungsbild beziehungsweise Liveplayer innerhalb des Beschreibungstextes.
- Titel und Beschreibungen unterstützen Emojis; bestehende Modultabellen werden unter MySQL auf `utf8mb4` aktualisiert.
- Auch normale Videobeiträge besitzen nun im Drei-Punkte-Menü den nativen Eintrag „Bearbeiten“.

# Version 2.8.1

- Der Formularwächter erkennt den leeren Dashboard-Composer jetzt anhand sichtbarer Eingaben statt anhand interner Richtext-Daten.
- Dynamische versteckte Formularwerte lösen beim Navigieren keine falsche Warnung mehr aus; echte Text- und Dateientwürfe bleiben geschützt.
- `[artikelbild]` wird nun auch in der Livestream-Einzelansicht ausgewertet und nicht mehr als Text angezeigt.

# Version 2.8.2

- Der Formularwächter berücksichtigt jetzt alle leeren, dynamisch erzeugten Streamformulare – einschließlich Kommentar- und Antwortformularen.
- Die Bereinigung läuft vor Maus-, Touch- und Tastatur-Navigation; ausgefüllte Texte und ausgewählte Dateien bleiben weiterhin geschützt.

# Version 2.8.6

- Die in Version 2.8.2 bis 2.8.5 ergänzten Eingriffe in HumHubs Formularwächter wurden vollständig entfernt. Die Falschmeldung wurde durch Local Link Preview verursacht und wird dort behoben.

# Version 2.8.5

- Der originale HumHub-Formularwächter wird nun unabhängig von der Ladereihenfolge zuverlässig nach der Widget-Initialisierung sowie unmittelbar vor einer Navigation abgelöst.
- Damit verschwindet die weiterhin reproduzierbare Falschmeldung beim Öffnen von Mitgliedern, Räumen und anderen Seiten aus einem unveränderten Dashboard.

# Version 2.8.4

- Der Formularwächter auf Dashboard- und Streamseiten reagiert nur noch auf echte Eingaben der Benutzer. Asynchron gesetzte, versteckte Widget-Felder lösen keine falsche Warnung mehr aus.
- Begonnene Texte, Dateiauswahlen und andere wirkliche Formularänderungen bleiben weiterhin gegen versehentliches Verlassen geschützt.

# Version 2.8.3

- Leere Streamformulare erhalten keinen veraltenden Vergleichszustand mehr; dynamische versteckte Felder können den Formularwächter daher nicht erneut auslösen.
- Der Schutz wird unmittelbar vor der ersten echten Texteingabe, Dateiauswahl oder sichtbaren Formularänderung wieder aktiviert.

### Livestream-Diagnose ab 2.8.9

Fehler beim Vorbereiten der Livequelle enthalten einen stabilen Fehlercode und eine
individuelle Referenz. Die Referenz steht auch im HumHub-Log unter `peertube.live`,
zusammen mit Phase, geladenen Codeversionen und Ursprungsdatei/-zeile. Diese
Diagnoseeinträge enthalten keine API-Antworttexte, Zugangsdaten oder Trace-Argumente.

`PT-LIVE-403-USER-LIMIT` bedeutet: Das technische PeerTube-Konto hat sein
Livestream-Limit erreicht. Vorhandene Livequellen und Kontolimit administrativ
prüfen; das Modul löscht keine Quellen und verändert keine Serverlimits.
`PT-LIVE-403-INSTANCE-LIMIT` bezeichnet das Instanzlimit. `PT-LIVE-HTTP-<Status>`
erhält den von PeerTube gemeldeten HTTP-Status; `PT-LIVE-DB` bezeichnet lokale
Datenbankfehler, `PT-LIVE-UNKNOWN` eine nicht sicher zuordenbare Ursache.

Das Startformular zeigt Modul-, Controller- und Fehlerbehandlungsversion. Nach
vollständiger Aktualisierung müssen alle drei `2.8.9` anzeigen. Unterschiede
weisen auf gemischte Dateien oder veralteten PHP-OPcache hin: Installation prüfen
und gegebenenfalls PHP/OPcache über die Serververwaltung neu laden. Die tatsächliche
Ursache des bisher allgemeinen Textes ist anhand des Produktionslogs nicht bewiesen.
Zusätzlich steht die Fehlermeldung in einer serverseitigen Fehlerzusammenfassung.

Bei Limitfehlern führt ein Link zur PeerTube-Videoverwaltung (`/my-library/videos`,
gegen PeerTube 8.2.4 geprüft). Dort mit dem technischen Konto anmelden und
nicht mehr benötigte Livequellen über deren Menü entfernen. Der Link selbst
löscht nichts. Eine entfernte Quelle verliert ihre Streaming-Zugangsdaten.
Die Verwaltung des gemeinsamen technischen Kontos ist der Administration
vorbehalten; der Link überträgt keine Anmeldung oder Zugangsdaten.
