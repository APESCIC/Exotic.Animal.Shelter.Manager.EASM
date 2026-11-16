@php
    /** @var \App\Support\DevLogin $devLogin */
    $devLogin = $devLogin ?? app(\App\Support\DevLogin::class);
    $returnTo = $returnTo ?? null;
@endphp
<section class="qa-panel" aria-labelledby="qa-panel-title" data-dev-login data-qa-signin-panel>
    <div class="qa-panel__header">
        <h2 id="qa-panel-title" class="qa-panel__title">Developer sign-in</h2>
        <span class="qa-panel__tag">Local only</span>
    </div>
    <p class="qa-panel__intro">
        Sign in as a fixed QA role for local Herd testing. Password
        <code>{{ \App\Support\DevLogin::PASSWORD }}</code>.
        Auto-login stays paused after signing out until you pick a role.
    </p>
    <ul class="qa-panel__roles">
        @foreach (\App\Enums\UserRole::cases() as $devRole)
            <li>
                <form method="post" action="{{ route('qa.login') }}" class="qa-panel__form">
                    @csrf
                    <input type="hidden" name="role" value="{{ $devRole->value }}">
                    @if ($returnTo)
                        <input type="hidden" name="return_to" value="{{ $returnTo }}">
                    @endif
                    <button type="submit" class="qa-panel__button">{{ $devRole->label() }}</button>
                    <span class="qa-panel__email">{{ \App\Support\DevLogin::email($devRole) }}</span>
                </form>
            </li>
        @endforeach
    </ul>
</section>
