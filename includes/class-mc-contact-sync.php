<?php
/**
 * Mc_Contact_Sync — shared field-mapping + contact-send helper.
 *
 * Factored from the duplicated mapping loops in mailercloud.php so that the
 * form-plugin connectors can reuse the exact same WordPress-attribute ->
 * MailerCloud-contact mapping the user-sync already uses.
 *
 * map_fields() is a PURE function (no WordPress dependency) so it is unit
 * testable in isolation. send() wraps the existing callWpRemoteRestApi()
 * transport and supports create / update / upsert.
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH') && ! defined('MC_CONTACT_SYNC_TEST')) {
    exit;
}

class Mc_Contact_Sync
{
    /**
     * Map a flat source array (e.g. a form submission keyed by field id) into a
     * MailerCloud contact payload, using a mapping config of rows shaped like
     * attribute_mapping.json:
     *   [ ['wordpress_attribute' => '<source key>', 'mailercloud_attribute' => '<dest>'], ... ]
     *
     * Destination rules (identical to the existing user-sync loop):
     *  - mailercloud_attribute === a 'tags' source row -> tags (JSON-decoded literal value)
     *  - mailercloud_attribute contains 'custom_fields_' -> nested under custom_fields[<id>]
     *  - otherwise -> a standard field (email / name / last_name / ...)
     *
     * @param array $mapping Rows of { wordpress_attribute, mailercloud_attribute }.
     * @param array $source  Flat key => value map of the submitted/source data.
     * @return array MailerCloud contact payload (no list_id; send() adds that).
     */
    public static function map_fields($mapping, $source)
    {
        $out    = array();
        $custom = array();

        if (empty($mapping) || ! is_array($mapping)) {
            return $out;
        }

        foreach ($mapping as $row) {
            if (! isset($row['wordpress_attribute'], $row['mailercloud_attribute'])) {
                continue;
            }
            $src_key = $row['wordpress_attribute'];
            $dest    = $row['mailercloud_attribute'];

            // tags row: the mapped value is a JSON-encoded list of tag ids/names.
            if ($src_key === 'tags') {
                $tags = json_decode($dest, true);
                if (! empty($tags)) {
                    $out['tags'] = $tags;
                }
                continue;
            }

            // Only map source keys that are actually present + non-empty.
            $value = isset($source[$src_key]) ? $source[$src_key] : null;
            if ($value === null || $value === '') {
                continue;
            }

            if (strpos($dest, 'custom_fields_') !== false) {
                $cf_id          = str_replace('custom_fields_', '', $dest);
                $custom[$cf_id] = $value;
            } else {
                $out[$dest] = $value;
            }
        }

        if (! empty($custom)) {
            $out['custom_fields'] = $custom;
        }

        return $out;
    }

    /**
     * Send a mapped contact payload to MailerCloud.
     *
     * @param array  $contact Mapped payload from map_fields() (must contain 'email').
     * @param string $list_id Target list id (added for create/upsert).
     * @param string $api_key MailerCloud API key.
     * @param string $mode    'upsert' (default) | 'create' | 'update'.
     * @return array { ok: bool, response: array } — ok true when MailerCloud accepted it.
     */
    public static function send($contact, $list_id, $api_key, $mode = 'upsert')
    {
        if (empty($contact['email']) || empty($api_key)) {
            return array('ok' => false, 'response' => array('message' => 'missing email or api key'));
        }

        if ($mode === 'update') {
            $email = $contact['email'];
            unset($contact['email']);
            $response = callWpRemoteRestApi(
                'PUT',
                MAILERCLOUD_SUBSCRIBER_SYNC_SINGLE_CONTACT_UPDATE_API_URL . rawurlencode($email),
                $api_key,
                json_encode($contact)
            );
        } else {
            if (! empty($list_id)) {
                $contact['list_id'] = $list_id;
            }
            $url = ($mode === 'create')
                ? MAILERCLOUD_SUBSCRIBER_SYNC_SINGLE_CONTACT_API_URL
                : MAILERCLOUD_UPSERT_CONTACT_API_URL;
            $response = callWpRemoteRestApi('POST', $url, $api_key, json_encode($contact));
        }

        $ok = is_array($response) && isset($response['status']) && intval($response['status']) === 1;
        return array('ok' => $ok, 'response' => $response);
    }
}
