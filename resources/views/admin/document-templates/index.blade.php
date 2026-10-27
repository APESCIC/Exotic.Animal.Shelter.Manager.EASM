@extends('layouts.app')

@section('title', 'Document templates · '.config('app.name'))

@section('content')
    <h1>Document templates</h1>
    <p class="hint">Templates staff use to generate adoption contracts, invoices and enclosure cards. The defaults are a starting point: check contract wording with your own legal adviser before use.</p>

    @if ($templates->isEmpty())
        <p class="hint">No templates yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($templates as $template)
                    <tr>
                        <td>{{ $template->name }}</td>
                        <td>{{ $template->type->label() }}</td>
                        <td>{{ $template->active ? 'Yes' : 'No' }}</td>
                        <td><a href="{{ route('admin.document-templates.edit', $template) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p><a class="button" href="{{ route('admin.document-templates.create') }}">Add template</a></p>
    <p><a href="{{ route('admin.dashboard') }}">Back to administration</a></p>
@endsection
