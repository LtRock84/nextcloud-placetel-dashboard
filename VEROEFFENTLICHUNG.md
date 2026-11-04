# Veröffentlichung vorbereiten

Die native App befindet sich unter `placetel/`; ihre Oberfläche wird aus
`ui-src/` gebaut. Im öffentlichen Repository und in Releasepaketen dürfen keine
Zugangsdaten, privaten Betriebsdaten oder Kundeninformationen enthalten sein.

Vor jedem Release: Änderungen und Datenschutz prüfen, isolierte Tests mit
synthetischen Daten ausführen, Version in `appinfo/info.xml` und Hinweise in
`appinfo/release-notes.json` sowie das Changelog aktualisieren. Den App-Store-
Kandidaten vollständig bauen und die veröffentlichte Dateiliste prüfen.

Das ausgestellte Zertifikat gehört zur App-ID `placetel`. Der private
Signierschlüssel bleibt außerhalb des Repositories. Die App-Dateien müssen für
das genaue veröffentlichte Paket neu signiert und die Signatur überprüft werden.
Auch das Release-Archiv erhält eine separate Signatur für den App Store. Nach
der Signierung dürfen die enthaltenen Dateien nicht mehr verändert werden.

Anschließend den signierten Stand unter
https://github.com/LtRock84/nextcloud-placetel-dashboard als Release bereitstellen
und das Archiv mit seiner Signatur im Nextcloud App Store einreichen. Ein
erfolgreicher Mocktest ersetzt keine kontrollierte Prüfung mit dem Anbieter.

Offizielle Hinweise: https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/release_process.html
und https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html.
