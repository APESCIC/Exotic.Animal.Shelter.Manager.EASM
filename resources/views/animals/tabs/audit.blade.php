<p class="hint">Who changed this animal or its medical records, and when.</p>
@if ($auditEntries->isEmpty())
    <p class="hint">No audit entries yet.</p>
@else
    <table>
        <thead>
            <tr>
                <th>When</th>
                <th>Who</th>
                <th>Subject</th>
                <th>Action</th>
                <th>Changes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($auditEntries as $entry)
                <tr>
                    <td>{{ $entry->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                    <td>{{ $entry->user?->name ?: '—' }}</td>
                    <td>{{ $entry->subjectLabel() }}</td>
                    <td>{{ $entry->action }}</td>
                    <td>
                        @if ($entry->action === 'created')
                            <span class="hint">Record created</span>
                        @elseif (is_array($entry->new_values) && $entry->new_values !== [])
                            <ul class="plain-list">
                                @foreach ($entry->new_values as $field => $value)
                                    <li><code>{{ $field }}</code>: {{ is_scalar($value) || $value === null ? ($value ?? '—') : json_encode($value) }}</li>
                                @endforeach
                            </ul>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
