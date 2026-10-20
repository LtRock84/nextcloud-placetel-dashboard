# App-Store-Vorbereitung

Stand: 20.10.2026. Der Eigentümer hat die öffentliche Sichtbarkeit des bereinigten
Quellrepositorys sowie den Zertifikatsantrag freigegeben.

- App-ID: placetel (endgültige Zuteilung durch Nextcloud noch offen).
- App: Placetel Dashboard. Maintainer: LtRock84.
- Support: https://github.com/LtRock84/nextcloud-placetel-dashboard/issues
- CSR mit CN=placetel und RSA-4096 ist vorbereitet und lokal geprüft.
- Zertifikatsantrag: https://github.com/nextcloud/app-certificate-requests/pull/1359 (eingereicht).
- Zertifikat: noch nicht ausgestellt.
- Store-Release: noch nicht eingereicht.

Die Nachprüfung der Zertifikatsanträge fand keinen bestehenden CSR unter
placetel/placetel.csr; das ist keine verbindliche Reservierung der App-ID.

Nach dem Antrag dessen URL hier ergänzen. Nach Ausstellung CN, Vertrauenskette
und Zugehörigkeit zum eigenen Schlüssel prüfen. Finales sauberes App-Verzeichnis
signieren, danach unverändert archivieren und öffentlich als Download anbieten.
Archivsignatur nach App-Store-Verfahren erzeugen. Erst dann im Store einreichen.

Vor der Freigabe bleiben die dokumentierten echten Zielsystem-/Anbieter-/Browser-
abnahmen sowie Sicherheits-, Abhängigkeits- und Lizenzreview offen. Unterstützte
Nextcloud-Versionen nicht ungeprüft ausweiten. Eine mögliche Vorabversion muss
auch technisch eine passende Vorabversionsnummer tragen.

Offizieller Ablauf:
https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html
