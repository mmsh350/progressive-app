<?php

namespace App\DTOs;

class WishDTO
{
    public function __construct(
        public int $wishCategoryId,
        public string $title,
        public string $description
    ) {}

    /**
     * Create a DTO from request data or array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            wishCategoryId: (int) ($data['wish_category_id'] ?? 0),
            title: (string) ($data['wish_title'] ?? $data['title'] ?? ''),
            description: (string) ($data['wish_description'] ?? $data['description'] ?? '')
        );
    }

    /**
     * Convert the DTO to an array matching database fields.
     */
    public function toArray(): array
    {
        return [
            'wish_category_id' => $this->wishCategoryId,
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
}
