@extends('layouts.app')

@section('title', $list->name.' · Lookups · '.config('app.name'))

@section('content')
    <h1>{{ $list->name }}</h1>
    @if ($list->description)
        <p class="hint">{{ $list->description }}</p>
    @endif
    <p class="hint">Retired values stay hidden on new forms but remain visible on records that already use them.</p>

    @if ($values->isEmpty())
        <p class="hint">No values in this list yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Order</th>
                    <th>Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($values as $value)
                    <tr>
                        <td>
                            {{ $value->name }}
                            @if ($value->description)
                                <span class="hint"> — {{ $value->description }}</span>
                            @endif
                        </td>
                        <td>{{ $value->sort_order }}</td>
                        <td>{{ $value->active ? 'Yes' : 'Retired' }}</td>
                        <td><a href="{{ route('admin.lookups.values.edit', [$list, $value]) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p>
        <a class="button" href="{{ route('admin.lookups.values.create', $list) }}">Add value</a>
        <a class="button" href="{{ route('admin.lookups.index') }}">All lists</a>
    </p>
@endsection
