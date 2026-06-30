<?php
require_once __DIR__ . '/../private/config.php';

function getAccessToken($presence_config) {
    if (empty($presence_config)) return null;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $presence_config['token_url']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => $presence_config['client_id'],
        'client_secret' => $presence_config['client_secret'],
        'scope'         => $presence_config['scope']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    global $curl_ca_bundle;
    if (!empty($curl_ca_bundle)) {
        curl_setopt($ch, CURLOPT_CAINFO, $curl_ca_bundle);
    }
    
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

$token = getAccessToken($presence_config);
echo $token;
