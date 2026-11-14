@extends('layouts.app')

@section('title', 'Backups · '.config('app.name'))

@section('content')
    <h1>Backups</h1>
    <p class="hint">Pure-PHP backups for the web updater: a database dump plus <code>.env</code> and storage. Files live under <code>storage/app/backups</code> (outside the public web root). Only the newest three are kept.</p>

    @if (session('status'))
        <p class="flash flash-success" role="status">{{ session('status') }}</p>
    @endif

    <section class="card">
        <h2>Create backup</h2>
        <form method="post" action="{{ route('admin.backups.store') }}">
            @csrf
            <p><button type="submit" class="button button-primary">Create backup now</button></p>
        </form>
    </section>

    <section class="card">
        <h2>Stored backups</h2>
        @if ($backups === [])
            <p class="hint">No backups yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Created</th>
                        <th>Status</th>
                        <th>Size</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($backups as $backup)
                        <tr>
                            <td><code>{{ $backup['id'] }}</code></td>
                            <td>{{ $backup['created_at'] ?? '—' }}</td>
                            <td>{{ $backup['status'] ?? '—' }}</td>
                            <td>{{ number_format($backup['bytes'] / 1024, 1) }} KiB</td>
                            <td>
                                <a href="{{ route('admin.backups.download', $backup['id']) }}">Download</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <p><a href="{{ route('admin.dashboard') }}">Back to administration</a></p>
@endsection
