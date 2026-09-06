# Repository role and restructure hold — ADR-034 / ADR-035 / ADR-036

**This repository is designated the Spoken Audio Node** under
[ADR-034](https://github.com/Starisian-Technologies/sparxstar-architecture-governance-registry/blob/main/standards/decisions/ADR-034-capture-experience-vs-audio-lifecycle-split.md).
It is not that today: it is still the near-complete CMS plugin, holding 96
source files under `src/` (71 PHP, 19 JS, 6 CSS) including templates,
shortcodes, admin screens, browser recorder code and CSS.

How that count is derived, so it can be re-checked rather than trusted:
`find src -type f -name '*.php' ! -name 'index.php' | wc -l` → 71. The raw
`*.php` count is 94; the 23 excluded files are the empty `index.php`
directory guards, which are not source. JS and CSS need no exclusion
(`find src -type f -name '*.js'` → 19, `-name '*.css'` → 6; the four
`*.css.bak` / `*.css.dead` files do not match `*.css`).

Role assignment lives here; the reason lives in the ADR. Do not restate the
rationale in this repository — cite the ADR number.

## Hold

[ADR-034](https://github.com/Starisian-Technologies/sparxstar-architecture-governance-registry/blob/main/standards/decisions/ADR-034-capture-experience-vs-audio-lifecycle-split.md),
[ADR-035](https://github.com/Starisian-Technologies/sparxstar-architecture-governance-registry/blob/main/standards/decisions/ADR-035-capture-profiles-not-a-platform-audio-ceiling.md) and
[ADR-036](https://github.com/Starisian-Technologies/sparxstar-architecture-governance-registry/blob/main/standards/decisions/ADR-036-elicitation-pacing-is-not-acoustic-prosody.md) are
**Proposed**. They were filed so the boundary is settled once before either
coding agent moves files. **Do not restructure this repository until they are
Accepted.**

## What this repository owns once the ADRs are Accepted

Ingestion, integrity, object storage, processing, acoustic analysis, bulk
import, and authorized consumption. A service, not a set of pages.

## What it stops owning on acceptance (ADR-034)

CMS templates and shortcodes · CMS admin screens · custom-post-type and
attachment persistence · a CMS custom-field plugin as its primary database ·
browser recorder code · CSS and frontend rendering · transcript review ·
prosodic interpretation.

The reviewed transcript and any translation are **ESU's** records — ESU being
the platform component that holds reviewed transcript, translation and
linguistic-interpretation records, as ADR-034 and ADR-036 assign them. Its name
is not expanded here: this repository is not ESU's home, and a definition
maintained in two places drifts. Take the expansion from ESU's own repository
or from the governance registry, not from this file.

This repository owns the asset and its processing lifecycle and emits
measurements; it does not hold the reviewed linguistic record.

Acoustic measurement — pitch, formant, intensity, duration — **is** ours
(ADR-036). Interpreting what those measurements mean is ESU's.

## What that implies for the work

- **The nineteen JS files under `src/js/` are not this repository's**, and
  fifteen of them are diverged copies of files in the capture UI package. One
  home per capture-path source file (ADR-034); reconcile into that package.
- **The CMS product remains a host**, embedding the capture package through a
  thin adapter and calling this service. Which repository carries that host
  product, and how far its own persistence is reshaped, is explicitly left open
  by ADR-034 — decide it before moving files, not during.
- **No platform-wide audio ceiling** may be enforced here either (ADR-035). A
  capture profile travels with the asset; an asset carrying none is not
  admissible as a source for acoustic measurement. Transcoding on the import
  path is forbidden.
