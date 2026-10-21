# Übernahme des App-Repositorys

Stand: 20.10.2026. Ziel ist ein später allgemein nutzbares Placetel Dashboard für Nextcloud; DispoLive bleibt eine optionale Zusatzfunktion. Keine Veröffentlichung ohne ausdrücklichen Auftrag.

## Aktueller Stand

Native PHP-App 0.2.0 für Nextcloud 35, PHP ab 8.3. React-Oberfläche mit reproduzierbarem Build. API-Zugang und Gruppenrechte werden pro Installation eingerichtet. Dashboard, Statistiken, Telefonbuchabgleich und optionale DispoLive-Anbindung sind implementiert. Popup-Versand über signierten Empfangspfad und optionalen PHP/OCC-Worker. Anleitung mit9 Kapiteln/53 Schritten, davon12 für Nextcloud-Administration.

## Offene Aufgaben

- Auf dem tatsächlichen Zielsystem Installation, regulären Cron, öffentlichen HTTPS-Empfang und Worker samt Neustart prüfen.
- Kontrollierte Anbieter-/Browserabnahme: eingehender Anruf, richtiger Kontakt, richtige DispoLive-Anzeige; API-Bestätigung allein beweist keine sichtbare Meldung.
- Vor Veröffentlichung Maintainer/Supportadressen, App-ID, Signatur, Lizenz-/Sicherheitsreview und Kompatibilitätsabnahme abschließen.
- Keine Geheimnisse, echten Anrufdaten, privaten Infrastrukturberichte oder Backups einchecken.

## Arbeitsweise

Diese Datei bei Änderungen um Stand, Prüfung und nächste Schritte ergänzen. Nur isolierte synthetische Daten für Tests verwenden. Produktionsänderungen benötigen eine ausdrücklich ausgelöste Bedienaktion. Die private ursprüngliche Desktopinstallation und deren Daten sind nicht Bestandteil dieses Repositorys.

## Zertifikat und App Store

20.10.2026: Eigentümer autorisiert ausdrücklich die öffentliche Sichtbarkeit dieses
Repositorys sowie Zertifikatsantrag und anschließende Store-Veröffentlichung.
Maintainer LtRock84, Support über den Issue-Tracker dieses Repositorys.
Öffentlicher CSR für App-ID placetel ist vorbereitet; der private Signierschlüssel
bleibt ausschließlich beim Release-Verantwortlichen, niemals in Git/Release.
Noch kein Zertifikat ausgestellt, keine native Signatur und kein Store-Release.
Nächster Schritt: Zertifikatsantrag mit öffentlichem Quelllink bei Nextcloud
einreichen; nach Ausstellung Zertifikat und Schlüsselzugehörigkeit prüfen.
Vor Store-Release verbleibende Abnahmen durchführen; keine Mockprüfung als
echte Anbieter-/Browserabnahme ausgeben. Status in STORE-STATUS.md fortführen.

Repository ist jetzt öffentlich. Zertifikatsantrag am20.10.2026 eingereicht:
https://github.com/nextcloud/app-certificate-requests/pull/1359
Nur ein öffentlicher CSR im Antrag, keine privaten Schlüssel. Ausstellung durch
Nextcloud steht aus; danach Signatur und Store-Einreichung fortsetzen.

## DCO-Korrektur am 21.10.2026

Der ursprüngliche CSR-Commit hatte keinen Signed-off-by-Vermerk. Der Zertifikats-
antrag #1359 war deshalb durch DCO blockiert; die vorige Statusprüfung hatte
Check-Runs übersehen. Commit im ausschließlich hierfür verwendeten Branch mit
erwartetem altem Head ersetzt: 35ca305268f21627b55fa5ce56ed9544f3f16c6d.
Baum/CSR und Basis unverändert; weiterhin genau ein Commit und eine Datei.
DCO-Check danach abgeschlossen mit success:
https://github.com/nextcloud/app-certificate-requests/runs/119044985450
Antrag weiterhin offen; Zertifikat noch nicht als ausgestellt bestätigt.
Künftige Statusprüfungen müssen Check-Runs und Workflow-Läufe berücksichtigen,
nicht ausschließlich PR-Kommentare oder Combined Status. Lokaler ursprünglicher
Arbeitsordner war bei dieser Prüfung nicht eingebunden; deshalb Übergabe hier
im Repository fortgeführt. Nächster Schritt bleibt Bearbeitung durch Nextcloud.

## Laufend: freiwillige Projektunterstützung (21.10.2026)

Werkstattmanager-Quellstand als Referenz gelesen: ProjectSupport.vue und
project-support.ts. Derselbe feste öffentliche PayPal.Me-Empfänger übernommen.
Neue React-Komponente mit Hinweisdialog, bewusster Weiterleitung in neuem Tab,
noopener/noreferrer und no-referrer, keine externen Skripte/Requests vor Weiterklick.
Seitenleiste, README, FUNDING.yml, Datenschutz, Changelog und App-Store-Metadaten
ergänzt. Kein zahlungsabhängiger Funktionsumfang. Build bestanden; Dialogprüfung
bestanden (DOM-Simulation, kein echter Browser-/Layouttest). Ursprünglicher Arbeitsordner auf externem Volume nicht eingebunden;
aktueller GitHub-Stand in temporärem Checkout /private/tmp/placetel-donation-work
bearbeitet. Lokaler Desktopdienst kann dadurch noch nicht aktualisiert werden.

Prüfung: sauberer npm-ci-Aufbau, Produktionsbuild, info.xml gegen das vorhandene
offizielle Schema gültig. Isolierte React-/DOM-Prüfung bestätigt Hinweis, festes
Zahlungsziel, neues Tab, noopener/noreferrer und no-referrer, Schließen/Abbrechen
und null Netzwerkanfragen. Native Dialogfunktionen im DOM simuliert; reale
Tastatur-/Fokus-/Mobilansicht im Browser noch prüfen.20 Backend-Dateien unverändert.
Kein Zahlungsversuch, keine Produktionsdaten oder Zertifikatsdateien verändert.
Neue Komponente ui-src/ProjectSupport.tsx; Empfänger derselbe wie Werkstattmanager.
Bei Wiederanbindung des ursprünglichen Volumes Änderungen gezielt in Desktop-
Quellen übernehmen und neu bauen; nicht dessen Daten/Schlüssel ersetzen.
Noch kein App-Store-Release oder NAS-Update.
