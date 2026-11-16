@inject('devLogin', 'App\Support\DevLogin')
@auth
    @if ($devLogin->enabled() && $devLogin->isQaUser(auth()->user()))
        <div class="qa-banner" role="status" data-qa-banner>
            <div class="qa-banner__text">
                Local QA — signed in as <strong>{{ auth()->user()->role->label() }}</strong>
                <span class="qa-banner__email">({{ auth()->user()->email }})</span>
            </div>
            <div class="qa-banner__switcher" data-qa-switcher>
                <span class="qa-banner__switcher-label">Switch role</span>
                @foreach (\App\Enums\UserRole::cases() as $devRole)
                    <form method="post" action="{{ route('qa.login') }}" class="qa-banner__form">
                        @csrf
                        <input type="hidden" name="role" value="{{ $devRole->value }}">
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                        <button type="submit"
                                class="qa-banner__role{{ auth()->user()->role === $devRole ? ' is-current' : '' }}"
                                @if (auth()->user()->role === $devRole) aria-current="true" @endif>
                            {{ $devRole->label() }}
                        </button>
                    </form>
                @endforeach
                <form method="post" action="{{ route('qa.logout') }}" class="qa-banner__form">
                    @csrf
                    <button type="submit" class="qa-banner__signout">Sign out of QA</button>
                </form>
            </div>
        </div>
    @endif
@endauth
