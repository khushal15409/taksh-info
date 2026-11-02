@extends('layouts.app')

@section('title', 'Page not found | TakshAllInOne')
@section('page', 'notfound')

@section('content')
        <div class="text-center py-16 space-y-4">
            <div class="w-28 h-28 mx-auto bg-orange-100 rounded-full flex items-center justify-center text-5xl">🧭</div>
            <h1 class="text-2xl font-extrabold text-gray-900">Page not found</h1>
            <p class="text-sm text-gray-500 max-w-sm mx-auto">The page you're looking for doesn't exist or has moved. Let's get you back to shopping.</p>
            <a href="{{ route('home') }}" class="inline-block bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-6 py-3 rounded-2xl shadow">Back to Home</a>
        </div>
@endsection
