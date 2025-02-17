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
  - Verbesserung der Fehlerbehandlung
  - Aktualisierung der Methodensignaturen mit Rückgabetypen
  - Implementierung von strikter Typisierung
  - Verbesserung der PHPDoc-Kommentare
  - Optimierung der Datenbankoperationen

- Optimierung der `ManualTask` Service-Klasse
  - Hinzufügung von `declare(strict_types=1);`
  - Umwandlung von `CONFIG_KEY` in private Konstante
  - Verbesserung der `getNumberOfManualTasks` Methode mit strikter Typisierung
  - Optimierung der `update` Methode mit verbesserter Fehlerbehandlung
  - Einführung der neuen privaten Methode `saveManualTaskCount`
  - Verbesserung der Datenbankoperationen mit sicheren SQL-Queries
  - Aktualisierung der PHPDoc-Kommentare
  - Implementierung von besserer Fehlerbehandlung und Validierung

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