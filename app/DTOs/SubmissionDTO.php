<?php

namespace App\DTOs;

class SubmissionDTO
{
    public function __construct(
        public string $fullName,
        public string $phoneNumber,
        public ?string $email,
        public string $gender,
        public string $ageGroup,
        public int $stateId,
        public int $lgaId,
        public ?int $wardId,
        public ?string $pollingUnit,
        public bool $voted2023,
        public bool $vote2027,
        public int $occupationId,
        public string $status = 'pending',
        public ?int $agentId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?WishDTO $wish = null,
        public ?string $imagePath = null,
        public ?string $referenceNumber = null
    ) {}

    /**
     * Create DTO from array data.
     */
    public static function fromArray(array $data): self
    {
        $wish = null;
        if (isset($data['wish_category_id']) || isset($data['wish'])) {
            $wishData = $data['wish'] ?? $data;
            $wish = WishDTO::fromArray($wishData);
        }

        return new self(
            fullName: (string) ($data['full_name'] ?? ''),
            phoneNumber: (string) ($data['phone_number'] ?? ''),
            email: $data['email'] ?? null,
            gender: (string) ($data['gender'] ?? ''),
            ageGroup: (string) ($data['age_group'] ?? ''),
            stateId: (int) ($data['state_id'] ?? 0),
            lgaId: (int) ($data['lga_id'] ?? 0),
            wardId: isset($data['ward_id']) ? (int) $data['ward_id'] : null,
            pollingUnit: $data['polling_unit'] ?? null,
            voted2023: filter_var($data['voted_2023'] ?? false, FILTER_VALIDATE_BOOLEAN),
            vote2027: filter_var($data['vote_2027'] ?? false, FILTER_VALIDATE_BOOLEAN),
            occupationId: (int) ($data['occupation_id'] ?? 0),
            status: (string) ($data['status'] ?? 'pending'),
            agentId: isset($data['agent_id']) ? (int) $data['agent_id'] : null,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            wish: $wish,
            imagePath: $data['image_path'] ?? null,
            referenceNumber: $data['reference_number'] ?? null
        );
    }

    /**
     * Convert DTO to database attributes.
     */
    public function toArray(): array
    {
        return [
            'full_name' => $this->fullName,
            'phone_number' => $this->phoneNumber,
            'email' => $this->email,
            'gender' => $this->gender,
            'age_group' => $this->ageGroup,
            'state_id' => $this->stateId,
            'lga_id' => $this->lgaId,
            'ward_id' => $this->wardId,
            'polling_unit' => $this->pollingUnit,
            'voted_2023' => $this->voted2023,
            'vote_2027' => $this->vote2027,
            'occupation_id' => $this->occupationId,
            'status' => $this->status,
            'agent_id' => $this->agentId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'reference_number' => $this->referenceNumber,
        ];
    }
}
