<label for="type">Type</label>
<select id="type" name="type" required>
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', $payment->type?->value) === $type->value)>{{ $type->label() }}</option>
    @endforeach
</select>

<label for="amount">Amount (£)</label>
<input id="amount" type="text" name="amount" inputmode="decimal" value="{{ old('amount', $amount) }}" required maxlength="10" placeholder="25.00">

<label for="received_on">Date received</label>
<input id="received_on" type="date" name="received_on" value="{{ old('received_on', $payment->received_on?->format('Y-m-d')) }}" required>

<label for="method">Payment method</label>
<select id="method" name="method" required>
    @foreach ($methods as $method)
        <option value="{{ $method->value }}" @selected(old('method', $payment->method?->value) === $method->value)>{{ $method->label() }}</option>
    @endforeach
</select>

<label for="person_id">From (person)</label>
<select id="person_id" name="person_id">
    <option value="">— Anonymous —</option>
    @foreach ($people as $person)
        <option value="{{ $person->id }}" @selected((string) old('person_id', $payment->person_id) === (string) $person->id)>{{ $person->name }}</option>
    @endforeach
</select>

<label for="animal_id">For animal (optional)</label>
<select id="animal_id" name="animal_id">
    <option value="">— None —</option>
    @foreach ($animals as $animal)
        <option value="{{ $animal->id }}" @selected((string) old('animal_id', $payment->animal_id) === (string) $animal->id)>{{ $animal->name }} ({{ $animal->species }})</option>
    @endforeach
</select>

<label for="reference">Reference</label>
<input id="reference" type="text" name="reference" value="{{ old('reference', $payment->reference) }}" maxlength="255">
<p class="hint">Receipt number, bank reference or platform transaction ID.</p>

<label class="check" for="gift_aid_eligible">
    <input type="hidden" name="gift_aid_eligible" value="0">
    <input id="gift_aid_eligible" name="gift_aid_eligible" type="checkbox" value="1" @checked(old('gift_aid_eligible', $payment->gift_aid_eligible))>
    Gift Aid eligible
</label>
<p class="hint">Only donations and sponsorships can be claimed, and only when the donor has a Gift Aid declaration covering the date. Fees are never eligible.</p>

<label for="notes">Notes</label>
<textarea id="notes" name="notes" maxlength="5000">{{ old('notes', $payment->notes) }}</textarea>
