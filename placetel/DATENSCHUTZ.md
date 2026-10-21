# Daten und externe Verbindungen

Ein Administrator richtet ein gemeinsames Placetel-PROFI-Konto ein. Angemeldete Nutzer sehen dessen Daten entsprechend den konfigurierten Gruppenrechten. Es gibt keine Mandantentrennung zwischen mehreren Firmenkonten innerhalb einer einzelnen Installation. Verschiedene Nextcloud-Installationen haben getrennte Datenbanken und Schlüssel.

Gespeichert werden die für Dashboard/Statistik erforderlichen Anrufkennungen, Rufnummern, Agenten-/Queue-Kennungen und Namen, Status, Zeitstempel, Abdeckung sowie lokale Rückrufnotizen und Änderungsprotokolle. Anrufdaten werden nach zwölf Monaten entfernt; Auditliste ist zusätzlich auf100 Einträge begrenzt. Keine Gesprächsaufzeichnungen.

API-Schlüssel werden serverseitig durch Nextclouds öffentliche ICrypto-API authentifiziert verschlüsselt. Sie erscheinen nicht in Bootstrap-Antworten, Protokollen, Browser-Bundles oder Releasepaketen. Die Nextcloud-Konfiguration ist Teil der Vertrauensgrenze; Administratoren mit Zugriff auf Server und dessen Konfigurationsgeheimnis können die Daten entschlüsseln. Vollständige, geschützte Backups von Datenbank und Nextcloud-Konfiguration sind daher erforderlich.

Kernbetrieb verbindet sich ausschließlich per HTTPS zur offiziellen Placetel-API unter api.placetel.de. Keine Drittanbieter-Telemetrie, keine extern geladenen Schriften oder Analyse-Skripte.

DispoLive ist standardmäßig aus. Erst nach eigener Mandanten-/Schlüsseleinrichtung und Aktivierung werden normalisierte erkennbare Rufnummern zur Kontaktsuche an den angegebenen HTTPS-Mandanten *.dispolive.de gesendet. Die Suche verwendet notification:false. Keine Fahrten-/Patienten-Vollimporte. Dieser Suchmodus sendet keine Popups; ein separat aktivierter Popup-Versand ist unten beschrieben. Gespeichert werden minimale Treffer (Name, Typ, ggf. ID) mit Status und Zeit; Suchcache positiv15min, negativ2min, maximale Aufbewahrung30Tage. Abschalten stoppt neue Abfragen und blendet Namen aus. Bereits übertragene Daten können dadurch nicht beim Anbieter zurückgeholt werden. Nicht mehr benötigte Zugangsschlüssel im Anbieterportal widerrufen.

Der optionale öffentliche Notify-Endpunkt akzeptiert ausschließlich unterstützte Form-Ereignisse mit gültiger HMAC-Signatur und begrenzter Größe. Er gewährt keinen Dashboard-Zugriff. Ereignisse werden nach Anruf-ID/Transporthash idempotent gespeichert; keine unbestätigte Zuordnung ihrer Dauer zu Callcenter-Statistiken.

Deaktivierung erhält Konfiguration/Historie für Wiederaufnahme. Vor endgültiger Datenentfernung Exporte/Backups und lokale Aufbewahrungsvorgaben beachten. Ein automatischer Löschvorgang beim App-Disable ist bewusst nicht vorgesehen.

## Optionaler Popup-Versand

Separat standardmäßig ausgeschaltet. Bei Aktivierung werden neue signierte eingehende
Anrufe mit Rufnummer, eingestelltem Arbeitsplatz-Telefon und ggf. Firmenkürzel an den
eigenen DispoLive-Mandanten übertragen (notification:true). Kontakte werden vom
Anbieter bestimmt; die lokale normale Suche bleibt notification:false.
Eine Call-ID-Zustellliste speichert Zustellstatus, Zeiten und Versuche maximal30 Tage;
Konfiguration enthält Arbeitsplatz und ausgewählte eigene Zielrufnummern. In der UI
werden aktuelle Zustellzustände angezeigt. Bereits gesendete Browsermeldungen kann
ein späteres Ausschalten nicht zurückrufen. Keine Patientendaten/Rohantworten in Logs.

## Freiwillige Projektunterstützung

Der Unterstützungsbutton öffnet zunächst nur einen lokalen Hinweis. Erst ein
weiterer Klick öffnet das feste öffentliche PayPal.Me-Profil Werkstattmanager in
einem neuen Tab ohne Referrer. Es gibt keine eingebetteten PayPal-Skripte oder
Vorabverbindungen und keine Übergabe von Anruf-, Kontakt- oder Zugangsdaten.
Die App speichert keine Zahlungen. Für die bewusst geöffnete PayPal-Seite gelten
die dortigen Datenschutzbedingungen. Unterstützung verändert keine App-Rechte.
