<?php

namespace App\Services;

use App\Handlers\ProcurementViewHandler;

class BacSecretariatServicesBuilder
{
    private ?MultichainService $multiChain = null;
    private ?StreamKeyService $streamKeyService = null;
    private ?ProcurementViewHandler $procurementHandler = null;
    private ?ProcurementStageTransitionService $stageTransitionService = null;
    private ?EventTypeLabelMapper $eventTypeLabelMapper = null;

    public function withMultiChain(MultichainService $multiChain): self
    {
        $this->multiChain = $multiChain;
        return $this;
    }

    public function withStreamKeyService(StreamKeyService $streamKeyService): self
    {
        $this->streamKeyService = $streamKeyService;
        return $this;
    }

    public function withProcurementHandler(ProcurementViewHandler $procurementHandler): self
    {
        $this->procurementHandler = $procurementHandler;
        return $this;
    }

    public function withStageTransitionService(ProcurementStageTransitionService $stageTransitionService): self
    {
        $this->stageTransitionService = $stageTransitionService;
        return $this;
    }

    public function withEventTypeLabelMapper(EventTypeLabelMapper $eventTypeLabelMapper): self
    {
        $this->eventTypeLabelMapper = $eventTypeLabelMapper;
        return $this;
    }

    public function build(): BacSecretariatServices
    {
        $this->validateRequiredServices();
        
        $services = new BacSecretariatServices(
            $this->multiChain,
            $this->streamKeyService,
            $this->procurementHandler
        );

        if ($this->stageTransitionService) {
            $services->setStageTransitionService($this->stageTransitionService);
        }

        if ($this->eventTypeLabelMapper) {
            $services->setEventTypeLabelMapper($this->eventTypeLabelMapper);
        }

        return $services;
    }

    private function validateRequiredServices(): void
    {
        if (!$this->multiChain) {
            throw new \RuntimeException('MultichainService is required');
        }
        if (!$this->streamKeyService) {
            throw new \RuntimeException('StreamKeyService is required');
        }
        if (!$this->procurementHandler) {
            throw new \RuntimeException('ProcurementHandler is required');
        }
    }
}