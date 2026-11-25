@extends('layouts.app')

@section('title', 'Shop not found | TakshAllInOne')
@section('page', 'shops')

@section('content')
    <x-empty-state emoji="🏪" title="This shop is not available" text="It may have been removed or is not open right now. Browse the other shops instead." cta-label="All shops" :cta-href="route('shops')" />
@endsection
