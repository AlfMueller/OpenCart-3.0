# OpenCart 3.0.4.0 - Wallee Zahlungsmodul (PHP 8.2 Kompatibel)

Dieses Repository enthält eine optimierte Version des Wallee Zahlungsmoduls für OpenCart, das es dem Shop ermöglicht, Zahlungen mit [Wallee](https://www.wallee.com) zu verarbeiten. Da Wallee keine aktive Unterstützung mehr für das Modul bietet, wurde diese Version speziell für die Kompatibilität mit PHP 8.2 angepasst und optimiert.

## Wichtige Hinweise

- Diese Version ist ein Community-Fork des originalen Wallee-Moduls
- Speziell optimiert für OpenCart 3.0.4.0
- Angepasst für volle PHP 8.2 Kompatibilität
- Enthält wichtige Sicherheitsupdates und Verbesserungen
- Aktiv in Entwicklung - Community-Beiträge willkommen!

## Entwicklungsstatus & Mitwirkung

Dieses Projekt befindet sich aktiv in der Entwicklung. Wir laden die Community herzlich ein, sich an der Weiterentwicklung zu beteiligen:

- 🐛 Fehler melden über GitHub Issues
- 💡 Verbesserungsvorschläge einbringen
- 🔧 Pull Requests für Bugfixes oder neue Features einreichen
- 📖 Dokumentation verbessern
- 🌍 Übersetzungen beisteuern

Jeder Beitrag ist willkommen! Gemeinsam können wir sicherstellen, dass das Modul auch in Zukunft sicher und effizient funktioniert.

## Branch-Informationen

Der aktuelle Entwicklungszweig ist `feature/php82-initial-setup`. Dieser Branch ist öffentlich zugänglich und kann von jedem eingesehen werden:

```bash
git clone https://github.com/AlfMueller/OpenCart-3.0.git
git checkout feature/php82-initial-setup
```

Für eigene Entwicklungen empfehlen wir:
1. Forken Sie das Repository
2. Erstellen Sie einen eigenen Feature-Branch
3. Committen Sie Ihre Änderungen
4. Erstellen Sie einen Pull Request

## Anforderungen

* [OpenCart](https://www.opencart.com/) 3.0.4.0
* PHP 8.2
* Ein aktives [Wallee](https://app-wallee.com/user/signup) Konto

## Hauptänderungen

- Strikte Typisierung für alle PHP-Klassen
- Modernisierte Array-Syntax
- Verbesserte Fehlerbehandlung
- Optimierte Datenbankabfragen
- Aktualisierte Namespace-Verwendung
- Verbesserte Dokumentation

## Installation

1. Sichern Sie Ihren Shop (Dateien und Datenbank)
2. Laden Sie die aktuelle Version des Moduls herunter
3. Laden Sie die Moduldateien in Ihr OpenCart-System hoch
4. Führen Sie eventuell notwendige Datenbankaktualisierungen durch
5. Konfigurieren Sie das Modul in Ihrem OpenCart-Backend

## Dokumentation

* [Englische Dokumentation](https://plugin-documentation.wallee.com/wallee-payment/opencart-3.0/1.0.58/docs/en/documentation.html)

## Support

Da dies eine Community-Version ist, wird der Support über GitHub Issues bereitgestellt. Bei spezifischen Fragen zu Ihrer Wallee-Integration können Sie sich an den [Wallee Support](https://app-wallee.com/space/select?target=/support) wenden.

## Lizenz

Dieses Projekt steht unter der Apache License 2.0. Weitere Details finden Sie in der [Lizenzdatei](https://github.com/wallee-payment/opencart-3.0/blob/1.0.58/LICENSE).