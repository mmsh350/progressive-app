<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\Wish;
use App\Models\RewardClaim;
use App\Models\User;
use App\Models\State;
use App\Models\Lga;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get summary KPI statistics.
     */
    public function getDashboardStats(?int $stateId = null): array
    {
        $submissionQuery = Submission::query();
        $wishQuery = Wish::query();
        $rewardQuery = RewardClaim::query();
        $agentQuery = User::safeRole('Agent');

        if ($stateId) {
            $submissionQuery->where('state_id', $stateId);
            $wishQuery->whereHas('submission', function($q) use ($stateId) {
                $q->where('state_id', $stateId);
            });
            $rewardQuery->whereHas('submission', function($q) use ($stateId) {
                $q->where('state_id', $stateId);
            });
            $agentQuery->whereHas('agentProfile', function($q) use ($stateId) {
                $q->where('state_id', $stateId);
            });
        }

        $totalSubmissions = $submissionQuery->count();
        $todaySubmissions = (clone $submissionQuery)->whereDate('created_at', Carbon::today())->count();
        $totalWishes = $wishQuery->count();
        $totalRewards = $rewardQuery->where('status', 'processed')->count();
        $totalAgents = $agentQuery->count();
        
        $totalStatesCovered = $submissionQuery->distinct('state_id')->count('state_id');
        $totalLgasCovered = $submissionQuery->distinct('lga_id')->count('lga_id');

        return [
            'total_submissions' => $totalSubmissions,
            'today_submissions' => $todaySubmissions,
            'total_wishes' => $totalWishes,
            'total_rewards' => $totalRewards,
            'total_agents' => $totalAgents,
            'total_states_covered' => $totalStatesCovered,
            'total_lgas_covered' => $totalLgasCovered,
        ];
    }

    /**
     * Get daily submission counts for last 30 days.
     */
    public function getSubmissionTrend(?int $stateId = null): array
    {
        $query = Submission::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', Carbon::now()->subDays(30));

        if ($stateId) {
            $query->where('state_id', $stateId);
        }

        $results = $query->groupBy('date')->orderBy('date', 'ASC')->get();

        $labels = [];
        $data = [];

        // Fill in missing dates with zero
        for ($i = 30; $i >= 0; $i--) {
            $dateString = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('M d');
            
            $found = $results->firstWhere('date', $dateString);
            $data[] = $found ? $found->count : 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Get count breakdown by occupation.
     */
    public function getOccupationBreakdown(?int $stateId = null): array
    {
        $query = Submission::join('occupations', 'submissions.occupation_id', '=', 'occupations.id')
            ->select('occupations.name', DB::raw('count(*) as count'));

        if ($stateId) {
            $query->where('submissions.state_id', $stateId);
        }

        $results = $query->groupBy('occupations.name')->get();

        return [
            'labels' => $results->pluck('name')->toArray(),
            'data' => $results->pluck('count')->toArray(),
        ];
    }

    /**
     * Get count distribution by State.
     */
    public function getStateDistribution(): array
    {
        $results = Submission::join('states', 'submissions.state_id', '=', 'states.id')
            ->select('states.name', DB::raw('count(*) as count'))
            ->groupBy('states.name')
            ->orderBy('count', 'DESC')
            ->limit(10)
            ->get();

        return [
            'labels' => $results->pluck('name')->toArray(),
            'data' => $results->pluck('count')->toArray(),
        ];
    }

    /**
     * Get count distribution by Wish Category.
     */
    public function getWishDistribution(?int $stateId = null): array
    {
        $query = Wish::join('wish_categories', 'wishes.wish_category_id', '=', 'wish_categories.id')
            ->join('submissions', 'wishes.submission_id', '=', 'submissions.id')
            ->select('wish_categories.name', DB::raw('count(*) as count'));

        if ($stateId) {
            $query->where('submissions.state_id', $stateId);
        }

        $results = $query->groupBy('wish_categories.name')->get();

        return [
            'labels' => $results->pluck('name')->toArray(),
            'data' => $results->pluck('count')->toArray(),
        ];
    }

    /**
     * Get voting statistics for 2023.
     */
    public function getVoting2023Analysis(?int $stateId = null): array
    {
        $query = Submission::select('voted_2023', DB::raw('count(*) as count'));

        if ($stateId) {
            $query->where('state_id', $stateId);
        }

        $results = $query->groupBy('voted_2023')->get();

        $votedMap = ['Voted' => 0, 'Did Not Vote' => 0];
        foreach ($results as $row) {
            $label = $row->voted_2023 ? 'Voted' : 'Did Not Vote';
            $votedMap[$label] = $row->count;
        }

        return [
            'labels' => array_keys($votedMap),
            'data' => array_values($votedMap),
        ];
    }

    /**
     * Get voting statistics for 2027.
     */
    public function getVoting2027Intention(?int $stateId = null): array
    {
        $query = Submission::select('vote_2027', DB::raw('count(*) as count'));

        if ($stateId) {
            $query->where('state_id', $stateId);
        }

        $results = $query->groupBy('vote_2027')->get();

        $votedMap = ['Will Vote' => 0, 'Will Not Vote' => 0];
        foreach ($results as $row) {
            $label = $row->vote_2027 ? 'Will Vote' : 'Will Not Vote';
            $votedMap[$label] = $row->count;
        }

        return [
            'labels' => array_keys($votedMap),
            'data' => array_values($votedMap),
        ];
    }

    /**
     * Get distribution of rewards claimed.
     */
    public function getRewardDistribution(?int $stateId = null): array
    {
        $query = RewardClaim::join('rewards', 'reward_claims.reward_id', '=', 'rewards.id')
            ->select('rewards.name', DB::raw('count(*) as count'));

        if ($stateId) {
            $query->join('submissions', 'reward_claims.submission_id', '=', 'submissions.id')
                ->where('submissions.state_id', $stateId);
        }

        $results = $query->groupBy('rewards.name')->get();

        return [
            'labels' => $results->pluck('name')->toArray(),
            'data' => $results->pluck('count')->toArray(),
        ];
    }
}
