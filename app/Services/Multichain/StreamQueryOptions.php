<?php

namespace App\Services\Multichain;

class StreamQueryOptions
{
    private string $stream;
    private bool $verbose;
    private int $count;
    private int $start;
    private ?string $localOrdering;
    private ?string $key;
    private ?string $address;

    /**
     * Create a new StreamQueryOptions instance
     * 
     * @param string $stream Stream name
     * @param bool $verbose Whether to return verbose output
     * @param int $count Maximum number of items to retrieve
     * @param int|null $start Start position (default is calculated from count)
     * @param string|null $localOrdering Local ordering option
     */
    public function __construct(
        string $stream,
        bool $verbose = true,
        int $count = 10,
        ?int $start = null,
        ?string $localOrdering = null
    ) {
        $this->stream = $stream;
        $this->verbose = $verbose;
        $this->count = $count;
        $this->start = $start ?? -$count; // Default to -count if null
        $this->localOrdering = $localOrdering;
        $this->key = null;
        $this->address = null;
    }

    /**
     * Create options for querying by stream key
     * 
     * @param string $stream Stream name
     * @param string $key Key to filter by
     * @param bool $verbose Whether to return verbose output
     * @param int $count Maximum number of items to retrieve
     * @param bool $localOrdering Local ordering option
     * @return self
     */
    public static function forKey(
        string $stream,
        string $key,
        bool $verbose = false,
        int $count = 1000,
        bool $localOrdering = false
    ): self {
        $options = new self($stream, $verbose, $count, -$count, $localOrdering ? 'true' : null);
        $options->key = $key;
        return $options;
    }

    /**
     * Create options for querying by publisher address
     * 
     * @param string $stream Stream name
     * @param string $address Publisher address to filter by
     * @param bool $verbose Whether to return verbose output
     * @param int $count Maximum number of items to retrieve
     * @param bool $localOrdering Local ordering option
     * @return self
     */
    public static function forPublisher(
        string $stream,
        string $address,
        bool $verbose = false,
        int $count = 1000,
        bool $localOrdering = false
    ): self {
        $options = new self($stream, $verbose, $count, -$count, $localOrdering ? 'true' : null);
        $options->address = $address;
        return $options;
    }

    /**
     * Get parameters for liststreamitems method
     * 
     * @return array
     */
    public function toStreamItemsParams(): array
    {
        $params = [$this->stream, $this->verbose, $this->count, $this->start];
        
        if ($this->localOrdering !== null) {
            $params[] = $this->localOrdering;
        }
        
        return $params;
    }

    /**
     * Get parameters for liststreamkeyitems method
     * 
     * @return array
     */
    public function toStreamKeyItemsParams(): array
    {
        if ($this->key === null) {
            throw new \InvalidArgumentException("Key is required for stream key items query");
        }
        
        return [
            $this->stream, 
            $this->key, 
            $this->verbose, 
            $this->count, 
            $this->start, 
            $this->localOrdering !== null
        ];
    }

    /**
     * Get parameters for liststreampublisheritems method
     * 
     * @return array
     */
    public function toStreamPublisherItemsParams(): array
    {
        if ($this->address === null) {
            throw new \InvalidArgumentException("Address is required for stream publisher items query");
        }
        
        return [
            $this->stream, 
            $this->address, 
            $this->verbose, 
            $this->count, 
            $this->start, 
            $this->localOrdering !== null
        ];
    }

    /**
     * Get log context based on query type
     * 
     * @return array
     */
    public function getLogContext(): array
    {
        $context = ['stream' => $this->stream];
        
        if ($this->key !== null) {
            $context['key'] = $this->key;
        }
        
        if ($this->address !== null) {
            $context['address'] = $this->address;
        }
        
        return $context;
    }

    // Getters
    public function getStream(): string
    {
        return $this->stream;
    }

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getLocalOrdering(): ?string
    {
        return $this->localOrdering;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }
}