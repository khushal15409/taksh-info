@extends('layouts.app')

@section('title', 'Product Details | TakshAllInOne')
@section('page', 'product-details')

@section('content')

        <nav id="crumbs" class="text-xs text-gray-500 flex items-center gap-2 mb-5 flex-wrap" aria-label="Breadcrumb"></nav>
        <div id="product-root"></div>
        <section id="related" class="mt-10"></section>
        <section id="recent" class="mt-10"></section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/product.js') }}"></script>
@endpush
