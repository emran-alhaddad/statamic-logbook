@extends('statamic::layout')

@section('title', __('Logbook'))

@section('content')
    <div class="flex items-center justify-between mb-3">
        <h1>{{ __('Logbook') }}</h1>
    </div>

    <div class="card p-0">
        <div class="flex border-b px-4">
            <a
                class="py-3 mr-4 text-sm font-medium @if($active === 'system') text-blue @else text-gray-700 @endif"
                href="{{ cp_route('utilities.logbook.system') }}"
            >
                {{ __('System Logs') }}
            </a>

            <a
                class="py-3 mr-4 text-sm font-medium @if($active === 'audit') text-blue @else text-gray-700 @endif"
                href="{{ cp_route('utilities.logbook.audit') }}"
            >
                {{ __('Audit Logs') }}
            </a>
        </div>

        <div class="p-4">
            @yield('panel')
        </div>
    </div>
@endsection
