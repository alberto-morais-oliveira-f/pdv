<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Controllers\Api;

use Am2tec\Pdv\Http\Requests\StoreCouponRequest;
use Am2tec\Pdv\Http\Requests\UpdateCouponRequest;
use Am2tec\Pdv\Http\Requests\ValidateCouponRequest;
use Am2tec\Pdv\Http\Resources\CouponResource;
use Am2tec\Pdv\Models\Coupon;
use Am2tec\Pdv\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class CouponController extends Controller
{
    public function __construct(private readonly CouponService $coupons) {}

    public function index(): AnonymousResourceCollection
    {
        return CouponResource::collection($this->coupons->list());
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = $this->coupons->create($request->validated());

        return (new CouponResource($coupon))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): CouponResource
    {
        return new CouponResource($this->coupons->update($coupon, $request->validated()));
    }

    public function destroy(Coupon $coupon): Response
    {
        $this->coupons->delete($coupon);

        return response()->noContent();
    }

    /**
     * Pre-validates a coupon code for the counter. Method is not named `validate`
     * so it does not collide with `ValidatesRequests::validate()`.
     */
    public function check(ValidateCouponRequest $request): JsonResponse
    {
        return response()->json($this->coupons->preview($request->code(), $request->gross()));
    }
}
