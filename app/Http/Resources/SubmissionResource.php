<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'full_name' => $this->full_name,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'gender' => $this->gender,
            'age_group' => $this->age_group,
            'state' => $this->state?->name,
            'lga' => $this->lga?->name,
            'ward' => $this->ward?->name,
            'polling_unit' => $this->polling_unit,
            'voted_2023' => $this->voted_2023,
            'vote_2027' => $this->vote_2027,
            'occupation' => $this->occupation?->name,
            'status' => $this->status,
            'has_spun' => $this->spin !== null,
            'reward_won' => $this->spin?->reward?->name ?? 'None',
            'claim_code' => $this->rewardClaim?->claim_code,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
