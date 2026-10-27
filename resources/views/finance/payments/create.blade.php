@extends('layouts.app')

@section('title', 'Record payment · '.config('app.name'))

@section('content')
    <h1>Record payment</h1>

    <form method="post" action="{{ route('payments.store') }}" data-dirty-guard>
        @csrf
        @include('finance.payments._form')
        <p>
            <button type="submit" class="button-primary">Save payment</button>
            <a class="button" href="{{ route('finance.index') }}">Cancel</a>
        </p>
    </form>
@endsection
