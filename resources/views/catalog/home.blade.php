@extends('layouts.app')

@section('title', 'TakshAllInOne - Premium Festive E-Commerce | Groceries, Quick Delivery & Services')
@section('page', 'home')

@section('content')

    @include('catalog.home.sections')

@endsection

@push('scripts')

    <script src="{{ \App\Helpers\Asset::v('assets/js/modules/home.js') }}"></script>

@endpush
