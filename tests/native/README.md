# Native Integrationstests

Ausschließlich frische Wegwerf-Nextcloud mit synthetischen Daten verwenden.
`PLACETEL_DISPOSABLE_TEST=1` ist Pflicht. Niemals Produktionsinstallation oder
Originaldatenbank verwenden. Keine echten Placetel-/DispoLive-Schlüssel eintragen.

- setup.py benötigt Docker, ein lokal vorhandenes Nextcloud35-/Postgres18.6-Image
  und /tmp/placetel-native-app-test.tar.gz (Archivwurzel placetel/).
- Variablen: PLACETEL_NATIVE_FIXTURE_DIR, PLACETEL_NATIVE_NAME,
  PLACETEL_NATIVE_PORT und PLACETEL_NATIVE_IMAGE. Standardnamen sind Wegwerf-Namen.
- contract.php im www-data-Kontext des Wegwerfcontainers ausführen. Es benötigt
  /tmp/native-parity.json. HTTP-Transport ist über die öffentliche NC35-Client-API
  vollständig synthetisch. Nur kompletter Marker NATIVE_CONTRACT_COMPLETE zählt.
- run-http.py in derselben Docker-Umgebung ausführen; es liest die dort erzeugten
  Dummy-Zugangsdaten. Diese Dateien niemals exportieren oder veröffentlichen.
- Ergebnisse bestehen aus Checks und Zählern. Eigene Container, Volumes, Netz und
  Dummy-Zugangsdaten anschließend gezielt entfernen; kein globales Docker-Prune.

Die Paritätsdatei ist eine rein synthetische Vergleichsreferenz für Statistik,
Rufnummern und Auswertungen. Anmeldungs-/Rollen-/CSRF-Tests verwenden echten
Nextcloud-Code. Layoutabnahme im angemeldeten Browser bleibt eine eigene Prüfung.

Popup-Prüfungen: popups.php prüft Versandzustände/Fehler mit synthetischem externen
Transport; webhook-contract.php startet kurzzeitig webhook-router.php ausschließlich
auf Container-Loopback. Er benutzt den echten Request/Controller und einen Mock-
DispoLive-Client. Niemals den Router in custom_apps oder im öffentlichen Webroot
installieren. verify.py führt die vollständige Kette aus und verlangt Marker sowie
40+70+40+7 erfolgreiche Checks (aktualisierte Suite; neue Abnahme offen); anschließend OCC-Worker/Job und Paketdateien prüfen.
