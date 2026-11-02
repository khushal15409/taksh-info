@extends('layouts.app')

@section('title', 'My Account | TakshAllInOne')
@section('page', 'profile')

@section('content')
        <div id="profile-root"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/profile.js') }}"></script>
@endpush
