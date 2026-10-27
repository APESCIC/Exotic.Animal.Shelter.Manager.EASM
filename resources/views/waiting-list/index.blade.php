@extends('layouts.app')

@section('title', 'Waiting list · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Waiting list</h1>
            <p class="hint">People waiting to surrender an animal or to adopt. Most urgent first, then longest waiting.</p>
        </div>
        @if (auth()->user()?->role?->canManagePeople())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('waiting-list.create') }}">Add to waiting list</a>
            </div>
        @endif
    </div>

    <form class="filters" method="get" action="{{ route('waiting-list.index') }}">
        <div>
            <label for="q">Find</label>
            <input id="q" name="q" type="text" value="{{ $q }}" placeholder="Species, name or details">
        </div>
        <div>
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">All</option>
                @foreach ($types as $option)
                    <option value="{{ $option->value }}" @selected($type === $option)>{{ $option->shortLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                @foreach ($statuses as $option)
                    <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit">Search</button>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>Urgency</th>
                <th>Type</th>
                <th>Species</th>
                <th>Contact</th>
                <th>Added</th>
                <th>Days</th>
                <th><span class="visually-hidden">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr data-urgency="{{ $entry->urgency->value }}">
                    <td><x-badge :tone="$entry->urgency->badgeTone()">{{ $entry->urgency->label() }}</x-badge></td>
                    <td>{{ $entry->type->shortLabel() }}</td>
                    <td>
                        {{ $entry->species }}
                        @if ($entry->details)<br><span class="hint">{{ \Illuminate\Support\Str::limit($entry->details, 120) }}</span>@endif
                    </td>
                    <td>
                        @if ($entry->person)
                            <a href="{{ route('people.show', $entry->person) }}">{{ $entry->person->name }}</a>
                        @else
                            {{ $entry->contact_name }}
                            @if ($entry->contact_phone)<br><span class="hint">{{ $entry->contact_phone }}</span>@endif
                            @if ($entry->contact_email)<br><span class="hint">{{ $entry->contact_email }}</span>@endif
                        @endif
                    </td>
                    <td>{{ \App\Support\UkDate::format($entry->added_on) }}</td>
                    <td>{{ $entry->daysWaiting() }}</td>
                    <td>
                        @if (auth()->user()?->role?->canManagePeople())
                            <a href="{{ route('waiting-list.edit', $entry) }}">Edit</a>
                            @if ($entry->status === \App\Enums\WaitingListStatus::Open)
                                <form class="inline-form" method="post" action="{{ route('waiting-list.close', $entry) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="placed">
                                    <button type="submit">Placed</button>
                                </form>
                                <form class="inline-form" method="post" action="{{ route('waiting-list.close', $entry) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="removed">
                                    <button type="submit">Remove</button>
                                </form>
                            @else
                                <form class="inline-form" method="post" action="{{ route('waiting-list.reopen', $entry) }}">
                                    @csrf
                                    <button type="submit">Reopen</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Nobody is on this list.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $entries->links() }}
@endsection
