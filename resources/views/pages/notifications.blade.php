@extends('layouts.app')

@section('title', 'Notifications | TakshAllInOne')
@section('page', 'notifications')

@section('content')

        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between"><h1 class="text-2xl font-extrabold text-gray-900">Notifications Hub</h1>
                <button id="mark-read" class="text-xs font-bold text-brand-600 hover:underline">Mark all as read</button></div>
            <div class="bg-white rounded-3xl p-6 border border-orange-100 shadow-sm space-y-4">
                <div id="notif-tabs" class="flex gap-5 border-b border-gray-100 pb-3 text-xs font-bold overflow-x-auto no-scrollbar"></div>
                <div id="notif-list" class="space-y-3"></div>
            </div>
        </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/notifications.js') }}"></script>
@endpush
