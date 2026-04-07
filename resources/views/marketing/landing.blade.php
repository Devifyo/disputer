@extends('layouts.marketing')

@section('title', 'Unjamm - Resolve complaints with companies faster')
@section('meta_description', 'Unjamm helps you escalate disputes with banks, airlines, telecom companies, and other institutions using structured escalation workflows.')

@push('styles')
    @include('marketing.partials._styles')
@endpush

@section('content')
    @include('marketing.partials._hero')
    @include('marketing.partials._how-it-works')
    @include('marketing.partials._why')
    @include('marketing.partials._story')
    @include('marketing.partials._outcomes')
    @include('marketing.partials._situations')
    @include('marketing.partials._faq')
    @include('marketing.partials._cta')
@endsection
