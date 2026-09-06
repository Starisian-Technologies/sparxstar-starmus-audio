# Repository role and migration state — ADR-034 / ADR-035 / ADR-036

**This repository is designated the Spoken Audio Node** under ADR-034. **It is
not that today**, and this file says plainly what has moved, what has not, and
what each remaining step is waiting on.

Role assignment lives here; the reason lives in the governance registry. Do not
restate the rationale in this repository — cite the record.

- [ADR-034 — split axis](https://github.com/Starisian-Technologies/sparxstar-architecture-governance-registry/blob/main/standards/decisions/ADR-034-capture-experience-vs-audio-lifecycle-split.md)
- [ADR-035 — capture profiles](https://github.com/Starisian-Technologies/sparxstar-architecture-governance-registry/blob/main/standards/decisions/ADR-035-capture-profiles-not-a-platform-audio-ceiling.md)
- [ADR-036 — elicitation pacing is not acoustic prosody](https://github.com/Starisian-Technologies/sparxstar-architecture-governance-registry/blob/main/standards/decisions/ADR-036-elicitation-pacing-is-not-acoustic-prosody.md)

Seam contracts, which say what actually crosses each boundary:

- [Capture → Ingestion](https://github.com/Starisian-Technologies/sparxstar-architecture-governance-registry/blob/main/contracts/spoken-audio-capture-to-ingestion.md)
- [Asset → Records](https://github.com/Starisian-Technologies/sparxstar-architecture-governance-registry/blob/main/contracts/spoken-audio-asset-to-records.md)

## What this repository will own

Server-side ingestion, validation, integrity, immutable object storage,
derivatives, quality measurements, bulk import, processing jobs, and authorized
consumption. A service, not a set of pages.

**It does not capture.** Browser microphone access, local and offline handling,
the chunked-upload client and capture UX are the capture UI package's. The
reviewed transcript and its translation are ESU's; acoustic measurement is
ours, and interpreting what a measurement means is ESU's.

## What it stops owning

CMS templates and shortcodes · CMS admin screens · custom-post-type and
attachment persistence · a CMS custom-field plugin as its primary database ·
browser recorder code · CSS and frontend rendering · transcript review ·
prosodic interpretation.

## Migration state, honestly

### Done

- The **manifest-driven UI package boundary** exists
  (`starmus-ui-packages.json`, `StarmusUiPackageResolver`). This is the
  mechanism that will let the plugin stop shipping its own copy of the capture
  JS and consume the built capture UI package instead. It is a precondition for
  everything below, and it landed.
- The capture UI package has removed the paced reader, the transcript-sync
  controller, and the platform-wide audio ceiling.

### Blocked, and on what

1. **Removing the duplicated capture JS from `src/js/`.** Nineteen files, of
   which fifteen are diverged copies of files in the capture UI package. One
   home per capture-path source file (ADR-034). **Blocked on** the capture UI
   package being resolvable through the manifest as a published artifact rather
   than a sibling checkout. Deleting them before then breaks a shipping
   product.
2. **Removing the prosody assets** (`src/js/prosody/`,
   `src/css/starmus-prosody-engine.css` and their built forms) and the
   `prosodyScript` / `prosodyStyle` manifest surface. **Blocked on** the
   elicitation pacing package being publishable: `StarmusProsodyPlayer.php`
   enqueues these today, and the resolver needs a URL to point at instead.
   Deleting the assets first takes the paced reader off every live page.
3. **Standing up the ingestion service itself.** **Blocked on** the items the
   Capture → Ingestion contract lists as *owed*: the endpoint path and auth
   model, the upload metadata key set, the acknowledgement and error envelope,
   and confirmation that the consumer verifies `sha256` as the producer sends.
   **No repository implements a guess at those.** Writing endpoint paths, field
   names or status codes for a service that does not exist is how fabricated
   identifiers ship, and this organisation has shipped them before.
4. **Which repository carries the CMS host product** once this one becomes a
   service — this repo's residue, or a new one. Explicitly left open by
   ADR-034. **Decide it before moving files, not during.**

## Rules that already bind this repository

- **No platform-wide audio ceiling** (ADR-035). A capture profile travels with
  the asset; an asset carrying none is *stored* and merely not admissible as a
  source for acoustic measurement — never rejected, because unconditional
  capture still holds.
- **No transcoding on the ingestion path.** `import` means the received bytes
  are preserved. Derivatives are additional objects.
- **Never hold the transcript of record.** A machine transcript may exist as a
  processing artifact of an asset, marked unreviewed. Consumers read ESU.
