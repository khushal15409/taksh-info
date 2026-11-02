@extends('layouts.app')

@section('title', 'Order Tracking | TakshAllInOne')
@section('page', 'tracking')

@section('content')
        <div id="tracking-root"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/tracking.js') }}"></script>
@endpush
