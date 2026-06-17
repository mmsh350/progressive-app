<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\AgentProfile;
use App\Models\Submission;
use App\DTOs\AgentProfileDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class EloquentAgentRepository implements AgentRepositoryInterface
{
    public function find(int $id): ?User
    {
        return User::with(['agentProfile.state'])->find($id);
    }

    public function createAgent(AgentProfileDTO $dto): User
    {
        return DB::transaction(function () use ($dto) {
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
            ]);

            $user->assignRole($dto->role);

            $user->agentProfile()->create([
                'state_id' => $dto->stateId,
                'status' => $dto->status,
                'created_by' => $dto->createdBy,
            ]);

            return $user;
        });
    }

    public function updateAgent(int $id, array $data, ?int $stateId): bool
    {
        return DB::transaction(function () use ($id, $data, $stateId) {
            $user = User::find($id);
            if (!$user) {
                return false;
            }

            $userFields = array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
            ]);

            if (!empty($data['password'])) {
                $userFields['password'] = Hash::make($data['password']);
            }

            $user->update($userFields);

            if ($user->agentProfile) {
                $user->agentProfile->update(['state_id' => $stateId]);
            } else {
                $user->agentProfile()->create(['state_id' => $stateId]);
            }

            return true;
        });
    }

    public function toggleStatus(int $id, string $status): bool
    {
        $user = User::find($id);
        if ($user && $user->agentProfile) {
            $user->agentProfile->status = $status;
            return $user->agentProfile->save();
        }
        return false;
    }

    public function getAgentsPaginated(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::safeRole('Agent')->with(['agentProfile.state']);

        $this->applySearch($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    public function getCoordinatorsPaginated(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::safeRole('State Coordinator')->with(['agentProfile.state']);

        $this->applySearch($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    public function getAgentPerformanceMetrics(int $agentId): array
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        $daily = Submission::where('agent_id', $agentId)
            ->whereDate('created_at', $today)
            ->count();

        $weekly = Submission::where('agent_id', $agentId)
            ->where('created_at', '>=', $startOfWeek)
            ->count();

        $monthly = Submission::where('agent_id', $agentId)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        return [
            'daily' => $daily,
            'weekly' => $weekly,
            'monthly' => $monthly,
        ];
    }

    protected function applySearch($query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['state_id'])) {
            $query->whereHas('agentProfile', function ($q) use ($filters) {
                $q->where('state_id', $filters['state_id']);
            });
        }
    }
}
