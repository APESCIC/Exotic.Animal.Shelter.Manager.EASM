@extends('layouts.app')

@section('title', 'Custom fields · '.config('app.name'))

@section('content')
    <h1>Custom fields</h1>
    <p class="hint">Define extra husbandry fields for animal records. Values are filled on create/edit animal — no core schema change.</p>

    @if ($definitions->isEmpty())
        <p class="hint">No custom fields yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Slug</th>
                    <th>Type</th>
                    <th>Order</th>
                    <th>Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($definitions as $definition)
                    <tr>
                        <td>{{ $definition->label }}</td>
                        <td><code>{{ $definition->slug }}</code></td>
                        <td>{{ $definition->type->label() }}</td>
                        <td>{{ $definition->sort_order }}</td>
                        <td>{{ $definition->active ? 'Yes' : 'No' }}</td>
                        <td><a href="{{ route('admin.custom-fields.edit', $definition) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p><a class="button" href="{{ route('admin.custom-fields.create') }}">Add custom field</a></p>
    <p><a href="{{ route('admin.dashboard') }}">Back to administration</a></p>
@endsection
