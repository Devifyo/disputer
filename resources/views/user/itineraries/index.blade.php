@extends('layouts.app')

@section('title', 'Flight Disputes')

@section('content')
    <div
        id="flight-dispute-app"
        class="flex-1 flex flex-col min-h-0"
        data-base="{{ url('/flight-disputes') }}"
    ></div>

    @vite('resources/js/flight-dispute/main.js')
@endsection
