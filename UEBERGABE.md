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
