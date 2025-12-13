@extends('statamic-logbook::cp.logbook._layout', ['active' => 'system'])

@section('panel')
    <p class="text-sm text-gray-700 mb-4">
        {{ __('System logs will appear here.') }}
    </p>

    <div class="text-xs text-gray-600">
        Stage 5B next: filters + pagination + export.
    </div>
@endsection
