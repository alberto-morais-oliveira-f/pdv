@extends($layout)

@section($section)
    <div class="pdv-page">
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
