@extends('layouts.app')

@section('title', $title . ' | TakshAllInOne')
@section('page', $page)

@section('content')
        <div id="form-root"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/forms.js') }}"></script>
@endpush
