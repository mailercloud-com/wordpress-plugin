<?php
    /**
     * callWpRemoteRestApi
     *
     * @param  mixed $method
     * @param  mixed $url
     * @param  mixed $api_key
     * @param  mixed $body
     * @return void
     */
    function callWpRemoteRestApi($method, $url, $api_key, $body = false)
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
            ];
            $response = wp_remote_request($url, $options);
        } else {
            $args = array(
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $api_key
            ]
        );
            $response =wp_remote_get($url, $args);
        }
        if (is_array($response) && is_wp_error($response)) {
            $message = __('Sorry We are not Verify APi Key, Please try again.', 'mailercloud');
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
                } elseif (isset($bodyData['errors'])) {
                    $errors =isset($bodyData['errors'])?$bodyData['errors']:[];
					$descriptionArray =[
					'method' => $method,
					'request' =>$body,
					'response' =>$bodyData,
					];
					/** ticket creation api **/
					$title = 'Api url :'.$url;
					$dataTicket = [
					'title' 	   => $title,
					'description'  => json_encode($descriptionArray)
					];
					
					 $optionsTicket = [
						'body'        => json_encode($dataTicket),
						'headers'     => [
							'Content-Type' => 'application/json',
							'Authorization' => $api_key
						],
						'data_format' => 'body',
					];
					$responseTicket = wp_remote_post(MAILERCLOUD_TICKET_CREATION_API_URL, $optionsTicket);
					 if (is_array($responseTicket) && is_wp_error($responseTicket)) {
						$message = __('Sorry error occured, Please try again.', 'mailercloud');
					} else {
						$bodyDataTicket = json_decode($responseTicket['body'], true);
					}
					/** end of ticket creation api **/
                    $response =[
                        'message' => $message,
                        'status' => $status,
                        'errors' => $errors
                    ];
					
					
                    return $response;
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
     * get_users_by_role
     *
     * @param  mixed $role
     * @param  mixed $orderby
     * @param  mixed $order
     * @return void
     */
    function get_users_by_role($role, $orderby, $order)
    {
        $args = array(
            'role__in'     => array('administrator', 'editor', 'author','subscriber'),
            'orderby' => $orderby,
            'order' => $order
        );
        $users =[];
        $userAll = get_users($args);
        foreach ($userAll as $user) {
            $UserData = get_user_meta($user->ID);
            $updated= isset($UserData['mailercloud_is_synched'][0])?$UserData['mailercloud_is_synched'][0]:'';
            //if( $updated ==''){
            $users[] = getwordpressUserAttributes($user->ID);
            // }
        }
        return $users;
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
