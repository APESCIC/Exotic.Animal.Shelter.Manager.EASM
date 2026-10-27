@extends('layouts.app')

@section('title', 'Generate document · '.config('app.name'))

@section('content')
    <h1>Generate document</h1>
    <p class="hint">Fill a template with an animal and/or a person. The text is fixed when generated; signing archives it as a PDF.</p>

    @if ($templates->isEmpty())
        <p class="hint">No active templates. An admin can add them under Administration → Document templates.</p>
    @else
        <form method="post" action="{{ route('documents.store') }}" data-dirty-guard>
            @csrf

            <label for="document_template_id">Template</label>
            <select id="document_template_id" name="document_template_id" required>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}" @selected((string) old('document_template_id') === (string) $template->id)>
                        {{ $template->name }} ({{ $template->type->label() }})
                    </option>
                @endforeach
            </select>

            <label for="animal_id">Animal</label>
            <select id="animal_id" name="animal_id">
                <option value="">— None —</option>
                @foreach ($animals as $animal)
                    <option value="{{ $animal->id }}" @selected((string) old('animal_id', $selectedAnimalId) === (string) $animal->id)>
                        {{ $animal->name }} ({{ $animal->species }})
                    </option>
                @endforeach
            </select>

            <label for="person_id">Person</label>
            <select id="person_id" name="person_id">
                <option value="">— None —</option>
                @foreach ($people as $person)
                    <option value="{{ $person->id }}" @selected((string) old('person_id', $selectedPersonId) === (string) $person->id)>
                        {{ $person->name }}
                    </option>
                @endforeach
            </select>

            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" maxlength="5000">{{ old('notes') }}</textarea>
            <p class="hint">Fills the <code>{document.notes}</code> token, for example invoice lines or special conditions.</p>

            <p><button type="submit" class="button-primary">Generate</button></p>
        </form>
    @endif
@endsection
