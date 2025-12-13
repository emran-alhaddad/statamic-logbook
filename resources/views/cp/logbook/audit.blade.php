@extends('statamic-logbook::cp.logbook._layout', ['active' => 'audit'])

@section('panel')
    <p class="text-sm text-gray-700 mb-4">
        {{ __('Audit logs will appear here.') }}
    </p>

    <div class="text-xs text-gray-600">
        Stage 5C next: action/subject filters + changes viewer + export.
    </div>
@endsection
