<?php

/**
 * Schema Mapper for Starmus Audio System.
 *
 * Translates frontend field keys to DVE canonical schema keys (sparx_sparxstar_* / sparx_aiwa_*).
 * Single-path mapping — no passthrough allowlist. Every field has an explicit DVE target.
 * Ref: DVE Schema Alignment v2.0 (Starisian Technologies, April 2026).
 *
 * @package Starisian\Sparxstar\Starmus\data\mappers
 *
 * @version 3.0.0
 */

declare(strict_types=1);
namespace Starisian\Sparxstar\Starmus\data\mappers;

use function get_current_user_id;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function sanitize_text_field;

use Starisian\Sparxstar\Starmus\helpers\StarmusLogger;
use Throwable;

use function wp_unslash;

if ( ! \defined('ABSPATH')) {
    exit;
}

class StarmusSchemaMapper
{
    /**
     * MAPPING DEFINITION: 'frontend_input_key' => 'DVE canonical output key'
     *
     * All fields map through a single path. Output keys follow DVE naming conventions:
     * - sparx_sparxstar_* for platform-level fields
     * - sparx_aiwa_*      for AiWA corpus fields
     *
     * FIELD_MAP contains the explicit one-to-one field mappings. Some dates, users,
     * JSON blobs, and other complex fields are handled separately in
     * map_form_data() and may not be listed here.
     *
     * Ref: DVE Schema Alignment v2.0 §3 (Complete Field Alignment Map)
     */
    private const FIELD_MAP = [
        // -- System Identity & Archival Discovery (§3.1) --
        'global_uuid' => 'sparx_sparxstar_global_uuid',
        'stable_uri' => 'sparx_sparxstar_stable_uri',
        'linked_data_uri' => 'sparx_sparxstar_linked_data_uri',

        // -- Rights & Licensing (§3.2) --
        // starmus_rights_type, starmus_rights_use, starmus_rights_geo, starmus_rights_royalty
        // are RETIRED. Draft assets carry recorder-only rights by default. Rights module is future build.
        'copyright_status' => 'sparx_sparxstar_copyright_status',
        'usage_constraints' => 'sparx_sparxstar_usage_constraints',

        // -- Consent & Agreement (§3.3) --
        'terms_type' => 'sparx_sparxstar_terms_type',
        'submission_id' => 'sparx_sparxstar_signatory_submission_id',
        'contributor_signature' => 'sparx_sparxstar_agreement_signature',
        'contributor_user_agent' => 'sparx_sparxstar_signatory_user_agent',
        // IP triple-alias — all three source keys normalize to one output (§5 Step 5)
        'ip_address' => 'sparx_sparxstar_signatory_ip',
        'submission_ip' => 'sparx_sparxstar_signatory_ip',
        'contributor_ip' => 'sparx_sparxstar_signatory_ip',
        'contributor_geolocation' => 'sparx_sparxstar_signatory_geolocation',
        'data_sensitivity_level' => 'sparx_aiwa_access_tier',
        'anonymization_status' => 'sparx_aiwa_speaker_anonymized',
        'consent_scope' => 'sparx_aiwa_consent_type',

        // -- Recording Session Metadata (§3.4) --
        'project_collection_id' => 'sparx_sparxstar_project_collection_id',
        'accession_number' => 'sparx_sparxstar_accession_number',
        'session_start_time' => 'sparx_sparxstar_session_start_time',
        'session_end_time' => 'sparx_sparxstar_session_end_time',
        'location' => 'sparx_sparxstar_session_location',
        // geolocation and gps_coordinates aliases consolidated to single output (§5 Step 5)
        'gps_coordinates' => 'sparx_sparxstar_session_gps',
        'geolocation' => 'sparx_sparxstar_session_gps',
        'recording_equipment' => 'sparx_sparxstar_recording_equipment',
        'audio_files_originals' => 'sparx_sparxstar_audio_files_originals',
        'media_condition_notes' => 'sparx_sparxstar_media_condition',
        'access_level' => 'sparx_sparxstar_access_level',
        'audio_quality_score_tax' => 'sparx_sparxstar_audio_quality_score',
        'recording_metadata' => 'sparx_sparxstar_recording_metadata',
        'processing_log' => 'sparx_sparxstar_processing_log',
        'parental_permission_slip' => 'sparx_sparxstar_parental_permission_slip',

        // -- Contributor Identity (§3.9 former PASSTHROUGH) --
        'contributor_name' => 'sparx_sparxstar_legal_name',
        // dc_title → post_title at post creation; never stored in ACF meta (§3.9)
        // dc_description → post_content; never stored in ACF meta (§3.9)
        'dc_subject' => 'sparx_sparxstar_assigned_subject',
        // dc_language consolidated with starmus_original_language → single canonical field (§3.9)
        'dc_language' => 'sparx_sparxstar_original_language',
        // dc_identifier was the UUID — consolidated to global_uuid canonical (§3.9)
        'dc_identifier' => 'sparx_sparxstar_global_uuid',
        // dc_creator aligns to contributor-identity fields (§3.9)
        'dc_creator' => 'sparx_sparxstar_legal_name',
        // dc_format lives inside recording_metadata JSON blob — no standalone field (§3.9)
        // session_date renamed to sparx_sparxstar_session_date (§3.9)

        // -- Audio Engineering & Fidelity (§3.5) --
        'sample_rate' => 'sparx_sparxstar_sample_rate',
        'bit_depth' => 'sparx_sparxstar_bit_depth',
        'tuning_hz' => 'sparx_sparxstar_tuning_hz',
        'channel_layout' => 'sparx_sparxstar_channel_layout',
        'integrated_lufs' => 'sparx_sparxstar_loudness',

        // -- Recording Processing & File Pipeline (§3.6) --
        'explicit' => 'sparx_aiwa_sensitive_content_warning',
        'is_music' => 'sparx_sparxstar_is_music',
        'school_reviewed' => 'sparx_sparxstar_school_reviewed',
        'contributor_verification' => 'sparx_sparxstar_contributor_verification',
        'qa_review' => 'sparx_sparxstar_qa_review',
        'waveform_json' => 'sparx_sparxstar_waveform_json',
        'original_source' => 'sparx_sparxstar_original_source',
        'archival_wav' => 'sparx_sparxstar_archival_wav',
        'mastered_mp3' => 'sparx_sparxstar_mastered_mp3',
        'cloud_object_uri' => 'sparx_sparxstar_cloud_object_uri',
        'device_fingerprint' => 'sparx_sparxstar_device_fingerprint',
        'environment_data' => 'sparx_sparxstar_environment_data',

        // -- Music Composition & Release (§3.8) --
        'bpm' => 'sparx_sparxstar_bpm',
        'musical_key' => 'sparx_sparxstar_musical_key',
        'isrc_code' => 'sparx_aiwa_isrc_code',
        'stems_cloud_uri' => 'sparx_sparxstar_stems_cloud_uri',
        'daw_project_uri' => 'sparx_sparxstar_daw_project_uri',
        // upc_code, catalog_number, label_name: DECIDE — likely belong in release post type (§3.8)
        'upc_code' => 'sparx_sparxstar_upc_code',
        'catalog_number' => 'sparx_sparxstar_catalog_number',
        'label_name' => 'sparx_sparxstar_label_name',

        // -- Transcription & Translation (§3.7) --
        // IMPORTANT: 'transcription' (plain text) is intentionally absent from this map.
        // Per DVE Alignment v2.0 §3.7, transcription text is NOT a meta field — it lives in
        // post_content of the sparx_aiwa_transcription post type. Any caller previously sending
        // a 'transcription' key in form data must be updated to create/update the transcription
        // post type via StarmusAudioDAL::create_transcription_post() instead. Passing
        // 'transcription' here will silently drop the value. Same applies to 'translation'.
        'transcription_json' => 'sparx_sparxstar_transcription_json',
        'translation_language' => 'sparx_sparxstar_translation_language',
        'original_language' => 'sparx_sparxstar_original_language',
        'back_translation_text' => 'sparx_sparxstar_back_translation_text',
        'transcription_hash' => 'sparx_sparxstar_transcription_hash',
        'translation_hash' => 'sparx_sparxstar_translation_hash',
        'audio_recording_parent' => 'sparx_sparxstar_linked_audio',
        'transcription_parent' => 'sparx_sparxstar_transcription_parent',
    ];

    /**
     * Maps form data to the DVE canonical ACF schema.
     *
     * Translates frontend field keys to DVE canonical field names via FIELD_MAP.
     * Single-path processing — no passthrough allowlist.
     * All timestamps use gmdate() (UTC). Ref: DVE Alignment v2.0 §5 Step 4.
     *
     * @param array<string, mixed> $data Raw or semi-sanitized form data.
     *
     * @return array<string, mixed> Data ready for ACF saving.
     */
    public static function map_form_data(array $data): array
    {
        $mapped = [];

        try {
            $user_id = get_current_user_id();

            // PROCESS ALL MAPPED FIELDS (single authoritative path — no passthrough allowlist)
            foreach (self::FIELD_MAP as $old_key => $new_key) {
                if (isset($data[$old_key])) {
                    $mapped[$new_key] = $data[$old_key];
                }
            }

            // COMPLEX LOGIC & RELATIONSHIPS

            // Contributor display name — aligns dc_creator to legal name field
            $mapped['sparx_sparxstar_legal_name'] = empty($data['dc_creator'])
                ? ($mapped['sparx_sparxstar_legal_name'] ?? 'Unknown Creator')
                : sanitize_text_field($data['dc_creator']);

            // User Links — keys match ACF field names registered in StarmusPostTypeLoader.
            $mapped['starmus_copyright_licensor'] = $user_id;
            $mapped['starmus_authorized_signatory'] = $user_id;

            // Dates — UTC only (gmdate). Ref: DVE Alignment v2.0 §5 Step 4 + F-08
            $mapped['sparx_aiwa_date_created'] = empty($data['date_created'])
                ? gmdate('Ymd')
                : $data['date_created'];

            $mapped['sparx_sparxstar_session_date'] = empty($data['session_date'])
                ? gmdate('Ymd')
                : $data['session_date'];

            // JSON Blobs (Safely handled)
            if ( ! empty($data['_starmus_env'])) {
                $mapped['sparx_sparxstar_environment_data'] = self::ensure_json_string($data['_starmus_env'], 'environment_data');

                // Extract Fingerprint
                $env_arr = self::decode_if_json($data['_starmus_env']);
                if (isset($env_arr['fingerprint'])) {
                    $mapped['sparx_sparxstar_device_fingerprint'] = $env_arr['fingerprint'];
                }
            }

            if ( ! empty($data['waveform_json'])) {
                $mapped['sparx_sparxstar_waveform_json'] = self::ensure_json_string($data['waveform_json'], 'waveform_json');
            }

            if ( ! empty($data['transcription_json'])) {
                $mapped['sparx_sparxstar_transcription_json'] = self::ensure_json_string($data['transcription_json'], 'transcription_json');
            }

            if ( ! empty($data['_starmus_calibration'])) {
                $mapped['transcriber'] = self::ensure_json_string($data['_starmus_calibration'], 'transcriber');
            }

            // Agreement — signatory block populated = declaration made. UTC timestamp required.
            // agreement_to_terms_toggle is RETIRED (DVE Alignment v2.0 §3.3). Signatory block presence is evidence.
            if ( ! empty($data['agreement'])) {
                $mapped['sparx_sparxstar_signatory_agreement_datetime'] = gmdate('Y-m-d H:i:s');
            }

            // IP Address — normalize triple-alias to single canonical output (DVE Alignment v2.0 §5 Step 5)
            if ( ! empty($data['ip_address'])) {
                $mapped['sparx_sparxstar_signatory_ip'] = $data['ip_address'];
            }

            // Taxonomies — these keys are used internally by StarmusSubmissionHandler to call
            // wp_set_post_terms(). They are NOT ACF field names and do not follow the sparx_sparxstar_*
            // convention because they are processed by a separate taxonomy assignment code path
            // rather than being saved as post meta via update_field(). The sparx_tax_* prefix
            // identifies them as internal taxonomy routing keys within this mapper.
            if ( ! empty($data['language'])) {
                $mapped['sparx_tax_language'] = (int) $data['language'];
            } elseif ( ! empty($data['starmus_tax_language'])) {
                $mapped['sparx_tax_language'] = (int) $data['starmus_tax_language'];
            }

            if ( ! empty($data['dialect'])) {
                $mapped['sparx_tax_dialect'] = (int) $data['dialect'];
            } elseif ( ! empty($data['starmus_tax_dialect'])) {
                $mapped['sparx_tax_dialect'] = (int) $data['starmus_tax_dialect'];
            }

            if ( ! empty($data['recording_type'])) {
                $mapped['recording-type'] = (int) $data['recording_type'];
            } elseif ( ! empty($data['starmus_story_type'])) {
                $mapped['recording-type'] = (int) $data['starmus_story_type'];
            }
        } catch (Throwable $throwable) {
            StarmusLogger::error('Mapper Critical Failure: ' . $throwable->getMessage());
            StarmusLogger::log($throwable);
        }

        return $mapped;
    }

    /**
     * Extracts user IDs for submission processing.
     *
     * Returns the user-linked fields that must be saved as post meta on every submission.
     * These keys match the ACF field names registered in the audio-recording post type.
     * Called by StarmusSubmissionHandler::save_all_metadata() which iterates the result
     * and calls update_acf_field() for each entry.
     *
     * @param array $data Raw form data (unused; user identity is resolved via WP session)
     *
     * @return array<string, int> Field name → user ID pairs, or empty array when not logged in.
     */
    public static function extract_user_ids(array $data): array
    {
        $user_id = get_current_user_id();
        if ($user_id === 0) {
            return [];
        }

        // Keys MUST match the ACF field names registered in StarmusPostTypeLoader.
        return [
            'starmus_copyright_licensor'  => $user_id,
            'starmus_authorized_signatory' => $user_id,
        ];
    }

    /**
     * Check if a specific field key should be treated as JSON.
     * References DVE canonical (sparx_sparxstar_* / sparx_aiwa_*) field names.
     *
     * NOTE: 'transcriber' does not follow the sparx_sparxstar_* convention because it is
     * an internal calibration payload key used exclusively within this mapper's processing
     * pipeline (mapped from _starmus_calibration in map_form_data). It is not an ACF field
     * name or a DVE schema field — it never surfaces in REST responses or post meta under
     * that key name. This is consistent with how sparx_tax_* taxonomy routing keys are
     * handled: internal-only, not subject to the DVE canonical naming requirement.
     */
    public static function is_json_field(string $field_name): bool
    {
        return \in_array($field_name, [
            'sparx_sparxstar_environment_data',
            'sparx_sparxstar_waveform_json',
            'sparx_sparxstar_transcription_json',
            'sparx_sparxstar_recording_metadata',
            'sparx_sparxstar_school_reviewed',
            'sparx_sparxstar_parental_permission_slip',
            'sparx_sparxstar_contributor_verification',
            'transcriber', // Internal calibration payload — see docblock above.
        ], true);
    }

    /**
     * Helper: Enforce JSON String with Error Logging.
     */
    private static function ensure_json_string(mixed $value, string $context = 'unknown'): string
    {
        if (\is_array($value)) {
            $json = json_encode($value);
            if (false === $json) {
                StarmusLogger::error(\sprintf('Mapper JSON Encode Failed (%s): ', $context) . json_last_error_msg());
                return '{}';
            }

            return $json;
        }

        if (\is_string($value)) {
            $unslashed = wp_unslash($value);
            json_decode($unslashed);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Return the unslashed string so WordPress-added magic quotes are stripped
                // before the value reaches storage. wp_unslash() is idempotent: it is safe
                // to call even when the value was not slashed.
                return $unslashed;
            }

            StarmusLogger::warning(\sprintf('Mapper received invalid JSON string for (%s). Wrapping.', $context));
            return (string) json_encode(['raw_preserved' => $value]);
        }

        return '{}';
    }

    /**
     * Helper: Decode JSON if applicable.
     */
    private static function decode_if_json(mixed $value): mixed
    {
        if (\is_string($value)) {
            $decoded = json_decode(wp_unslash($value), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }
}
