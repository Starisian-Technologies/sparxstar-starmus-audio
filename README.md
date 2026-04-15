![Sparxstar Starmus Audio](https://github.com/user-attachments/assets/c51b26bb-f95f-4d8c-9340-dacdacca5d4f)

# Sparxstar Starmus Audio

**Frontline audio acquisition for the SPARXSTAR platform — built for Africa.**

A mobile-first, offline-capable WordPress plugin that captures audio, enforces contributor consent, normalises metadata to the DVE schema, and produces certified artifacts for the AiWA corpus.  Primary deployment target: 2G/3G networks and low-end Android devices in West Africa (The Gambia and surrounding regions).

---

**=== Sparxstar Starmus Audio ===**

**Contributors:** Starisian Technologies, Max Barrett

**Tags:** WordPress, Audio, Web Audio API, recorder, offline, tus, resumable, africa, gambia, oral-history

**Requires at least:** 6.8

**Tested up to:** 6.9

**Requires PHP:** 8.2

**Stable tag:** v0.9.2

**License:** Starisian Technologies Proprietary License (STPD) — See LICENSE.md

**Copyright:** Copyright (c) 2025-2026 Starisian Technologies. All rights reserved.

[![CodeQL](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/github-code-scanning/codeql/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/github-code-scanning/codeql) [![Copilot](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/copilot-swe-agent/copilot/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/copilot-swe-agent/copilot) [![Copilot code review](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/copilot-pull-request-reviewer/copilot-pull-request-reviewer/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/copilot-pull-request-reviewer/copilot-pull-request-reviewer) [![Dependabot Updates](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/dependabot/dependabot-updates/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/dependabot/dependabot-updates)

[![Security Checks](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/security.yml/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/security.yml) [![Proof HTML, Lint JS & CSS](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/proof-html-js-css.yml/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/proof-html-js-css.yml) [![Release Code Quality Final Review](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/release.yml/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/release.yml) [![Generate Documentation](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/docs.yml/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-starmus-audio/actions/workflows/docs.yml)

---

## 🌍 Mission

Starmus is the **frontline acquisition layer** of the SPARXSTAR platform.  Its sole function is to capture audio, enforce contributor consent, normalise metadata to the DVE (Digital Village Elder) schema, and produce certified artifacts that graduate into the AiWA corpus.  No data flows into AiWA until Starmus certifies it.

Starmus operates at **Briefcase level (Layer 1)** — group-level acquisition governed by community sovereignty.  It is a data intake system, not a storage system, not a governance system, and not an AI system.

**Design constraints that are non-negotiable:**

- Audio must be capturable on low-end Android (2GB RAM, Android 8+) on a 2G connection.
- Recordings must never be lost — not on network failure, not on browser crash.
- Consent is a forensic declaration that travels with the asset, not a checkbox.
- Offline is a required operational state, not an edge case.

---

## ✨ Key Features

- **Mobile-First, Two-Step UI:** Clean, accessible interface separating metadata entry from recording, optimised for small screens and limited bandwidth.
- **Resumable Uploads (TUS):** Powered by the `tus.io` protocol — uploads in small chunks and auto-resumes after network interruptions, including complete browser close and reopen.
- **Offline Submission Queue:** Failed or offline submissions are saved in IndexedDB and automatically uploaded when connectivity returns.  Critical data is never stored in `localStorage`.
- **Africa-Optimised Audio Pipeline:** Generates bandwidth-tiered versions (2G / 3G / WiFi) via FFmpeg and stores them to Cloudflare R2 for edge delivery close to the contributor.
- **Max Recording Duration Enforcement:** Hard-stops at 120 s (production), 180 s (development), or 300 s (draft) at the capture module level — not just the UI.
- **Progressive Enhancement:**
    - **Tier A (Modern Browsers):** Microphone calibration, noise suppression, real-time speech signal analysis.
    - **Tier B/C (Legacy / Low-End):** Graceful degradation to a simpler recording experience or base64 file-upload fallback.
- **DVE Schema Alignment:** All field names follow the canonical DVE schema (`sparx_sparxstar_*` / `sparx_aiwa_*` prefixes).  See [DVE Schema Alignment v2.0](Starmus_DVE_Alignment_v2.0.pdf).
- **Forensic Consent Records:** Consent is captured as a signatory block (name, IP, UA, geolocation, timestamp) — attributable, non-repudiable, and attached to the asset permanently.
- **Audio Annotation Editor:** `[starmus_audio_editor]` shortcode powered by Peaks.js for segment labelling and transcript sync.

---

## 📦 Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.8 |
| PHP | 8.2 (strict types) |
| Database | MariaDB / MySQL (via WordPress) |
| Storage | Cloudflare R2 (primary) or AWS S3 (backup) |
| Server binary | `ffmpeg` on PATH for audio optimisation |
| Server binary | `audiowaveform` on PATH for editor waveform generation |
| TUS daemon | `tusd` endpoint recommended for resumable uploads |
| Browser (optimal) | Chrome / Firefox / Edge with MediaRecorder API |
| Browser (fallback) | Any browser supporting `<input type="file">` |

---

## 🚀 Installation

1. Download the latest release `.zip` from this repository.
2. In WordPress Admin: **Plugins → Add New → Upload Plugin**.
3. Upload the `.zip` and click **Install Now**, then **Activate**.
4. Configure storage credentials (R2 or S3) in the plugin settings or via `wp-config.php` constants.
5. (Recommended) Deploy a `tusd` server and set the endpoint in plugin settings.

### Configuration Constants

```php
// wp-config.php
define('STARMUS_STORAGE_PROVIDER', 'r2'); // 'r2' or 'aws'

// Cloudflare R2
define('STARMUS_R2_ACCOUNT_ID', '...');
define('STARMUS_R2_BUCKET', 'starmus-audio');
define('STARMUS_R2_ACCESS_KEY', '...');
define('STARMUS_R2_SECRET_KEY', '...');
define('STARMUS_R2_ENDPOINT', 'https://pub.your-r2-custom-domain.com');

// AWS S3 (alternative)
define('STARMUS_S3_BUCKET', '...');
define('STARMUS_S3_REGION', 'us-east-1');
define('STARMUS_S3_ACCESS_KEY', '...');
define('STARMUS_S3_SECRET_KEY', '...');
```

---

## 🖥 Usage

The plugin provides three primary shortcodes:

### 1. Audio Recorder

Displays the two-step recording form.

```php
[starmus_audio_recorder_form]
```

### 2. User's Recordings List

Displays a paginated list of the logged-in user's submissions.

```php
[starmus_my_recordings]
```

### 3. Audio Editor

Displays the Peaks.js annotation editor.  Must be accessed via a secure link containing `post_id` and a nonce, typically generated from the "My Recordings" list.

```php
[starmus_audio_editor]
```

Example URL: `https://yoursite.com/edit-recording/?post_id=123&nonce=...`

---

## 🏗 Architecture

### Platform Role

```
Contributor
    ↓
WordPress Page (Bootstrap injected by PHP before any JS runs)
    ↓
UI Controller (step 1 validation, metadata)
    ↓
Recorder Engine (MediaRecorder, gain, signal analysis)
    ↓
Submissions Handler (TUS or fallback, IndexedDB offline queue)
    ↓
WordPress REST API  (/wp-json/star/v1/upload-chunk etc.)
    ↓
StarmusRESTHandler → StarmusSubmissionHandler → StarmusAudioDAL
    ↓
MariaDB + Cloudflare R2 storage
    ↓
Post-Processing Queue → AiWA corpus (after transcript approval)
```

### Layer Map

| Layer | Path | Responsibility |
|---|---|---|
| Entry | `starmus-audio-recorder.php`, `src/StarmusAudioRecorder.php` | Bootstrap, hook registration |
| API | `src/api/` | REST endpoints: `upload-chunk`, `upload-fallback`, `upload-chunk-legacy`, `status` |
| Core | `src/core/` | Submission, consent, post type registration, settings, asset loader |
| Data | `src/data/` | DAL, base DAL, prosody DAL, schema mapper, job repositories |
| Services | `src/services/` | Audio pipeline, FFmpeg, ID3, waveform, R2/S3 storage (`IStarmusStorageService`), bandwidth detection |
| Frontend (PHP) | `src/frontend/` | Recorder UI, editor UI, re-recorder UI, consent UI, shortcode loader |
| JavaScript | `src/js/` | Recorder, TUS upload, state store, UI, offline queue, SPARXSTAR integration, transcript controller |
| Admin | `src/admin/` | Admin panel, SageMaker job queue, task manager |
| Integrations | `src/integrations/` | HuggingFace, SageMaker, app-mode bridge |
| Schema | `acf-json/` | ACF/SCF field definitions aligned to DVE canonical schema |
| Templates | `src/templates/` | Recorder, editor, re-recorder, consent form, recording detail, my-recordings |
| i18n | `src/i18n/`, `languages/` | Internationalisation, POT files |
| Tests | `tests/` | PHPUnit (unit + integration), Playwright (E2E), JS (metadata schema) |

### Bootstrap Contract

PHP **must** inject the following object before any JS bundle executes:

```js
window.STARMUS_BOOTSTRAP = {
    pageType: "recorder" | "rerecorder" | "editor",
    postId: number | null,
    restUrl: string,
    mode: "draft" | "development" | "production",
    canCommit: boolean,
    transcript: array | null,
    audioUrl: string | null
}
```

JS modules refuse to initialise without this object.  No alternate bootstrap path is permitted.

### DVE Schema Alignment

All metadata fields follow the **DVE (Digital Village Elder) canonical schema**.  Field names use the `sparx_sparxstar_*` (platform fields) and `sparx_aiwa_*` (AiWA corpus fields) prefixes throughout the codebase, SchemaMapper, ACF JSON, and REST responses.

Reference: [Starmus_DVE_Alignment_v2.0.pdf](Starmus_DVE_Alignment_v2.0.pdf)

---

## 📐 Custom Post Types & Taxonomies

| Type | Handle | Purpose |
|---|---|---|
| CPT | `audio-recording` | Primary audio artifact |
| CPT | `consent-agreement` | Forensic consent declaration |
| Taxonomy | `language` | Language / dialect of the recording |
| Taxonomy | `recording_type` | Classification (oral history, song, narrative, …) |

---

## 🔧 Core Hooks

### Actions

**`starmus_before_recorder_render`** — Fires before the recorder form renders.

```php
add_action('starmus_before_recorder_render', function() {
    if (!is_user_logged_in()) wp_safe_redirect(wp_login_url()); exit;
});
```

**`starmus_after_audio_upload`** — Fires after recording and metadata are saved.

```php
add_action('starmus_after_audio_upload', function($audio_post_id, $attachment_id, $form_data) {
    wp_mail(get_option('admin_email'), 'New submission', get_permalink($audio_post_id));
}, 10, 3);
```

**`starmus_before_editor_render`** — Fires before the editor loads.

**`starmus_before_annotations_save`** — Fires via REST before annotations are saved.

**`starmus_after_annotations_save`** — Fires after annotations are saved.

### Filters

**`starmus_audio_upload_success_response`** — Modify the JSON success response.

```php
add_filter('starmus_audio_upload_success_response', function($response, $post_id, $form_data) {
    if (isset($form_data['recording_type']) && 'oral-history' === $form_data['recording_type']) {
        $response['redirect_url'] = home_url('/add-details/?id=' . $post_id);
    }
    return $response;
}, 10, 3);
```

**`starmus_editor_template`** — Override the editor template path.

---

## 🛡 Security

- All REST write endpoints require `upload_files` capability.
- Nonce validation on all form submissions.
- Path traversal protection on uploaded files.
- MIME type and extension allowlist: `audio/webm`, `audio/ogg`, `audio/mpeg`, `audio/wav`, `audio/mp4`.
- Consent is a server-side forensic record — never client-inferred.
- See [SECURITY.md](SECURITY.md) for vulnerability reporting.

---

## 🧑‍💻 Development Setup

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
pnpm install

# Build JS bundles
pnpm build

# Run PHP tests
composer test

# Run JS tests
pnpm test

# Lint PHP
composer lint

# Lint JS + CSS
pnpm lint
```

WP-CLI commands: [docs/WP-CLI.md](docs/WP-CLI.md)

For Composer tools requiring a GitHub token, copy `auth.json.example` to `auth.json` and add your personal access token.

---

## 📋 Standards Alignment Status

| Finding | Severity | Status |
|---|---|---|
| F-01: Sirus integration absent | Critical | Pending (cross-repo, Phase 1 blocker) |
| F-02: Direct AWS SDK use without interface | Critical | ✅ Fixed — `IStarmusStorageService` introduced |
| F-03: No Sirus consent verification | High | Pending (blocked by F-01) |
| F-04: Max recording duration not enforced | High | ✅ Fixed — hard stop at module level |
| F-05: FFmpeg / R2 overlap | High | ✅ Fixed — R2 service is authoritative path |
| F-06: `SELECT *` in JobSearchRepository | High | ✅ Fixed — explicit column list |
| F-07: PASSTHROUGH_ALLOWLIST dual-path | Medium | ✅ Fixed — single FIELD_MAP |
| F-08: `date()` instead of `gmdate()` | Medium | ✅ Fixed — UTC throughout |
| F-09: AI provider integrations hardcoded | Medium | Pending |
| F-10: TUS checksum verification unclear | Medium | Pending — audit required |
| F-11: Template capability checks | Medium | Pending (blocked by F-01) |
| F-12: Raw transaction queries undocumented | Low | ✅ Fixed — inline comment added |
| F-13: UI-only capability check undocumented | Low | ✅ Fixed — inline comment added |
| F-14: LanguageSignalAnalyzer 20 s window | Low | ✅ Fixed — capped at 5000 ms |

DVE field rename (all `starmus_*` → `sparx_sparxstar_*` / `sparx_aiwa_*`) is complete in SchemaMapper.  Data migration script for existing records is required before deploying to production.

Full specification: [Starmus_Tech_Spec_v1.0 (1).pdf](<Starmus_Tech_Spec_v1.0 (1).pdf>)

---

## 📜 License

**LicenseRef-Starisian-Technologies-Proprietary**

This software is governed by the **Starisian Technologies Confidential License**.  Unauthorised use or distribution is strictly prohibited.

By accessing this repository you accept:
- [LICENSE.md](LICENSE.md) — legal terms and jurisdiction
- [TERMS.md](TERMS.md) — ethics and allowed use

**Not allowed:** surveillance, coercion, military use.  
**Encouraged:** oral history, education, culture, community voice.

---

## Cultural & Creative Projects Welcome

While released under a restricted proprietary licence, we actively support nonprofit, educational, and cultural storytelling projects.  If you are working in underserved communities or preserving oral traditions, reach out — we are happy to explore free or discounted licensing.

---

## 🌍 Contact

**Starisian Technologies**  
815 E Street, Suite 12083  
San Diego, CA 92101  
**Email:** <support@starisian.com>

---

*Built for creators. Built for culture. Built for Africa.*
