@extends('layouts.app')

@section('title', 'Lookups · '.config('app.name'))

@section('content')
    <h1>Lookups</h1>
    <p class="hint">Reusable lists for animal forms and custom fields. Values are retired, not deleted, so existing records keep their text.</p>

    @if ($lists->isEmpty())
        <p class="hint">No lookup lists yet. Run the lookup seeder to add the UK starter lists.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>List</th>
                    <th>Key</th>
                    <th>Values</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lists as $list)
                    <tr>
                        <td>{{ $list->name }}</td>
                        <td><code>{{ $list->key }}</code></td>
                        <td>{{ $list->values_count }}</td>
                        <td><a href="{{ route('admin.lookups.show', $list) }}">Manage values</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p><a href="{{ route('admin.dashboard') }}">Back to administration</a></p>
@endsection
