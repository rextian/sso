<?php
/**
 * REXTIAN SSO - 短信发送辅助
 * 支持阿里云、腾讯云、京东云 SMS 或 Mock
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings_helper.php';

function sendSmsCode(string $phone, string $code): array {
    $provider = getSetting('sms_provider') ?: 'aliyun';

    switch ($provider) {
        case 'tencent':
            return sendSmsTencent($phone, $code);
        case 'jdcloud':
            return sendSmsJdcloud($phone, $code);
        case 'aliyun':
        default:
            return sendSmsAliyun($phone, $code);
    }
}

function sendSmsAliyun(string $phone, string $code): array {
    $key = getSetting('sms_key');
    $secret = getSetting('sms_secret');
    $signName = getSetting('sms_sign_name') ?: 'REXTIAN';
    $templateCode = getSetting('sms_template_code') ?: 'SMS_123456789';

    if (!$key || !$secret) {
        error_log("[SMS Mock] phone={$phone} code={$code}");
        return ['success' => true, 'mock' => true];
    }

    $params = [
        'AccessKeyId' => $key,
        'Action' => 'SendSms',
        'Format' => 'JSON',
        'Version' => '2017-05-25',
        'SignatureMethod' => 'HMAC-SHA1',
        'SignatureVersion' => '1.0',
        'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'SignatureNonce' => uniqid('', true),
        'PhoneNumbers' => $phone,
        'SignName' => $signName,
        'TemplateCode' => $templateCode,
        'TemplateParam' => json_encode(['code' => $code]),
    ];
    ksort($params);
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $stringToSign = 'GET&' . rawurlencode('/') . '&' . rawurlencode($query);
    $signature = base64_encode(hash_hmac('sha1', $stringToSign, $secret . '&', true));
    $params['Signature'] = $signature;

    $url = 'https://dysmsapi.aliyuncs.com/?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if ($httpCode === 200 && isset($data['Code']) && $data['Code'] === 'OK') {
        return ['success' => true, 'mock' => false];
    }
    return ['success' => false, 'message' => $data['Message'] ?? '发送失败'];
}

function sendSmsTencent(string $phone, string $code): array {
    $secretId = getSetting('sms_tencent_secret_id');
    $secretKey = getSetting('sms_tencent_secret_key');
    $sdkAppId = getSetting('sms_tencent_sdk_app_id');
    $signName = getSetting('sms_tencent_sign_name') ?: 'REXTIAN';
    $templateId = getSetting('sms_tencent_template_id');

    if (!$secretId || !$secretKey || !$sdkAppId || !$templateId) {
        error_log("[SMS Mock] phone={$phone} code={$code}");
        return ['success' => true, 'mock' => true];
    }

    $host = 'sms.tencentcloudapi.com';
    $service = 'sms';
    $version = '2021-01-11';
    $action = 'SendSms';
    $region = 'ap-guangzhou';
    $timestamp = time();
    $algorithm = 'TC3-HMAC-SHA256';

    $phoneE164 = preg_match('/^1[3-9]\d{9}$/', $phone) ? '+86' . $phone : $phone;
    if (!str_starts_with($phoneE164, '+')) {
        $phoneE164 = '+86' . $phoneE164;
    }

    $payloadObj = [
        'SmsSdkAppId' => $sdkAppId,
        'SignName' => $signName,
        'TemplateId' => $templateId,
        'TemplateParamSet' => [$code],
        'PhoneNumberSet' => [$phoneE164],
    ];
    $payload = json_encode($payloadObj);
    $hashedRequestPayload = hash('sha256', $payload);
    $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\n";
    $signedHeaders = 'content-type;host';
    $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$hashedRequestPayload}";

    $date = gmdate('Y-m-d', $timestamp);
    $credentialScope = "{$date}/{$service}/tc3_request";
    $hashedCanonicalRequest = hash('sha256', $canonicalRequest);
    $stringToSign = "{$algorithm}\n{$timestamp}\n{$credentialScope}\n{$hashedCanonicalRequest}";

    $secretDate = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
    $secretService = hash_hmac('sha256', $service, $secretDate, true);
    $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
    $signature = hash_hmac('sha256', $stringToSign, $secretSigning);
    $authorization = "{$algorithm} Credential={$secretId}/{$credentialScope}, SignedHeaders=content-type;host, Signature={$signature}";

    $ch = curl_init("https://{$host}/");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Host: ' . $host,
            'X-TC-Action: ' . $action,
            'X-TC-Timestamp: ' . $timestamp,
            'X-TC-Version: ' . $version,
            'X-TC-Region: ' . $region,
            'Authorization: ' . $authorization,
        ],
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    $sendStatus = $data['Response']['SendStatusSet'][0] ?? null;
    if ($httpCode === 200 && $sendStatus && ($sendStatus['Code'] ?? '') === 'Ok') {
        return ['success' => true, 'mock' => false];
    }
    $msg = $sendStatus['Message'] ?? $data['Response']['Error']['Message'] ?? '发送失败';
    return ['success' => false, 'message' => $msg];
}

function sendSmsJdcloud(string $phone, string $code): array {
    $accessKey = getSetting('sms_jdcloud_access_key');
    $secretKey = getSetting('sms_jdcloud_secret_key');
    $signId = getSetting('sms_jdcloud_sign_id');
    $templateId = getSetting('sms_jdcloud_template_id');

    if (!$accessKey || !$secretKey) {
        error_log("[SMS Mock] phone={$phone} code={$code}");
        return ['success' => true, 'mock' => true];
    }

    if (!$signId || !$templateId) {
        return ['success' => false, 'message' => '请配置京东云签名ID和模板ID'];
    }

    // 京东云文本短信 batchSend API
    $host = 'sms.jdcloud-api.com';
    $region = 'cn-north-1';
    $method = 'POST';
    $uri = '/v1/regions/' . $region . '/batchSend';
    $body = json_encode([
        'signId' => $signId,
        'templateId' => $templateId,
        'phoneSet' => [$phone],
        'params' => [$code],
    ]);

    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    $date = gmdate('Y-m-d', time());
    $nonce = bin2hex(random_bytes(8));
    $credentialScope = "{$date}/{$region}/jdcloud-api/jdcloud4_request";
    $hashedPayload = hash('sha256', $body);
    $canonicalHeaders = "host:{$host}\nx-jdcloud-date:{$timestamp}\nx-jdcloud-nonce:{$nonce}\n";
    $signedHeaders = 'host;x-jdcloud-date;x-jdcloud-nonce';
    $canonicalRequest = "{$method}\n{$uri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$hashedPayload}";

    $algorithm = 'JDCLOUD4-HMAC-SHA256';
    $stringToSign = "{$algorithm}\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

    $kDate = hash_hmac('sha256', $date, 'JDCLOUD4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 'jdcloud-api', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'jdcloud4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $authorization = "{$algorithm} Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

    $ch = curl_init("https://{$host}{$uri}");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Host: ' . $host,
            'x-jdcloud-date: ' . $timestamp,
            'x-jdcloud-nonce: ' . $nonce,
            'Authorization: ' . $authorization,
        ],
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if ($httpCode === 200 && isset($data['result']['data']) && !empty($data['result']['data'])) {
        $first = $data['result']['data'][0] ?? [];
        if (($first['status'] ?? 0) === 0) {
            return ['success' => true, 'mock' => false];
        }
        return ['success' => false, 'message' => $first['message'] ?? '发送失败'];
    }
    $msg = $data['error']['message'] ?? $data['result']['message'] ?? '发送失败';
    return ['success' => false, 'message' => $msg];
}
