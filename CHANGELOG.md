# Changelog  

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Geändert
- Optimierung der `AbstractService` Klasse
  - Hinzufügung von `declare(strict_types=1);`
  - Verbesserung der PHPDoc-Kommentare
  - Aktualisierung der Methodensignaturen mit Rückgabetypen
  - Typsichere Implementierung der statischen `instance()` Methode
  - Optimierung der Hilfsmethoden mit strikter Typisierung

- Optimierung der `AbstractJob` Klasse
  - Hinzufügung von `declare(strict_types=1);`
  - Verbesserung der Fehlerbehandlung in `handleApiException`
  - Aktualisierung der PHPDoc-Kommentare mit präziseren Rückgabetypen
  - Implementierung von strikter Typisierung für alle Methoden
  - Verbesserung der Lesbarkeit und Wartbarkeit des Codes

- Optimierung der `Completion` Service-Klasse
  - Hinzufügung von `declare(strict_types=1);`
  - Verbesserung der Fehlerbehandlung mit spezifischen Ausnahmen
  - Detailliertere PHPDoc-Kommentare für Ausnahmen
  - Verbesserte Code-Formatierung für bessere Lesbarkeit
  - Optimierung der Methodensignaturen
  - Konsistente Verwendung von `catch`-Blöcken
  - Verbesserte Strukturierung von Service-Aufrufen

- Optimierung der `ManualTask` Service-Klasse
  - Hinzufügung von `declare(strict_types=1);`
  - Umwandlung von `CONFIG_KEY` in private Konstante
  - Verbesserung der `getNumberOfManualTasks` Methode mit strikter Typisierung
  - Optimierung der `update` Methode mit verbesserter Fehlerbehandlung
  - Einführung der neuen privaten Methode `saveManualTaskCount`
  - Verbesserung der Datenbankoperationen mit sicheren SQL-Queries
  - Aktualisierung der PHPDoc-Kommentare
  - Implementierung von besserer Fehlerbehandlung und Validierung

- Optimierung der `Refund` Service-Klasse
  - Hinzufügung von `declare(strict_types=1);`
  - Verbesserung der `getExternalRefundId` Methode mit Rückgabetyp und PHPDoc
  - Optimierung der `create` Methode:
    - Detaillierte Array-Typdefinitionen für `$reductions`
    - Strikte Typisierung für `$restock` Parameter
    - Verbesserte Fehlerbehandlung mit spezifischen Meldungen
  - Verbesserung der `send` Methode:
    - Korrektur der API-Exception-Behandlung
    - Typsichere Implementierung mit Rückgabetypen
  - Optimierung der privaten Hilfsmethoden:
    - Präzise Typdefinitionen für Arrays und Objekte
    - Verbesserte Typumwandlungen für numerische Werte
  - Einheitliche Verwendung von modernen PHP-Konstrukten
  - Umfassende PHPDoc-Dokumentation
  - Implementierung von strikter Fehlerbehandlung

- Optimierung der `VoidJob` Service-Klasse
  - Hinzufügung von `declare(strict_types=1);`
  - Verbesserung der `create` Methode:
    - Hinzufügung von PHPDoc mit Parametern und Rückgabetyp
    - Implementierung von strikter Typisierung
    - Verbesserte Fehlerbehandlung mit spezifischen Meldungen
  - Optimierung der `send` Methode:
    - Hinzufügung von PHPDoc mit Parametern und Rückgabetyp
    - Typsichere Implementierung mit Rückgabetypen
    - Korrektur der API-Exception-Behandlung
    - Verbesserte Fehlerbehandlung
  - Einheitliche Verwendung von modernen PHP-Konstrukten
  - Verbesserte Code-Formatierung und Lesbarkeit

- Optimierung der `Webhook` Service-Klasse
  - Hinzufügung von `declare(strict_types=1);`
  - Verbesserung der Eigenschaftsdeklarationen mit Nullability und Typen
  - Optimierung der `install` Methode:
    - Strikte Typisierung für Parameter und Rückgabewert
    - Verbesserte Fehlerbehandlung mit spezifischen Ausnahmen
    - Klarere Logik für die Webhook-Installation
  - Verbesserung der privaten Hilfsmethoden:
    - Typsichere Implementierung mit präzisen Rückgabetypen
    - Optimierte Webhook-URL und Listener-Verwaltung
  - Entfernung von ungenutztem Code und veralteten Methoden
  - Modernisierung der Array-Syntax
  - Verbesserte Code-Formatierung und Lesbarkeit
  - Umfassende PHPDoc-Dokumentation

### Sicherheit
- Verbesserte Fehlerbehandlung in allen Service-Klassen
- Sicherere SQL-Query-Generierung
- Strikte Typisierung zur Vermeidung von Typ-bezogenen Fehlern
- Verbesserte Validierung von Eingabeparametern

### Technische Verbesserungen
- Einführung von PHP 8.2 Kompatibilität
- Verbesserung der Code-Qualität und Wartbarkeit
- Optimierung der Performanz durch bessere Typisierung
- Vereinheitlichung des Coding-Stils 