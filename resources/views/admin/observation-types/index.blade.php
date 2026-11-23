@extends('layouts.app')

@section('title', 'Observation types · '.config('app.name'))

@section('content')
    <h1>Observation types</h1>
    <p class="hint">Define structured daily observation fields by species. Staff and volunteers record values on the animal Log tab and in bulk entry.</p>

    @if ($types->isEmpty())
        <p class="hint">No observation types yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Kind</th>
                    <th>Order</th>
                    <th>Species filter</th>
                    <th>Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr>
                        <td>{{ $type->name }}</td>
                        <td>
                            {{ $type->kind->label() }}
                            @if ($type->lookupList)
                                <span class="hint">({{ $type->lookupList->name }})</span>
                            @endif
                        </td>
                        <td>{{ $type->sort_order }}</td>
                        <td>
                            @if (is_array($type->species_filter) && $type->species_filter !== [])
                                {{ implode(', ', $type->species_filter) }}
                            @else
                                <span class="hint">All species</span>
                            @endif
                        </td>
                        <td>{{ $type->active ? 'Yes' : 'Retired' }}</td>
                        <td><a href="{{ route('admin.observation-types.edit', $type) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p><a class="button" href="{{ route('admin.observation-types.create') }}">Add observation type</a></p>
    <p><a href="{{ route('admin.dashboard') }}">Back to administration</a></p>
@endsection