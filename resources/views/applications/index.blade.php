@extends('layouts.app')

@section('title', 'Applications · '.config('app.name'))

@section('content')
    <h1>Applications</h1>
    <p class="hint">Public adopter, volunteer, and foster applications. Accepting an application creates a contact (Person) only.</p>

    <form class="filters" method="get" action="{{ route('applications.index') }}">
        <div>
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">All types</option>
                @foreach (\App\Enums\ApplicationType::cases() as $applicationType)
                    <option value="{{ $applicationType->value }}" @selected($type === $applicationType->value)>{{ $applicationType->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Open statuses</option>
                @foreach (\App\Enums\ApplicationStatus::cases() as $applicationStatus)
                    <option value="{{ $applicationStatus->value }}" @selected($status === $applicationStatus->value)>{{ $applicationStatus->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Filter</button>
    </form>

    <h2>Open</h2>
    @if ($openApplications->isEmpty())
        <p class="hint">No open applications.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Animal</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($openApplications as $application)
                    <tr>
                        <td><a href="{{ route('applications.show', $application) }}">{{ $application->name }}</a></td>
                        <td>{{ $application->type->label() }}</td>
                        <td>{{ $application->status->label() }}</td>
                        <td>
                            @if ($application->animal)
                                <a href="{{ route('animals.show', $application->animal) }}">{{ $application->animal->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ \App\Support\UkDate::format($application->created_at) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Recently closed</h2>
    @if ($closedApplications->isEmpty())
        <p class="hint">No accepted or rejected applications yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Person</th>
                    <th>Reviewed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($closedApplications as $application)
                    <tr>
                        <td><a href="{{ route('applications.show', $application) }}">{{ $application->name }}</a></td>
                        <td>{{ $application->type->label() }}</td>
                        <td>{{ $application->status->label() }}</td>
                        <td>
                            @if ($application->person)
                                <a href="{{ route('people.show', $application->person) }}">{{ $application->person->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $application->reviewed_at ? \App\Support\UkDate::format($application->reviewed_at) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
