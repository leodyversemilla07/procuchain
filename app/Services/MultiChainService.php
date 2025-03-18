<?php

namespace App\Services;

use App\Libraries\MultiChainClient;
use Exception;
use Illuminate\Support\Facades\Log;

class MultichainService
{
    public $client;

    public function __construct()
    {
        $this->client = new MultiChainClient(
            config('multichain.rpc.host'),
            config('multichain.rpc.port'),
            config('multichain.rpc.username'),
            config('multichain.rpc.password'),
            config('multichain.use_ssl', false)
        );

        $this->client->setoption(MC_OPT_CHAIN_NAME, config('multichain.chain_name'));
        $this->client->setoption(MC_OPT_USE_CURL, true);
        $this->client->setoption(MC_OPT_VERIFY_SSL, config('multichain.verify_ssl', true));
    }

    public function testConnection(): array
    {
        try {
            $info = $this->client->getinfo();

            if (! $this->client->success()) {
                throw new Exception(
                    sprintf(
                        'Connection failed - Error %d: %s',
                        $this->client->errorcode(),
                        $this->client->errormessage()
                    )
                );
            }

            return [
                'success' => true,
                'chain' => $info['chainname'],
                'version' => $info['version'],
                'protocol' => $info['protocol'],
                'node_address' => $info['nodeaddress'],
            ];

        } catch (Exception $e) {
            Log::error('MultiChain Connection Test Failed', [
                'host' => config('multichain.rpc.host'),
                'port' => config('multichain.rpc.port'),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $this->client->errorcode(),
                'error_message' => $this->client->errormessage(),
            ];
        }
    }

    public function getInfo(): array
    {
        try {
            $info = $this->client->getinfo();

            if (! $this->client->success()) {
                throw new Exception(
                    sprintf(
                        'Error %d: %s',
                        $this->client->errorcode(),
                        $this->client->errormessage()
                    )
                );
            }

            return $info;

        } catch (Exception $e) {
            Log::error('Failed to get blockchain info', [
                'error' => $e->getMessage(),
                'error_code' => $this->client->errorcode(),
                'error_message' => $this->client->errormessage(),
            ]);
            throw new Exception('Failed to get blockchain info: '.$e->getMessage());
        }
    }

    public function publishFrom(string $fromAddress, string $stream, string $key, array $data): string
    {
        try {
            $txid = $this->client->publishfrom($fromAddress, $stream, $key, bin2hex(json_encode($data)));

            if (! $this->client->success()) {
                throw new Exception(
                    sprintf(
                        'Error %d: %s',
                        $this->client->errorcode(),
                        $this->client->errormessage()
                    )
                );
            }

            Log::info('Blockchain transaction created', [
                'txid' => $txid,
                'stream' => $stream,
                'key' => $key,
                'from_address' => $fromAddress,
            ]);

            return $txid;
        } catch (Exception $e) {
            Log::error('Blockchain transaction failed', [
                'error' => $e->getMessage(),
                'error_code' => $this->client->errorcode(),
                'error_message' => $this->client->errormessage(),
                'key' => $key,
                'from_address' => $fromAddress,
            ]);
            throw new Exception('Failed to publish to blockchain: '.$e->getMessage());
        }
    }

    public function publishMultiFrom(string $fromAddress, string $stream, array $items): string
    {
        try {
            $formattedItems = array_map(function ($item) {
                return [
                    'key' => $item['key'],
                    'data' => bin2hex(json_encode($item['data'])),
                ];
            }, $items);

            $txid = $this->client->publishmultifrom(
                $fromAddress,
                $stream,
                $formattedItems,
            );

            if (! $this->client->success()) {
                throw new Exception(
                    sprintf(
                        'Error %d: %s',
                        $this->client->errorcode(),
                        $this->client->errormessage()
                    )
                );
            }

            Log::info('Blockchain multi-transaction created', [
                'txid' => $txid,
                'stream' => $stream,
                'item_count' => count($items),
                'from_address' => $fromAddress,
            ]);

            return $txid;
        } catch (Exception $e) {
            Log::error('Blockchain multi-transaction failed', [
                'error' => $e->getMessage(),
                'error_code' => $this->client->errorcode(),
                'error_message' => $this->client->errormessage(),
                'from_address' => $fromAddress,
            ]);
            throw new Exception('Failed to publish multiple items to blockchain: '.$e->getMessage());
        }
    }

    public function listStreamKeyItems(string $stream, string $key, int $count = 1000, bool $verbose = false, bool $localOrdering = false): array
    {
        try {
            $start = -$count;
            $items = $this->client->liststreamkeyitems($stream, $key, $verbose, $count, $start, $localOrdering);

            if (! $this->client->success()) {
                throw new Exception(
                    sprintf(
                        'Error %d: %s',
                        $this->client->errorcode(),
                        $this->client->errormessage()
                    )
                );
            }

            return array_map(function ($item) {
                $item['data'] = json_decode(hex2bin($item['data']), true);

                return $item;
            }, $items);
        } catch (Exception $e) {
            Log::error('Failed to list stream key items', [
                'stream' => $stream,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to list stream key items: '.$e->getMessage());
        }
    }

    public function listStreamItems(string $stream, bool $verbose = false, int $count = 1000, bool $localOrdering = false): array
    {
        try {
            $start = -$count;
            $items = $this->client->liststreamitems($stream, $verbose, $count, $start, $localOrdering);

            if (! $this->client->success()) {
                throw new Exception(
                    sprintf(
                        'Error %d: %s',
                        $this->client->errorcode(),
                        $this->client->errormessage()
                    )
                );
            }

            return array_map(function ($item) {
                $item['data'] = json_decode(hex2bin($item['data']), true);

                return $item;
            }, $items);
        } catch (Exception $e) {
            Log::error('Failed to list stream items', [
                'stream' => $stream,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to list stream items: '.$e->getMessage());
        }
    }

    public function listStreamPublisherItems(string $stream, string $address, bool $verbose = false, int $count = 1000, bool $localOrdering = false): array
    {
        try {
            $start = -$count;
            $items = $this->client->liststreampublisheritems($stream, $address, $verbose, $count, $start, $localOrdering);

            if (! $this->client->success()) {
                throw new Exception(
                    sprintf(
                        'Error %d: %s',
                        $this->client->errorcode(),
                        $this->client->errormessage()
                    )
                );
            }

            return array_map(function ($item) {
                $item['data'] = json_decode(hex2bin($item['data']), true);

                return $item;
            }, $items);
        } catch (Exception $e) {
            Log::error('Failed to list stream publisher items', [
                'stream' => $stream,
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to list stream publisher items: '.$e->getMessage());
        }
    }
}
