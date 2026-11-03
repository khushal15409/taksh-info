@extends('layouts.app')

@section('title', 'Login | TakshAllInOne')
@section('page', 'login')

@section('content')
        <div id="login-root"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/modules/login.js') }}"></script>
@endpush
