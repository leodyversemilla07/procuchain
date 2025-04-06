<?php

namespace App\Libraries;

use App\Libraries\Multichain\ConnectionConfig;
use App\Libraries\Multichain\CurlTransport;
use App\Libraries\Multichain\SocketTransport;

define('MC_DEFAULT_ERROR_CODE', 502);
define('MC_OPT_CHAIN_NAME', 1);
define('MC_OPT_USE_CURL', 2);
define('MC_OPT_VERIFY_SSL', 3);

class MultichainClient
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $chainname;
    private $error_code = 0;
    private $error_message = '';
    private $usessl = false;
    private $transport;

    /**
     * Create a new MultichainClient instance
     * 
     * @param ConnectionConfig $config Connection configuration object
     * @return void
     */
    public function __construct(ConnectionConfig $config)
    {
        $this->initializeFromConfig($config);
    }

    /**
     * Create a MultichainClient from Laravel configuration
     * 
     * @param array $config Configuration array
     * @return self
     */
    public static function fromConfig(array $config): self
    {
        return new self(ConnectionConfig::fromConfig($config));
    }

    /**
     * Create a MultichainClient from connection parameters
     * 
     * @param string $host Host address
     * @param int $port Port number
     * @param string $username RPC username
     * @param string $password RPC password
     * @param bool $usessl Use SSL connection
     * @param string|null $chainName Chain name
     * @param bool $verifySSL Verify SSL certificates
     * @return self
     */
    public static function fromParams(
        string $host,
        int $port,
        string $username,
        string $password,
        bool $usessl = false,
        ?string $chainName = null,
        bool $verifySSL = true
    ): self {
        $config = new ConnectionConfig($host, $port, $username, $password, $usessl, $chainName, $verifySSL);
        return new self($config);
    }

    /**
     * Initialize client from ConnectionConfig object
     * 
     * @param ConnectionConfig $config
     * @return void
     */
    private function initializeFromConfig(ConnectionConfig $config): void
    {
        $this->host = $config->getHost();
        $this->port = $config->getPort();
        $this->username = $config->getUsername();
        $this->password = $config->getPassword();
        $this->usessl = $config->useSSL();
        $this->chainname = $config->getChainName();

        $this->transport = $this->usessl ? new CurlTransport : new SocketTransport;

        // Apply transport settings
        if ($this->usessl && $this->transport instanceof CurlTransport) {
            $this->transport->setVerifySSL($config->verifySSL());
        }
    }

    public function setoption($option, $value)
    {
        switch ($option) {
            case MC_OPT_CHAIN_NAME:
                $this->chainname = $value;
                break;
            case MC_OPT_USE_CURL:
                $this->transport = $value ? new CurlTransport : new SocketTransport;
                break;
            case MC_OPT_VERIFY_SSL:
                if ($this->transport instanceof CurlTransport) {
                    $this->transport->setVerifySSL($value);
                }
                break;
            default:
                return false;
        }

        return true;
    }

    public function __call($method, $params)
    {
        $url = ($this->usessl ? 'https' : 'http') . '://' . $this->host . ':' . $this->port . '/';
        $payload = $this->preparePayload($method, $params);
        $strUserPass64 = base64_encode($this->username . ':' . $this->password);

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
            'Connection: close',
            'Authorization: Basic ' . $strUserPass64,
        ];

        $response = $this->transport->execute($url, $headers, $payload);

        if (!$response['success']) {
            $error = $this->transport->getLastError();
            $this->error_code = $error['code'];
            $this->error_message = $error['message'];

            return null;
        }

        return $this->parseResponse($response['data']);
    }

    private function preparePayload($method, $params)
    {
        $request = [
            'id' => time(),
            'method' => $method,
            'params' => $params,
        ];

        if (!is_null($this->chainname)) {
            $request['chain_name'] = $this->chainname;
        }

        return json_encode($request);
    }

    private function parseResponse($encoded)
    {
        if ($this->isEmptyResponse($encoded)) {
            return null;
        }

        $decoded = $this->decodeJsonResponse($encoded);
        if ($decoded === null) {
            return null;
        }

        if (!$this->isValidResponseStructure($decoded)) {
            return null;
        }

        $this->error_code = 0;

        if ($this->hasError($decoded)) {
            $this->handleError($decoded['error']);

            return null;
        }

        $this->error_message = '';

        return $decoded['result'];
    }

    private function isEmptyResponse($encoded)
    {
        if (is_null($encoded)) {
            if ($this->error_code == 200) {
                $this->error_message = 'Missing Response';
            }

            return true;
        }

        return false;
    }

    private function decodeJsonResponse($encoded)
    {
        $decoded = json_decode($encoded, true);
        if (is_null($decoded)) {
            $this->error_message = 'Invalid JSON Response';

            return null;
        }

        return $decoded;
    }

    private function isValidResponseStructure($decoded)
    {
        if (!array_key_exists('error', $decoded) || !array_key_exists('result', $decoded)) {
            $this->error_message = 'Invalid Response Structure';

            return false;
        }

        return true;
    }

    private function hasError($decoded)
    {
        return !is_null($decoded['error']);
    }

    private function handleError($error)
    {
        $this->error_code = $error['code'];
        $this->error_message = $error['message'];

        if ($this->error_code == -1 && strpos($this->error_message, "\n\n") !== false) {
            $this->error_message = "Wrong parameters. Usage:\n\n" . $this->error_message;
        }
    }

    public function errorcode()
    {
        return $this->error_code;
    }

    public function errormessage()
    {
        return $this->error_message;
    }

    public function success()
    {
        return $this->error_code == 0;
    }
}
