@extends($layout)

@section($section)
    <div class="pdv-page">
        <nav class="pdv-nav" aria-label="{{ __('PDV') }}">
            @foreach (['counter', 'terminals', 'coupons', 'sales'] as $item)
                <a
                    href="{{ $routes[$item] }}"
                    data-testid="pdv-nav-{{ $item }}"
                    @class(['pdv-nav__link', 'is-active' => $item === $page])
                >
                    @if ($item === 'counter')
                        <i class="fa-solid fa-cash-register"></i>
                    @elseif ($item === 'terminals')
                        <i class="fa-solid fa-credit-card"></i>
                    @elseif ($item === 'coupons')
                        <i class="fa-solid fa-ticket"></i>
                    @else
                        <i class="fa-solid fa-receipt"></i>
                    @endif
                    {{ __('pdv::ui.nav.'.$item) }}
                </a>
            @endforeach
        </nav>

        <div id="pdv-app"
             data-page="{{ $page }}"
             data-api-base="{{ $apiBase }}"
             data-csrf="{{ csrf_token() }}"
             data-routes="{{ json_encode($routes) }}"
             data-strings="{{ json_encode($strings) }}">
            <p class="pdv-loading">{{ __('pdv::ui.common.loading') }}</p>
        </div>
    </div>
@endsection
