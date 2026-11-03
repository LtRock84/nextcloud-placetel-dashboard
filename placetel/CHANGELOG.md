# Änderungen

## 0.2.3

- Rückrufliste: Alle aktuell gefilterten offenen Einträge können gemeinsam als erledigt markiert werden; vorhandene Notizen bleiben erhalten.
- Eine nachweislich spätere Annahme schließt den zugehörigen Rückruf automatisch ab und zeigt den Annahmezeitpunkt grün an.
- Rückrufe ohne nachweisbare spätere Annahme bleiben offen und werden mit einem roten Hinweis klar erkennbar. Gleichzeitige Zeitstempel zählen nicht mehr fälschlich als spätere Annahme.

## 0.2.2

- Nextcloud 34 zusätzlich freigegeben; grundlegende Funktionen auf 34.0.5 mit Beispieldaten geprüft.
- App-Store-Kategorie zu Einbindung geändert.

## 0.2.1

- Vorschauaufnahmen des Dashboards und der Agentenstatistik im Nextcloud App Store ergänzt. Die Aufnahmen zeigen ausschließlich fiktive Demodaten.

## 0.2.0

- Native Nextcloud-PHP-App ohne separaten Node-/Gateway-Dienst.
- Eigenes Placetel-Konto in den App-Einstellungen einrichten; verschlüsselte Schlüsselspeicherung und Schutz vor Vermischung mit anderem Konto.
- DispoLive als standardmäßig ausgeschaltete Zusatzfunktion mit eigenem Mandanten und Ein-/Ausschalter.
- Nextcloud-Gruppenrollen, zentrale Abrufsteuerung und Hintergrundjobs.
- Bestehendes Dashboard, Statistiksemantik, Rückrufnotizen, Routingvorschau und signierte Notify-Ereignisse übernommen.
- Unsupported Skills-/Zuordnungsplanung bleibt gesperrt. Routingzeiten folgen dem Cron-Takt.
- Öffentliche Pakete verwenden eine gesonderte Dateiauswahl und enthalten keine Betriebs-/Kundendaten oder privaten NAS-Unterlagen.
- Gesamttelefonzeit je Agent mit getrennten eingehenden und ausgehenden Näherungswerten.
- Optionale DispoLive-Popups je angerufener Nummer an mehrere Arbeitsplätze.

### Ergänzung: optionaler DispoLive-Popup-Versand

- Signed-IncomingCall → notification:true mit explizitem Arbeitsplatz-Ziel und
  optionalem Firmenkürzel/Zielnummernfilter; standardmäßig aus, nur Admin konfiguriert.
- Persistente Call-ID-Deduplizierung, Zustellstatus, begrenzte429-Wiederholung,
  90s-Ablauf und keine blinden Wiederholungen nach unbekanntem Ergebnis.
- Getrennte Empfangs-/Versandsperren, gemeinsames DispoLive-Ratenlimit;
  optionaler nativer PHP/OCC-Worker für wartende Meldungen.

### Projekt unterstützen

- Freiwillige Projektunterstützung in der Seitenleiste mit vorgeschaltetem Hinweis,
  gleichem PayPal.Me-Ziel wie Werkstattmanager und ohne Zahlungsintegration.
- Unterstützungslink in Projektbeschreibung und GitHub-Sponsor-Konfiguration.
