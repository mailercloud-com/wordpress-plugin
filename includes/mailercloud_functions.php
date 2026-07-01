<?php
    /**
     * Log a Mailercloud API failure to the site's debug log (only when WP_DEBUG is on).
     *
     * Replaces the old behaviour of opening a /v1/ticket on every failure, which fired a
     * second blocking HTTP call on each error and amplified load during API incidents.
     * Team-side alerting (Slack with trace) is handled SERVER-SIDE on the Mailercloud API,
     * never from this client-distributed plugin.
     *
     * @param string $method
     * @param string $url
     * @param mixed  $body
     * @param mixed  $response WP_Error or the wp_remote_* response array.
     * @return void
     *
     * @note The request body may contain subscriber PII (email/name). It is logged only
     *       when WP_DEBUG is on, to the site's own debug.log — do not leave WP_DEBUG
     *       enabled on a production site. The API key is sent in the Authorization header
     *       and is never passed to this function, so it is never logged.
     */
    function mc_log_api_error($method, $url, $body, $response)
    {
        if (! (defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }
        if (is_wp_error($response)) {
            $detail = $response->get_error_message();
        } elseif (is_array($response) && isset($response['body'])) {
            $detail = $response['body'];
        } else {
            $detail = wp_json_encode($response);
        }
        $resp_str = is_string($detail) ? $detail : wp_json_encode($detail);
        $req_str  = is_string($body) ? $body : wp_json_encode($body);
        // Strip CR/LF so a crafted API message cannot inject extra lines into the log.
        $resp_str = str_replace(array("\r", "\n"), ' ', (string) $resp_str);
        $req_str  = str_replace(array("\r", "\n"), ' ', (string) $req_str);
        error_log(sprintf(
            '[Mailercloud] API error: %s %s | response: %s | request: %s',
            $method,
            $url,
            $resp_str,
            $req_str
        ));
    }

    /**
     * Report a client-facing plugin failure to Mailercloud by reusing the /v1/ticket
     * endpoint (which raises the server-side team Slack alert). This deliberately does NOT
     * repeat the mistake that got the old per-error ticket calls removed:
     *   - Rate-limited: at most one report per distinct error per hour, per site
     *     (WP transient throttle) — so 100+ installs can't storm the API during an incident.
     *   - Non-blocking: fire-and-forget (blocking=false, short timeout) so it never delays
     *     the visitor's request or adds a second blocking call on the hot path.
     *   - No PII: the ticket carries only the endpoint, the API error detail, and
     *     site/WP/PHP context — never the submitted contact data. The API key travels in the
     *     Authorization header (as the API requires) and is never placed in the body.
     *
     * @param string $context  Short label, e.g. "contact upsert failed".
     * @param string $url       The Mailercloud endpoint that failed.
     * @param mixed  $detail    Error message / response body.
     * @param string $api_key   The site's Mailercloud API key (auth for the ticket).
     * @return void
     */
    function mc_report_error($context, $url, $detail, $api_key)
    {
        if (empty($api_key)) {
            return; // cannot authenticate the ticket without the site's API key
        }
        // Strip CR/LF first, then cap length (so a boundary newline can't survive the cut).
        $detail = substr(str_replace(array("\r", "\n"), ' ', (string) $detail), 0, 300);
        // Defense-in-depth: never let subscriber PII into a team ticket. Redact any email in
        // the detail, and strip per-contact path segments from the endpoint
        // (update mode builds /v1/contacts/<email>).
        $detail = preg_replace('/[^\s@"]+@[^\s@"]+\.[^\s@"]+/', '<redacted-email>', $detail);
        // Redact only an email-bearing path segment (raw or %40-encoded) — keep legitimate
        // endpoint words like /v1/contacts/upsert intact.
        $url = preg_replace('#(/v1/contacts/)[^/?]*(?:@|%40)[^/?]*#i', '${1}<redacted>', (string) $url);
        // Throttle on a signature of the error so repeats within the hour are suppressed.
        $sig = 'mc_err_' . md5($context . '|' . $url . '|' . $detail);
        if (get_transient($sig)) {
            return;
        }
        set_transient($sig, 1, HOUR_IN_SECONDS);

        $description = wp_json_encode(array(
            'context'  => $context,
            'endpoint' => $url,
            'detail'   => $detail,
            'site'     => home_url(),
            'wp'       => get_bloginfo('version'),
            'php'      => PHP_VERSION,
        ));
        wp_remote_post(MAILERCLOUD_TICKET_CREATION_API_URL, array(
            'body'        => wp_json_encode(array(
                'title'       => 'WP plugin error: ' . $context,
                'description' => $description,
                'status'      => 500,
            )),
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Authorization' => $api_key,
            ),
            'data_format' => 'body',
            'timeout'     => 2,
            'blocking'    => false, // fire-and-forget — never block the request or amplify load
        ));
    }

    /**
     * callWpRemoteRestApi
     *
     * @param  mixed $method
     * @param  mixed $url
     * @param  mixed $api_key
     * @param  mixed $body
     * @return array  { status:0|1, message, and one of data|id|errors }
     */
    function callWpRemoteRestApi($method, $url, $api_key, $body = false, $source = '')
    {
        $message = '';
        $status = 0;
        $data =[];
        $response =[
        'message' => $message,
        'status' => $status,
        'data' => $data
    ];
        if ($method =="POST") {
            $options = [
                'body'        => $body,
                'headers'     => [
                    'Content-Type' => 'application/json',
                    'Authorization' => $api_key
                ],
                'data_format' => 'body',
                'timeout'     => 15,
            ];
            $response = wp_remote_post($url, $options);
        } elseif ($method =="PUT") {
            $options = [
                'body'        => $body,
                'headers'     => [
                    'Content-Type' => 'application/json',
                    'Authorization' => $api_key
                ],
                'method' => 'PUT',
                'timeout' => 15,
            ];
            $response = wp_remote_request($url, $options);
        } else {
            $args = array(
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $api_key
            ],
            'timeout' => 15,
        );
            $response =wp_remote_get($url, $args);
        }
        if (is_wp_error($response)) {
            // Transport failure (timeout/DNS/SSL). Log the trace for the site owner and
            // surface a clean error to the caller. We no longer open a /v1/ticket here —
            // that fired a second blocking call per failure and amplified load during
            // incidents; team alerting is handled server-side on the Mailercloud API.
            mc_log_api_error($method, $url, $body, $response);
            mc_report_error(($source !== '' ? $source . ' — ' : '') . 'Mailercloud API unreachable', $url, $response->get_error_message(), $api_key);
            return [
                'message' => __('Could not reach Mailercloud. Please try again.', 'mailercloud'),
                'status'  => 0,
                'errors'  => $response->get_error_message(),
            ];
        } else {
            $bodyData = json_decode($response['body'], true);
            if (!isset($bodyData['data'])) {
                if (isset($bodyData['id'])) {
                    $id =isset($bodyData['id'])?$bodyData['id']:[];
                    $response =[
                        'message' => $message,
                        'status' => 1,
                        'id' => $id
                    ];
                    return $response;
                } elseif (isset($bodyData['contact_id'])) {
                    // /v1/contacts/upsert returns {contact_id, status:"created"|"updated"} (no "id").
                    $response = [
                        'message' => isset($bodyData['status']) ? $bodyData['status'] : '',
                        'status' => 1,
                        'id' => $bodyData['contact_id'],
                        'contact_id' => $bodyData['contact_id'],
                    ];
                    return $response;
                } elseif (isset($bodyData['errors'])) {
                    // API rejected the request. Return the errors to the caller (so the admin
                    // UI can show them), log the trace, and raise a rate-limited team alert.
                    mc_log_api_error($method, $url, $body, $response);
                    mc_report_error(($source !== '' ? $source . ' — ' : '') . 'Mailercloud API rejected request', $url, wp_json_encode($bodyData['errors']), $api_key);
                    return [
                        'message' => __('Mailercloud returned an error.', 'mailercloud'),
                        'status'  => 0,
                        'errors'  => $bodyData['errors'],
                    ];
                } elseif (isset($bodyData['message'])) {
                    $message =$bodyData['message'];
                    $status = 1;
                } else {
                    $message = __('Some error has occurred.', 'mailercloud');
                }
            } else {
                $data =isset($bodyData['data'])?$bodyData['data']:[];
                $status = 1;
                $message = __('OK', 'mailercloud');
            }
        }
        $response =[
            'message' => $message,
            'status' => $status,
            'data' => $data
        ];
        return $response;
    }
    
    /**
     * getwordpressUserAttributes
     *
     * @param  mixed $user_id
     * @return void
     */
    function getwordpressUserAttributes($user_id)
    {
        $DBRecord = [];
        $user = get_userdata($user_id);
        if ($user) {
            $DBRecord['Email'] = $user->user_email;
            $DBRecord['UserLogin'] = $user->user_login;
            $DBRecord['UserNicename'] = $user->user_nicename;
            $DBRecord['DisplayName'] = $user->display_name;
            $DBRecord['FirstName'] = $user->first_name;
            $DBRecord['LastName'] = $user->last_name;
            $DBRecord['UserRegistered'] = $user->user_registered;
            $UserData = get_user_meta($user->ID);
            $DBRecord['BillingFirstName'] = isset($UserData['billing_first_name'][0])?$UserData['billing_first_name'][0]:'';
            $DBRecord['BillingLastName'] = isset($UserData['billing_last_name'][0])?$UserData['billing_last_name'][0]:'';
            $DBRecord['BillingCompany'] = isset($UserData['billing_company'][0])?$UserData['billing_company'][0]:'';
            $DBRecord['BillingAddress1'] = isset($UserData['billing_address_1'][0])?$UserData['billing_address_1'][0]:'';
            $DBRecord['BillingCity'] = isset($UserData['billing_city'][0])?$UserData['billing_city'][0]:'';
            $DBRecord['BillingState'] = isset($UserData['billing_state'][0])?$UserData['billing_state'][0]:'';
            $DBRecord['BillingPostcode'] = isset($UserData['billing_postcode'][0])? $UserData['billing_postcode'][0]:'';
            $DBRecord['BillingCountry'] = isset($UserData['billing_country'][0])?$UserData['billing_country'][0]:'';
            $DBRecord['BillingPhone'] = isset($UserData['billing_phone'][0])?$UserData['billing_phone'][0]:'';
        }
        return $DBRecord;
    }
    
/**
 * get_mailercloud_webforms
 *
 * @param  mixed $method
 * @param  mixed $url
 * @param  mixed $api_key
 * @param  mixed $data
 * @return void
 */
function get_mailercloud_webforms($method, $url, $api_key, $data = false)
{
    $webforms = [];
    $webforms_data =[];
    if ($data) {
        $webforms_data =  $data;
    } else {
        $webforms_data = array(
                    'limit' => 100,
                    'page' => 1,
                    'search' => '',
                    'sort_field' => '',
                    'sort_order' => '',
                    'date_from' => '',
                    'date_to' => '',
                   'status' => 'Active'
                );
    }
    if ($api_key) {
        $response= callWpRemoteRestApi(
            $method,
            $url,
            $api_key,
            json_encode($webforms_data)
        );
        if (isset($response['data'])) {
            $webforms = $response['data'];
        }
    }
    return $webforms;
}
