<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ListSalesRequest extends FormRequest
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
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Requested day in the app timezone; defaults to today.
     */
    public function day(): Carbon
    {
        $timezone = (string) config('app.timezone');
        $date = $this->validated('date');

        return is_string($date)
            ? Carbon::createFromFormat('!Y-m-d', $date, $timezone)
            : Carbon::now($timezone)->startOfDay();
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 50);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return __('pdv::sales.validation');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('pdv::sales.attributes');
    }
}
