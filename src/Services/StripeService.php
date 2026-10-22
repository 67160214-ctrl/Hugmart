<?php
require_once __DIR__ . '/../Config/Payment.php';

/**
 * เรียก Stripe REST API ด้วย cURL
 * @throws RuntimeException เมื่อไม่ได้ตั้งค่าคีย์ หรือ Stripe ตอบกลับ error
 */
function stripeRequest(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
{
    $cfg = paymentConfig();
    if ($cfg['stripe_secret_key'] === '') {
        throw new RuntimeException('Stripe secret key is not configured');
    }

    $url = 'https://api.stripe.com' . $path;
    $headers = [];
    if ($idempotencyKey !== null) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init();
    if (strtoupper($method) === 'GET') {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params, '', '&');
        }
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $cfg['stripe_secret_key'] . ':',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Stripe connection error: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid response from Stripe (HTTP ' . $status . ')');
    }
    if ($status >= 400) {
        throw new RuntimeException($data['error']['message'] ?? ('Stripe error (HTTP ' . $status . ')'));
    }

    return $data;
}

/**
 * ตรวจลายเซ็นของ webhook (Stripe-Signature) ตามรูปแบบ t=...,v1=...
 */
function stripeVerifyWebhook(string $payload, string $sigHeader, string $secret, int $tolerance = 300): bool
{
    if ($secret === '' || $sigHeader === '') {
        return false;
    }

    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) {
            continue;
        }
        if ($kv[0] === 't') {
            $timestamp = (int)$kv[1];
        } elseif ($kv[0] === 'v1') {
            $signatures[] = $kv[1];
        }
    }

    if (!$timestamp || empty($signatures) || abs(time() - $timestamp) > $tolerance) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}