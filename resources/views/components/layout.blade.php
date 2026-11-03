{{-- Page layout for module views: <x-layout title="Orders" page="orders"> ...content... </x-layout>. Same shell as layouts/app.blade.php. --}}
@props(['title' => 'TakshAllInOne', 'page' => 'home'])
@extends('layouts.app')

@section('title', $title)
@section('page', $page)

@section('content')
    {{ $slot }}
@endsection
