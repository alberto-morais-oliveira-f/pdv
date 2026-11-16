<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchCatalogRequest extends FormRequest
{
    /**
     * Authorization is delegated to the host's configured route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function searchTerm(): string
    {
        return (string) ($this->validated('q') ?? '');
    }

    public function searchLimit(): int
    {
        return (int) ($this->validated('limit') ?? 20);
    }
}
