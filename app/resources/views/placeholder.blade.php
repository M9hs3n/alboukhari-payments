@extends('layouts.app')

@section('content')
<div style="max-width:500px;margin:80px auto" class="page-card" style="text-align:center;padding:40px">
    <div style="font-size:60px;margin-bottom:10px">🚧</div>
    <h2 style="margin-top:0">{{ $title }}</h2>
    <p class="text-muted">{{ $message }}</p>
    <a href="{{ route('home') }}" class="btn btn-primary mt-3">← {{ __('Back to home') }}</a>
</div>
@endsection
