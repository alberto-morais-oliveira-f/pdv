<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Services;

use Am2tec\Pdv\Enums\DiscountType;
use Am2tec\Pdv\Models\Coupon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function __construct(private readonly DiscountCalculator $calculator) {}

    /**
     * @return Collection<int, Coupon>
     */
    public function list(): Collection
    {
        return Coupon::query()->orderBy('id')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Coupon
    {
        return Coupon::query()->create($data)->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->update($data);

        return $coupon->refresh();
    }

    public function delete(Coupon $coupon): void
    {
        $coupon->delete();
    }

    /**
     * @throws ValidationException
     */
    public function findValidByCode(string $code): Coupon
    {
        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->first();

        if ($coupon === null) {
            $this->refuse(__('pdv::coupons.errors.not_found'));
        }

        if (! $coupon->active) {
            $this->refuse(__('pdv::coupons.errors.inactive'));
        }

        $now = Carbon::now();

        if ($coupon->starts_at !== null && $coupon->starts_at->gt($now)) {
            $this->refuse(__('pdv::coupons.errors.not_started'));
        }

        if ($coupon->ends_at !== null && $this->expiresAt($coupon->ends_at)->lt($now)) {
            $this->refuse(__('pdv::coupons.errors.expired'));
        }

        if ($coupon->max_uses !== null && $coupon->uses_count >= $coupon->max_uses) {
            $this->refuse(__('pdv::coupons.errors.max_uses'));
        }

        return $coupon;
    }

    /**
     * @return array{code: string, type: string, value: int, discount_preview: int|null}
     *
     * @throws ValidationException
     */
    public function preview(string $code, ?int $gross): array
    {
        $coupon = $this->findValidByCode($code);

        $preview = $gross === null
            ? null
            : $this->calculator->resolve($gross, ['mode' => DiscountType::Coupon->value], $coupon)['amount'];

        return [
            'code' => $coupon->code,
            'type' => $coupon->type->value,
            'value' => $coupon->value,
            'discount_preview' => $preview,
        ];
    }

    /**
     * Date-only `ends_at` is stored at midnight; treat that as the end of the calendar day.
     */
    private function expiresAt(Carbon $endsAt): Carbon
    {
        if ($endsAt->format('H:i:s') === '00:00:00') {
            return $endsAt->copy()->endOfDay();
        }

        return $endsAt;
    }

    /**
     * @throws ValidationException
     */
    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['code' => [$message]]);
    }
}
