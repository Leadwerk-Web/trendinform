# Static source contract (Global Theme Distribution)

Copy this file to the root of every **new** customer HTML repository. Cursor and
humans authoring pages must follow it. The full GTD registry, build commands and
release rules live in `Global Theme Distribution/AGENTS.md`.

This repository contains **only** canonical HTML and local assets. WordPress theme
PHP, importer code, plugins, generated ZIPs and credentials do not belong here.
WordPress never reads this GitHub repository. GTD compiles a signed theme ZIP;
after import, HTML lives in WordPress and CSS/JS stay in the theme.

## Schemas (do not copy into this HTML repo)

Canonical JSON schemas live in the GTD registry
`Global Theme Distribution/schemas/`. This HTML repository must not duplicate
them. The technical profile in `projects/<slug>/` references them via `$schema`.

| Schema | Role |
| --- | --- |
| `schemas/project.schema.json` | Project identity, page discovery, routes, WordPress, build gates |
| `schemas/project-variables.schema.json` | Company, brand, contact, features (no secrets) |
| `schemas/content.schema.json` | Compiled content package inside the theme ZIP |
| `schemas/release.schema.json` | Signed release manifest |
| `schemas/translation-seed.schema.json` | Optional translation seeds |

Starter copies (GTD registry, not this repo):

1. `templates/project/leadwerk.project.json` → `projects/<slug>/project.json`
2. `templates/project/project.variables.json` → `projects/<slug>/project.variables.json`
3. Keep `templates/project/leadwerk.modules.json` next to the profile as the
   Leadwerk Optionen module contract (do not copy it into the HTML repo).
4. Point `source.root` at this HTML checkout.
5. Keep `$schema` as `../../schemas/project.schema.json` (and the variables schema)
   when the files live under `projects/<slug>/`.
6. New sites: `"pagePatterns": ["**/*.html"]`. Set a stable `sourceKey`, `route`
   and `template` in `pageOverrides` **before first release**.
7. `build.editableContentPolicy` is `require`. For a public company starter,
   centralized onboarding enables stable release only after validate and dry-run
   pass; a failed gate publishes no profile. Explicit legacy/sensitive projects
   may keep a reviewed QA policy.
   New sites also use `build.qualityProfile: "production"`: local logo/favicon,
   a system/noindex 404, accessible image alternatives and a local OG image are
   hard gates rather than optional polish.
   Treat `404.html` and `danke.html` as designed conversion/support surfaces,
   not bare text fallbacks. Start from the Starter's responsive `utility-*`
   composition and adapt it to the project's existing tokens, typography and
   visual language without deleting its semantic `<main>`, single `<h1>`,
   clear recovery actions, decorative `aria-hidden` artwork or editable
   `notfound.*` / `thanks.*` fields. Both pages stay `noindex`; 404 remains the
   system template and Danke remains the form confirmation target.
8. New sites ship the full Leadwerk Optionen menu: Fields, Importer, Theme Center
   (companion plugin `wordpress/leadwerk-theme-center`), Übersetzungen /
   Sprachen / Diagnose, and the Migration screens. Set
   `wordpress.modules` to `["fields", "importer", "translations", "migration"]`
	and keep `build.moduleSources` for translations and migration. Do not put
	those PHP sources in the HTML repository.
	The canonical source language may be German, English or Turkish and is inferred
	from the root `index.html` during automatic onboarding. Language-prefixed preview
	folders (`de/`, `en/`, `tr/`) are not imported as duplicate WordPress pages;
	target languages are activated in the embedded Leadwerk Sprachen screen.
	Place one empty `<div data-lw-language-switcher-slot hidden></div>` inside the
	main header navigation. When at least two published languages exist, WordPress
	replaces this slot with the accessible project-styled header language menu.
	Never create a floating language switcher. Existing projects with explicit
	`data-lw-language-target` links keep their own header/menu implementation.
	Keep the slot in the header flex flow next to the mobile menu control; never
	position it with `fixed` or absolute page coordinates. The shared translation
	module renders a compact globe-only control on mobile and owns its neutral
	glass surface, focus state and viewport-safe dropdown. Project CSS must not
	reintroduce a blue pill background or hide the injected control on mobile.
   `brand.logo` and `brand.favicon` must be real source paths (for example
   `svg/logo.svg`). After import they preselect Logo and Favicon in
   Leadwerk Optionen.
9. Contact forms are WPForms Lite, not a theme module. Install the companion
   plugin `wpforms-lite` (1.10.2+) **before** the first import with
   `features.forms: true`. Set a typed `forms` object in
   `project.variables.json` (provider `wpforms`, unique `sourceFormId`,
   `thankYouSourceKey`, `privacySourceKey`, fields including `email`). Exactly
   one HTML `<form id="{sourceFormId}">` must exist in the project. Import
   replaces that shell with `[leadwerk_gtd_form]`, creates the WPForms form
   and writes **WPForms Formular-ID** in Leadwerk Optionen. The site starter
   already ships exactly one `<form id="kontakt-form"
   data-lw-wpforms="contact">`; automatic onboarding recognizes this explicit
   marker and generates the typed form contract without a manual profile step.

## WPForms

WPForms Lite is a companion plugin from wordpress.org (`wpforms-lite`). It is
not bundled in the theme ZIP. Theme Center does not install it.

When `features.forms` is true:

1. Install and activate WPForms Lite ≥ `forms.minimumVersion` (default 1.10.2)
   before import. WordPress must be 6.9+ so `wpforms/create-form` is available.
2. `contact.formRecipient` is required. Do not put API keys or passwords in
   `project.variables.json`.
3. `forms.sourceFormId` must identify **exactly one** `<form>` in the HTML
   source. Static preview keeps that markup; after import the importer swaps it
   for the live WPForms shortcode.
4. `thankYouSourceKey` and `privacySourceKey` must be discovered `sourceKey`
   values (typically `danke.html` and `datenschutz.html`).
5. Field keys are stable. One field must be `email` with type `email` (Reply-To).
   Checkbox and radio fields need `choices`.
6. Import creates or reconciles the form and fills Leadwerk Optionen
   `wpforms_form_id`. Do not hard-code the numeric ID in HTML.
7. Import configures the owned form's native WPForms confirmation as a redirect
   to `thankYouSourceKey` for AJAX and non-JavaScript submissions. The theme's
   `wpformsAjaxSubmitSuccess` listener is a compatibility fallback, not the
   primary redirect mechanism.
8. Form CSS must cover **both** the static shell and the WordPress WPForms
   markup. `.form input` does not win against WPForms plugin CSS. The class
   names after import are stable; extend the same rules (or pair them) so the
   live form looks like the local preview.

### Form CSS after import

The importer replaces `<form id="{sourceFormId}" class="form">` with:

```html
<div class="form leadwerk-wpforms" data-leadwerk-form-slot="{forms.key}">
  <div class="leadwerk-wpforms">
    <div class="wpforms-container wpforms-container-full wpforms-render-modern" id="wpforms-{n}">
      <form id="wpforms-form-{n}" class="wpforms-validate wpforms-form wpforms-ajax-form">
        <div class="wpforms-field-container">
          <div id="wpforms-{n}-field_{id}-container" class="wpforms-field wpforms-field-text|email|textarea|checkbox|radio">
            <label class="wpforms-field-label">…</label>
            <input class="wpforms-field-medium"> or <textarea> or checkbox list
          </div>
        </div>
        <div class="wpforms-submit-container">
          <button type="submit" class="wpforms-submit" id="wpforms-submit-{n}">…</button>
        </div>
      </form>
    </div>
  </div>
</div>
```

Use these selectors, never a numeric form ID (`#wpforms-60`):

| Local shell | WPForms after import |
| --- | --- |
| `form.form` | `.leadwerk-wpforms`, `.leadwerk-wpforms .wpforms-container`, `.leadwerk-wpforms .wpforms-form` |
| `.form` layout / gap | `.leadwerk-wpforms .wpforms-field-container` |
| `.form label` | `.leadwerk-wpforms .wpforms-field-label` |
| `.form input`, `.form textarea` | `.leadwerk-wpforms .wpforms-field input:not([type="checkbox"]):not([type="radio"])`, `.leadwerk-wpforms .wpforms-field textarea` |
| checkbox / radio label | `.leadwerk-wpforms .wpforms-field-label-inline` |
| `.form .btn`, `button[type="submit"]` | `.leadwerk-wpforms .wpforms-submit` |
| privacy `<a>` | `.leadwerk-wpforms .leadwerk-privacy-link` |

WPForms modern markup also reads CSS variables. Override them on `.leadwerk-wpforms` with the same tokens as the local fields and button:

`--wpforms-field-border-radius`, `--wpforms-field-background-color`, `--wpforms-field-border-color`, `--wpforms-field-text-color`, `--wpforms-label-color`, `--wpforms-button-background-color`, `--wpforms-button-border-color`, `--wpforms-button-text-color`, `--wpforms-button-border-radius`. Also set the **size** variables or WPForms keeps its 41px/17px defaults and the live button is smaller than `.form .btn`: `--wpforms-button-size-height`, `--wpforms-button-size-padding-h`, `--wpforms-button-size-font-size`, `--wpforms-field-size-input-height`, `--wpforms-field-size-padding-h`.

Write one rule for both surfaces:

```css
.form,
.leadwerk-wpforms .wpforms-field-container {
  display: grid;
  gap: 14px;
  max-width: 520px;
}
.form input,
.form textarea,
.leadwerk-wpforms .wpforms-field input:not([type="checkbox"]):not([type="radio"]),
.leadwerk-wpforms .wpforms-field textarea {
  width: 100%;
  padding: 14px 16px;
  border-radius: 16px;
  border: 1px solid #d7c9ae;
  background: #fff;
  font: inherit;
}
.form .btn,
.leadwerk-wpforms .wpforms-submit {
  /* same padding, radius, background, color as the site button */
}
.leadwerk-wpforms {
  --wpforms-field-border-radius: 16px;
  --wpforms-field-background-color: #fff;
  --wpforms-field-border-color: #d7c9ae;
  --wpforms-button-background-color: var(--tomato);
  --wpforms-button-text-color: #fff;
  --wpforms-button-border-radius: 999px;
}
```

The theme ZIP ships a generic `assets/forms.css` reset only (width, honeypot, modal). Brand look stays in the project stylesheet. Register and login shells that must stay HTML keep a **different** `id` from `forms.sourceFormId`.

Example `project.variables.json` fragment:

```json
"features": { "translations": true, "forms": true, "migration": true },
"forms": {
  "provider": "wpforms",
  "key": "contact",
  "minimumVersion": "1.10.2",
  "title": "Kontakt",
  "submitLabel": "Nachricht senden",
  "sourceFormId": "kontakt-form",
  "thankYouSourceKey": "example-danke-v1",
  "privacySourceKey": "example-datenschutz-v1",
  "privacyLinkText": "Datenschutzerklärung",
  "confirmationMessage": "Vielen Dank für deine Nachricht.",
  "fields": [
    { "key": "name", "type": "text", "label": "Name", "required": true },
    { "key": "email", "type": "email", "label": "E-Mail", "required": true },
    { "key": "message", "type": "textarea", "label": "Nachricht", "required": true },
    {
      "key": "consent",
      "type": "checkbox",
      "label": "Datenschutz",
      "required": true,
      "choices": ["Ich habe die Datenschutzerklärung gelesen."]
    }
  ]
}
```

```html
<form id="kontakt-form" class="form" data-lw-wpforms="contact" action="danke.html" method="post">
```

## First push and automatic onboarding

This contract file is the repository opt-in marker. After the first clean push
of a public repository owned by the immutable `Leadwerk-Web` GitHub organization
identity, central GTD discovery normally reconciles it within five minutes and
passes validate plus dry-run and creates a public stable Theme Market profile.
The checkout may be located in any
workstation folder; local absolute paths are never part of project identity.
Desktop folder scanning is only a convenience for local diff/validation and a
manual folder picker is recovery, not an onboarding requirement. Private
repositories require an explicitly reviewed GitHub App/server credential.

The same central reconciliation enables GitHub Pages in branch-publishing mode
with the repository default branch (`main` for starter projects) and `/` as the
publishing root. The static preview is then available at
`https://leadwerk-web.github.io/<repository>/`; later clean pushes to `main`
publish through GitHub's Pages build automatically. Keep `.nojekyll` at the
repository root. Pages is a preview/deployment surface only: GTD still builds the
separately signed WordPress theme and never imports content from the Pages URL.

## Raster normalization before every push

The source repository stores deployment-ready image bytes. Before committing,
Cursor/Codex or another project agent must scan the whole repository for `.jpg`,
`.jpeg` and `.png` files **case-insensitively** and normalize every match:

1. Apply EXIF orientation, then encode WebP at quality `85`; preserve alpha and
   correct color appearance.
2. Keep the original directory. Derive a deterministic lowercase ASCII
   kebab-case basename and always use the lowercase `.webp` extension. The same
   input path must always produce the same output path.
3. Update every reference in HTML, CSS, JavaScript, JSON and manifests, including
   `src`, `srcset`, `poster`, CSS `url()`, structured data and Open Graph metadata.
4. Decode/inspect the WebP and search the repository for old references. Delete
   the JPG/JPEG/PNG original **only after** both checks succeed.
5. Fail closed if conversion fails, a reference is ambiguous, or two files would
   normalize to the same output path. Never overwrite one image with another and
   never delete the originals on a partial run.

The starter CI rejects committed JPG/JPEG/PNG sources and non-lowercase WebP
paths. SVG, video and font files are not part of this raster conversion. Keep the
starter `.nojekyll` file so GitHub Pages serves all static paths without Jekyll
rewrites.

## New page

1. Add a real HTML document (`about.html` or `services/index.html`).
2. One `<main>`, one `<h1>`, `<html lang>` matching the project locale.
3. Non-empty `<title>` and meta description. No production host in canonical URLs.
4. Annotate editable nodes (`data-lw-section`, `data-lw-field`, `data-lw-type`).
5. Internal links are project paths or `data-lw-page-ref`. No `href="#"`.
6. Images and fonts are local. Filenames lowercase. No Google Fonts / CDN in
   production markup.
7. If the GTD profile uses an explicit `pagePatterns` list, add the file there
   and set `pageOverrides.sourceKey` **before first release**. New sites should
   use `**/*.html` so discovery is automatic; `sourceKey` still belongs in
   `pageOverrides`.
8. Validate with GTD (`gtd validate` / `gtd build --dry-run`). Do not weaken
   validators. Push a clean commit. Theme Center installs the next published ZIP;
   a wp-admin popup then applies only HTML/field/section/media changes and offers
   the next server version. Unchanged pages are not rewritten. CSS/JS stay in the
   theme ZIP.

## Rich sites from Codex, Fable, Vite or another builder

Give GTD the production static export. Every public route is a real, prerendered
HTML document with its critical text/media/links present before JavaScript runs.
A blank SPA mount element is not editable, indexable or importable and must be
prerendered first.

- Local classic scripts and `type="module"` entrypoints work. Relative static
  imports and `new URL(..., import.meta.url)` dependencies are discovered.
  Bundle bare package imports; do not use inline executable scripts, import maps,
  `async`, `nomodule` or remote runtime CDNs.
- For assets discovered only at runtime (`fetch`, worker, WASM, GLTF/GLB, JSON,
  computed image/video names), add a narrow `source.pinnedAssets` glob. Keep them
  inside an `assetRoot` and resolve them relative to the module URL.
- Tabs, accordions, modals, animation, canvas/WebGL and consent-gated embeds are
  supported as progressive enhancement. Keyboard, focus, reduced motion and a
  no-JS fallback remain part of the source contract.
- Login, cart, payment, search, API, CPT or SSR behavior needs a centrally reviewed
  WordPress overlay/adapter. Never put PHP or credentials in this repository.
- Do not annotate generated wrapper hashes. Put stable `data-lw-field`, section,
  repeater and item keys on semantic nodes whose identity survives a rebuild.

## Content, brand and SEO brief

Complete `PROJECT-BRIEF.md` before production. It is the factual source for the
agent: company/legal name, address, contact/form recipient, audience, offer,
service area, tone, page goals, target phrases, social profiles, brand assets,
required modules and legal review owner. Do not fabricate missing facts.

Every public page has a useful title and description. Add a page-specific local
`og:image` or set `seo.defaultOgImage` to a 1200×630 WebP. Existing GTD projects
may still contain PNG/JPEG/WebP, but newly authored raster files must follow the
normalization contract above. Meaningful
images have descriptive alt text; decorative images are explicitly marked. The
profile points to real local logo and favicon files. Canonical URL, OG URL,
hreflang and structured Organization/LocalBusiness data are generated from the
installed site and the typed project variables.

Every HTML document also declares one page-specific Yoast seed in `<head>`:

```html
<meta name="leadwerk:focus-keyphrase" content="Primary service plus place or audience">
```

Use the approved phrase from `PROJECT-BRIEF.md`; do not copy one generic phrase
to every page. GTD imports it into `_yoast_wpseo_focuskw`. If an older source
omits it, GTD safely seeds the normalized H1 so Fokus-Keyphrase is never blank,
but explicit intent is preferred.

## Annotations

```html
<section data-lw-section="hero">
  <p data-lw-field="hero.eyebrow" data-lw-type="text">...</p>
  <h1 data-lw-field="hero.title" data-lw-type="richtext">...</h1>
  <img data-lw-field="hero.image" data-lw-type="image"
       src="assets/hero.webp" alt="...">
</section>
```

- `data-lw-field` is a stable unique dot path. Never derive it from visible text.
- Types: `text`, `textarea`, `richtext`, `url`, `email`, `number`, `boolean`,
  `image`, `video`, `file`, `page_reference`, `choice`, `html`.
- Repeaters use `data-lw-repeater` plus a permanent `data-lw-item-key`.
- Header/footer shared values use `data-lw-global`. Same key, same type everywhere.
- JS-only content is not importable unless the seed exists in the HTML.
- Production pages must not use `data-lw-type="html"`. Never put a complete
  `<ul>`, multi-paragraph container, gallery or card into one raw-HTML field.
  Annotate each semantic child: list/card/gallery containers use
  `data-lw-repeater`, every repeated child gets a permanent
  `data-lw-item-key`, plain copy uses `text`/`textarea`, inline formatting uses
  `richtext`, and every media node uses `image`/`video`/`file`. This keeps the
  WordPress editor compact and prevents editors from touching markup.

## Static vs WordPress parity

WordPress does not serve the HTML files. Import writes the `<body>` fragment into
`post_content`, enqueues CSS/JS from the theme ZIP, and rewrites local `src` /
`href` to Media Library URLs and permalinks. The static preview and the live
frontend look the same only if the source already follows these rules. Do not
patch WordPress after import to hide a source mistake.

1. **Image filenames must not equal page slugs.** WordPress attachments steal
   permalinks. `firenze.webp` plus page `/firenze/` becomes `/firenze-2/`. Use a
   prefix that cannot collide (`assets/cover-firenze.webp`, never
   `assets/firenze.webp`).
2. **SVG is ASCII-only.** The importer sanitizer / Latin-1 path rejects umlauts
   in SVG markup. Keep logos and favicons in ASCII (`Gelato`, not `Geläto`).
3. **Cart and similar JS must store live image URLs.** After import,
   `assets/cover-….webp` 404s. On add-to-cart read `img.currentSrc || img.src`
   from the card in the DOM, not the authored relative path in `data-*`.
4. **JS navigation must use rewritten permalinks.** `location.href = "konto.html"`
   404s on WordPress. Resolve via `document.querySelector('a[data-lw-page-ref="…"]').href`
   (the importer already rewrote those anchors). Keep the `.html` path only as
   static fallback.
5. **Form CSS must cover both shells.** `.form input` does not win against
   WPForms. Pair every field/button rule with `.leadwerk-wpforms` /
   `.wpforms-*` and set `--wpforms-*` on `.leadwerk-wpforms`. Never target
   `#wpforms-60`. See the class map above. Register/login keep **different**
   `id`s from `forms.sourceFormId`. Exactly one HTML form matches that id.
   WPForms field **labels** come from `project.variables.json`, not from the
   HTML `<label>` text. Keep umlauts and wording identical in both places.
6. **`[hidden]` loses to `display: grid`.** Account cards, empty-cart notices
   and similar siblings that use grid/flex stay visible if you only set the
   `hidden` attribute. Every site stylesheet needs:

   ```css
   [hidden] { display: none !important; }
   ```

7. **Do not hard-code production hosts** in canonical tags, CSS `url()`, or JS.
   Internal links stay project paths plus `data-lw-page-ref`.
8. **Body class from source is preserved** (`_leadwerk_gtd_body_classes`). You
   may scope CSS to `body.dd`. Theme.json / block-library CSS are already
   dequeued by the suite. Brand look stays in the project stylesheet, not in
   the generic `assets/forms.css` (that file is a reset; two-column form grids
   belong only under `.modal`).

Copy-paste helpers live in `templates/project/js/progressive-enhancement.js`.

## Forbidden

- Inline event handlers, `javascript:` URLs, localhost endpoints, embedded secrets
- Changing a released `sourceKey` because the filename or title changed
- Skipping published theme versions on a live WordPress site
- `/en/` HTML shells unless the project is authored multilingual
- Guessing missing schema; fail the build instead
