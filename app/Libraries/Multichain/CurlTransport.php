<?php

namespace App\Libraries\Multichain;

class CurlTransport implements TransportInterface
{
    private $verifySSL = true;

    private $lastErrorCode = 0;

    private $lastErrorMessage = '';

    public function setVerifySSL(bool $verify): void
    {
        $this->verifySSL = $verify;
    }

    public function execute(string $url, array $headers, string $payload): array
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (! $this->verifySSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            $this->lastErrorCode = curl_errno($ch);
            $this->lastErrorMessage = curl_error($ch);
            curl_close($ch);

            return ['success' => false, 'data' => null];
        }

        $info = curl_getinfo($ch);
        $httpCode = $info['http_code'];
        curl_close($ch);

        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'data' => $response,
            'http_code' => $httpCode,
        ];
    }

    public function getLastError(): array
    {
        return [
            'code' => $this->lastErrorCode,
            'message' => $this->lastErrorMessage,
        ];
    }
}
