<form class="filters" method="get" action="{{ $action }}">
    <div>
        <label for="from">From</label>
        <input id="from" name="from" type="date" value="{{ $from->toDateString() }}" required>
    </div>
    <div>
        <label for="to">To</label>
        <input id="to" name="to" type="date" value="{{ $to->toDateString() }}" required>
    </div>
    <div>
        <button type="submit">Show</button>
    </div>
</form>
