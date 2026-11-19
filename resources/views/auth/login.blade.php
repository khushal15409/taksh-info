@extends('layouts.app')

@section('title', 'Login | TakshAllInOne')
@section('page', 'login')

@section('content')
        <div id="login-root"></div>
@endsection

@push('scripts')
    <script src="{{ \App\Helpers\Asset::v('assets/js/modules/login.js') }}"></script>
@endpush
