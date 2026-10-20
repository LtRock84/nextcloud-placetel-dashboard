# Übernahme des privaten App-Repositorys

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
