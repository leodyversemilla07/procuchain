<?php

namespace App\Services;

class ProcurementServices
{
    protected $multiChain;

    protected $streamKeyService;

    protected $procurementHandler;

    protected $stageTransitionService;

    protected $eventTypeLabelMapper;

    public function __construct(
        MultichainService $multiChain,
        StreamKeyService $streamKeyService,
    ) {
        $this->multiChain = $multiChain;
        $this->streamKeyService = $streamKeyService;
    }

    public function setStageTransitionService(ProcurementStageTransitionService $service): self
    {
        $this->stageTransitionService = $service;

        return $this;
    }

    public function setEventTypeLabelMapper(EventTypeLabelMapper $mapper): self
    {
        $this->eventTypeLabelMapper = $mapper;

        return $this;
    }

    public function getMultiChain(): MultichainService
    {
        return $this->multiChain;
    }

    public function getStreamKeyService(): StreamKeyService
    {
        return $this->streamKeyService;
    }

    public function getStageTransitionService(): ProcurementStageTransitionService
    {
        if (! $this->stageTransitionService) {
            $this->stageTransitionService = new ProcurementStageTransitionService;
        }

        return $this->stageTransitionService;
    }

    public function getEventTypeLabelMapper(): EventTypeLabelMapper
    {
        if (! $this->eventTypeLabelMapper) {
            $this->eventTypeLabelMapper = new EventTypeLabelMapper;
        }

        return $this->eventTypeLabelMapper;
    }
}
