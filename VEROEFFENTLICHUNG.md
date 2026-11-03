# Veröffentlichung

Das installierbare Paket enthält die native Nextcloud-App. Node wird nur für den UI-Build benötigt. Private Konfiguration, Zugangsdaten und Betriebsdaten gehören nicht in das Repository oder das Paket.

Vor einer Veröffentlichung den sauberen Quellstand bauen, auf künstlichen Daten und unterstützten Nextcloud-Versionen prüfen, Metadaten und Paketinhalt kontrollieren und anschließend das endgültige App-Verzeichnis mit dem eigenen Zertifikat signieren. Nach der Signierung dürfen die App-Dateien nicht verändert werden. Danach Integrität und Archivsignatur prüfen, das Archiv als GitHub-Release bereitstellen und im App Store einreichen.

Die App verwendet Nextcloud 35 und PHP ab 8.3. Placetel-Zugang, HTTPS-Empfang und der optionale DispoLive-Worker müssen auf dem jeweiligen Zielsystem eingerichtet und geprüft werden.

Weitere Informationen: https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/release_process.html und https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html
