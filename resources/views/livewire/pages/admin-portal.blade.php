<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Submission;
use App\Models\State;
use App\Models\Lga;
use App\Models\Occupation;
use App\Models\WishCategory;
use App\Models\Reward;
use App\Models\RewardClaim;
use App\Models\User;
use App\Models\Campaign;
use App\Models\AgentProfile;
use App\DTOs\AgentProfileDTO;
use App\Services\AnalyticsService;
use App\Services\AgentService;
use App\Repositories\SubmissionRepositoryInterface;
use App\Actions\ApproveSubmissionAction;
use App\Actions\RejectSubmissionAction;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SubmissionsExport;

new class extends Component {
    use WithPagination;

    public string $activeTab = 'analytics';

    // Filter properties for Submissions
    public string $search = '';
    public ?int $stateId = null;
    public ?int $lgaId = null;
    public ?int $occupationId = null;
    public string $gender = '';
    public string $ageGroup = '';
    public string $voted2023 = '';
    public string $vote2027 = '';
    public string $status = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    // LGA mapping
    public $lgas = [];

    // Bulk action state
    public array $selectedSubmissions = [];
    public ?int $assignAgentId = null;

    // CRUD state for Agents
    public string $agentName = '';
    public string $agentEmail = '';
    public string $agentPassword = '';
    public ?int $agentStateId = null;
    public string $agentRole = 'Agent';

    // CRUD state for Rewards
    public ?int $editRewardId = null;
    public string $editRewardName = '';
    public float $editRewardProbability = 0.00;
    public int $editRewardDailyLimit = 0;
    public int $editRewardInventoryLimit = 0;
    public bool $editRewardIsActive = true;

    // CRUD state for Campaigns
    public string $campaignName = '';
    public string $campaignDescription = '';
    public string $campaignStartDate = '';
    public string $campaignEndDate = '';
    public array $campaignStates = [];
    public bool $campaignIsActive = true;

    // View photo drawer
    public ?string $previewPhotoUrl = null;

    public function mount()
    {
        // Scope Coordinator state immediately
        if (auth()->user()->hasRole('State Coordinator')) {
            $this->stateId = auth()->user()->agentProfile?->state_id;
            $this->lgas = Lga::where('state_id', $this->stateId)->orderBy('name')->get()->toArray();
        } elseif (auth()->user()->hasRole('Agent')) {
            $this->stateId = auth()->user()->agentProfile?->state_id;
            $this->lgas = Lga::where('state_id', $this->stateId)->orderBy('name')->get()->toArray();
        } else {
            // Admin can choose
            $firstState = State::orderBy('name')->first();
            if ($firstState) {
                $this->lgas = Lga::where('state_id', $firstState->id)->orderBy('name')->get()->toArray();
            }
        }
    }

    public function updatedStateId()
    {
        if (auth()->user()->hasRole('State Coordinator')) {
            // Enforce coordination scope
            $this->stateId = auth()->user()->agentProfile?->state_id;
        }
        $this->lgas = Lga::where('state_id', $this->stateId)->orderBy('name')->get()->toArray();
        $this->lgaId = null;
    }

    public function changeTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    // --- Submissions Actions ---

    public function approveSubmission(int $id, ApproveSubmissionAction $action)
    {
        $action->execute($id);
        session()->flash('message', 'Submission approved successfully.');
    }

    public function rejectSubmission(int $id, RejectSubmissionAction $action)
    {
        $action->execute($id);
        session()->flash('message', 'Submission rejected successfully.');
    }

    public function bulkApprove(ApproveSubmissionAction $action)
    {
        foreach ($this->selectedSubmissions as $id) {
            $action->execute((int)$id);
        }
        $this->selectedSubmissions = [];
        session()->flash('message', 'Selected submissions approved successfully.');
    }

    public function bulkReject(RejectSubmissionAction $action)
    {
        foreach ($this->selectedSubmissions as $id) {
            $action->execute((int)$id);
        }
        $this->selectedSubmissions = [];
        session()->flash('message', 'Selected submissions rejected successfully.');
    }

    public function bulkDelete(SubmissionRepositoryInterface $repo)
    {
        $repo->bulkDelete(array_map('intval', $this->selectedSubmissions));
        $this->selectedSubmissions = [];
        session()->flash('message', 'Selected submissions deleted successfully.');
    }

    public function bulkAssignAgent(SubmissionRepositoryInterface $repo)
    {
        $this->validate(['assignAgentId' => 'required|exists:users,id']);
        $repo->bulkAssignAgent(array_map('intval', $this->selectedSubmissions), $this->assignAgentId);
        $this->selectedSubmissions = [];
        $this->assignAgentId = null;
        session()->flash('message', 'Agent assigned to selected submissions.');
    }

    public function exportFiltered(SubmissionRepositoryInterface $repo)
    {
        $filters = $this->getFiltersArray();
        $data = $repo->getForExport($filters);
        
        return Excel::download(new SubmissionsExport($data), 'submissions_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
    }

    // --- Agent Actions ---

    public function createAgentUser(AgentService $service)
    {
        $this->validate([
            'agentName' => 'required|string|max:255',
            'agentEmail' => 'required|email|unique:users,email',
            'agentPassword' => 'required|string|min:8',
            'agentStateId' => 'required|exists:states,id',
            'agentRole' => 'required|in:Agent,State Coordinator',
        ]);

        $dto = AgentProfileDTO::fromArray([
            'name' => $this->agentName,
            'email' => $this->agentEmail,
            'password' => $this->agentPassword,
            'state_id' => $this->agentStateId,
            'role' => $this->agentRole,
            'created_by' => auth()->id(),
        ]);

        $service->createAgent($dto);

        // Reset inputs
        $this->agentName = '';
        $this->agentEmail = '';
        $this->agentPassword = '';
        
        session()->flash('message', "{$this->agentRole} account registered successfully.");
    }

    public function toggleAgentStatus(int $id, string $currentStatus, AgentService $service)
    {
        $newStatus = $currentStatus === 'active' ? 'suspended' : 'active';
        if ($newStatus === 'suspended') {
            $service->suspendAgent($id);
        } else {
            $service->activateAgent($id);
        }
        session()->flash('message', "Agent profile updated successfully.");
    }

    public function getPerformanceMetrics(int $agentId): array
    {
        return app(AgentService::class)->getPerformanceMetrics($agentId);
    }

    // --- Reward Inventory ---

    public function openEditReward(int $id)
    {
        $reward = Reward::find($id);
        if ($reward) {
            $this->editRewardId = $reward->id;
            $this->editRewardName = $reward->name;
            $this->editRewardProbability = (float) $reward->probability;
            $this->editRewardDailyLimit = $reward->daily_limit;
            $this->editRewardInventoryLimit = $reward->inventory_limit;
            $this->editRewardIsActive = $reward->is_active;
        }
    }

    public function saveReward()
    {
        $this->validate([
            'editRewardProbability' => 'required|numeric|min:0|max:100',
            'editRewardDailyLimit' => 'required|integer|min:0',
            'editRewardInventoryLimit' => 'required|integer|min:0',
        ]);

        $reward = Reward::find($this->editRewardId);
        if ($reward) {
            $reward->update([
                'probability' => $this->editRewardProbability,
                'daily_limit' => $this->editRewardDailyLimit,
                'inventory_limit' => $this->editRewardInventoryLimit,
                'is_active' => $this->editRewardIsActive,
            ]);

            $this->editRewardId = null;
            session()->flash('message', 'Reward parameters updated successfully.');
        }
    }

    // --- Campaign Actions ---

    public function createCampaign()
    {
        $this->validate([
            'campaignName' => 'required|string|max:255',
            'campaignStartDate' => 'required|date',
            'campaignEndDate' => 'required|date|after_or_equal:campaignStartDate',
            'campaignStates' => 'required|array|min:1',
        ]);

        $campaign = Campaign::create([
            'name' => $this->campaignName,
            'description' => $this->campaignDescription,
            'start_date' => $this->campaignStartDate,
            'end_date' => $this->campaignEndDate,
            'is_active' => $this->campaignIsActive,
        ]);

        $campaign->states()->sync($this->campaignStates);

        // Reset
        $this->campaignName = '';
        $this->campaignDescription = '';
        $this->campaignStartDate = '';
        $this->campaignEndDate = '';
        $this->campaignStates = [];

        session()->flash('message', 'Campaign registered successfully.');
    }

    public function toggleCampaign(int $id)
    {
        $camp = Campaign::find($id);
        if ($camp) {
            $camp->update(['is_active' => !$camp->is_active]);
            session()->flash('message', 'Campaign status updated.');
        }
    }

    // --- Helper Logic ---

    protected function getFiltersArray(): array
    {
        $filters = [
            'search' => $this->search,
            'state_id' => $this->stateId,
            'lga_id' => $this->lgaId,
            'occupation_id' => $this->occupationId,
            'gender' => $this->gender,
            'age_group' => $this->ageGroup,
            'voted_2023' => $this->voted2023 !== '' ? $this->voted2023 : null,
            'vote_2027' => $this->vote2027 !== '' ? $this->vote2027 : null,
            'status' => $this->status,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];

        if (auth()->user()->hasRole('State Coordinator')) {
            $filters['state_id'] = auth()->user()->agentProfile?->state_id;
        } elseif (auth()->user()->hasRole('Agent')) {
            $filters['agent_id'] = auth()->id();
        }

        return array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    public function showPhoto(string $path)
    {
        $this->previewPhotoUrl = Storage::url($path);
    }

    public function closePhoto()
    {
        $this->previewPhotoUrl = null;
    }

    public function with(AnalyticsService $analyticsService): array
    {
        $scopeStateId = auth()->user()->hasRole('State Coordinator') ? auth()->user()->agentProfile?->state_id : null;
        
        // Load stats datasets for charts
        $dashboardKpi = $analyticsService->getDashboardStats($scopeStateId);
        $trendDataset = $analyticsService->getSubmissionTrend($scopeStateId);
        $occupationDataset = $analyticsService->getOccupationBreakdown($scopeStateId);
        $wishDataset = $analyticsService->getWishDistribution($scopeStateId);
        
        $filters = $this->getFiltersArray();

        return [
            'dashboardKpi' => $dashboardKpi,
            'trendDataset' => $trendDataset,
            'occupationDataset' => $occupationDataset,
            'wishDataset' => $wishDataset,
            'submissionsList' => Submission::with(['state', 'lga', 'occupation', 'image', 'spin.reward'])
                ->when($filters['search'] ?? null, function ($q, $search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('full_name', 'like', "%{$search}%")
                              ->orWhere('phone_number', 'like', "%{$search}%")
                              ->orWhere('reference_number', 'like', "%{$search}%");
                    });
                })
                ->when($filters['state_id'] ?? null, fn($q, $id) => $q->where('state_id', $id))
                ->when($filters['lga_id'] ?? null, fn($q, $id) => $q->where('lga_id', $id))
                ->when($filters['occupation_id'] ?? null, fn($q, $id) => $q->where('occupation_id', $id))
                ->when($filters['gender'] ?? null, fn($q, $g) => $q->where('gender', $g))
                ->when($filters['age_group'] ?? null, fn($q, $a) => $q->where('age_group', $a))
                ->when(isset($filters['voted_2023']), fn($q) => $q->where('voted_2023', $filters['voted_2023']))
                ->when(isset($filters['vote_2027']), fn($q) => $q->where('vote_2027', $filters['vote_2027']))
                ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
                ->when($filters['agent_id'] ?? null, fn($q, $id) => $q->where('agent_id', $id))
                ->when($filters['date_from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($filters['date_to'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
                ->latest()
                ->paginate(10),
            
            'allStates' => State::orderBy('name')->get(),
            'allOccupations' => Occupation::where('is_active', true)->orderBy('name')->get(),
            'allAgents' => User::safeRole('Agent')->get(),
            'allCampaigns' => Campaign::with(['states'])->latest()->get(),
            'allRewards' => Reward::all(),
            'rewardClaimsLog' => RewardClaim::with(['submission', 'reward'])->latest()->paginate(10, ['*'], 'claims_page'),
            'agentsList' => User::safeRole('Agent')->with(['agentProfile.state'])->latest()->get(),
            'coordinatorsList' => User::safeRole('State Coordinator')->with(['agentProfile.state'])->latest()->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Feedback banner -->
    @if (session()->has('message'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 font-bold text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- Scoped Header Alert -->
    @if (auth()->user()->hasRole('State Coordinator'))
        <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs font-semibold">
            ℹ️ You are viewing data scoped to your assigned state: <strong>{{ auth()->user()->agentProfile?->state?->name ?? 'Lagos' }}</strong>.
        </div>
    @endif

    <!-- Dashboard Tabs -->
    <div class="flex border-b border-gray-200 bg-white rounded-t-xl overflow-hidden">
        <button wire:click="changeTab('analytics')" class="flex-1 py-4 text-center font-bold text-sm border-b-2 hover:bg-gray-50 transition {{ $activeTab === 'analytics' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500' }}">
            📊 Analytics & Trends
        </button>
        <button wire:click="changeTab('submissions')" class="flex-1 py-4 text-center font-bold text-sm border-b-2 hover:bg-gray-50 transition {{ $activeTab === 'submissions' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500' }}">
            📋 Submissions Grid
        </button>
        
        @if (auth()->user()->hasAnyRole(['Super Admin', 'Admin']))
            <button wire:click="changeTab('rewards')" class="flex-1 py-4 text-center font-bold text-sm border-b-2 hover:bg-gray-50 transition {{ $activeTab === 'rewards' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500' }}">
                🎁 Spin Rewards
            </button>
            <button wire:click="changeTab('agents')" class="flex-1 py-4 text-center font-bold text-sm border-b-2 hover:bg-gray-50 transition {{ $activeTab === 'agents' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500' }}">
                👥 Agents & Coordinators
            </button>
        @endif

        @if (auth()->user()->hasRole('Super Admin'))
            <button wire:click="changeTab('campaigns')" class="flex-1 py-4 text-center font-bold text-sm border-b-2 hover:bg-gray-50 transition {{ $activeTab === 'campaigns' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500' }}">
                ⚙️ Campaigns
            </button>
        @endif
    </div>

    <!-- TAB 1: ANALYTICS -->
    @if ($activeTab === 'analytics')
        <div class="space-y-6">
            <!-- Summary stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-gradient-to-br from-blue-600 to-blue-700 p-5 rounded-2xl text-white shadow-sm">
                    <p class="text-xs text-blue-100 font-bold uppercase tracking-wider">Total Declarations</p>
                    <h3 class="text-2xl font-black mt-1">{{ number_format($dashboardKpi['total_submissions']) }}</h3>
                </div>
                <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 p-5 rounded-2xl text-white shadow-sm">
                    <p class="text-xs text-emerald-100 font-bold uppercase tracking-wider">Today's Registrations</p>
                    <h3 class="text-2xl font-black mt-1">{{ number_format($dashboardKpi['today_submissions']) }}</h3>
                </div>
                <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 p-5 rounded-2xl text-white shadow-sm">
                    <p class="text-xs text-indigo-100 font-bold uppercase tracking-wider">Development Wishes</p>
                    <h3 class="text-2xl font-black mt-1">{{ number_format($dashboardKpi['total_wishes']) }}</h3>
                </div>
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 p-5 rounded-2xl text-white shadow-sm">
                    <p class="text-xs text-purple-100 font-bold uppercase tracking-wider">Rewards Processed</p>
                    <h3 class="text-2xl font-black mt-1">{{ number_format($dashboardKpi['total_rewards']) }}</h3>
                </div>
            </div>

            <!-- Crisp SVG Graphical Breakdowns (Bulletproof Dashboard Visuals) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Trend Chart representation -->
                <div class="bg-white p-6 rounded-2xl border border-gray-200">
                    <h4 class="font-extrabold text-gray-900 text-sm mb-4">Submission Growth (Last 30 Days)</h4>
                    <div class="h-48 flex items-end justify-between space-x-1 pt-6 border-b border-l border-gray-200 px-4">
                        @foreach(array_slice($trendDataset['data'], -15) as $idx => $val)
                            @php 
                                $maxVal = max($trendDataset['data']) ?: 10;
                                $percentage = ($val / $maxVal) * 100;
                            @endphp
                            <div class="flex-grow flex flex-col items-center group">
                                <div class="bg-blue-500 w-full hover:bg-blue-600 rounded-t-sm transition-all duration-300 relative" style="height: {{ max($percentage, 5) }}%;">
                                    <span class="absolute -top-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-[10px] py-0.5 px-1 rounded opacity-0 group-hover:opacity-100 transition shadow">{{ $val }}</span>
                                </div>
                                <span class="text-[9px] text-gray-400 font-medium mt-1 uppercase">{{ array_slice($trendDataset['labels'], -15)[$idx] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Occupation Breakdown representation -->
                <div class="bg-white p-6 rounded-2xl border border-gray-200">
                    <h4 class="font-extrabold text-gray-900 text-sm mb-4">Occupation Demographics</h4>
                    <div class="space-y-3 pt-2">
                        @foreach($occupationDataset['labels'] as $idx => $label)
                            @php
                                $count = $occupationDataset['data'][$idx];
                                $total = array_sum($occupationDataset['data']) ?: 1;
                                $width = ($count / $total) * 100;
                            @endphp
                            <div>
                                <div class="flex items-center justify-between text-xs font-bold text-gray-700 mb-1">
                                    <span>{{ $label }}</span>
                                    <span>{{ $count }} ({{ round($width, 1) }}%)</span>
                                </div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $width }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Wish categories distribution -->
                <div class="bg-white p-6 rounded-2xl border border-gray-200 md:col-span-2">
                    <h4 class="font-extrabold text-gray-900 text-sm mb-4">Development Needs Categories</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($wishDataset['labels'] as $idx => $label)
                            @php
                                $count = $wishDataset['data'][$idx];
                                $total = array_sum($wishDataset['data']) ?: 1;
                                $percentage = ($count / $total) * 100;
                            @endphp
                            <div class="bg-gray-50 border border-gray-200 p-4 rounded-xl flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-extrabold text-gray-500 uppercase">{{ $label }}</span>
                                    <h5 class="text-xl font-black text-gray-900 mt-0.5">{{ $count }}</h5>
                                </div>
                                <div class="h-10 w-10 bg-indigo-50 border border-indigo-200 text-indigo-600 rounded-lg flex items-center justify-center font-bold text-xs">
                                    {{ round($percentage, 0) }}%
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: SUBMISSIONS GRID -->
    @if ($activeTab === 'submissions')
        <div class="space-y-6">
            <!-- Advanced Filter Panel -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200 space-y-4">
                <h3 class="text-sm font-extrabold text-gray-900 flex items-center gap-1">
                    <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 8.293A1 1 0 013 7.586V4z" />
                    </svg>
                    Advanced Filters
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Search</label>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name, phone or reference" class="w-full text-xs rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    
                    @if(!auth()->user()->hasRole('State Coordinator'))
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">State</label>
                            <select wire:model.live="stateId" class="w-full text-xs rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All States</option>
                                @foreach($allStates as $st)
                                    <option value="{{ $st->id }}">{{ $st->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">LGA</label>
                        <select wire:model.live="lgaId" class="w-full text-xs rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All LGAs</option>
                            @foreach($lgas as $lg)
                                <option value="{{ $lg['id'] }}">{{ $lg['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Occupation</label>
                        <select wire:model.live="occupationId" class="w-full text-xs rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Occupations</option>
                            @foreach($allOccupations as $occ)
                                <option value="{{ $occ->id }}">{{ $occ->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Gender</label>
                        <select wire:model.live="gender" class="w-full text-xs rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Genders</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Age Group</label>
                        <select wire:model.live="ageGroup" class="w-full text-xs rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Ages</option>
                            <option value="18-25">18-25</option>
                            <option value="26-35">26-35</option>
                            <option value="36-50">36-50</option>
                            <option value="50+">50+</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Approval Status</label>
                        <select wire:model.live="status" class="w-full text-xs rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="rewarded">Rewarded</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Date From</label>
                        <input type="date" wire:model.live="dateFrom" class="w-full text-xs rounded-xl border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <!-- Bulk actions bar -->
            <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center space-x-2 text-xs font-bold text-gray-500">
                    <span>Selected: {{ count($selectedSubmissions) }}</span>
                </div>
                
                <div class="flex flex-wrap items-center gap-2">
                    <button wire:click="bulkApprove" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-bold rounded-lg text-white bg-emerald-500 hover:bg-emerald-600 transition" @if(empty($selectedSubmissions)) disabled @endif>
                        Approve
                    </button>
                    <button wire:click="bulkReject" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-bold rounded-lg text-white bg-amber-500 hover:bg-amber-600 transition" @if(empty($selectedSubmissions)) disabled @endif>
                        Reject
                    </button>
                    
                    @if(auth()->user()->hasAnyRole(['Super Admin', 'Admin']))
                        <button wire:click="bulkDelete" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-bold rounded-lg text-white bg-rose-500 hover:bg-rose-600 transition" @if(empty($selectedSubmissions)) disabled @endif>
                            Delete
                        </button>
                        
                        <div class="flex items-center space-x-1">
                            <select wire:model="assignAgentId" class="text-xs rounded-lg border-gray-300 py-1.5 focus:ring-blue-500">
                                <option value="">Assign Agent</option>
                                @foreach($allAgents as $ag)
                                    <option value="{{ $ag->id }}">{{ $ag->name }}</option>
                                @endforeach
                            </select>
                            <button wire:click="bulkAssignAgent" class="inline-flex items-center px-3 py-2 border border-gray-300 text-xs font-bold rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition" @if(empty($selectedSubmissions)) disabled @endif>
                                Assign
                            </button>
                        </div>
                    @endif

                    <button wire:click="exportFiltered" class="inline-flex items-center px-4 py-2 border border-gray-300 text-xs font-bold rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition">
                        📥 Export Excel
                    </button>
                </div>
            </div>

            <!-- Submissions Table -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-bold">
                            <tr>
                                <th class="p-4 w-4">
                                    <!-- select all checkbox placeholder -->
                                </th>
                                <th class="p-4">Reference</th>
                                <th class="p-4">Citizen Name</th>
                                <th class="p-4">Phone</th>
                                <th class="p-4">Location</th>
                                <th class="p-4">Occupation</th>
                                <th class="p-4">PVC Photo</th>
                                <th class="p-4">Spin Reward</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($submissionsList as $sub)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-4 w-4">
                                        <input type="checkbox" wire:model.live="selectedSubmissions" value="{{ $sub->id }}" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    </td>
                                    <td class="p-4 font-bold text-gray-900 select-all">{{ $sub->reference_number }}</td>
                                    <td class="p-4">
                                        <div class="font-bold text-gray-800">{{ $sub->full_name }}</div>
                                        <div class="text-[10px] text-gray-400">{{ $sub->email ?? 'No Email' }}</div>
                                    </td>
                                    <td class="p-4">{{ $sub->phone_number }}</td>
                                    <td class="p-4">
                                        <div class="font-semibold text-gray-800">{{ $sub->state?->name }}</div>
                                        <div class="text-[10px] text-gray-400">{{ $sub->lga?->name }}</div>
                                    </td>
                                    <td class="p-4">{{ $sub->occupation?->name }}</td>
                                    <td class="p-4">
                                        @if($sub->image)
                                            <button wire:click="showPhoto('{{ $sub->image->image_path }}')" class="inline-flex items-center text-blue-600 font-bold hover:underline">
                                                🖼️ View photo
                                            </button>
                                        @else
                                            <span class="text-gray-400">No photo</span>
                                        @endif
                                    </td>
                                    <td class="p-4">
                                        @if($sub->spin)
                                            <span class="font-semibold text-emerald-600">{{ $sub->spin->reward?->name ?? 'No Reward' }}</span>
                                        @else
                                            <span class="text-gray-400">Not spun</span>
                                        @endif
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex px-2 py-1.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide 
                                            @if($sub->status === 'approved') bg-emerald-50 text-emerald-700 border border-emerald-100
                                            @elseif($sub->status === 'rejected') bg-rose-50 text-rose-700 border border-rose-100
                                            @elseif($sub->status === 'rewarded') bg-indigo-50 text-indigo-700 border border-indigo-100
                                            @else bg-amber-50 text-amber-700 border border-amber-100
                                            @endif">
                                            {{ $sub->status }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right space-x-1">
                                        @if($sub->status === 'pending')
                                            <button wire:click="approveSubmission({{ $sub->id }})" class="inline-flex items-center px-2 py-1 rounded bg-emerald-500 hover:bg-emerald-600 text-white font-bold transition">
                                                Approve
                                            </button>
                                            <button wire:click="rejectSubmission({{ $sub->id }})" class="inline-flex items-center px-2 py-1 rounded bg-amber-500 hover:bg-amber-600 text-white font-bold transition">
                                                Reject
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="p-8 text-center text-gray-500 font-medium">No submission records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-gray-100">
                    {{ $submissionsList->links() }}
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: SPIN REWARDS -->
    @if ($activeTab === 'rewards')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Rewards List -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="font-extrabold text-gray-900 text-sm">Reward Settings & Inventory</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-bold">
                        <tr>
                            <th class="p-4">Name</th>
                            <th class="p-4">Type</th>
                            <th class="p-4">Probability</th>
                            <th class="p-4">Daily Cap</th>
                            <th class="p-4">Inventory Limit</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($allRewards as $reward)
                            <tr class="hover:bg-gray-50">
                                <td class="p-4 font-bold text-gray-900">{{ $reward->name }}</td>
                                <td class="p-4 capitalize">{{ $reward->type }}</td>
                                <td class="p-4 font-bold">{{ $reward->probability }}%</td>
                                <td class="p-4">{{ $reward->daily_limit === 0 ? 'Unlimited' : $reward->daily_limit }}</td>
                                <td class="p-4">{{ $reward->inventory_limit === 0 ? 'Unlimited' : $reward->inventory_limit }}</td>
                                <td class="p-4">
                                    <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $reward->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $reward->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <button wire:click="openEditReward({{ $reward->id }})" class="text-blue-600 font-bold hover:underline">
                                        Modify
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Right: Edit Form Block -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm h-fit">
                @if ($editRewardId)
                    <form wire:submit="saveReward" class="space-y-4">
                        <h4 class="font-extrabold text-gray-900 text-sm border-b pb-2">Modify {{ $editRewardName }}</h4>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Probability Weight (%)</label>
                            <input type="number" step="0.01" wire:model="editRewardProbability" class="w-full text-xs rounded-xl border-gray-300">
                            @error('editRewardProbability') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Daily Cap (0 = Unlimited)</label>
                            <input type="number" wire:model="editRewardDailyLimit" class="w-full text-xs rounded-xl border-gray-300">
                            @error('editRewardDailyLimit') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Inventory Limit (0 = Unlimited)</label>
                            <input type="number" wire:model="editRewardInventoryLimit" class="w-full text-xs rounded-xl border-gray-300">
                            @error('editRewardInventoryLimit') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center space-x-2 pt-2">
                            <input type="checkbox" wire:model="editRewardIsActive" id="reward_active" class="h-5 w-5 text-blue-600 border-gray-300 rounded">
                            <label for="reward_active" class="text-xs font-bold text-gray-700 cursor-pointer">Active in Spin Engine</label>
                        </div>

                        <div class="flex space-x-2 pt-4">
                            <button type="submit" class="flex-grow inline-flex items-center justify-center px-4 py-2.5 text-xs font-bold rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition">
                                Save Settings
                            </button>
                            <button type="button" wire:click="$set('editRewardId', null)" class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 text-xs font-bold rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                @else
                    <div class="text-center py-12 text-gray-400">
                        <span class="text-3xl">⚙️</span>
                        <p class="text-xs font-semibold mt-2">Select a reward parameter to modify from the table list.</p>
                    </div>
                @endif
            </div>

            <!-- Won Claims Audit Log -->
            <div class="lg:col-span-3 bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="font-extrabold text-gray-900 text-sm">Reward Claims & Audit Log</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-bold">
                        <tr>
                            <th class="p-4">Claim Ref</th>
                            <th class="p-4">Winner</th>
                            <th class="p-4">State</th>
                            <th class="p-4">Reward</th>
                            <th class="p-4">Claim Code</th>
                            <th class="p-4">Processed Date</th>
                            <th class="p-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($rewardClaimsLog as $claim)
                            <tr class="hover:bg-gray-50">
                                <td class="p-4 font-bold text-gray-900">{{ $claim->submission?->reference_number }}</td>
                                <td class="p-4 font-semibold text-gray-700">{{ $claim->submission?->full_name }}</td>
                                <td class="p-4">{{ $claim->submission?->state?->name }}</td>
                                <td class="p-4 text-emerald-600 font-extrabold">{{ $claim->reward?->name }}</td>
                                <td class="p-4 font-mono font-black text-blue-600 select-all">{{ $claim->claim_code }}</td>
                                <td class="p-4 text-gray-400">{{ $claim->processed_at?->toDateTimeString() ?? 'N/A' }}</td>
                                <td class="p-4">
                                    <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wide {{ $claim->status === 'processed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $claim->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="p-4 border-t border-gray-100">
                    {{ $rewardClaimsLog->links() }}
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 4: AGENTS MANAGER -->
    @if ($activeTab === 'agents')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Add Agent Form -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm h-fit">
                <form wire:submit="createAgentUser" class="space-y-4">
                    <h3 class="font-extrabold text-gray-900 text-sm border-b pb-2">Add Agent / Coordinator</h3>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Full Name</label>
                        <input type="text" wire:model="agentName" placeholder="e.g. Tunde Johnson" class="w-full text-xs rounded-xl border-gray-300" required>
                        @error('agentName') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Email Address</label>
                        <input type="email" wire:model="agentEmail" placeholder="e.g. tunde@apc.org" class="w-full text-xs rounded-xl border-gray-300" required>
                        @error('agentEmail') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Password</label>
                        <input type="password" wire:model="agentPassword" class="w-full text-xs rounded-xl border-gray-300" required>
                        @error('agentPassword') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Assign State Scoping</label>
                        <select wire:model="agentStateId" class="w-full text-xs rounded-xl border-gray-300" required>
                            <option value="">Select State</option>
                            @foreach($allStates as $st)
                                <option value="{{ $st->id }}">{{ $st->name }}</option>
                            @endforeach
                        </select>
                        @error('agentStateId') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">System Role</label>
                        <select wire:model="agentRole" class="w-full text-xs rounded-xl border-gray-300" required>
                            <option value="Agent">Agent (Registrations)</option>
                            <option value="State Coordinator">State Coordinator (Reporting)</option>
                        </select>
                        @error('agentRole') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 text-xs font-bold rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition">
                        Create Account
                    </button>
                </form>
            </div>

            <!-- Right: List Grid -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Coordinators List -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="font-extrabold text-gray-900 text-sm">State Coordinators Scopes</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-bold">
                            <tr>
                                <th class="p-4">Name</th>
                                <th class="p-4">Email</th>
                                <th class="p-4">Assigned State</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($coordinatorsList as $coord)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-4 font-bold text-gray-900">{{ $coord->name }}</td>
                                    <td class="p-4">{{ $coord->email }}</td>
                                    <td class="p-4 font-semibold text-blue-600">{{ $coord->agentProfile?->state?->name ?? 'None' }}</td>
                                    <td class="p-4">
                                        <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ ($coord->agentProfile?->status ?? 'active') === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                            {{ $coord->agentProfile?->status ?? 'active' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <button wire:click="toggleAgentStatus({{ $coord->id }}, '{{ $coord->agentProfile?->status ?? 'active' }}')" class="text-rose-600 font-bold hover:underline">
                                            {{ ($coord->agentProfile?->status ?? 'active') === 'active' ? 'Suspend' : 'Activate' }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Agents List and performance metrics -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="font-extrabold text-gray-900 text-sm">Field Agents Performance</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-bold">
                            <tr>
                                <th class="p-4">Name</th>
                                <th class="p-4">Email</th>
                                <th class="p-4">State Scope</th>
                                <th class="p-4">Registrations Count</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($agentsList as $ag)
                                @php
                                    $perf = $this->getPerformanceMetrics($ag->id);
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="p-4 font-bold text-gray-900">{{ $ag->name }}</td>
                                    <td class="p-4">{{ $ag->email }}</td>
                                    <td class="p-4">{{ $ag->agentProfile?->state?->name ?? 'None' }}</td>
                                    <td class="p-4 font-bold text-gray-700">
                                        Today: <span class="text-blue-600">{{ $perf['daily'] }}</span> | 
                                        Month: <span class="text-purple-600">{{ $perf['monthly'] }}</span>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ ($ag->agentProfile?->status ?? 'active') === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                            {{ $ag->agentProfile?->status ?? 'active' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <button wire:click="toggleAgentStatus({{ $ag->id }}, '{{ $ag->agentProfile?->status ?? 'active' }}')" class="text-rose-600 font-bold hover:underline">
                                            {{ ($ag->agentProfile?->status ?? 'active') === 'active' ? 'Suspend' : 'Activate' }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 5: CAMPAIGNS -->
    @if ($activeTab === 'campaigns')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Add Campaign Form -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm h-fit">
                <form wire:submit="createCampaign" class="space-y-4">
                    <h3 class="font-extrabold text-gray-900 text-sm border-b pb-2">Create Campaign Setting</h3>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Campaign Name</label>
                        <input type="text" wire:model="campaignName" placeholder="e.g. Lagos Progressive Drive" class="w-full text-xs rounded-xl border-gray-300" required>
                        @error('campaignName') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Campaign Description</label>
                        <textarea wire:model="campaignDescription" rows="3" class="w-full text-xs rounded-xl border-gray-300"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Start Date</label>
                            <input type="date" wire:model="campaignStartDate" class="w-full text-xs rounded-xl border-gray-300" required>
                            @error('campaignStartDate') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">End Date</label>
                            <input type="date" wire:model="campaignEndDate" class="w-full text-xs rounded-xl border-gray-300" required>
                            @error('campaignEndDate') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase">Target States</label>
                        <div class="h-32 overflow-y-auto border border-gray-200 rounded-xl p-3 space-y-2">
                            @foreach($allStates as $st)
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" wire:model="campaignStates" value="{{ $st->id }}" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-xs text-gray-700 font-medium">{{ $st->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('campaignStates') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center space-x-2">
                        <input type="checkbox" wire:model="campaignIsActive" id="camp_active" class="h-5 w-5 text-blue-600 border-gray-300 rounded">
                        <label for="camp_active" class="text-xs font-bold text-gray-700 cursor-pointer">Active</label>
                    </div>

                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 text-xs font-bold rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition">
                        Register Campaign
                    </button>
                </form>
            </div>

            <!-- Right: Campaigns Grid List -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm h-fit">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="font-extrabold text-gray-900 text-sm">Campaigns List</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-bold">
                        <tr>
                            <th class="p-4">Name</th>
                            <th class="p-4">Target States</th>
                            <th class="p-4">Timeline</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($allCampaigns as $camp)
                            <tr class="hover:bg-gray-50">
                                <td class="p-4">
                                    <div class="font-bold text-gray-900">{{ $camp->name }}</div>
                                    <div class="text-[10px] text-gray-400">{{ $camp->description ?? 'No description' }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-wrap gap-1 max-w-xs">
                                        @foreach($camp->states as $st)
                                            <span class="inline-flex px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 text-[9px] font-bold">{{ $st->name }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="p-4 font-medium text-gray-700">
                                    {{ $camp->start_date?->format('M d, Y') }} - {{ $camp->end_date?->format('M d, Y') }}
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $camp->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $camp->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <button wire:click="toggleCampaign({{ $camp->id }})" class="text-blue-600 font-bold hover:underline">
                                        Toggle
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Photo Drawer Overlay Modal -->
    @if ($previewPhotoUrl)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            <div class="relative bg-white rounded-3xl max-w-2xl w-full p-4 shadow-2xl flex flex-col items-center">
                <button wire:click="closePhoto" class="absolute top-4 right-4 h-8 w-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 flex items-center justify-center font-bold shadow-sm transition">
                    ✕
                </button>
                <h4 class="font-bold text-gray-900 text-sm mb-4">PVC Selfie Verification Image</h4>
                <div class="w-full max-h-[70vh] overflow-hidden rounded-2xl border border-gray-200 flex items-center justify-center bg-gray-50">
                    <img src="{{ $previewPhotoUrl }}" alt="PVC Selfie" class="object-contain max-h-[65vh]">
                </div>
            </div>
        </div>
    @endif
</div>
