<div class="text-center py-8 space-y-3">
    <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center"><i data-lucide="check" class="w-10 h-10"></i></div>
    <h3 class="text-lg font-extrabold text-gray-900">Thank you!</h3>
    <p class="text-sm text-gray-500 max-w-sm mx-auto">{{ $done['message'] }}</p>
    <p class="text-xs text-gray-400">Reference ID: <b class="text-gray-700">{{ $done['ref'] }}</b></p>
    <a href="{{ route('home') }}" class="inline-block bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-6 py-3 rounded-2xl">Back to Home</a>
</div>
