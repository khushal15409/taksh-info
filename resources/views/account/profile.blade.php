@extends('layouts.app')

@section('title', 'My Account | TakshAllInOne')
@section('page', 'profile')

@section('content')

    @if (! $user)
        <x-empty-state emoji="🔐" title="Login to view your account" text="Log in with your mobile number to manage your profile, addresses and orders."
            cta-label="Login with OTP" :cta-href="route('login', ['next' => request()->getRequestUri()])" />
    @else
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            @include('account.partials.sidebar')
            <div class="lg:col-span-3 bg-white p-6 lg:p-8 rounded-3xl border border-orange-100 shadow-sm space-y-6">
                @if ($tab === 'profile')
                    @include('account.partials.profile-form')
                @elseif ($tab === 'addresses')
                    @include('account.partials.addresses')
                @elseif ($tab === 'orders')
                    @include('account.partials.orders')
                @else
                    @include('account.partials.returns')
                @endif
            </div>
        </div>
    @endif

@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/account.js') }}"></script>

@endpush
