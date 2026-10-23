# Placetel Dashboard für Nextcloud

Native Nextcloud-App für Placetel mit Dashboard, Agentenstatistik, Warteschleifenansicht und optionaler DispoLive-Anbindung. DispoLive kann in den Einstellungen ein- oder ausgeschaltet werden.

Dieses Repository enthält den Entwicklungsstand 0.2.0 als noch nicht veröffentlichten App-Store-Kandidaten. Die Veröffentlichung des Quellcodes ist freigegeben; Zertifikatsantrag und Store-Vorbereitung laufen. Es gibt noch keine App-Store-Veröffentlichung und keine Release-Signatur.

## Voraussetzungen und Installation

Nextcloud 35, PHP ab 8.3 und ein eigener Placetel-API-Zugang. Die App läuft nativ mit PHP; ein separater Node-Server ist nicht erforderlich. Installation und Einrichtung: [App-Anleitung](placetel/README.md). Die vollständige Schritt-für-Schritt-Anleitung ist zusätzlich in der App aufklappbar und als [Textfassung](placetel/help/ereignisanbindung.txt) enthalten.

Die Installation verwendet ein gemeinsames Placetel-Konto für die freigegebenen Nextcloud-Benutzer. Zugriffsgruppen in den App-Einstellungen festlegen. Persönliche Schlüssel werden erst in der installierten App eingegeben und verschlüsselt gespeichert.

## Oberfläche bauen

```sh
npm ci
npm run build
```

Node.js wird nur zum Entwickeln/Bauen benötigt. Das installierbare App-Verzeichnis ist `placetel/`; React-Quellen liegen in `ui-src/`. Der Build erzeugt Oberfläche und Textanleitung aus derselben Inhaltsquelle.

## Optionale DispoLive-Popups

Für Anrufermeldungen muss Placetel den signaturgeschützten HTTPS-Empfangspfad erreichen können. Für zeitnahe Verarbeitung paralleler Anrufe steht ein optionaler PHP/OCC-Worker bereit. Die Installation der App legt keinen Serverdienst an. HTTPS, Cron und Worker sind in der Einrichtungsanleitung ausführlich erklärt.

## Prüfungen und offene Abnahme

Die nativen Backendprüfungen liefen auf isolierten Nextcloud-35.0.1- und -35.0.2-Instanzen mit jeweils 152 erfolgreichen Prüfungen und gemocktem API-Transport. Spätere Änderungen an Oberfläche und Anleitung wurden durch Build, React-Darstellung und reproduzierten Quellbuild geprüft; sie sind kein neuer kompletter Nextcloud-Installationstest. Die echte Anbieter-, Zielserver- und DispoLive-Browserabnahme bleibt erforderlich.

Tests verwenden ausschließlich synthetische Daten: [Testanleitung](tests/native/README.md). Niemals gegen eine produktive Anlage ausführen. Vor späterer Veröffentlichung gelten die Punkte in [VEROEFFENTLICHUNG.md](VEROEFFENTLICHUNG.md), darunter Maintainer, Metadaten, Signatur und weitere Abnahmen.

## Datenschutz und Lizenz

Keine betrieblichen Daten oder Zugangsdaten gehören in dieses Repository. [Datenschutz](placetel/DATENSCHUTZ.md), [AGPL-3.0-Lizenz](LICENSE), [Drittanbieter-Lizenzen](placetel/THIRDPARTY-LICENSES.txt).

## Projekt unterstützen

[♥ Projekt über PayPal unterstützen](https://www.paypal.me/Werkstattmanager)

In der App steht unten in der Seitenleiste **Projekt unterstützen**. Vor dem Wechsel
zu PayPal erklärt ein Hinweis die freiwillige Unterstützung: Die App bleibt kostenlos,
keine zusätzlichen Funktionen oder individuellen Leistungen und keine Spendenbescheinigung.
Der Betrag ist bei PayPal frei wählbar. Der Empfänger ist dasselbe öffentliche
PayPal-Profil wie im Werkstattmanager; er kann nicht über Einstellungen oder
Anrufdaten verändert werden. GitHubs Sponsor-Link verwendet dieselbe Adresse.

Keine eingebetteten Zahlungsdienste, Skripte oder Vorabverbindungen. Erst der
Weiterklick öffnet PayPal in einem neuen Tab ohne Referrer. Die App überträgt dabei
keine Anruf-/Kontaktdaten und verarbeitet oder speichert keine Zahlungen.

## Neue Funktionen im Entwicklungsstand

- Gesamttelefonzeit pro Agent mit getrennter eingehender und ausgehender Zeit.
  Live-Dauern werden wegen fehlender bestätigter Annahmezeitpunkte als Näherungen
  ausgewiesen; laufende und unbekannte Dauern bleiben separat.
- Optionale DispoLive-Popups lassen sich je angerufener Rufnummer an mehrere
  Arbeitsplätze verteilen. Die Regeln werden vom Administrator eingerichtet.

Der Quellstand kann vor Ausstellung des App-Zertifikats aktualisiert werden.
Diese Änderungen sind noch kein signiertes Release und kein App-Store-Angebot.
Die neue native Nextcloud-Laufzeitabnahme steht noch aus.
