<?php

namespace App\DTOs;

class AgentProfileDTO
{
    public function __construct(
        public int $userId,
        public ?int $stateId,
        public string $status = 'active',
        public ?int $createdBy = null,
        // Optional nested user creation fields
        public ?string $name = null,
        public ?string $email = null,
        public ?string $password = null,
        public ?string $role = null
    ) {}

    /**
     * Create DTO from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) ($data['user_id'] ?? 0),
            stateId: isset($data['state_id']) ? (int) $data['state_id'] : null,
            status: (string) ($data['status'] ?? 'active'),
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            password: $data['password'] ?? null,
            role: $data['role'] ?? 'Agent'
        );
    }

    /**
     * Convert DTO to profile database values.
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'state_id' => $this->stateId,
            'status' => $this->status,
            'created_by' => $this->createdBy,
        ];
    }
}
