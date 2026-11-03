@extends('layouts.app')

@section('title', 'My Wallet | TakshAllInOne')
@section('page', 'wallet')

@section('content')
@if (! $summary)
    <x-empty-state emoji="🔐" title="Login to open your wallet" text="Please log in with your mobile number to see this page." cta-label="Login with OTP" :cta-href="route('login', ['next' => request()->getRequestUri()])" />
@else
    @php
        $n = fn ($v) => number_format((float) $v, 2) === number_format((int) $v, 2) ? number_format((int) $v) : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $pretty = fn ($s) => \Illuminate\Support\Str::of((string) $s)->replace('_', ' ')->title();
        $sources = ['order_reward' => 'Order reward', 'refund' => 'Refund', 'withdrawal' => 'Withdrawal'];
        $canWithdraw = $banks && $summary['available_points'] >= $summary['min_withdrawal_points'];
    @endphp
    <div class="max-w-5xl mx-auto space-y-6">
        <h1 class="text-2xl font-extrabold text-gray-900">My Wallet</h1>

        <section class="rounded-3xl bg-gradient-to-r from-brand-500 via-brand-600 to-red-700 text-white p-8 grid sm:grid-cols-3 gap-6 relative overflow-hidden">
            <div class="absolute -right-4 -bottom-8 text-[9rem] opacity-15 select-none">👛</div>
            <div class="relative sm:col-span-2">
                <p class="text-xs uppercase tracking-widest text-orange-100 font-bold">Available to withdraw</p>
                <p class="text-4xl font-extrabold font-festive mt-1">{{ $n($summary['available_points']) }} <span class="text-base font-bold text-orange-100">points</span></p>
                <p class="text-xs text-orange-100 mt-1">≈ @rupee2($summary['available_rupees_equivalent']) • 1 point = ₹{{ $n($summary['rupees_per_point']) }} • Minimum withdrawal {{ $summary['min_withdrawal_points'] }} points</p>
            </div>
            <div class="relative text-xs space-y-1 text-orange-50">
                <div>Balance: <b>{{ $n($summary['balance_points']) }}</b> pts</div>
                <div>Pending withdrawals: <b>{{ $n($summary['pending_withdrawal_points']) }}</b> pts</div>
                <div class="text-orange-100/90 pt-1">🎁 Earn {{ str_replace('order_total_rupees', 'order total', $summary['order_reward']['points_formula']) }} on delivered orders. {{ $summary['order_reward']['example'] }}</div>
            </div>
        </section>

        <div class="grid lg:grid-cols-2 gap-6">
            <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4">
                <h2 class="font-extrabold text-base text-gray-900">Withdraw to bank</h2>
                <form method="POST" action="{{ route('wallet.withdrawal.store') }}" data-lock class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="font-bold text-gray-500" for="w-bank">Bank account</label>
                        <select id="w-bank" name="bank_detail_id" class="field mt-1">
                            @forelse ($banks as $b)<option value="{{ $b['id'] }}" @selected((int) old('bank_detail_id') === $b['id'])>{{ $b['bank_name'] }} {{ $b['account_number'] }}</option>@empty<option value="">No bank account added</option>@endforelse
                        </select>
                    </div>
                    <div>
                        <label class="font-bold text-gray-500" for="w-points">Points to withdraw</label>
                        <input id="w-points" name="points" type="number" value="{{ old('points') }}" min="{{ $summary['min_withdrawal_points'] }}" max="{{ $summary['available_points'] }}" step="1" class="field mt-1" placeholder="Min {{ $summary['min_withdrawal_points'] }}">
                    </div>
                    <button @disabled(! $canWithdraw) class="w-full bg-brand-500 hover:bg-brand-600 disabled:opacity-50 text-white font-bold py-3 rounded-2xl transition">Request Withdrawal</button>
                </form>
                @if ($withdrawals)
                    <div class="space-y-2 pt-2">
                        <h3 class="font-bold text-xs text-gray-500 uppercase tracking-wider">Your requests</h3>
                        @foreach ($withdrawals as $w)
                            <div class="flex items-center gap-3 p-3 rounded-2xl border border-gray-200 text-xs">
                                <div class="flex-1 min-w-0">
                                    <div class="font-bold text-gray-800">{{ $n($w['points']) }} pts • @rupee2($w['amount_rupees'])</div>
                                    <div class="text-gray-400">{{ \Illuminate\Support\Carbon::parse($w['created_at'])->format('j M Y, h:i A') }}@if (! empty($w['bank_detail'])) • {{ $w['bank_detail']['bank_name'] }} {{ $w['bank_detail']['account_number'] }}@endif</div>
                                    @if ($w['rejection_reason'])<div class="text-red-500 font-semibold">{{ $w['rejection_reason'] }}</div>@endif
                                </div>
                                <x-status-badge :status="$w['status']" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4">
                <h2 class="font-extrabold text-base text-gray-900">Bank accounts</h2>
                @foreach ($banks as $b)
                    <div class="flex items-center gap-3 p-3 rounded-2xl border border-gray-200 text-xs">
                        <div class="w-9 h-9 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center"><i data-lucide="landmark" class="w-4 h-4"></i></div>
                        <div class="flex-1 min-w-0"><div class="font-bold text-gray-800">{{ $b['account_holder_name'] }}</div><div class="text-gray-400">{{ $b['bank_name'] }} • {{ $b['account_number'] }} • {{ $b['ifsc_code'] }}@if ($b['upi']) • {{ $b['upi'] }}@endif</div></div>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('wallet.bank.store') }}" data-lock class="grid grid-cols-2 gap-3 text-xs" novalidate>
                    @csrf
                    <div class="col-span-2"><label class="font-bold text-gray-500">Account holder name</label><input name="account_holder_name" value="{{ old('account_holder_name') }}" class="field mt-1" required></div>
                    <div><label class="font-bold text-gray-500">Account number</label><input name="account_number" value="{{ old('account_number') }}" inputmode="numeric" maxlength="34" class="field mt-1" required></div>
                    <div><label class="font-bold text-gray-500">Bank name</label><input name="bank_name" value="{{ old('bank_name') }}" class="field mt-1" required></div>
                    <div><label class="font-bold text-gray-500">IFSC code</label><input name="ifsc_code" value="{{ old('ifsc_code') }}" maxlength="11" class="field mt-1 uppercase" placeholder="SBIN0001234" required></div>
                    <div><label class="font-bold text-gray-500">Branch (optional)</label><input name="branch" value="{{ old('branch') }}" class="field mt-1"></div>
                    <div class="col-span-2"><label class="font-bold text-gray-500">UPI id (optional)</label><input name="upi" value="{{ old('upi') }}" class="field mt-1" placeholder="name@bank"></div>
                    <button class="col-span-2 border-2 border-brand-500 text-brand-600 hover:bg-orange-50 font-bold py-2.5 rounded-2xl transition">+ Add bank account</button>
                </form>
            </section>
        </div>

        <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3">
            <h2 class="font-extrabold text-base text-gray-900">Transaction history</h2>
            @forelse ($ledger as $t)
                @php($credit = $t['type'] === 'credit')
                <div class="flex items-center gap-3 text-xs p-3 rounded-2xl bg-orange-50/40 border border-orange-100">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center {{ $credit ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-500' }}"><i data-lucide="{{ $credit ? 'arrow-down-left' : 'arrow-up-right' }}" class="w-4 h-4"></i></span>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-gray-800">{{ $t['description'] ?: ($sources[$t['source']] ?? $pretty($t['source'])) }}</div>
                        <div class="text-gray-400">{{ \Illuminate\Support\Carbon::parse($t['created_at'])->format('j M Y, h:i A') }} • {{ $sources[$t['source']] ?? $pretty($t['source']) }}@if (! empty($t['order'])) • #{{ $t['order']['order_number'] }}@endif</div>
                    </div>
                    @if ($t['status'] !== 'completed')<x-status-badge :status="$t['status']" />@endif
                    <div class="font-extrabold {{ $credit ? 'text-emerald-600' : 'text-red-500' }}">{{ $credit ? '+' : '−' }}{{ $n($t['amount']) }}</div>
                </div>
            @empty
                <p class="text-sm text-gray-400">No transactions yet. Points you earn on delivered orders and refunds appear here.</p>
            @endforelse
            <x-pagination :paginator="$ledger" class="pt-2" />
        </section>
    </div>
@endif
@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/wallet.js') }}"></script>

@endpush
