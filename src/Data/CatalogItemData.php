<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Data;

final readonly class CatalogItemData
{
    /**
     * @param  string  $type  product|service|subscription
     * @param  int  $unitAmount  unit price in cents
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $type,
        public string $sourceType,
        public string $sourceId,
        public string $description,
        public int $unitAmount,
        public array $metadata = [],
    ) {}

    /**
     * @return array{type: string, source_type: string, source_id: string, description: string, unit_amount: int, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'description' => $this->description,
            'unit_amount' => $this->unitAmount,
            'metadata' => $this->metadata,
        ];
    }
}
