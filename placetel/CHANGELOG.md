# Änderungen

## 0.2.0 – nativer Veröffentlichungskandidat

- Native Nextcloud-PHP-App ohne separaten Node-/Gateway-Dienst.
- Eigenes Placetel-Konto in den App-Einstellungen einrichten; verschlüsselte Schlüsselspeicherung und Schutz vor Vermischung mit anderem Konto.
- DispoLive als standardmäßig ausgeschaltete Zusatzfunktion mit eigenem Mandanten und Ein-/Ausschalter.
- Nextcloud-Gruppenrollen, zentrale Abrufsteuerung und Hintergrundjobs.
- Bestehendes Dashboard, Statistiksemantik, Rückrufnotizen, Routingvorschau und signierte Notify-Ereignisse übernommen.
- Unsupported Skills-/Zuordnungsplanung bleibt gesperrt. Routingzeiten folgen dem Cron-Takt.
- Öffentliche Pakete verwenden eine gesonderte Dateiauswahl und enthalten keine Betriebs-/Kundendaten oder privaten NAS-Unterlagen.
- Noch kein Store-Upload oder signiertes öffentliches Release.

### Ergänzung: optionaler DispoLive-Popup-Versand

- Signed-IncomingCall → notification:true mit explizitem Arbeitsplatz-Ziel und
  optionalem Firmenkürzel/Zielnummernfilter; standardmäßig aus, nur Admin konfiguriert.
- Persistente Call-ID-Deduplizierung, Zustellstatus, begrenzte429-Wiederholung,
  90s-Ablauf und keine blinden Wiederholungen nach unbekanntem Ergebnis.
- Getrennte Empfangs-/Versandsperren, gemeinsames DispoLive-Ratenlimit;
  optionaler nativer PHP/OCC-Worker für wartende Meldungen.

## Noch nicht veröffentlicht – 21.10.2026

- Freiwillige Projektunterstützung in der Seitenleiste mit vorgeschaltetem Hinweis,
  gleichem PayPal.Me-Ziel wie Werkstattmanager und ohne Zahlungsintegration.
- Unterstützungslink in Projektbeschreibung und GitHub-Sponsor-Konfiguration.
