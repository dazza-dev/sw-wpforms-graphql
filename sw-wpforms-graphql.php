<?php

/**
 * Plugin Name: SW - WPForms GraphQL
 * Plugin URI: https://www.seniors.com.co
 * Description: Exposes WPForms form structure via WPGraphQL and provides a REST endpoint for form submissions.
 * Version: 1.0.0
 * Author: Seniors
 * Author URI: https://www.seniors.com.co
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sw-wpforms-graphql
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register WPForms GraphQL types and fields.
 */
function sw_wpforms_register_graphql_fields(): void
{
    if (!class_exists('WPGraphQL') || !function_exists('wpforms')) {
        return;
    }

    register_graphql_object_type('SwWpFormFieldChoice', [
        'description' => 'A choice option for select/radio/checkbox fields',
        'fields' => [
            'label' => ['type' => 'String'],
            'value' => ['type' => 'String'],
        ],
    ]);

    register_graphql_object_type('SwWpFormField', [
        'description' => 'A single form field',
        'fields' => [
            'id'          => ['type' => ['non_null' => 'Int'], 'description' => 'Field ID'],
            'type'        => ['type' => ['non_null' => 'String'], 'description' => 'Field type (text, email, textarea, select, etc.)'],
            'label'       => ['type' => 'String', 'description' => 'Field label'],
            'description' => ['type' => 'String', 'description' => 'Field help/description text'],
            'placeholder' => ['type' => 'String', 'description' => 'Field placeholder text'],
            'required'    => ['type' => 'Boolean', 'description' => 'Whether the field is required'],
            'cssClass'    => ['type' => 'String', 'description' => 'Custom CSS class'],
            'size'        => ['type' => 'String', 'description' => 'Field size (small, medium, large)'],
            'choices'     => ['type' => ['list_of' => 'SwWpFormFieldChoice'], 'description' => 'Choices for select/radio/checkbox'],
            'sublabels'   => ['type' => 'String', 'description' => 'Sublabels JSON for compound fields (name, address)'],
            'format'      => ['type' => 'String', 'description' => 'Field format (e.g. first-last for name fields)'],
            'config'      => ['type' => 'String', 'description' => 'Full raw field config as JSON (for compound/advanced fields)'],
        ],
    ]);

    register_graphql_object_type('SwWpForm', [
        'description' => 'A WPForms form',
        'fields' => [
            'id'                   => ['type' => ['non_null' => 'Int'], 'description' => 'Form ID'],
            'title'                => ['type' => 'String', 'description' => 'Form title'],
            'submitText'           => ['type' => 'String', 'description' => 'Submit button text'],
            'submitProcessingText' => ['type' => 'String', 'description' => 'Submit button processing text'],
            'fields'               => ['type' => ['list_of' => 'SwWpFormField'], 'description' => 'Form fields'],
        ],
    ]);

    register_graphql_field('RootQuery', 'wpForm', [
        'type'        => 'SwWpForm',
        'description' => 'Get a WPForms form structure by ID',
        'args'        => [
            'id' => [
                'type'        => ['non_null' => 'Int'],
                'description' => 'WPForms form ID',
            ],
        ],
        'resolve' => function ($_root, $args) {
            $form_id = absint($args['id']);
            $form = wpforms()->form->get($form_id);

            if (!$form) {
                return null;
            }

            $form_data = wpforms_decode($form->post_content);

            $submit_text = $form_data['settings']['submit_text'] ?? null;
            $submit_processing_text = $form_data['settings']['submit_text_processing'] ?? null;

            if (empty($form_data['fields'])) {
                return [
                    'id'                   => $form_id,
                    'title'                => $form->post_title,
                    'submitText'           => $submit_text,
                    'submitProcessingText' => $submit_processing_text,
                    'fields'               => [],
                ];
            }

            $fields = [];
            foreach ($form_data['fields'] as $field) {
                $choices = null;
                if (!empty($field['choices'])) {
                    $choices = [];
                    foreach ($field['choices'] as $choice) {
                        // WPForms stores an empty string value when "Show Values"
                        // is off, so fall back to the label (?? only catches null).
                        $label = $choice['label'] ?? '';
                        $value = $choice['value'] ?? '';
                        $choices[] = [
                            'label' => $label,
                            'value' => $value !== '' ? $value : $label,
                        ];
                    }
                }

                $fields[] = [
                    'id'          => (int) $field['id'],
                    'type'        => $field['type'] ?? 'text',
                    'label'       => $field['label'] ?? '',
                    'description' => $field['description'] ?? null,
                    'placeholder' => $field['placeholder'] ?? null,
                    'required'    => !empty($field['required']),
                    'cssClass'    => $field['css'] ?? null,
                    'size'        => $field['size'] ?? null,
                    'choices'     => $choices,
                    'sublabels'   => !empty($field['sublabels']) ? wp_json_encode($field['sublabels']) : null,
                    'format'      => $field['format'] ?? null,
                    'config'      => wp_json_encode($field),
                ];
            }

            return [
                'id'                   => $form_id,
                'title'                => $form->post_title,
                'submitText'           => $submit_text,
                'submitProcessingText' => $submit_processing_text,
                'fields'               => $fields,
            ];
        },
    ]);
}
add_action('graphql_register_types', 'sw_wpforms_register_graphql_fields');

/**
 * REST API endpoint for form submissions.
 *
 * POST /wp-json/sw/v1/forms/{form_id}/submit
 * Body: { "fields": { "1": "value1", "2": "value2" } }
 */
function sw_wpforms_register_rest_routes(): void
{
    if (!function_exists('wpforms')) {
        return;
    }

    register_rest_route('sw/v1', '/forms/(?P<form_id>\d+)/submit', [
        'methods'  => 'POST',
        'callback' => 'sw_wpforms_handle_submission',
        'permission_callback' => '__return_true',
        'args' => [
            'form_id' => [
                'required'          => true,
                'validate_callback' => function ($value) {
                    return is_numeric($value) && $value > 0;
                },
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
}
add_action('rest_api_init', 'sw_wpforms_register_rest_routes');

/**
 * Save a base64 image data-URL (e.g. a drawn signature from a canvas) to the
 * media library and return its URL. Returns '' if it isn't a valid data-URL.
 *
 * This lets a headless form include a "signature" field: give a WPForms field
 * the CSS class `signature`, render a canvas for it on the front-end, and send
 * its `toDataURL()` value — this converts it to a real image on submit.
 */
function sw_wpforms_save_data_url(string $data_url): string
{
    if (strpos($data_url, 'data:image/') !== 0 || strpos($data_url, ',') === false) {
        return '';
    }
    [$meta, $b64] = explode(',', $data_url, 2);
    $decoded = base64_decode($b64);
    if ($decoded === false) {
        return '';
    }
    $ext    = strpos($meta, 'image/jpeg') !== false ? 'jpg' : 'png';
    $upload = wp_upload_bits('firma-' . gmdate('Ymd-His') . '-' . wp_generate_password(6, false) . '.' . $ext, null, $decoded);
    if (!empty($upload['error'])) {
        return '';
    }

    $attach_id = wp_insert_attachment([
        'post_mime_type' => $ext === 'jpg' ? 'image/jpeg' : 'image/png',
        'post_title'     => 'Firma ' . current_time('mysql'),
        'post_status'    => 'inherit',
    ], $upload['file']);
    if (!is_wp_error($attach_id)) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));
    }

    return $upload['url'];
}

function sw_wpforms_handle_submission(\WP_REST_Request $request): \WP_REST_Response
{
    $form_id = $request->get_param('form_id');
    $submitted_fields = $request->get_param('fields');

    if (empty($submitted_fields) || !is_array($submitted_fields)) {
        return new \WP_REST_Response([
            'success' => false,
            'message' => 'No fields provided.',
        ], 400);
    }

    $form = wpforms()->form->get($form_id);
    if (!$form) {
        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Form not found.',
        ], 404);
    }

    $form_data = wpforms_decode($form->post_content);

    if (empty($form_data['fields'])) {
        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Form has no fields.',
        ], 400);
    }

    $entry_fields = [];
    foreach ($form_data['fields'] as $field_config) {
        $field_id = $field_config['id'];
        $value = $submitted_fields[$field_id] ?? '';

        if (!empty($field_config['required']) && empty($value)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => sprintf('Field "%s" is required.', $field_config['label'] ?? $field_id),
            ], 422);
        }

        if ($field_config['type'] === 'email' && !empty($value) && !is_email($value)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => sprintf('Field "%s" must be a valid email.', $field_config['label'] ?? $field_id),
            ], 422);
        }

        // A drawn-signature field arrives as a base64 image data-URL → save it
        // as a real image and store the URL instead of the huge string.
        if (is_array($value)) {
            $clean_value = implode(', ', array_map('sanitize_text_field', $value));
        } elseif (is_string($value) && strpos($value, 'data:image/') === 0) {
            $clean_value = sw_wpforms_save_data_url($value);
        } else {
            $clean_value = sanitize_text_field($value);
        }

        $entry_fields[$field_id] = [
            'id'    => $field_id,
            'type'  => $field_config['type'] ?? 'text',
            'name'  => $field_config['label'] ?? '',
            'value' => $clean_value,
        ];
    }

    // Create the entry. WPForms stores the `fields` column as a JSON string,
    // so it must be encoded (passing the raw array saves an empty entry).
    $entry_id = wpforms()->entry->add([
        'form_id' => $form_id,
        'fields'  => wp_json_encode($entry_fields),
    ]);

    // Data passed to the notification hook keeps the array form.
    $entry_data = [
        'form_id' => $form_id,
        'fields'  => $entry_fields,
    ];

    if (!$entry_id) {
        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to save entry.',
        ], 500);
    }

    // Trigger WPForms notifications (emails)
    $notifications = new \WPForms_Entry_Handler();
    do_action('wpforms_process_complete', $entry_fields, $entry_data, $form_data, $entry_id);

    return new \WP_REST_Response([
        'success'  => true,
        'message'  => 'Form submitted successfully.',
        'entry_id' => $entry_id,
    ], 200);
}
