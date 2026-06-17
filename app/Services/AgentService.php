<?php

namespace App\Services;

use App\Repositories\AgentRepositoryInterface;
use App\Models\User;
use App\DTOs\AgentProfileDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class AgentService
{
    public function __construct(
        protected AgentRepositoryInterface $agentRepository
    ) {}

    /**
     * Create an Agent.
     */
    public function createAgent(AgentProfileDTO $dto): User
    {
        return $this->agentRepository->createAgent($dto);
    }

    /**
     * Update an agent assignment and settings.
     */
    public function updateAgent(int $id, array $data, ?int $stateId): bool
    {
        return $this->agentRepository->updateAgent($id, $data, $stateId);
    }

    /**
     * Suspend an agent.
     */
    public function suspendAgent(int $id): bool
    {
        return $this->agentRepository->toggleStatus($id, 'suspended');
    }

    /**
     * Activate an agent.
     */
    public function activateAgent(int $id): bool
    {
        return $this->agentRepository->toggleStatus($id, 'active');
    }

    /**
     * Fetch performance metrics for an agent.
     */
    public function getPerformanceMetrics(int $agentId): array
    {
        return $this->agentRepository->getAgentPerformanceMetrics($agentId);
    }

    /**
     * List agents paginated.
     */
    public function getAgents(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->agentRepository->getAgentsPaginated($filters, $perPage);
    }

    /**
     * List coordinators paginated.
     */
    public function getCoordinators(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->agentRepository->getCoordinatorsPaginated($filters, $perPage);
    }
}
