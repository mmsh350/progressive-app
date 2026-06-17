<?php

namespace App\Repositories;

use App\Models\User;
use App\DTOs\AgentProfileDTO;
use Illuminate\Pagination\LengthAwarePaginator;

interface AgentRepositoryInterface
{
    /**
     * Find an agent/coordinator by User ID.
     */
    public function find(int $id): ?User;

    /**
     * Create an Agent/Coordinator user and their profile.
     */
    public function createAgent(AgentProfileDTO $dto): User;

    /**
     * Update an agent details and state assignment.
     */
    public function updateAgent(int $id, array $data, ?int $stateId): bool;

    /**
     * Suspend or Activate an agent.
     */
    public function toggleStatus(int $id, string $status): bool;

    /**
     * Get paginated list of Agents.
     *
     * @param array<string, mixed> $filters
     */
    public function getAgentsPaginated(array $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get paginated list of State Coordinators.
     *
     * @param array<string, mixed> $filters
     */
    public function getCoordinatorsPaginated(array $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get registration metrics for an agent.
     *
     * @return array{daily: int, weekly: int, monthly: int}
     */
    public function getAgentPerformanceMetrics(int $agentId): array;
}
