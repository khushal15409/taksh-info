@extends('layouts.app')

@section('title', 'Help & Support | TakshAllInOne')
@section('page', 'help')

@section('content')
        <div id="help-root"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/forms.js') }}"></script>
    <script src="{{ asset('assets/js/pages/help.js') }}"></script>
@endpush
