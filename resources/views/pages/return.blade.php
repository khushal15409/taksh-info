@extends('layouts.app')

@section('title', 'Return Request | TakshAllInOne')
@section('page', 'returns')

@section('content')
        <div id="return-root"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/return.js') }}"></script>
@endpush
