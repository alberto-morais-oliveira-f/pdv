@extends($layout)

@section($section)
    <div class="pdv-page">
        <nav class="pdv-nav">
            @foreach (['counter', 'terminals', 'sales'] as $item)
                <a href="{{ $routes[$item] }}" @class(['pdv-nav__link', 'is-active' => $item === $page])>{{ __('pdv::ui.nav.'.$item) }}</a>
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
