@inject('devLogin', 'App\Support\DevLogin')
@php
    $role = auth()->user()?->role;
    $groups = [
        'Overview' => [
            ['label' => 'Dashboard', 'route' => 'home', 'active' => ['home']],
        ],
        'Animals' => [
            ['label' => 'Animals', 'route' => 'animals.index', 'active' => ['animals.index', 'animals.show', 'animals.create', 'animals.edit', 'animals.movements.*', 'animals.medical.*', 'animals.diets.*', 'animals.observations.*', 'animals.media.*', 'animals.diary.*', 'medical.*', 'diets.*', 'documents.*']],
            ['label' => 'Shelter view', 'route' => 'animals.shelter', 'active' => ['animals.shelter']],
            ['label' => 'Enclosures', 'route' => 'enclosures.index', 'active' => ['enclosures.*']],
            ['label' => 'Lost and found', 'route' => 'lost-found.index', 'active' => ['lost-found.*']],
            ['label' => 'Animal control', 'route' => 'animal-control.index', 'active' => ['animal-control.*']],
        ],
        'People' => [
            ['label' => 'People', 'route' => 'people.index', 'active' => ['people.*']],
            ['label' => 'Applications', 'route' => 'applications.index', 'active' => ['applications.*']],
            ['label' => 'Waiting list', 'route' => 'waiting-list.index', 'active' => ['waiting-list.*']],
        ],
        'Care' => [
            ['label' => 'Mobile tasks', 'route' => 'mobile.index', 'active' => ['mobile.*']],
            ['label' => 'Diary and tasks', 'route' => 'diary.index', 'active' => ['diary.*']],
            ['label' => 'Rota', 'route' => 'rota.index', 'active' => ['rota.*']],
            ['label' => 'Stock', 'route' => 'stock.index', 'active' => ['stock.*']],
        ],
    ];

    $groups['Compliance'] = [
        ['label' => 'DWA licences', 'route' => 'compliance.dwa.index', 'active' => ['compliance.dwa.*']],
    ];

    if ($role?->canViewCompliance()) {
        array_unshift($groups['Compliance'], ['label' => 'CITES register', 'route' => 'compliance.cites.index', 'active' => ['compliance.cites.*']]);
    }

    if ($role?->canViewReports()) {
        $groups['Reports'] = [
            ['label' => 'Reports', 'route' => 'reports.index', 'active' => ['reports.*']],
        ];
    }

    if ($role?->canViewFinance()) {
        $groups['Finance'] = [
            ['label' => 'Ledger', 'route' => 'finance.index', 'active' => ['finance.*', 'payments.*', 'sponsorships.*']],
        ];
    }

    if ($role?->isAdmin()) {
        $groups['Admin'] = [
            ['label' => 'Administration', 'route' => 'admin.dashboard', 'active' => ['admin.dashboard']],
            ['label' => 'Settings', 'route' => 'admin.settings.edit', 'active' => ['admin.settings.*']],
            ['label' => 'Custom fields', 'route' => 'admin.custom-fields.index', 'active' => ['admin.custom-fields.*']],
            ['label' => 'Document templates', 'route' => 'admin.document-templates.index', 'active' => ['admin.document-templates.*']],
            ['label' => 'Partner feed', 'route' => 'admin.publishing.edit', 'active' => ['admin.publishing.*']],
            ['label' => 'Backups', 'route' => 'admin.backups.index', 'active' => ['admin.backups.*']],
            ['label' => 'Update', 'route' => 'admin.update.index', 'active' => ['admin.update.*']],
        ];
    }
@endphp

<aside class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="{{ route('home') }}">
        <img class="sidebar-brand-icon" src="{{ asset('images/pwa/easm.svg') }}" alt="EASM" width="36" height="36">
        <span>
            {{ config('app.name') }}
            <small>Exotic species shelter</small>
        </span>
    </a>

    <nav aria-label="Main">
        @foreach ($groups as $title => $items)
            <div class="nav-group">
                <p class="nav-group-title">{{ $title }}</p>
                <ul>
                    @foreach ($items as $item)
                        <li>
                            <a class="nav-link" href="{{ route($item['route']) }}"@if (request()->routeIs(...$item['active'])) aria-current="page"@endif>{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        <div class="nav-group">
            <p class="nav-group-title">Public site</p>
            <ul>
                <li><a class="nav-link" href="{{ route('public.adopt.index') }}">Adoptable animals</a></li>
                <li><a class="nav-link" href="{{ route('public.apply.index') }}">Public applications</a></li>
            </ul>
        </div>

        @if ($devLogin->enabled())
            <div class="nav-group" data-dev-login data-qa-switcher>
                <p class="nav-group-title">Developer QA</p>
                <ul>
                    @foreach (\App\Enums\UserRole::cases() as $devRole)
                        <li>
                            <form method="post" action="{{ route('qa.login') }}" class="qa-sidebar-form">
                                @csrf
                                <input type="hidden" name="role" value="{{ $devRole->value }}">
                                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                <button type="submit" class="nav-link qa-sidebar-button"@if ($role === $devRole) aria-current="page"@endif>{{ $devRole->label() }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </nav>
</aside>
