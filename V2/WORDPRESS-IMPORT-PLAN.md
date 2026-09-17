# Trend in Form – WordPress-Importplan

## Zielbild

Die statische Website bleibt die visuelle Referenz. WordPress speichert die importierten Inhalte jedoch nicht als einen unübersichtlichen HTML-Block, sondern als einzeln editierbare Text-, Link- und Medienfelder. Das Theme setzt diese Felder zur Laufzeit wieder in die geprüfte HTML-Struktur ein.

```text
Statische HTML/CSS/JS-Dateien
        ↓ Manifest + Vorprüfung
Parser → Text / Links / Bilder / Hintergründe / Video
        ↓ stabile Feldschlüssel
Leadwerk Fields (native post_meta, ACF-ähnliche Oberfläche)
        ↓
Leadwerk Theme → WordPress-Permalinks + Media Library + WPForms
```

## Pakete

- `leadwerk-fields`: native ACF-ähnliche `get_field()`-/`update_field()`-API, Seiten-Metaboxen und zentrale Website-Einstellungen.
- `leadwerk-wpml-clone`: DE/EN-Verknüpfung und Übersetzungsfelder; URLs und Medien bleiben gemeinsam nutzbar.
- `leadwerk_importer`: Vorprüfung, Medienimport, Seitenerstellung, Feldzuordnung, SEO-Metadaten und abschließende WordPress-Konfiguration.
- `leadwerk_theme`: rendert die importierte Struktur, löst interne `.html`-Referenzen zu WordPress-Permalinks auf und stellt Header, Footer, 404 sowie WPForms bereit.

## Importreihenfolge

1. WordPress-Datenbank und `wp-content` sichern.
2. WPForms Pro aktivieren.
3. `leadwerk-fields`, `leadwerk-wpml-clone` und `leadwerk_importer` nach `wp-content/plugins/` kopieren.
4. `leadwerk_theme` nach `wp-content/themes/` kopieren und aktivieren.
5. `leadwerk_importer/wpforms-trend-in-form-projektanfrage.json` über **WPForms → Werkzeuge → Importieren** importieren.
6. Importer zuerst als Dry Run starten. Nur bei null blockierenden Fehlern live importieren.
7. Unter **Einstellungen → Trend in Form Website** die Formular-ID, Kontaktdaten, Navigation, Footer, Logos und 404-Texte kontrollieren.
8. Permalinks einmal speichern beziehungsweise Rewrite-Regeln leeren.

## Vorprüfungen und Abbruchbedingungen

Der Import darf keine WordPress-Einstellungen finalisieren, wenn eine der folgenden Prüfungen fehlschlägt:

- Manifest fehlt, enthält doppelte `source_key`-Werte oder doppelte Slugs.
- HTML-Datei oder `404.html` fehlt beziehungsweise lässt sich nicht parsen.
- Ein referenziertes Bild, Hintergrundbild, Video oder Poster fehlt.
- Leadwerk Fields oder das Trend-in-Form-Theme ist nicht aktiv.
- Ein Medium kann nicht in die Mediathek übernommen oder einem Feld zugeordnet werden.

## Datenmodell und Redaktionsworkflow

- Jede sichtbare Textstelle wird als eigener `text_items`-Eintrag gespeichert.
- Jeder Link wird als eigener `link_items`-Eintrag gespeichert.
- Bilder, CSS-Hintergründe, Video-Poster und Videos werden als `media_items` mit Mediathek-ID gespeichert.
- Stabile Feldschlüssel verbinden die gespeicherten Werte mit genau einem DOM-Knoten.
- Wiederholte Imports aktualisieren die Struktur, erhalten aber redaktionell geänderte Texte, URLs, Bildauswahl und Alternativtexte für bestehende Feldschlüssel.
- Header, Footer, Navigation, Kontaktdaten, Logos, mobile CTA und 404-Inhalte werden zentral gepflegt.

## URL- und Formularregeln

- `index.html`, `danke.html`, `legal.html`, `impressum.html` und `datenschutz.html` werden zu sprachfähigen WordPress-Permalinks aufgelöst.
- Fragmentlinks zeigen auf die WordPress-Startseite, zum Beispiel `/#kontakt`.
- WPForms verwendet zwei echte Seiten, einen Fortschrittsbalken, GDPR-Pflichtfeld und die lokale Danke-Seite.
- Das Theme erzwingt die Danke-URL anhand des Formulartitels, damit der Export auch auf einer anderen Domain oder in einem Unterverzeichnis portabel bleibt.

## Abnahmetests

- Startseite, Danke, Rechtliches, Impressum und Datenschutz liefern HTTP 200.
- Eine unbekannte URL liefert den gestalteten Inhalt mit echtem HTTP-404-Status.
- Keine aktive Ausgabe enthält `.html`-, Kraft-Fliesen-, PNG-, JPG- oder JPEG-Referenzen.
- Alle Medienfelder besitzen eine gültige Attachment-ID.
- WPForms: Schritt 1 validiert Pflichtfelder; Schritt 2 enthält Raumgröße, Lichtwunsch, Lichtfarbe, Termin, Nachricht und Datenschutz; erfolgreicher Versand führt zu `/danke/`.
- Desktop und 390-px-Mobilansicht haben keine horizontale Überbreite; mobile Felder stehen einspaltig.
- Browser-Konsole bleibt frei von JavaScript-Fehlern.
- Zweiter Live-Import erzeugt weder neue Seiten noch doppelte Medien.

## Rollback

1. Datenbank-Sicherung einspielen.
2. Vorheriges Theme aktivieren.
3. Die drei Leadwerk-Plugins deaktivieren.
4. Bei einem partiellen Import nur Seiten und Medien mit den Metaschlüsseln `leadwerk_source_key` beziehungsweise `leadwerk_source_path` prüfen; keine pauschale Datenbankbereinigung ausführen.

## Lokaler Abnahmestand vom 12.08.2026

- Ziel: `http://trend-in-form.local/`
- WordPress 7.0.3, PHP 8.3.30, WPForms Pro 1.9.9.4.
- 5 Seiten, 28 eindeutige Medien, 264 Textfelder, 43 Linkfelder und 35 Medienfelder importiert.
- Dry Run und Live-Import: 0 Fehler, 0 Warnungen.
- Wiederholungsimport: gleiche Seiten-IDs und weiterhin 28 Medien; keine Duplikate.
- WPForms-Testversand und Danke-Weiterleitung erfolgreich; der ausschließlich für QA erzeugte Testeintrag wurde danach entfernt.
- Sicherung vor Inhaltsimport: `/Users/atlas/Local Sites/trend-in-form/backups/pre-content-import-2026-08-12.sql`.
