@extends('layouts.app')

@section('title', 'My Wallet | TakshAllInOne')
@section('page', 'wallet')

@section('content')
        <div id="wallet-root"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/wallet.js') }}"></script>
@endpush
