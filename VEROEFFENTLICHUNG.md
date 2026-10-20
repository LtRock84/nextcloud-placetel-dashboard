# Veröffentlichung vorbereiten

Dieses Verzeichnis enthält ausschließlich allgemeine öffentliche Projekttexte. Private NAS-Aufträge, lokale Backups, Betriebsprotokolle und Referenzprojekte gehören **nicht** in ein öffentliches Repository oder Release.

`npm run build:public` erzeugt ein minimales App-Installationsarchiv und ein separates vollständiges Quellpaket. Laufzeitpaket ohne Tests/Buildtools/Node-Server; Quellpaket mit PHP, React-Quellen, Buildskript, Lockfile und künstlichen Prüf-Fixtures. Kein Node/Composer auf dem Nextcloud-Server erforderlich. Node wird nur zum Entwickeln/Bauen der Oberfläche verwendet.

Vor echter Veröffentlichung:

1. Öffentliche Repository-/Supportadresse und verantwortlichen Maintainer festlegen. Die example.org-Adresse in info.xml ist **ausdrücklich ein Platzhalter**. `PLACETEL_PUBLIC_SUPPORT_URL` und `PLACETEL_PUBLIC_AUTHOR` beim Releasebuild setzen; `npm run check:public -- --release` verweigert Platzhalter/fehlende Signatur.
2. Verfügbarkeit/Registrierung der App-ID `placetel` beim Nextcloud App Store prüfen. Eine ergebnislose öffentliche Suche beweist nicht, dass die ID frei oder registriert ist. ID nicht nach Veröffentlichung beliebig ändern.
3. Kompatibilitätsmatrix, native Browser-/Mobil-/Tastatur-/Dark-Mode-/Lastabnahme und Anbieter-Testkonto prüfen. Keine tatsächliche Portal-Abnahme aus Mocktests ableiten. Beschränkung auf NC35 beibehalten, bis weitere Versionen real getestet sind.
4. Code-/Sicherheitsreview, Abhängigkeits-/Lizenzprüfung, reproduzierbaren Quellbuild und Paket-Hashvergleich finalisieren. Öffentliches Repository bereitstellen, nicht diesen privaten Arbeitsordner veröffentlichen.
5. Eigenen Signierschlüssel **außerhalb** des Quellcodes/Releaseverzeichnisses verwalten, CSR für diese App-ID beim Nextcloud-Zertifikatsprozess beantragen. Schlüssel anderer Projekte niemals übernehmen. Ein eigener CSR für placetel wurde am20.10.2026 eingereicht: https://github.com/nextcloud/app-certificate-requests/pull/1359. Der private Schlüssel wird ausschließlich im macOS-Schlüsselbund des Release-Verantwortlichen verwahrt. Ausstellung und Signierung stehen aus.
6. Finales sauberes App-Verzeichnis mit `occ integrity:sign-app` signieren, danach nicht verändern, natives Integritätsresultat und Archivsignatur prüfen. Öffentlichen Download bereitstellen und erst danach im Store einreichen. Keine automatische Veröffentlichung durch diese Skripte.

Offizielle Quellen (20.10.2026):
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/release_process.html
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html
- https://docs.nextcloud.com/server/stable/developer_manual/basics/backgroundjobs.html

Die Minimalinstallation „App installieren + eigenen Schlüssel eingeben“ ist technisch umgesetzt. Voraussetzung ist eine normal funktionierende Nextcloud mit HTTPS, Datenbank und Standard-Hintergrundjobs. Fehlende Callcenter-Rechte oder Anbieterfunktionen lassen sich nicht durch die App ersetzen.

Aktueller öffentlicher Quellstand: https://github.com/LtRock84/nextcloud-placetel-dashboard
Maintainer: LtRock84. Support: dortiger Issue-Tracker. Noch kein Store-Release.
