# Placetel Dashboard für Nextcloud

Native Nextcloud-App für Placetel-PROFI-Callcenter. Installation, eigenen API-Schlüssel in den App-Einstellungen eintragen, Konto prüfen – kein Node-Dienst, zusätzlicher Container oder AppAPI erforderlich. DispoLive ist eine optionale, bei neuen Installationen ausgeschaltete Zusatzfunktion.

## Funktionen

- Live-Wartende und letzte drei angenommene Anrufe je Warteschleife; Datenalter und Ausfälle erkennbar.
- Agentenstatus, Prioritäten und dokumentierte Warteschleifeneinstellungen mit Änderungsvorschau.
- Statistik nach Agent, Warteschleife, Tag, Woche, Monat und Datumsbereich: angenommene Gespräche, eindeutige normalisierte Rufnummern, Dauerabdeckung und Stundenverteilung. Wiederholte Klingelversuche zählen nicht als Annahmen; anonyme Anrufer separat.
- Rückrufkandidaten mit lokalen Notizen, Wartezeitwarnungen, Telefonbuch und Routingvorlagen mit Vorschau.
- Optionaler signierter Placetel-Notify-Empfang.
- Optionaler DispoLive-Rufnummernabgleich mit eigenem Mandanten und API-Schlüssel, an-/abschaltbar. Optionaler Popup-Versand für neue signierte Anrufereignisse an ein explizit eingestelltes Arbeitsplatz-Telefon.

## Voraussetzungen und Einrichtung

Nextcloud35, PHP≥8.3 und eine korrekt betriebene Nextcloud mit funktionierenden Hintergrundjobs. Unterstützt werden Placetel-PROFI-Konten mit den jeweiligen API-/Callcenter-Berechtigungen. Pro Installation wird ein gemeinsames Placetel-Konto eingerichtet, keine getrennten Konten pro Nextcloud-Nutzer. Die Oberfläche ist deutsch; Statistikzeitzone Europe/Berlin, nationale Rufnummern werden mit Standardregion DE normalisiert.

Nach einer späteren Store-Veröffentlichung kann die App über die normale Nextcloud-App-Verwaltung installiert werden. Der vorliegende **unsignierte Kandidat** lässt sich vorab als Ordner `placetel` in `custom_apps` installieren und mit `occ app:enable placetel` aktivieren. Noch keine allgemeine Store-Freigabe behauptet.

1. In Nextcloud „Placetel Dashboard → Einstellungen“ als Administrator öffnen.
2. Eigenen Placetel-Web-API-Schlüssel eintragen. Das Konto wird über `/me` geprüft; der Schlüssel wird verschlüsselt in der Nextcloud-Datenbank gespeichert und nicht wieder angezeigt.
3. Leser-/Dispositionsgruppen bei Bedarf einstellen. Standardmäßig dürfen angemeldete Nutzer lesen; nur Nextcloud-Administratoren dürfen Schlüssel verwalten. Ohne Dispositionsgruppe können nur Administratoren die Anlage ändern. Der Einrichtungsdialog weist auf den gemeinsamen Datenzugriff hin.
4. Nur bei Bedarf unter „Zusatzfunktion: DispoLive“ eigenen HTTPS-Mandanten und Schlüssel speichern und den Schalter aktivieren. Ohne Aktivierung werden keine Rufnummern an DispoLive übertragen. Ausschalten behält den verschlüsselten Schlüssel, stoppt Abfragen und blendet Treffer aus; „Trennen“ entfernt den Schlüssel.

### Hilfe direkt bei jedem Einrichtungsfeld

Die Einstellungen erklären Herkunft und Zweck der Felder mit Beispielen und Links:
Placetel-Web-API-Schlüssel (kein Loginpasswort), eigene DispoLive-Basisadresse,
DispoLive-Schlüssel vom zuständigen Administrator/Support, bekannte Testnummer und
Nextcloud-Gruppen-IDs. Die Placetel-Einrichtung steht vor den optionalen Zusatzfunktionen.
Für DispoLive variieren Freischaltung/Menüs je Berechtigung und Version; die App
behauptet deshalb keinen ungeprüften festen Menüpfad.

Beim optionalen Notify-Empfang wird die Adresse aus der geöffneten Nextcloud
inklusive Unterverzeichnis ermittelt und kann kopiert werden. Der Nutzer erzeugt
per Button ein zufälliges gemeinsames Geheimnis, kopiert es vor dem Speichern
ins Placetel-Portal und aktiviert Notify für gewünschte Nummern. HTTP-Ansichten
weisen auf erforderliches öffentliches HTTPS hin. Ein gespeichertes Geheimnis
bestätigt noch keine Erreichbarkeit: Testanruf unter „Ereignisse“ kontrollieren.
Für ein öffentliches Deployment muss der Nextcloud-Administrator den dedizierten
Empfangspfad freigeben. Die App kann Proxy-/Firewall-Einstellungen und Anbieter-
Freischaltungen nicht automatisch erledigen. DispoLive-Popups werden separat mit Arbeitsplatz-Ziel aktiviert; Hinweise zur Zustellung stehen unten.

Demo-Daten sind künstliche Beispieldaten und vom Echtbetrieb getrennt. Es werden keine fremden Zugangsdaten oder Kundendaten mitgeliefert. Ein Kontowechsel auf ein anderes Placetel-Konto wird bei bestehender Historie verhindert; dafür ist eine getrennte Migration erforderlich.

## Aktualisierung und Grenzen

Bei geöffneter Ansicht werden Abrufe zentral pro Installation koordiniert; weitere Browser teilen sich Datenstand und Rate-Limits. Hintergrundimporte laufen über Nextcloud-Jobs. Cron-Intervall und Warteschlange können die Aktualisierung bei geschlossenem Browser verzögern. Placetel-Limits und Pagination können auch Live-Abrufe verzögern; maßgeblich ist der angezeigte Datenstand.

Agenten-Warteschleifenzuordnung und Skills samt entsprechender Einsatzplanung bleiben gesperrt, weil keine bestätigte offizielle Schnittstelle vorliegt. Dokumentierte Routingwechsel sind davon getrennt: manuell mit Vorschau oder geplant im Nextcloud-Cron-Takt; keine minutengenaue Ausführungsgarantie. Unbestätigte Schreiboperationen werden nicht automatisch wiederholt.

Callcenter-Gesprächsdauern sind derzeit ausdrücklich Näherungen ab Warteschleifenaustritt, keine bestätigten reinen Sprechzeiten. Fehlende Historie wird als Datenlücke gekennzeichnet. Der erste Import kann länger dauern. Diese Version ist auf kleine Teams ausgelegt; ein einzelner paginierter Import ist auf10000 Datensätze begrenzt und bricht darüber ohne Veröffentlichung einer unvollständigen Tagesliste ab. Lastabnahme für große Callcenter steht aus.

## Datenschutz und Betrieb

Siehe `DATENSCHUTZ.md`. Telefoniehistorie zwölf Monate, DispoLive-Minimalcache30 Tage; Konfiguration bleibt bis zur Entfernung bestehen. Nur die eigene Nextcloud-Datenbank wird genutzt, keine externen Analysedienste/Telemetrie. Backup muss Datenbank **und Nextcloud-Konfigurationsgeheimnis** enthalten, damit Schlüssel wieder entschlüsselt werden können. In-App-Schlüssel entfernen ersetzt nicht das Widerrufen im Anbieterportal. App-Deaktivierung stoppt Nutzung, löscht vorhandene Daten nicht automatisch.

Unabhängiges Community-Projekt; Placetel und DispoLive sind die jeweiligen Produktnamen. Keine offizielle Hersteller-App oder Herstellerpartnerschaft behauptet.

## DispoLive-Anrufer-Popups (freiwillige Zusatzfunktion)

Nach dem Placetel-Notify-Test und eigener DispoLive-Einrichtung kann ein Administrator
unter „Anrufer-Popups in DispoLive“ den Versand einschalten. Standardmäßig aus.
Arbeitsplatz-Ziel: DispoLive → Mitarbeiter → Grunddaten → Arbeitsplatz-Telefon.
Diesen Wert exakt übernehmen; ein gemeinsamer Wert kann mehrere Benutzer betreffen.
Optionales Firmenkürzel und Filter auf eigene angerufene Nummern werden erklärt.
Die tatsächliche Empfängerzuordnung und Browseranzeige mit einem Testanruf prüfen.

Nur neue signierte eingehende Anrufe, keine Historie oder normalen Suchabfragen,
lösen notification:true aus. DispoLive ermittelt Namen anhand der Rufnummer selbst.
Unterdrückte Nummern werden nicht abgefragt; mehrdeutige Treffer werden nicht lokal
auf eine vermeintliche Person reduziert. Der Ablauf erfindet keine Kontaktnamen.

Der erste Versuch erfolgt unmittelbar im Webhook, mit kurzen Netzwerkzeitgrenzen.
Für Rate-Limits/gleichzeitige Anrufe optional als Nextcloud-Webnutzer ausführen:

    php occ placetel:notifications:work --seconds=0

Dies ist ein zusätzlicher dauerhaft überwachter PHP-Prozess für diese Funktion,
kein Pflichtdienst des Basisdashboards. Ohne Worker kann eine wartende Meldung bis
zum nächsten Ereignis/Dashboard-/Cron-Lauf liegen bleiben und nach90s verfallen.
Worker führt keine Telefonie-Routingänderungen oder Historienimporte aus.

Zustellstatus ist sichtbar: vorgemerkt, läuft, API bestätigt, abgelehnt, veraltet oder
Ergebnis unbekannt. Call-ID verhindert normale Wiederholungen; neue Anrufe gleicher
Nummer bleiben eigenständig. Pending-Meldungen werden bei Annahme/Ende verworfen.
Nur eindeutig zurückgewiesene HTTP429 werden höchstens3mal versucht. Bei Timeout,
Verbindungsfehler oder unklarem HTTP5xx keine automatische Wiederholung, um doppelte
Meldungen zu vermeiden. Eine Exactly-once-Garantie bietet die Anbieter-API nicht.

## Ausführliche Anleitung direkt in der App

Oben unter Einstellungen → Einrichtung: Schritt für Schritt → „Ausführliche Einrichtungsanleitung
anzeigen“ stehen acht aufklappbare Kapitel mit41 Schritten. Direktsprünge bei Notify
und Popup-Worker öffnen das passende Kapitel. Die identische vollständige Textfassung
lässt sich über „Vollständige Textanleitung öffnen / drucken“ anzeigen.

Sie erläutert Zuständigkeiten, Zugangsdaten, öffentliche HTTPS-Erreichbarkeit,
genaue Proxy-Ausnahme mit Signaturprüfung, sichere unsignierte Verbindungstests,
Placetel-Notify, DispoLive-Ziel, Linux-/Docker-/NAS-Worker, systemd-Vorlagen,
Start/Status/Logs/Neustart/Stop und Fehlercodes. Vorlagen lassen sich kopieren;
Befehle werden niemals durch die App ausgeführt. Herkunft der Beispielwerte und
notwendige Anpassungen sind erklärt; Hosting ohne Serverrechte wird gesondert behandelt.

Die Docker-systemd-Vorlage beaufsichtigt bewusst begrenzte60s-OCC-Läufe mit Wiederanlauf,
weil das Stoppen eines docker-exec-Clients den inneren PHP-Prozess nicht zuverlässig
beendet. Zum sofortigen Verhindern neuer Meldungen zuerst den Popup-Schalter ausschalten.
Eine bereits laufende Anfrage kann noch abgeschlossen werden.

Die Anleitung enthält nun ein eigenes Kapitel „Nextcloud selbst administrieren“
mit zwölf einfachen Schritten: Administratorkonto/Serverzugriff, Versions- und
Backupprüfung, aktuelle Kandidateninstallation gegenüber späterem Storeweg,
Aktivierung, Benutzer/Gruppen/App-Rechte sowie regulärer Cron und Betrieb.
Regulärer Nextcloud-Cron und zeitnaher Popup-Worker bleiben getrennt erklärt.
