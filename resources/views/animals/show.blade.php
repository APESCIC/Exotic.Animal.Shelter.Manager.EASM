@extends('layouts.app')

@section('title', $animal->name.' · '.config('app.name'))

@section('content')
    <h1>{{ $animal->name }}</h1>
    <p class="hint">{{ $animal->species }}@if ($animal->breed_type) · {{ $animal->breed_type }}@endif</p>

    @if ($animal->primaryPhotoUrl())
        <img class="photo" src="{{ $animal->primaryPhotoUrl() }}" alt="Primary photo of {{ $animal->name }}">
    @endif

    <table>
        <tr><th>Sex</th><td>{{ $animal->sex->label() }}</td></tr>
        <tr><th>Date of birth</th><td>{{ $animal->date_of_birth ? \App\Support\UkDate::format($animal->date_of_birth) : '—' }}</td></tr>
        <tr><th>Age (years)</th><td>{{ $animal->age_years ?? '—' }}</td></tr>
        <tr><th>Colour</th><td>{{ $animal->colour ?: '—' }}</td></tr>
        <tr><th>Identifying code</th><td>{{ $animal->identifying_code ?: '—' }}</td></tr>
        <tr><th>Location</th><td>{{ $animal->location ?: '—' }}</td></tr>
        <tr><th>Enclosure</th><td>{{ $animal->enclosure ?: '—' }}</td></tr>
        <tr><th>CITES</th><td>{{ $animal->cites ?: '—' }}</td></tr>
        <tr><th>DWA</th><td>{{ $animal->dwa ?: '—' }}</td></tr>
        <tr><th>Bonded animals</th><td>{{ $animal->bonded_animals ?: '—' }}</td></tr>
        <tr><th>Entry reason</th><td>{{ $animal->entry_reason ?: '—' }}</td></tr>
        <tr><th>Flags</th><td>{{ $animal->flags ?: '—' }}</td></tr>
        <tr><th>Non-shelter</th><td>{{ $animal->non_shelter ? 'Yes' : 'No' }}</td></tr>
        <tr><th>Deceased</th><td>{{ $animal->deceased_at ? \App\Support\UkDate::format($animal->deceased_at) : '—' }}</td></tr>
        <tr><th>Death reason</th><td>{{ $animal->death_reason ?: '—' }}</td></tr>
    </table>

    <h2>Movements</h2>
    @if ($animal->movements->isEmpty())
        <p class="hint">No movements recorded yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Contact</th>
                    <th>Reason</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($animal->movements as $movement)
                    <tr>
                        <td>{{ \App\Support\UkDate::format($movement->moved_at) }}</td>
                        <td>{{ $movement->type->label() }}</td>
                        <td>
                            @if ($movement->person)
                                <a href="{{ route('people.show', $movement->person) }}">{{ $movement->person->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $movement->reason ?: '—' }}</td>
                        <td>{{ $movement->notes ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (auth()->user()?->role?->canManageMovements())
        <p><a class="button" href="{{ route('animals.movements.create', $animal) }}">Record movement</a></p>
    @endif

    <h2>Medical</h2>
    <p class="hint">Vaccinations, tests, and treatments with due / given / expiry dates. Free-text names for exotic schedules.</p>
    @if ($animal->medicalRecords->isEmpty())
        <p class="hint">No medical records yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Name</th>
                    <th>Due</th>
                    <th>Given</th>
                    <th>Expires</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($animal->medicalRecords as $record)
                    <tr>
                        <td>{{ $record->type->label() }}</td>
                        <td>{{ $record->name }}</td>
                        <td>{{ $record->due_on ? \App\Support\UkDate::format($record->due_on) : '—' }}</td>
                        <td>{{ $record->given_on ? \App\Support\UkDate::format($record->given_on) : '—' }}</td>
                        <td>{{ $record->expires_on ? \App\Support\UkDate::format($record->expires_on) : '—' }}</td>
                        <td>{{ $record->notes ?: '—' }}</td>
                        <td>
                            @if (auth()->user()?->role?->canManageMedical())
                                <a href="{{ route('medical.edit', $record) }}">Edit</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (auth()->user()?->role?->canManageMedical())
        <p><a class="button" href="{{ route('animals.medical.create', $animal) }}">Add medical record</a></p>
    @endif

    <h2>Diets</h2>
    @if ($animal->diets->isEmpty())
        <p class="hint">No diets recorded yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Started</th>
                    <th>Ended</th>
                    <th>Details</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($animal->diets as $diet)
                    <tr>
                        <td>{{ $diet->name }}</td>
                        <td>{{ $diet->started_on ? \App\Support\UkDate::format($diet->started_on) : '—' }}</td>
                        <td>{{ $diet->ended_on ? \App\Support\UkDate::format($diet->ended_on) : '—' }}</td>
                        <td>{{ $diet->details ?: '—' }}</td>
                        <td>
                            @if (auth()->user()?->role?->canManageMedical())
                                <a href="{{ route('diets.edit', $diet) }}">Edit</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (auth()->user()?->role?->canManageMedical())
        <p><a class="button" href="{{ route('animals.diets.create', $animal) }}">Add diet</a></p>
    @endif

    <h2>Observations</h2>
    @if ($animal->observations->isEmpty())
        <p class="hint">No daily observations yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>By</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($animal->observations as $observation)
                    <tr>
                        <td>{{ \App\Support\UkDate::format($observation->observed_on) }}</td>
                        <td>{{ $observation->user?->name ?: '—' }}</td>
                        <td>{{ $observation->body }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (auth()->user()?->role?->canManageMedical())
        <p><a class="button" href="{{ route('animals.observations.create', $animal) }}">Add observation</a></p>
    @endif

    <h2>Diary / tasks</h2>
    <p class="hint">Assigned staff tasks with due dates. Open tasks also appear in the <a href="{{ route('diary.index') }}">diary inbox</a>.</p>
    @if ($animal->diaryTasks->isEmpty())
        <p class="hint">No diary tasks yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Assignee</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($animal->diaryTasks as $task)
                    <tr>
                        <td>{{ $task->title }}</td>
                        <td>{{ $task->assignee?->name ?: '—' }}</td>
                        <td>{{ $task->due_on ? \App\Support\UkDate::format($task->due_on) : '—' }}</td>
                        <td>{{ $task->completed_at ? 'Done '.\App\Support\UkDate::format($task->completed_at) : 'Open' }}</td>
                        <td>
                            @if (auth()->user()?->role?->canManageDiary())
                                @if ($task->completed_at)
                                    <form method="post" action="{{ route('diary.reopen', $task) }}" style="display:inline">
                                        @csrf
                                        <button type="submit">Reopen</button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('diary.complete', $task) }}" style="display:inline">
                                        @csrf
                                        <button type="submit">Complete</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (auth()->user()?->role?->canManageDiary())
        <p><a class="button" href="{{ route('animals.diary.create', $animal) }}">Add diary task</a></p>
    @endif

    <h2>Media</h2>
    <p class="hint">Extra photos and PDFs. The primary photo above is separate.</p>
    @if ($animal->media->isEmpty())
        <p class="hint">No extra media yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Preview</th>
                    <th>Name</th>
                    <th>Kind</th>
                    <th>Uploaded by</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($animal->media as $item)
                    <tr>
                        <td>
                            @if ($item->isPhoto())
                                <a href="{{ $item->url() }}" target="_blank" rel="noopener">
                                    <img class="thumb" src="{{ $item->url() }}" alt="{{ $item->original_name }}">
                                </a>
                            @else
                                <a href="{{ $item->url() }}" target="_blank" rel="noopener">Download PDF</a>
                            @endif
                        </td>
                        <td>{{ $item->original_name }}</td>
                        <td>{{ $item->kind->label() }}</td>
                        <td>{{ $item->uploader?->name ?: '—' }}</td>
                        <td>
                            @if (auth()->user()?->role?->canManageMedia())
                                <form method="post" action="{{ route('media.destroy', $item) }}" style="display:inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (auth()->user()?->role?->canManageMedia())
        <p><a class="button" href="{{ route('animals.media.create', $animal) }}">Upload media</a></p>
    @endif

    @php
        $customValues = $animal->customFieldValues->keyBy('definition_id');
    @endphp
    @if (($customFieldDefinitions ?? collect())->isNotEmpty())
        <h2>Custom fields</h2>
        <table>
            @foreach ($customFieldDefinitions as $definition)
                @php
                    $stored = $customValues->get($definition->id)?->value;
                @endphp
                <tr>
                    <th>{{ $definition->label }}</th>
                    <td>{{ $definition->displayValue($stored) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (auth()->user()?->role?->canManageAnimals())
        <p><a class="button" href="{{ route('animals.edit', $animal) }}">Edit</a></p>
    @endif
@endsection
