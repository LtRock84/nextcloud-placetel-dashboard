# App-Store-Status

Stand 03.11.2026: Nextcloud hat das Zertifikat für die App-ID `placetel` ausgestellt. Version 0.2.1 ist als stabiles Release im Nextcloud App Store und auf GitHub veröffentlicht. Das Zertifikat wurde mit dem privaten Schlüssel abgeglichen; 157 synthetische Prüfungen auf Nextcloud 35.0.2 sind für Version 0.2.0 bestanden. Die Abnahme auf individuellen Zielsystemen bleibt erforderlich.

Store: https://apps.nextcloud.com/apps/placetel
Release: https://github.com/LtRock84/nextcloud-placetel-dashboard/releases/tag/v0.2.1

Version 0.2.1 ergänzt zwei öffentliche Demo-Screenshots. Ihre Originaldateien sind
erreichbar; der Nextcloud-Bildproxy lieferte bei der Prüfung noch keine Bilddaten.
Die Plattformliste markierte Nextcloud 35.0.0 als nicht unterstützt; der öffentlich
einsehbare Synchronisierungscode filtert solche Plattformen heraus. Dies ist eine
begründete Vermutung zur Ursache, der tatsächliche Serverbetrieb ist nicht geprüft.

Zertifikatsantrag: https://github.com/nextcloud/app-certificate-requests/pull/1359

Der private Signierschlüssel gehört weder in das Repository noch in das App-Paket.
