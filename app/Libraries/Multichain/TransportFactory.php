<?php

namespace App\Libraries\Multichain;

class TransportFactory
{
    public static function create(string $type, array $options = []): TransportInterface
    {
        switch ($type) {
            case 'curl':
                $transport = new CurlTransport;
                if (isset($options['verify_ssl'])) {
                    $transport->setVerifySSL($options['verify_ssl']);
                }

                return $transport;

            case 'socket':
                return new SocketTransport;

            default:
                throw new \InvalidArgumentException("Unknown transport type: $type");
        }
    }
}
