@extends('layouts.app')

@section('title', 'Payment Status | TakshAllInOne')
@section('page', 'payresult')

@section('content')
        <div id="payresult-root"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/payresult.js') }}"></script>
@endpush
