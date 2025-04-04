<?php

namespace App\Libraries\Multichain;

class SocketTransport implements TransportInterface
{
    private $lastErrorCode = 0;

    private $lastErrorMessage = '';

    public function execute(string $url, array $headers, string $payload): array
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'];

        $fp = fsockopen($host, $port);
        if (! $fp) {
            $this->lastErrorCode = 502;
            $this->lastErrorMessage = 'Unable to Connect';

            return ['success' => false, 'data' => null];
        }

        fwrite($fp, "POST / HTTP/1.1\r\n");
        fwrite($fp, "Host: $host\r\n");

        foreach ($headers as $header) {
            fwrite($fp, "$header\r\n");
        }

        fwrite($fp, 'Content-length: '.strlen($payload)."\r\n");
        fwrite($fp, "Connection: close\r\n\r\n");
        fwrite($fp, $payload."\r\n\r\n");

        $chunks = [];
        while (! feof($fp)) {
            $chunks[] = fgets($fp, 32768);
        }
        $response = implode('', $chunks);
        fclose($fp);

        $header_end = strpos($response, "\r\n\r\n");
        if (! $header_end) {
            $this->lastErrorCode = 502;
            $this->lastErrorMessage = 'Invalid Response';

            return ['success' => false, 'data' => null];
        }

        $encoded = trim(substr($response, $header_end + 4));
        $headers = explode("\r\n", substr($response, 0, $header_end));

        $httpCode = 200;
        if (substr($headers[0], 0, 4) == 'HTTP') {
            $arr = explode(' ', trim($headers[0]));
            $httpCode = (int) $arr[1];
        }

        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'data' => $encoded,
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
