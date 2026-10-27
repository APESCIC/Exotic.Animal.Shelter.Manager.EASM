@extends('layouts.app')

@section('title', 'Edit payment · '.config('app.name'))

@section('content')
    <h1>Edit payment</h1>

    <form method="post" action="{{ route('payments.update', $payment) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('finance.payments._form')
        <p>
            <button type="submit" class="button-primary">Save payment</button>
            <a class="button" href="{{ route('finance.index') }}">Cancel</a>
        </p>
    </form>
@endsection
