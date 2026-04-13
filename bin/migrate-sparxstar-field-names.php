#!/usr/bin/env php
<?php

/**
 * Data Migration: starmus_* → sparx_sparxstar_* / sparx_aiwa_*
 *
 * Migrates audio-recording post meta keys from the legacy starmus_* namespace to
 * DVE canonical field names (sparx_sparxstar_* / sparx_aiwa_*) as defined in
 * DVE Schema Alignment v2.0 (Starisian Technologies, April 2026).
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/migrate-sparxstar-field-names.php -- --dry-run
 *   wp eval-file bin/migrate-sparxstar-field-names.php -- --live
 *   wp eval-file bin/migrate-sparxstar-field-names.php -- --live --batch=50
 *   wp eval-file bin/migrate-sparxstar-field-names.php -- --rollback --dry-run
 *   wp eval-file bin/migrate-sparxstar-field-names.php -- --rollback --live
 *
 * Options:
 *   --dry-run   Preview what would change without writing. Default mode.
 *   --live      Apply changes. Must be explicit.
 *   --rollback  Reverse direction: copy sparx_sparxstar_* → starmus_* (recovery only).
 *   --batch=N   Posts per query batch (default: 100).
 *   --post-type CPT slug to migrate (default: audio-recording).
 *
 * Safety:
 *   - Old keys are PRESERVED during migration (copy, not move).
 *   - New key is only written if the old key has a non-empty value.
 *   - New key is only written if it does not already have a value (idempotent).
 *   - Pass --overwrite to force overwrite of existing new-key values.
 *
 * After verifying the migration in a development environment, remove old keys
 * in a follow-up pass once all code callers have been updated.
 *
 * @package Starisian\Sparxstar\Starmus
 */

declare(strict_types=1);

if ( ! defined('ABSPATH') && ! defined('WP_CLI')) {
    echo "Run via: wp eval-file bin/migrate-sparxstar-field-names.php -- [options]\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Field map: old starmus_* key => new DVE canonical key
// Source of truth: DVE Schema Alignment v2.0 §3 + §3.9 (PASSTHROUGH fields)
// ---------------------------------------------------------------------------
$FIELD_MAP = [
    // §3.1 System Identity
    'starmus_global_uuid'           => 'sparx_sparxstar_global_uuid',
    'starmus_stable_uri'            => 'sparx_sparxstar_stable_uri',
    'starmus_linked_data_uri'       => 'sparx_sparxstar_linked_data_uri',

    // §3.2 Rights & Licensing
    'starmus_copyright_status'      => 'sparx_sparxstar_copyright_status',
    'starmus_usage_constraints'     => 'sparx_sparxstar_usage_constraints',
    // starmus_rights_type, starmus_rights_use, starmus_rights_geo, starmus_rights_royalty RETIRED.
    // Do not migrate — draft assets carry recorder-only rights by default.

    // §3.3 Consent & Agreement
    'starmus_terms_type'            => 'sparx_sparxstar_terms_type',
    'starmus_submission_id'         => 'sparx_sparxstar_signatory_submission_id',
    'starmus_contributor_signature' => 'sparx_sparxstar_agreement_signature',
    'starmus_agree_ua'              => 'sparx_sparxstar_signatory_user_agent',
    'starmus_agree_ip'              => 'sparx_sparxstar_signatory_ip',
    'starmus_agree_geo'             => 'sparx_sparxstar_signatory_geolocation',
    'starmus_data_sensitivity'      => 'sparx_aiwa_access_tier',
    'starmus_anon_status'           => 'sparx_aiwa_speaker_anonymized',
    'starmus_consent_scope'         => 'sparx_aiwa_consent_type',
    'agreement_datetime'            => 'sparx_sparxstar_signatory_agreement_datetime',

    // §3.4 Recording Session Metadata
    'starmus_project_collection_id' => 'sparx_sparxstar_project_collection_id',
    'starmus_accession_number'      => 'sparx_sparxstar_accession_number',
    'starmus_session_location'      => 'sparx_sparxstar_session_location',
    'starmus_session_gps'           => 'sparx_sparxstar_session_gps',
    'starmus_recording_equipment'   => 'sparx_sparxstar_recording_equipment',
    'starmus_audio_files_originals' => 'sparx_sparxstar_audio_files_originals',
    'starmus_media_condition'       => 'sparx_sparxstar_media_condition',
    'starmus_access_level'          => 'sparx_sparxstar_access_level',
    'starmus_audio_quality_score'   => 'sparx_sparxstar_audio_quality_score',
    'starmus_recording_metadata'    => 'sparx_sparxstar_recording_metadata',
    'starmus_processing_log'        => 'sparx_sparxstar_processing_log',

    // §3.5 Audio Engineering
    'starmus_sample_rate'           => 'sparx_sparxstar_sample_rate',
    'starmus_bit_depth'             => 'sparx_sparxstar_bit_depth',
    'starmus_tuning_hz'             => 'sparx_sparxstar_tuning_hz',
    'starmus_channel_layout'        => 'sparx_sparxstar_channel_layout',
    'starmus_integrated_lufs'       => 'sparx_sparxstar_loudness',

    // §3.6 Recording Processing & File Pipeline
    'starmus_is_explicit'           => 'sparx_aiwa_sensitive_content_warning',
    'starmus_is_music'              => 'sparx_sparxstar_is_music',
    'starmus_school_reviewed'       => 'sparx_sparxstar_school_reviewed',
    'starmus_contributor_verification' => 'sparx_sparxstar_contributor_verification',
    'starmus_qa_review'             => 'sparx_sparxstar_qa_review',
    'starmus_waveform_json'         => 'sparx_sparxstar_waveform_json',
    'starmus_original_source'       => 'sparx_sparxstar_original_source',
    'starmus_archival_wav'          => 'sparx_sparxstar_archival_wav',
    'starmus_mastered_mp3'          => 'sparx_sparxstar_mastered_mp3',
    'starmus_cloud_object_uri'      => 'sparx_sparxstar_cloud_object_uri',
    'starmus_device_fingerprint'    => 'sparx_sparxstar_device_fingerprint',
    'starmus_environment_data'      => 'sparx_sparxstar_environment_data',

    // §3.7 Transcription & Translation (JSON / hashes only — text is in post_content)
    'starmus_transcription_json'    => 'sparx_sparxstar_transcription_json',
    'starmus_transcription_hash'    => 'sparx_sparxstar_transcription_hash',
    'starmus_translation_hash'      => 'sparx_sparxstar_translation_hash',
    'starmus_translation_language'  => 'sparx_sparxstar_translation_language',
    'starmus_original_language'     => 'sparx_sparxstar_original_language',
    'starmus_back_translation_text' => 'sparx_sparxstar_back_translation_text',
    'starmus_linked_audio'          => 'sparx_sparxstar_linked_audio',
    'starmus_transcription_parent'  => 'sparx_sparxstar_transcription_parent',

    // §3.8 Music Composition & Release
    'starmus_bpm'                   => 'sparx_sparxstar_bpm',
    'starmus_musical_key'           => 'sparx_sparxstar_musical_key',
    'starmus_isrc_code'             => 'sparx_aiwa_isrc_code',
    'starmus_stems_cloud_uri'       => 'sparx_sparxstar_stems_cloud_uri',
    'starmus_daw_project_uri'       => 'sparx_sparxstar_daw_project_uri',
    'starmus_upc_code'              => 'sparx_sparxstar_upc_code',
    'starmus_catalog_number'        => 'sparx_sparxstar_catalog_number',
    'starmus_label_name'            => 'sparx_sparxstar_label_name',

    // §3.9 Former PASSTHROUGH fields
    'contributor_name'              => 'sparx_sparxstar_legal_name',
    'dc_subject'                    => 'sparx_sparxstar_assigned_subject',
    'dc_language'                   => 'sparx_sparxstar_original_language',
    'dc_identifier'                 => 'sparx_sparxstar_global_uuid',
    'parental_permission_slip'      => 'sparx_sparxstar_parental_permission_slip',
    'geolocation'                   => 'sparx_sparxstar_session_gps',
    'date_created'                  => 'sparx_aiwa_date_created',
    'session_date'                  => 'sparx_sparxstar_session_date',
];

// ---------------------------------------------------------------------------
// Parse arguments
// ---------------------------------------------------------------------------
$args      = WP_CLI::get_runner()->arguments ?? [];
$assoc     = WP_CLI::get_runner()->assoc_args ?? [];

$dry_run   = isset($assoc['dry-run']) || ! isset($assoc['live']);
$rollback  = isset($assoc['rollback']);
$overwrite = isset($assoc['overwrite']);
$batch     = max(1, (int) ($assoc['batch'] ?? 100));
$post_type = sanitize_key($assoc['post-type'] ?? 'audio-recording');

if ($rollback) {
    $FIELD_MAP = array_flip($FIELD_MAP);
}

$direction = $rollback ? 'rollback (new → old)' : 'forward (old → new)';
$mode      = $dry_run ? 'DRY-RUN' : 'LIVE';

WP_CLI::log("=== Starmus Field Migration ===");
WP_CLI::log("Direction : {$direction}");
WP_CLI::log("Mode      : {$mode}");
WP_CLI::log("Post type : {$post_type}");
WP_CLI::log("Batch size: {$batch}");
WP_CLI::log("Overwrite : " . ($overwrite ? 'yes' : 'no (skip if target exists)'));
WP_CLI::log(str_repeat('-', 50));

if ( ! $dry_run) {
    WP_CLI::confirm("This will write to the database. Continue?");
}

// ---------------------------------------------------------------------------
// Iterate posts in batches
// ---------------------------------------------------------------------------
$offset         = 0;
$total_posts    = 0;
$total_copied   = 0;
$total_skipped  = 0;
$total_empty    = 0;

do {
    $posts = get_posts([
        'post_type'      => $post_type,
        'post_status'    => 'any',
        'posts_per_page' => $batch,
        'offset'         => $offset,
        'fields'         => 'ids',
    ]);

    if (empty($posts)) {
        break;
    }

    $total_posts += count($posts);

    foreach ($posts as $post_id) {
        foreach ($FIELD_MAP as $old_key => $new_key) {
            $old_val = get_post_meta($post_id, $old_key, true);

            if ($old_val === '' || $old_val === null || $old_val === false) {
                $total_empty++;
                continue;
            }

            $existing_new = get_post_meta($post_id, $new_key, true);
            if ($existing_new !== '' && $existing_new !== null && ! $overwrite) {
                WP_CLI::debug("  Post {$post_id}: {$new_key} already set — skip");
                $total_skipped++;
                continue;
            }

            if ($dry_run) {
                WP_CLI::log("  [DRY-RUN] Post {$post_id}: {$old_key} → {$new_key}");
                $total_copied++;
            } else {
                update_post_meta($post_id, $new_key, $old_val);
                WP_CLI::debug("  Post {$post_id}: copied {$old_key} → {$new_key}");
                $total_copied++;
            }
        }
    }

    $offset += $batch;

    WP_CLI::log("Processed offset {$offset} ({$total_posts} posts so far) …");

} while (count($posts) === $batch);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
WP_CLI::log(str_repeat('-', 50));
WP_CLI::log("Posts processed : {$total_posts}");
WP_CLI::log("Fields copied   : {$total_copied}" . ($dry_run ? ' (dry-run, no writes)' : ''));
WP_CLI::log("Fields skipped  : {$total_skipped} (target already had value)");
WP_CLI::log("Fields empty    : {$total_empty} (source was empty)");

if ($dry_run) {
    WP_CLI::warning("Dry-run complete. No changes written. Re-run with --live to apply.");
} else {
    WP_CLI::success("Migration complete. Old keys preserved — verify before removing them.");
}
