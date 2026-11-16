<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;

class PdvPageController extends Controller
{
    public function counter(): View
    {
        return $this->page('counter');
    }

    public function terminals(): View
    {
        return $this->page('terminals');
    }

    public function coupons(): View
    {
        return $this->page('coupons');
    }

    public function sales(): View
    {
        return $this->page('sales');
    }

    /**
     * All pages share one shell; the Vue app picks the screen from `data-page`.
     */
    private function page(string $page): View
    {
        return view('pdv::page', [
            'page' => $page,
            'apiBase' => url((string) config('pdv.routes.prefix', 'pdv/api')),
            'layout' => (string) config('pdv.web.layout', 'pdv::layout'),
            'section' => (string) config('pdv.web.section', 'content'),
            'routes' => [
                'counter' => route('pdv.counter'),
                'terminals' => route('pdv.terminals'),
                'coupons' => route('pdv.coupons'),
                'sales' => Route::has('pdv.sales.index')
                    ? route('pdv.sales.index')
                    : route('pdv.sales'),
            ],
            'strings' => (array) trans('pdv::ui'),
        ]);
    }
}
