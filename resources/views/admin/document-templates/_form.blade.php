<label for="name">Name</label>
<input id="name" type="text" name="name" value="{{ old('name', $template->name) }}" required maxlength="255">

<label for="type">Type</label>
<select id="type" name="type" required>
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', $template->type?->value) === $type->value)>
            {{ $type->label() }}
        </option>
    @endforeach
</select>

<label for="body">Template text</label>
<textarea id="body" name="body" rows="18" required maxlength="20000">{{ old('body', $template->body) }}</textarea>
<p class="hint">Plain text. Leave a blank line between paragraphs. Start a paragraph with <code>#</code> and a space for a heading. Empty values print as a line to fill in by hand.</p>

<details>
    <summary>Placeholders</summary>
    <table>
        <tbody>
            @foreach ($tokens as $token => $description)
                <tr><td><code>{{ '{'.$token.'}' }}</code></td><td>{{ $description }}</td></tr>
            @endforeach
        </tbody>
    </table>
</details>

<label class="check" for="active">
    <input id="active" name="active" type="checkbox" value="1" @checked(old('active', $template->active ?? true))>
    Active (staff can generate documents from it)
</label>
