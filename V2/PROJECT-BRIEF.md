# Project Brief — Trend in Form

Gemeinsame Quelle für HTML, GTD-Profil und Raidboxes-Import. Unbekannte Punkte nicht erfinden.

## Identität und Ziel

- Projektname: Trend in Form
- Sichtbare Marke: Trend in Form GmbH
- Firma: Trend in Form GmbH
- Nutzen: Spanndecke, Licht und Akustik aus einer Hand, Montage meist an einem Tag, ohne Abriss
- Primärziel: Termin vor Ort / Kostenvoranschlag (Google-Ads später auf /spanndecke/)
- Zielgruppe: Privat (Bad, Küche, Wohnraum) und Objekt/Gewerbe in Karlsruhe, Pforzheim und Umgebung
- Ton: Du, klar, ohne Agentursprech. Keine Stellenangebote mehr.

## Seiten

- Startseite `/` (`tif-home`)
- Spanndecke `/spanndecke/` (`tif-spanndecke`)
- Impressum `/impressum/` (`tif-impressum`, noindex)
- Datenschutz `/datenschutz/` (`tif-datenschutz`, noindex)
- Danke `/danke/` (`tif-danke`, noindex, WPForms-Bestätigung)
- 404 Systemseite (`tif-404-v1`, noindex)

Bestehende WordPress-Seiten mit denselben Source-Keys bleiben erhalten. Import legt keine neuen WPForms an.

## Marke und Medien

- Logo hell: `assets/logo.png`, Logo dunkel/negativ: `assets/logo_negativ.png`
- Favicon: `assets/icons/`
- OG: `assets/og-image.jpg` (1200×630)
- Farben: Gelb `#F7D954`, Petrol `#3E7C87`
- Schriften: Outfit / Inter, lokal in `assets/fonts`
- Hero-Video: `assets/hero-video.mp4` und `assets/hero-video-mobile.mp4`, Laden erst nach Seitenaufbau
- Referenz-Slider: `data-src` / `data-srcset`, Platzhalter `assets/lazy-pixel.gif`

## SEO und Messung

- Start: Focus „Spanndecken Karlsruhe“
- Spanndecke: Focus „Spanndecke Karlsruhe“
- JSON-LD liegt im statischen Head (LocalBusiness, FAQ, Service). In WordPress übernimmt GTD/Yoast, keine Doppelung.
- Tracking, Consent und Datenschutz-Anpassung an den Live-Stack: Raidboxes/Atlas nach Dev-Import
- Alte Sitemap-URLs: 301 über Overlay (Hash-Ziele wie `/#b2b` bleiben im Overlay, nicht in GTD-Redirects)

## Formular und Recht

- Empfänger: mk@trend-in-form.de, Kopie jonask@trend-in-form.de
- Formular: bestehendes WPForms, IDs in Leadwerk Optionen (`wpforms_form_id_de` zuerst)
- `features.forms` bleibt false, Overlay tauscht nur `#leadForm`
- Impressum/Datenschutz: Kundenstand, Plugins der alten Seite (Maps, reCAPTCHA, Wordfence) entfernt
- Partnerlogos Fuller / Der Fliesenbruder / Wand Atelier: noch Platzhalter, Nachlieferung Florian

## Veröffentlichung

- Statische Vorschau: https://leadwerk-web.github.io/trendinform/V2/
- GTD-Profil: `projects/trendinform/`, `releaseEnabled` true, öffentlicher Theme Market
- Live erst nach Rückmeldung an Florian, Domain ohne www, Mail bleibt bei ALL-Inkl
