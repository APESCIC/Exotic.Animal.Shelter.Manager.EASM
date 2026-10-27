@extends('layouts.app')

@section('title', 'Edit '.$item->name.' · '.config('app.name'))

@section('content')
    <h1>Edit stock item</h1>
    <p class="hint">To change the quantity, record a delivery, use or stocktake count on the item page.</p>

    <form method="post" action="{{ route('stock.update', $item) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('stock._form')
        <p>
            <button type="submit" class="button-primary">Save item</button>
            <a class="button" href="{{ route('stock.show', $item) }}">Cancel</a>
        </p>
    </form>
@endsection
