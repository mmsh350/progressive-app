<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\State;
use App\Models\Lga;
use App\Models\Ward;
use App\Models\Occupation;
use App\Models\WishCategory;
use App\Models\Submission;
use App\Actions\ProcessSubmissionAction;
use App\Actions\ProcessSpinAction;
use App\Services\SubmissionService;
use App\Services\AnalyticsService;

new #[Layout('layouts.home')] class extends Component {
    use WithFileUploads;

    // Form Wizard Properties
    public int $currentStep = 1;
    public string $fullName = '';
    public string $phoneNumber = '';
    public string $email = '';
    public string $gender = 'male';
    public string $ageGroup = '18-25';
    public ?int $stateId = null;
    public ?int $lgaId = null;
    public ?int $wardId = null;
    public string $pollingUnit = '';
    public bool $voted2023 = false;
    public bool $vote2027 = false;
    public ?int $occupationId = null;
    public ?int $wishCategoryId = null;
    public string $wishTitle = '';
    public string $wishDescription = '';
    public $pvcSelfie;
    public bool $agreement = false;

    // Dropdowns
    public $lgas = [];
    public $wards = [];

    // Reference tracker
    public string $searchRef = '';
    public $trackedSubmission = null;
    public bool $trackerSearched = false;

    // Spin & Win Modal
    public bool $showSpinModal = false;
    public ?int $spinSubmissionId = null;
    public bool $spinning = false;
    public ?string $spinResult = null;
    public ?string $spinVoucherCode = null;

    // Stats
    public array $stats = [];

    // Completed card details for print/download
    public string $completedCardName = '';
    public string $completedCardPhone = '';
    public ?string $completedCardState = '';
    public ?string $completedCardLga = '';
    public ?string $completedCardPhotoUrl = null;

    public function mount(AnalyticsService $analyticsService)
    {
        $this->stats = $analyticsService->getDashboardStats();

        $firstState = State::orderBy('name')->first();
        if ($firstState) {
            $this->stateId = $firstState->id;
            $this->updatedStateId();
        }

        $firstOcc = Occupation::where('is_active', true)->orderBy('name')->first();
        if ($firstOcc) {
            $this->occupationId = $firstOcc->id;
        }

        $firstCat = WishCategory::orderBy('name')->first();
        if ($firstCat) {
            $this->wishCategoryId = $firstCat->id;
        }
    }

    public function updatedStateId()
    {
        $this->lgas = Lga::where('state_id', $this->stateId)->orderBy('name')->get()->toArray();
        $this->lgaId = !empty($this->lgas) ? $this->lgas[0]['id'] : null;
        $this->updatedLgaId();
    }

    public function updatedLgaId()
    {
        if ($this->lgaId) {
            $this->wards = Ward::where('lga_id', $this->lgaId)->orderBy('name')->get()->toArray();
            $this->wardId = !empty($this->wards) ? $this->wards[0]['id'] : null;
        } else {
            $this->wards = [];
            $this->wardId = null;
        }
    }

    public function nextStep()
    {
        $this->validateCurrentStep();
        $this->currentStep++;
    }

    public function previousStep()
    {
        $this->currentStep--;
    }

    protected function validateCurrentStep()
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'fullName' => 'required|string|max:255',
                'phoneNumber' => 'required|digits:11',
                'email' => 'nullable|email|max:255',
                'gender' => 'required|in:male,female,other',
                'ageGroup' => 'required|in:18-25,26-35,36-50,50+',
            ], [
                'phoneNumber.digits' => 'The phone number must be exactly 11 digits (e.g. 08012345678).',
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'stateId' => 'required|exists:states,id',
                'lgaId' => 'required|exists:lgas,id',
                'wardId' => 'nullable|exists:wards,id',
                'pollingUnit' => 'nullable|string|max:255',
            ]);
        } elseif ($this->currentStep === 3) {
            $this->validate([
                'voted2023' => 'required|boolean',
                'vote2027' => 'required|boolean',
            ]);
        } elseif ($this->currentStep === 4) {
            $this->validate([
                'occupationId' => 'required|exists:occupations,id',
                'wishCategoryId' => 'required|exists:wish_categories,id',
                'wishTitle' => 'required|string|max:255',
                'wishDescription' => 'required|string',
            ]);
        }
    }

    public function submitDeclaration(ProcessSubmissionAction $action)
    {
        $this->validate([
            'pvcSelfie' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
            'agreement' => 'required|accepted',
        ]);

        try {
            $data = [
                'full_name' => $this->fullName,
                'phone_number' => $this->phoneNumber,
                'email' => $this->email ?: null,
                'gender' => $this->gender,
                'age_group' => $this->ageGroup,
                'state_id' => $this->stateId,
                'lga_id' => $this->lgaId,
                'ward_id' => $this->wardId,
                'polling_unit' => $this->pollingUnit ?: null,
                'voted_2023' => $this->voted2023,
                'vote_2027' => $this->vote2027,
                'occupation_id' => $this->occupationId,
                'wish_category_id' => $this->wishCategoryId,
                'wish_title' => $this->wishTitle,
                'wish_description' => $this->wishDescription,
                'agreement' => $this->agreement,
            ];

            // Extract the user session IP address
            $data['ip_address'] = request()->ip() ?? '127.0.0.1';
            $data['user_agent'] = request()->userAgent();

            // Check if agent is logged in
            if (auth()->check()) {
                $data['agent_id'] = auth()->id();
            }

            $submission = $action->execute($data, $this->pvcSelfie);

            $this->spinSubmissionId = $submission->id;
            $this->showSpinModal = true;
            $this->currentStep = 6; // Success step

            // Save searchRef to show tracking code immediately on success view
            $this->searchRef = $submission->reference_number;

            // Capture completed card details for download/print before resetting
            $this->completedCardName = $this->fullName;
            $this->completedCardPhone = $this->phoneNumber;
            $this->completedCardState = State::find($this->stateId)?->name;
            $this->completedCardLga = Lga::find($this->lgaId)?->name;
            $this->completedCardPhotoUrl = $this->pvcSelfie && method_exists($this->pvcSelfie, 'temporaryUrl') ? $this->pvcSelfie->temporaryUrl() : null;

            $this->resetWizard();
        } catch (\Exception $e) {
            session()->flash('error', 'Error submitting declaration: ' . $e->getMessage());
        }
    }

    public function executeSpin(ProcessSpinAction $spinAction)
    {
        if (!$this->spinSubmissionId || $this->spinning) {
            return;
        }

        $this->spinning = true;

        try {
            $ipAddress = request()->ip() ?? '127.0.0.1';
            $reward = $spinAction->execute($this->spinSubmissionId, $ipAddress);

            $this->spinResult = $reward->name;

            // Refresh database data to grab claim voucher code
            $submission = Submission::with(['rewardClaim'])->find($this->spinSubmissionId);
            $this->spinVoucherCode = $submission->rewardClaim?->claim_code;
        } catch (\Exception $e) {
            $this->spinResult = 'No Reward (Limit Exceeded / ' . $e->getMessage() . ')';
        } finally {
            $this->spinning = false;
        }
    }

    protected function resetWizard()
    {
        $this->fullName = '';
        $this->phoneNumber = '';
        $this->email = '';
        $this->gender = 'male';
        $this->ageGroup = '18-25';
        $this->pollingUnit = '';
        $this->voted2023 = false;
        $this->vote2027 = false;
        $this->wishTitle = '';
        $this->wishDescription = '';
        $this->pvcSelfie = null;
        $this->agreement = false;
    }

    public function trackSubmission(SubmissionService $service)
    {
        $this->validate([
            'searchRef' => 'required|string',
        ]);

        $this->trackedSubmission = $service->getSubmissionStatus($this->searchRef);
        $this->trackerSearched = true;
    }

    public function closeTracking()
    {
        $this->trackedSubmission = null;
        $this->trackerSearched = false;
    }

    public function restartWizard()
    {
        $this->currentStep = 1;
        $this->resetWizard();
    }

    public function with(): array
    {
        return [
            'states' => State::orderBy('name')->get(),
            'occupations' => Occupation::where('is_active', true)->orderBy('name')->get(),
            'wishCategories' => WishCategory::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="min-h-screen bg-slate-50 flex flex-col font-sans selection:bg-[#008751] selection:text-white">

    <!-- Top Decorative Flag Stripe -->
    <div class="h-1.5 w-full bg-gradient-to-r from-[#008751] via-white via-sky-400 to-[#0079C1]"></div>

    <!-- Premium Header -->
    <header
        class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-gray-100 shadow-sm transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <!-- APC Circular Logo -->
                <img src="{{ asset('images/apc_ogo.png') }}" alt="APC Logo"
                    class="h-14 w-14 object-contain filter drop-shadow-sm hover:scale-105 transition duration-300">
                <div>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight leading-none">
                        Progressive <span class="text-[#008751]">Nigeria</span>
                    </h1>
                    <p class="text-[11px] text-slate-500 font-bold uppercase tracking-wider mt-1">Support & Wishes
                        Portal</p>
                </div>
            </div>

            <div class="flex items-center space-x-6">
                <a href="#tracker"
                    class="hidden md:inline-flex items-center text-sm font-bold text-slate-600 hover:text-[#008751] transition-colors duration-200">
                    Track Declaration
                </a>

                @if (Route::has('login'))
                    @auth
                        <a href="{{ route('dashboard') }}"
                            class="inline-flex items-center justify-center px-5 py-2.5 text-xs font-bold uppercase tracking-wider rounded-xl text-white bg-[#008751] hover:bg-emerald-800 shadow-md shadow-emerald-100 transition duration-200 hover:-translate-y-0.5">
                            Dashboard
                        </a>
                    @else
                    @endauth
                @endif
            </div>
        </div>
    </header>

    <!-- Patriotic Hero Section -->
    <section
        class="relative bg-gradient-to-br from-[#008751] via-[#005c36] to-[#014126] py-24 px-4 sm:px-6 lg:px-8 text-white overflow-hidden">
        <!-- Graphic overlay pattern -->
        <div
            class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-white via-transparent to-transparent">
        </div>
        <div class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-sky-400/20 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 h-96 w-96 rounded-full bg-amber-400/10 blur-3xl"></div>

        <div class="max-w-5xl mx-auto text-center relative z-10">
            <span
                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-extrabold tracking-wider bg-amber-500 text-slate-950 uppercase shadow-md mb-6 border border-amber-400/30">
                🇳🇬 APC Road to 2027
            </span>
            <h1 class="text-4xl sm:text-6xl font-black tracking-tight mb-6 leading-tight">
                Let's Build a Greater <span
                    class="bg-clip-text text-transparent bg-gradient-to-r from-emerald-300 via-white to-sky-300">Nigeria</span>,
                Together
            </h1>
            <p class="text-base sm:text-lg text-emerald-50/90 max-w-3xl mx-auto mb-10 font-medium leading-relaxed">
                Join millions of progressive citizens declaring support, sharing regional development wishes, and
                shaping the vision for Nigeria 2027.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="#wizard"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 text-base font-bold rounded-2xl bg-white text-[#008751] hover:bg-emerald-50 shadow-xl shadow-emerald-950/40 hover:-translate-y-0.5 transition duration-200">
                    Declare Support Now
                </a>
                <a href="#about"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 text-base font-bold rounded-2xl bg-[#008751]/40 hover:bg-[#008751]/60 backdrop-blur border border-white/20 hover:-translate-y-0.5 transition duration-200">
                    Learn More
                </a>
            </div>
        </div>
    </section>

    <!-- Key Statistics -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-12 relative z-20">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Stat Card 1 -->
            <div
                class="bg-white rounded-2xl p-6 shadow-xl border border-slate-100/80 flex items-center space-x-4 hover:scale-[1.02] transition duration-300">
                <div
                    class="h-12 w-12 rounded-xl bg-emerald-50 text-[#008751] flex items-center justify-center font-bold shadow-inner">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider">Total Submissions</p>
                    <h3 class="text-2xl font-black text-slate-800 tracking-tight mt-0.5">
                        {{ number_format($stats['total_submissions'] ?? 12450) }}</h3>
                </div>
            </div>

            <!-- Stat Card 2 -->
            <div
                class="bg-white rounded-2xl p-6 shadow-xl border border-slate-100/80 flex items-center space-x-4 hover:scale-[1.02] transition duration-300">
                <div
                    class="h-12 w-12 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold shadow-inner">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider">LGAs Covered</p>
                    <h3 class="text-2xl font-black text-slate-800 tracking-tight mt-0.5">
                        {{ $stats['total_lgas_covered'] ?? 74 }} / 774</h3>
                </div>
            </div>

            <!-- Stat Card 3 -->
            <div
                class="bg-white rounded-2xl p-6 shadow-xl border border-slate-100/80 flex items-center space-x-4 hover:scale-[1.02] transition duration-300">
                <div
                    class="h-12 w-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shadow-inner">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider">Development Wishes</p>
                    <h3 class="text-2xl font-black text-slate-800 tracking-tight mt-0.5">
                        {{ number_format($stats['total_wishes'] ?? 4820) }}</h3>
                </div>
            </div>
        </div>
    </section>

    <!-- Visual How It Works Guide -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="text-center max-w-xl mx-auto mb-12">
            <h2 class="text-3xl font-black text-slate-900 tracking-tight">Make Your Voice Count</h2>
            <p class="text-sm text-slate-500 mt-2">Submit your voluntary declaration and community needs in three simple
                steps.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div class="p-6 bg-white border border-gray-100 rounded-3xl shadow-sm">
                <div
                    class="h-12 w-12 bg-emerald-50 text-[#008751] rounded-2xl mx-auto flex items-center justify-center font-bold text-lg mb-4">
                    1</div>
                <h4 class="font-extrabold text-slate-800 text-base">Fill Support Info</h4>
                <p class="text-xs text-slate-500 mt-2 leading-relaxed">Provide basic details like your name, phone
                    number, and location (State, LGA, Ward).</p>
            </div>
            <div class="p-6 bg-white border border-gray-100 rounded-3xl shadow-sm">
                <div
                    class="h-12 w-12 bg-sky-50 text-[#0079C1] rounded-2xl mx-auto flex items-center justify-center font-bold text-lg mb-4">
                    2</div>
                <h4 class="font-extrabold text-slate-800 text-base">Share Your Wish</h4>
                <p class="text-xs text-slate-500 mt-2 leading-relaxed">Let the campaign know what developmental projects
                    are critically needed in your locality.</p>
            </div>
            <div class="p-6 bg-white border border-gray-100 rounded-3xl shadow-sm">
                <div
                    class="h-12 w-12 bg-amber-50 text-amber-600 rounded-2xl mx-auto flex items-center justify-center font-bold text-lg mb-4">
                    3</div>
                <h4 class="font-extrabold text-slate-800 text-base">Verify & Rewards</h4>
                <p class="text-xs text-slate-500 mt-2 leading-relaxed">Upload a selfie with your PVC to verify
                    authentication, and get a spin at our airtime/data rewards.</p>
            </div>
        </div>
    </section>

    <!-- Support Wizard Forms -->
    <section id="wizard" class="pb-20 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto scroll-mt-24">
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden border-t-8 border-[#008751]">
            <div
                class="bg-gradient-to-r from-slate-900 to-slate-800 px-8 py-8 text-white flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black tracking-tight">Voluntary Support Declaration</h2>
                    <p class="text-xs text-slate-400 mt-1 font-medium">Be counted! Declare your stand and submit
                        requests for your local area</p>
                </div>
                <div class="bg-white/10 px-4 py-2 rounded-xl text-center backdrop-blur">
                    <span class="text-xs text-slate-300 font-extrabold uppercase tracking-widest block">Step</span>
                    <span
                        class="text-2xl font-black text-[#008751] mt-0.5 block leading-none">{{ $currentStep }}/5</span>
                </div>
            </div>

            <!-- Horizontal Step progress bar timeline indicator -->
            <div class="bg-slate-50 border-b border-slate-100 py-6 px-4">
                <div class="flex items-center justify-between max-w-xl mx-auto">
                    @foreach (range(1, 5) as $step)
                        <div class="flex items-center {{ !$loop->last ? 'flex-grow' : '' }}">
                            <div
                                class="flex items-center justify-center h-10 w-10 rounded-full font-black text-sm border-2 transition-all duration-300
                                {{ $currentStep === $step ? 'bg-[#008751] border-[#008751] text-white shadow-md shadow-emerald-100 scale-110' : ($currentStep > $step ? 'bg-emerald-50 border-[#008751] text-[#008751]' : 'bg-white border-slate-200 text-slate-400') }}">
                                @if ($currentStep > $step)
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                @else
                                    {{ $step }}
                                @endif
                            </div>
                            @if (!$loop->last)
                                <div
                                    class="flex-grow h-0.5 mx-2 {{ $currentStep > $step ? 'bg-[#008751]' : 'bg-slate-200' }}">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="p-8 sm:p-10">
                <!-- Validation messages -->
                @if (session()->has('error'))
                    <div
                        class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-800 text-sm font-semibold">
                        {{ session('error') }}
                    </div>
                @endif

                <!-- STEP 1: PERSONAL INFORMATION -->
                @if ($currentStep === 1)
                    <div class="space-y-6">
                        <div class="border-b border-slate-100 pb-4 mb-6">
                            <h3 class="text-lg font-extrabold text-slate-800">Personal Information</h3>
                            <p class="text-xs text-slate-400 mt-1">Please enter your basic identification details
                                correctly.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Full
                                    Name</label>
                                <input type="text" wire:model="fullName" placeholder="e.g. Kola Ibrahim"
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm"
                                    required>
                                @error('fullName')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Phone
                                    Number</label>
                                <input type="tel" wire:model="phoneNumber" placeholder="e.g. 08012345678"
                                    maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm"
                                    required>
                                @error('phoneNumber')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email
                                    Address (Optional)</label>
                                <input type="email" wire:model="email" placeholder="e.g. kola@example.com"
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm">
                                @error('email')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label
                                        class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Gender</label>
                                    <select wire:model="gender"
                                        class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm">
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    @error('gender')
                                        <span
                                            class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Age
                                        Group</label>
                                    <select wire:model="ageGroup"
                                        class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm">
                                        <option value="18-25">18 - 25</option>
                                        <option value="26-35">26 - 35</option>
                                        <option value="36-50">36 - 50</option>
                                        <option value="50+">50+</option>
                                    </select>
                                    @error('ageGroup')
                                        <span
                                            class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- STEP 2: LOCATION INFORMATION -->
                @if ($currentStep === 2)
                    <div class="space-y-6">
                        <div class="border-b border-slate-100 pb-4 mb-6">
                            <h3 class="text-lg font-extrabold text-slate-800">Your Location Details</h3>
                            <p class="text-xs text-slate-400 mt-1">Specify your current residential state and local
                                boundaries.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">State
                                    of Residence</label>
                                <select wire:model.live="stateId"
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm">
                                    @foreach ($states as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                                    @endforeach
                                </select>
                                @error('stateId')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Local
                                    Government Area (LGA)</label>
                                <select wire:model.live="lgaId"
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm">
                                    @foreach ($lgas as $lg)
                                        <option value="{{ $lg['id'] }}">{{ $lg['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('lgaId')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Ward
                                    (Optional)</label>
                                <select wire:model="wardId"
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm">
                                    <option value="">Select Ward</option>
                                    @foreach ($wards as $wd)
                                        <option value="{{ $wd['id'] }}">{{ $wd['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('wardId')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Polling
                                    Unit (Optional)</label>
                                <input type="text" wire:model="pollingUnit"
                                    placeholder="e.g. Polling Unit 003, Ward 2"
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm">
                                @error('pollingUnit')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                <!-- STEP 3: VOTING INFORMATION -->
                @if ($currentStep === 3)
                    <div class="space-y-6">
                        <div class="border-b border-slate-100 pb-4 mb-6">
                            <h3 class="text-lg font-extrabold text-slate-800">Voting History & Intentions</h3>
                            <p class="text-xs text-slate-400 mt-1">Provide information about your active voter
                                participation.</p>
                        </div>

                        <div class="space-y-6">
                            <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <label class="block text-sm font-extrabold text-slate-700 mb-3">Did you vote in the
                                    2023 General Elections?</label>
                                <div class="flex flex-wrap gap-6">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" wire:model="voted2023" value="1"
                                            class="h-5 w-5 text-[#008751] border-slate-300 focus:ring-[#008751]">
                                        <span class="ms-3 text-sm font-bold text-slate-700">Yes, I voted</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" wire:model="voted2023" value="0"
                                            class="h-5 w-5 text-slate-400 border-slate-300 focus:ring-[#008751]">
                                        <span class="ms-3 text-sm font-bold text-slate-600">No, I did not vote</span>
                                    </label>
                                </div>
                                @error('voted2023')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100">
                                <label class="block text-sm font-extrabold text-slate-700 mb-3">Will you vote in the
                                    upcoming 2027 General Elections?</label>
                                <div class="flex flex-wrap gap-6">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" wire:model="vote2027" value="1"
                                            class="h-5 w-5 text-[#008751] border-slate-300 focus:ring-[#008751]">
                                        <span class="ms-3 text-sm font-bold text-slate-700">Yes, I intend to
                                            vote</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" wire:model="vote2027" value="0"
                                            class="h-5 w-5 text-slate-400 border-slate-300 focus:ring-[#008751]">
                                        <span class="ms-3 text-sm font-bold text-slate-600">No, I do not plan to
                                            vote</span>
                                    </label>
                                </div>
                                @error('vote2027')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                <!-- STEP 4: OCCUPATION & WISH INFORMATION -->
                @if ($currentStep === 4)
                    <div class="space-y-6">
                        <div class="border-b border-slate-100 pb-4 mb-6">
                            <h3 class="text-lg font-extrabold text-slate-800">Occupation & Wish Details</h3>
                            <p class="text-xs text-slate-400 mt-1">Tell us about yourself and suggest developmental
                                actions for your area.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Occupation</label>
                                <select wire:model="occupationId"
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm">
                                    @foreach ($occupations as $occ)
                                        <option value="{{ $occ->id }}">{{ $occ->name }}</option>
                                    @endforeach
                                </select>
                                @error('occupationId')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Development
                                    Wish Category</label>
                                <select wire:model="wishCategoryId"
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm">
                                    @foreach ($wishCategories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                                @error('wishCategoryId')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Wish
                                    Title</label>
                                <input type="text" wire:model="wishTitle"
                                    placeholder="e.g. Clean water supply facility for Ward 1"
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm"
                                    required>
                                @error('wishTitle')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Wish
                                    Description</label>
                                <textarea wire:model="wishDescription" rows="4"
                                    placeholder="Briefly write how this project will positively affect your local community..."
                                    class="w-full rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3.5 px-4 shadow-sm"
                                    required></textarea>
                                @error('wishDescription')
                                    <span
                                        class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                <!-- STEP 5: VERIFICATION & DECLARATION -->
                @if ($currentStep === 5)
                    <div class="space-y-6">
                        <div class="border-b border-slate-100 pb-4 mb-6">
                            <h3 class="text-lg font-extrabold text-slate-800">PVC Selfie Verification</h3>
                            <p class="text-xs text-slate-400 mt-1">Verify your identity to authenticate this voluntary
                                declaration entry.</p>
                        </div>

                        <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl">
                            <label class="block text-sm font-extrabold text-slate-800 mb-2">Upload Selfie Holding Your
                                PVC</label>
                            <p class="text-xs text-slate-500 mb-4">Upload a clear photo showing your face while holding
                                your Permanent Voter Card (PVC). File format: JPG, PNG, WEBP (Max 5MB).</p>

                            <div class="flex items-center justify-center w-full">
                                <input type="file" wire:model="pvcSelfie" id="pvcSelfieInput" class="hidden" accept="image/*" />
 
                                 @if (!$pvcSelfie)
                                     <!-- Standard Upload zone -->
                                     <label for="pvcSelfieInput"
                                         class="flex flex-col items-center justify-center w-full h-48 border-2 border-slate-300 border-dashed rounded-2xl cursor-pointer bg-white hover:bg-slate-50 transition duration-200">
                                         <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                             <svg class="w-10 h-10 mb-3 text-slate-400" fill="none"
                                                 stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                     d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                             </svg>
                                             <p class="mb-2 text-sm text-slate-700 font-bold"><span
                                                     class="text-[#008751]">Click to upload</span> or drag and drop</p>
                                             <p class="text-xs text-slate-400">JPEG, PNG, WEBP (Max 5MB)</p>
                                         </div>
                                     </label>
                                 @else
                                     <!-- Catchy Preview Card (click to re-upload) -->
                                     @php
                                         try {
                                             $tempUrl = method_exists($pvcSelfie, 'temporaryUrl') ? $pvcSelfie->temporaryUrl() : null;
                                         } catch (\Exception $e) {
                                             $tempUrl = null;
                                         }
                                         $stateName = $stateId ? \App\Models\State::find($stateId)?->name : null;
                                         $lgaName = $lgaId ? \App\Models\Lga::find($lgaId)?->name : null;
                                     @endphp
                                     <label for="pvcSelfieInput" class="cursor-pointer block relative group transition-all duration-300 w-full max-w-sm sm:max-w-md">
                                         <div class="border-2 border-[#008751]/30 bg-white rounded-3xl shadow-xl overflow-hidden transition-all duration-300 group-hover:shadow-2xl group-hover:border-[#008751]/60">
                                             <!-- Green Header with Nigeria colors -->
                                             <div class="bg-gradient-to-r from-[#008751] via-[#005c36] to-[#014126] text-white px-5 py-3 flex items-center justify-between">
                                                 <div class="flex items-center space-x-2">
                                                     <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                                     <span class="text-[10px] font-black tracking-widest uppercase">APC 2027 Voter verification</span>
                                                 </div>
                                                 <span class="text-[9px] bg-white/20 px-2 py-0.5 rounded-full font-bold">PREVIEW</span>
                                             </div>
                                             
                                             <!-- Card Body -->
                                             <div class="p-6 flex flex-col sm:flex-row items-center sm:items-start gap-5 bg-gradient-to-br from-white to-slate-50/50">
                                                 <!-- Photo Preview Frame -->
                                                 <div class="relative flex-shrink-0">
                                                     @if ($tempUrl)
                                                        <div class="h-32 w-28 rounded-2xl border-4 border-white bg-slate-100 overflow-hidden shadow-md"
                                                             style="background-image: url('{{ $tempUrl }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
                                                        </div>
                                                     @else
                                                        <div class="h-32 w-28 rounded-2xl border-4 border-white bg-slate-100 overflow-hidden shadow-md flex items-center justify-center">
                                                            <svg class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                            </svg>
                                                        </div>
                                                     @endif
                                                     <!-- Success Overlay Checkmark -->
                                                     <span class="absolute -bottom-2 -right-2 bg-[#008751] text-white p-1.5 rounded-full text-xs font-black border-2 border-white shadow-md">
                                                         <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                         </svg>
                                                     </span>
                                                 </div>
                                                 
                                                 <!-- Details Info Grid -->
                                                 <div class="flex-grow w-full text-center sm:text-left space-y-3">
                                                     <div>
                                                         <span class="text-[9px] text-slate-400 font-extrabold uppercase block tracking-wider">Full Name</span>
                                                         <h4 class="text-sm font-black text-slate-800 tracking-tight mt-0.5">{{ $fullName ?: 'Not Specified' }}</h4>
                                                     </div>
                                                     <div>
                                                         <span class="text-[9px] text-slate-400 font-extrabold uppercase block tracking-wider">Phone Number</span>
                                                         <h4 class="text-sm font-black text-slate-800 tracking-tight mt-0.5">{{ $phoneNumber ?: 'Not Specified' }}</h4>
                                                     </div>
                                                     @if ($stateName || $lgaName)
                                                         <div>
                                                             <span class="text-[9px] text-slate-400 font-extrabold uppercase block tracking-wider">Voter Location</span>
                                                             <h4 class="text-xs font-bold text-slate-700 tracking-tight mt-0.5">
                                                                 {{ $lgaName ?? 'LGA' }}, {{ $stateName ?? 'State' }} State
                                                             </h4>
                                                         </div>
                                                     @endif
                                                     <div class="pt-2">
                                                         <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-extrabold tracking-wider bg-amber-50 text-amber-700 border border-amber-200 uppercase">
                                                             Pending Submission
                                                         </span>
                                                     </div>
                                                 </div>
                                             </div>
                                             
                                             <!-- Card Footer metadata -->
                                             <div class="bg-slate-50 px-5 py-3 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-500 font-bold">
                                                 <div class="truncate max-w-[150px]">
                                                     File: {{ $pvcSelfie->getClientOriginalName() }}
                                                 </div>
                                                 <span>{{ round($pvcSelfie->getSize() / 1024, 1) }} KB</span>
                                             </div>
                                         </div>
 
                                         <!-- Hover Overlay explaining how to change the photo -->
                                         <div class="absolute inset-0 bg-slate-950/45 opacity-0 group-hover:opacity-100 rounded-3xl transition duration-200 flex items-center justify-center text-white font-bold text-sm">
                                             <span class="bg-[#008751] px-4 py-2.5 rounded-xl flex items-center gap-2 shadow-lg border border-white/10 hover:scale-105 transition-transform">
                                                 <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                                 </svg>
                                                 Change Photo
                                             </span>
                                         </div>
                                     </label>
                                 @endif
                            </div>

                            @error('pvcSelfie')
                                <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Agreement Box -->
                        <div class="bg-slate-50 border border-slate-200 p-6 rounded-2xl">
                            <label class="flex items-start cursor-pointer">
                                <input type="checkbox" wire:model="agreement"
                                    class="h-6 w-6 text-[#008751] border-slate-300 rounded focus:ring-[#008751] mt-0.5">
                                <span class="ms-3 text-sm text-slate-600 font-bold leading-normal">
                                    I voluntarily declare support for the All Progressives Congress (APC) and affirm
                                    that the PVC details provided above belong to me. I authorize processing this data
                                    securely under NDPR regulations.
                                </span>
                            </label>
                            @error('agreement')
                                <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                @endif

                <!-- SUCCESS VIEW -->
                @if ($currentStep === 6)
                    <div class="text-center py-10 space-y-6">
                        <div
                            class="h-20 w-20 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-600 mx-auto border border-emerald-100 shadow-sm">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-slate-800">Support Declared Successfully!</h3>
                            <p class="text-sm text-slate-500 mt-2">Thank you for standing up. Your support declaration
                                has been registered.</p>
                        </div>

                        <div
                            class="bg-emerald-50/50 border border-emerald-100 p-6 rounded-2xl max-w-sm mx-auto shadow-sm">
                            <p class="text-[10px] text-emerald-700 font-extrabold uppercase tracking-wider">Declaration
                                Tracking Reference</p>
                            <h4 id="declaration-reference-number" class="text-2xl font-black text-emerald-950 mt-1 select-all tracking-wider font-mono">
                                {{ $searchRef }}</h4>
                            <p class="text-[11px] text-slate-400 mt-2 font-medium">Use this reference code to check
                                your reward status below.</p>
                        </div>

                        <!-- Completed PVC Card Preview for Print -->
                        @if ($completedCardPhotoUrl)
                            <div class="my-6">
                                <p class="text-xs text-slate-400 font-bold mb-3">Your Digital Voter Verification Card:</p>
                                <div id="pvc-card-to-print" class="border-2 border-[#008751]/30 bg-white rounded-3xl shadow-xl overflow-hidden max-w-sm sm:max-w-md mx-auto text-left">
                                    <!-- Green Header with Nigeria colors -->
                                    <div class="bg-gradient-to-r from-[#008751] via-[#005c36] to-[#014126] text-white px-5 py-3 flex items-center justify-between">
                                        <div class="flex items-center space-x-2">
                                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                                            <span class="text-[10px] font-black tracking-widest uppercase">APC 2027 Voter verification</span>
                                        </div>
                                        <span class="text-[9px] bg-emerald-600/30 border border-emerald-400/40 text-emerald-100 px-2 py-0.5 rounded-full font-bold">VERIFIED</span>
                                    </div>
                                    
                                    <!-- Card Content -->
                                    <div class="p-6 flex flex-col sm:flex-row items-center sm:items-start gap-5 bg-gradient-to-br from-white to-slate-50/50">
                                        <!-- Photo Preview Frame -->
                                        <div class="flex-shrink-0">
                                            <div class="h-32 w-28 rounded-2xl border-4 border-white bg-slate-100 overflow-hidden shadow-md"
                                                style="background-image: url('{{ $completedCardPhotoUrl }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
                                            </div>
                                        </div>
                                        
                                        <!-- Details Info Grid -->
                                        <div class="flex-grow w-full text-center sm:text-left space-y-3">
                                            <div>
                                                <span class="text-[9px] text-slate-400 font-extrabold uppercase block tracking-wider">Full Name</span>
                                                <h4 class="text-sm font-black text-slate-800 tracking-tight mt-0.5">{{ $completedCardName }}</h4>
                                            </div>
                                            <div>
                                                <span class="text-[9px] text-slate-400 font-extrabold uppercase block tracking-wider">Phone Number</span>
                                                <h4 class="text-sm font-black text-slate-800 tracking-tight mt-0.5">{{ $completedCardPhone }}</h4>
                                            </div>
                                            @if ($completedCardState || $completedCardLga)
                                                <div>
                                                    <span class="text-[9px] text-slate-400 font-extrabold uppercase block tracking-wider">Voter Location</span>
                                                    <h4 class="text-xs font-bold text-slate-700 tracking-tight mt-0.5">
                                                        {{ $completedCardLga ?? 'LGA' }}, {{ $completedCardState ?? 'State' }} State
                                                    </h4>
                                                </div>
                                            @endif
                                            <div class="pt-2">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-extrabold tracking-wider bg-emerald-50 text-[#008751] border border-emerald-200 uppercase">
                                                    Reference: {{ $searchRef }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                            @if ($completedCardPhotoUrl)
                                <button type="button" onclick="downloadCardAsImage()"
                                    class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 text-sm font-bold rounded-xl text-white bg-[#008751] hover:bg-emerald-800 shadow-md shadow-emerald-100 transition">
                                    Download Card Image
                                </button>
                            @endif
                            <button type="button" wire:click="restartWizard"
                                class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-slate-200 text-sm font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50 shadow-sm transition">
                                New Submission
                            </button>
                            <a href="#tracker"
                                class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 text-sm font-bold rounded-xl text-slate-700 hover:text-[#008751] transition">
                                Track My Submission
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Wizard Actions -->
                @if ($currentStep < 6)
                    <div class="flex items-center justify-between border-t border-slate-100 pt-6 mt-8">
                        @if ($currentStep > 1)
                            <button type="button" wire:click="previousStep"
                                class="inline-flex items-center justify-center px-5 py-3 border border-slate-200 text-sm font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50 shadow-sm transition duration-150">
                                Back
                            </button>
                        @else
                            <div></div>
                        @endif

                        @if ($currentStep < 5)
                            <button type="button" wire:click="nextStep"
                                class="inline-flex items-center justify-center px-6 py-3 text-sm font-bold rounded-xl text-white bg-[#008751] hover:bg-emerald-800 shadow-md shadow-emerald-100 transition duration-150">
                                Continue
                            </button>
                        @else
                            <button type="button" wire:click="submitDeclaration"
                                class="inline-flex items-center justify-center px-8 py-3.5 text-sm font-bold rounded-xl text-white bg-[#008751] hover:bg-emerald-800 shadow-lg shadow-emerald-100 transition duration-150">
                                Submit Declaration
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

    <!-- Reference Tracker Lookup -->
    <section id="tracker" class="bg-white py-24 px-4 sm:px-6 lg:px-8 border-y border-slate-100 scroll-mt-24">
        <div class="max-w-xl mx-auto space-y-8">
            <div class="text-center">
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold tracking-wider bg-slate-100 text-slate-600 uppercase mb-3">
                    Verification
                </span>
                <h2 class="text-3xl font-black text-slate-900 tracking-tight">Track Your Submission</h2>
                <p class="text-sm text-slate-500 mt-2">Enter your reference code below to check verification state and
                    spin results.</p>
            </div>

            <div class="bg-slate-50 rounded-3xl p-6 shadow-sm border border-slate-100">
                <form wire:submit="trackSubmission" class="flex flex-col sm:flex-row gap-3">
                    <input type="text" wire:model="searchRef" placeholder="e.g. APC-2027-00000001"
                        class="flex-grow rounded-xl border-slate-200 focus:ring-2 focus:ring-[#008751]/20 focus:border-[#008751] text-sm py-3 px-4 shadow-sm uppercase font-mono tracking-wider"
                        required>
                    <button type="submit"
                        class="inline-flex items-center justify-center px-6 py-3 text-sm font-bold rounded-xl text-white bg-[#008751] hover:bg-emerald-800 shadow-md shadow-emerald-100 transition">
                        Check Status
                    </button>
                </form>

                <!-- Status View -->
                @if ($trackerSearched)
                    <div class="mt-8 border-t border-slate-200/50 pt-6">
                        @if ($trackedSubmission)
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-extrabold text-slate-400 uppercase tracking-widest">Status
                                        Details</h4>
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider
                                        @if ($trackedSubmission->status === 'approved') bg-emerald-50 text-emerald-700 border border-emerald-100
                                        @elseif($trackedSubmission->status === 'rejected') bg-rose-50 text-rose-700 border border-rose-100
                                        @elseif($trackedSubmission->status === 'rewarded') bg-sky-50 text-sky-700 border border-sky-100
                                        @else bg-amber-50 text-amber-700 border border-amber-100 @endif">
                                        {{ $trackedSubmission->status }}
                                    </span>
                                </div>
                                <div
                                    class="grid grid-cols-2 gap-4 text-sm bg-white p-4 rounded-2xl border border-slate-100">
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-extrabold uppercase">Citizen Name</p>
                                        <p class="text-slate-800 font-bold mt-0.5">{{ $trackedSubmission->full_name }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-extrabold uppercase">Submission Date
                                        </p>
                                        <p class="text-slate-800 font-semibold mt-0.5">
                                            {{ $trackedSubmission->created_at->format('M d, Y') }}</p>
                                    </div>
                                    <div class="col-span-2 border-t border-slate-50 pt-2">
                                        <p class="text-[10px] text-slate-400 font-extrabold uppercase">Location
                                            Information</p>
                                        <p class="text-slate-800 font-bold mt-0.5">
                                            {{ $trackedSubmission->state?->name }},
                                            {{ $trackedSubmission->lga?->name }}</p>
                                    </div>
                                    @if ($trackedSubmission->rewardClaim)
                                        <div class="col-span-2 border-t border-slate-50 pt-2 grid grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-[10px] text-slate-400 font-extrabold uppercase">Won
                                                    Reward</p>
                                                <p class="text-[#008751] font-black mt-0.5">
                                                    {{ $trackedSubmission->rewardClaim->reward->name }}</p>
                                            </div>
                                            <div>
                                                <p class="text-[10px] text-slate-400 font-extrabold uppercase">Claim
                                                    Code</p>
                                                <p class="text-sky-600 font-mono font-bold mt-0.5 select-all">
                                                    {{ $trackedSubmission->rewardClaim->claim_code }}</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <button type="button" wire:click="closeTracking"
                                    class="w-full text-center text-xs font-bold text-slate-400 hover:text-slate-700 transition">
                                    Close Results
                                </button>
                            </div>
                        @else
                            <div class="text-center py-4 bg-rose-50 border border-rose-100 rounded-xl">
                                <p class="text-xs font-bold text-rose-600">Invalid tracking reference code. Please
                                    check and try again.</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

    <!-- Interactive Spin & Win Modal -->
    @if ($showSpinModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
            <style>
                [x-cloak] {
                    display: none !important;
                }
            </style>
            <div x-data="{
                spinning: false,
                angle: 0,
                duration: 0,
                showResult: false,
                spinWheel() {
                    if (this.spinning) return;
                    this.spinning = true;
                    this.showResult = false;
                    this.duration = 4; // 4 seconds animation

                    // Immediately spin multiple times
                    this.angle = 1440;

                    $wire.executeSpin().then(() => {
                        let result = $wire.get('spinResult');
                        let segmentIndex = 5; // Default to Try Again

                        if (result.includes('500')) segmentIndex = 0;
                        else if (result.includes('5GB')) segmentIndex = 1;
                        else if (result.includes('1000')) segmentIndex = 2;
                        else if (result.includes('10GB')) segmentIndex = 3;
                        else if (result.includes('2000')) segmentIndex = 4;

                        // Exact landing angle for segment center:
                        // pointer is at top (0 deg). Segment index center is at (index * 60 + 30).
                        // To align that segment to the pointer, we rotate the wheel by 2880 + (360 - (index * 60 + 30))
                        const targetAngle = 2880 + (360 - (segmentIndex * 60 + 30));
                        this.angle = targetAngle;

                        setTimeout(() => {
                            this.spinning = false;
                            this.showResult = true;
                        }, 4200);
                    }).catch(err => {
                        // Fallback landing on segment 5 (Try Again)
                        const targetAngle = 2880 + (360 - (5 * 60 + 30));
                        this.angle = targetAngle;
                        setTimeout(() => {
                            this.spinning = false;
                            this.showResult = true;
                        }, 4200);
                    });
                }
            }"
                class="bg-white rounded-3xl max-w-md w-full shadow-2xl overflow-hidden transform scale-100 transition-all border border-slate-100">
                <div class="bg-gradient-to-br from-[#008751] to-[#005c36] p-6 text-white text-center relative">
                    <div class="absolute top-4 right-4">
                        <button type="button" wire:click="$set('showSpinModal', false)"
                            class="text-white/80 hover:text-white text-xl font-bold">&times;</button>
                    </div>
                    <span class="text-3xl">🎁</span>
                    <h3 class="text-xl font-black mt-2">Spin & Win Rewards</h3>
                    <p class="text-xxs text-emerald-100 uppercase tracking-widest mt-1 font-bold">1 Free Spin Per
                        Registered PVC</p>
                </div>

                <div class="p-8 text-center space-y-6">
                    <!-- Spinner Wheel Screen -->
                    <div x-show="!showResult" class="space-y-6">
                        <p class="text-sm text-slate-500">Congratulations on your declaration! Click below to spin the
                            real reward wheel for a chance to win airtime or data.</p>

                        <div class="relative w-64 h-64 mx-auto pb-4" wire:ignore>
                            <!-- Pointer Arrow -->
                            <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-4 z-30">
                                <svg class="h-8 w-8 text-amber-500 filter drop-shadow-md" fill="currentColor"
                                    viewBox="0 0 24 24">
                                    <path d="M12 21l-8-14h16z" />
                                </svg>
                            </div>

                            <!-- Rotating wheel -->
                            <div class="w-60 h-60 mx-auto rounded-full border-[10px] border-slate-800 shadow-2xl overflow-hidden relative"
                                :style="'transform: rotate(' + angle + 'deg); transition: transform ' + duration +
                                    's cubic-bezier(0.15, 0.85, 0.35, 1)'">

                                <!-- Segment backgrounds via conic gradient -->
                                <div class="absolute inset-0"
                                    style="background: conic-gradient(
                                     #059669 0deg 60deg,
                                     #0284c7 60deg 120deg,
                                     #d97706 120deg 180deg,
                                     #4f46e5 180deg 240deg,
                                     #7c3aed 240deg 300deg,
                                     #e11d48 300deg 360deg
                                 )">
                                </div>

                                <!-- Text Labels inside segments -->
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none"
                                    style="transform: rotate(30deg)">
                                    <span
                                        class="text-[11px] font-black text-white uppercase tracking-wider -translate-y-20 rotate-90">₦500</span>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none"
                                    style="transform: rotate(90deg)">
                                    <span
                                        class="text-[11px] font-black text-white uppercase tracking-wider -translate-y-20 rotate-90">5GB</span>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none"
                                    style="transform: rotate(150deg)">
                                    <span
                                        class="text-[11px] font-black text-white uppercase tracking-wider -translate-y-20 rotate-90">₦1000</span>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none"
                                    style="transform: rotate(210deg)">
                                    <span
                                        class="text-[11px] font-black text-white uppercase tracking-wider -translate-y-20 rotate-90">10GB</span>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none"
                                    style="transform: rotate(270deg)">
                                    <span
                                        class="text-[11px] font-black text-white uppercase tracking-wider -translate-y-20 rotate-90">₦2000</span>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none"
                                    style="transform: rotate(330deg)">
                                    <span
                                        class="text-[9px] font-black text-white uppercase tracking-wider -translate-y-20 rotate-90">Try
                                        Again</span>
                                </div>

                                <!-- Inner wheel lines dividing segments -->
                                <div class="absolute inset-0 opacity-15">
                                    <div
                                        class="absolute top-0 bottom-0 left-1/2 w-[2px] bg-white transform -translate-x-1/2">
                                    </div>
                                    <div
                                        class="absolute left-0 right-0 top-1/2 h-[2px] bg-white transform -translate-y-1/2">
                                    </div>
                                    <div
                                        class="absolute top-0 bottom-0 left-1/2 w-[2px] bg-white transform -translate-x-1/2 rotate-60">
                                    </div>
                                    <div
                                        class="absolute top-0 bottom-0 left-1/2 w-[2px] bg-white transform -translate-x-1/2 rotate-120">
                                    </div>
                                </div>
                            </div>

                            <!-- Central Spin Pivot -->
                            <div
                                class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-8 w-12 h-12 bg-slate-900 rounded-full border-4 border-slate-700 flex items-center justify-center shadow-lg z-20">
                                <div class="w-3 h-3 bg-amber-500 rounded-full"></div>
                            </div>
                        </div>

                        <button type="button" @click="spinWheel" :disabled="spinning"
                            class="w-full inline-flex items-center justify-center px-6 py-4 text-base font-extrabold rounded-2xl text-white bg-[#008751] hover:bg-emerald-800 disabled:bg-slate-350 shadow-md shadow-emerald-100 transition duration-150">
                            <span x-show="!spinning">Spin the Wheel!</span>
                            <span x-show="spinning" class="flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                Spinning...
                            </span>
                        </button>
                    </div>

                    <!-- Result Screen (updated by Livewire) -->
                    <div x-show="showResult" class="space-y-4" x-cloak>
                        <h4 class="text-xs font-extrabold text-slate-400 uppercase tracking-widest">Your Reward Result
                        </h4>

                        @if ($spinResult)
                            @if (str_contains($spinResult, 'No Reward'))
                                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200 max-w-xs mx-auto">
                                    <span class="text-4xl">🤝</span>
                                    <h5 class="text-lg font-extrabold text-slate-700 mt-2">Thank you!</h5>
                                    <p class="text-xs text-slate-500 mt-1">Thank you for supporting the APC 2027
                                        vision. Your community wishes are registered.</p>
                                </div>
                            @else
                                <div class="bg-emerald-50 p-6 rounded-2xl border border-emerald-100 max-w-xs mx-auto">
                                    <span class="text-4xl animate-bounce inline-block">🎉</span>
                                    <h5 class="text-2xl font-black text-emerald-800 mt-2">{{ $spinResult }}</h5>
                                    <p class="text-[10px] text-emerald-600 font-extrabold uppercase mt-1">Claimed
                                        Successfully</p>
                                </div>

                                @if ($spinVoucherCode)
                                    <div
                                        class="bg-sky-50 border border-sky-100 p-4 rounded-xl max-w-xs mx-auto shadow-inner">
                                        <p class="text-[9px] text-sky-700 font-extrabold uppercase tracking-widest">
                                            Voucher Claim Code</p>
                                        <p
                                            class="text-lg font-black text-sky-950 font-mono mt-0.5 select-all tracking-wider">
                                            {{ $spinVoucherCode }}</p>
                                        <p class="text-[10px] text-slate-400 mt-1 font-medium">Use this code to claim
                                            your reward.</p>
                                    </div>
                                @endif
                            @endif
                        @endif

                        <button type="button" wire:click="$set('showSpinModal', false)"
                            class="w-full inline-flex items-center justify-center px-5 py-3 border border-slate-200 text-sm font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50 transition">
                            Close Window
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- About Section -->
    <section id="about"
        class="py-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
        <div>
            <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold tracking-wider bg-emerald-50 text-[#008751] uppercase mb-4">
                Campaign Information
            </span>
            <h2 class="text-3xl font-black text-slate-900 leading-tight">About APC 2027 Citizen Engagement Portal</h2>
            <p class="text-slate-500 mt-6 leading-relaxed text-sm">
                The Progressive Nigerian Support & Wishes Portal is a secure digital civic engagement tool designed to
                collect citizen feedback, developmental desires, and verified political support records dynamically.
            </p>
            <p class="text-slate-500 mt-4 leading-relaxed text-sm">
                Every development request submitted is categorized and made available to campaign coordinators to map
                regional development requirements and advocate for local communities.
            </p>
        </div>
        <div class="bg-gradient-to-tr from-emerald-50 to-white rounded-3xl p-8 border border-emerald-100 shadow-sm">
            <h3 class="font-extrabold text-[#008751] text-lg">NDPR Security Guarantee</h3>
            <p class="text-xs text-slate-500 mt-3 leading-relaxed">
                Your voter identity information is protected with advanced encryption tools. Private directories hold
                PVC selfie files to shield user identity, and registrations comply fully with Nigeria Data Protection
                Regulation (NDPR) guidelines.
            </p>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="bg-white py-24 border-t border-slate-100">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-black text-slate-900">Frequently Asked Questions</h2>
                <p class="text-sm text-slate-500 mt-2">Answers to key questions regarding the citizen portal and
                    campaign registration.</p>
            </div>

            <div class="space-y-6">
                <div class="border border-slate-100 bg-slate-50/50 rounded-2xl p-6">
                    <h4 class="font-extrabold text-slate-800 text-base">Who can participate?</h4>
                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">Any Nigerian citizen who wishes to support
                        the All Progressives Congress (APC) and share local developmental requests can declare support
                        voluntarily.</p>
                </div>
                <div class="border border-slate-100 bg-slate-50/50 rounded-2xl p-6">
                    <h4 class="font-extrabold text-slate-800 text-base">How are spin rewards processed?</h4>
                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">After submitting a valid PVC registration,
                        the system grants one spin at the lucky wheel. Rewards are random based on active daily quota
                        limits.</p>
                </div>
                <div class="border border-slate-100 bg-slate-50/50 rounded-2xl p-6">
                    <h4 class="font-extrabold text-slate-800 text-base">Is my data secure?</h4>
                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">Yes. Your registration details, including
                        the PVC image, are kept inside secure private folders that are strictly scoped to verified
                        campaign administrators.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-950 text-white py-16 mt-auto border-t border-slate-900">
        <div
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-8">
            <div class="flex items-center space-x-4">
                <!-- Circular APC Logo -->
                <img src="{{ asset('images/apc_ogo.png') }}" alt="APC Logo"
                    class="h-10 w-10 object-contain filter brightness-95">
                <div>
                    <h2 class="font-black text-sm text-slate-200">All Progressives Congress (APC)</h2>
                    <p class="text-[10px] text-slate-500 mt-0.5">© 2026 Progressive Nigeria Portal. NDPR Compliant. All
                        Rights Reserved.</p>
                </div>
            </div>
            <div class="flex space-x-6 text-xs font-bold text-slate-400">
                <a href="#" class="hover:text-[#008751] transition">Privacy Policy</a>
                <a href="#" class="hover:text-[#008751] transition">Terms of Service</a>
                <a href="#" class="hover:text-[#008751] transition">Contact Campaign</a>
            </div>
        </div>
    </footer>


</div>
