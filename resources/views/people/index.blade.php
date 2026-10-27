@extends('layouts.app')

@section('title', 'People · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>People</h1>
            <p class="hint">Adopters, fosters, vets, volunteers, staff, donors, and custom contacts. Flag banned owners and search by name, email, phone, or postcode.</p>
        </div>
        @if (auth()->user()?->role?->canManagePeople())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('people.create') }}">Add contact</a>
            </div>
        @endif
    </div>

    <form class="filters" method="get" action="{{ route('people.index') }}">
        <div>
            <label for="q">Find</label>
            <input id="q" name="q" type="text" value="{{ $q }}">
        </div>
        <div>
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->value }}" @selected($category === $cat->value)>{{ $cat->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="check" for="banned">
                <input id="banned" name="banned" type="checkbox" value="1" @checked($bannedOnly)>
                Banned only
            </label>
            <button type="submit">Search</button>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Flags</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($people as $person)
                <tr>
                    <td><a href="{{ route('people.show', $person) }}">{{ $person->name }}</a></td>
                    <td>{{ $person->categoryLabel() }}</td>
                    <td>{{ $person->email ?: '—' }}</td>
                    <td>{{ $person->phone ?: '—' }}</td>
                    <td>
                        @if ($person->banned) Banned @endif
                        @if ($person->homechecked)@if ($person->banned) · @endif Homechecked @endif
                        @if (! $person->banned && ! $person->homechecked)—@endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No people match.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $people->links() }}
@endsection
