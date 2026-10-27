@php
    $role = auth()->user()?->role;
    $groups = [
        'Overview' => [
            ['label' => 'Dashboard', 'route' => 'home', 'active' => ['home']],
        ],
        'Animals' => [
            ['label' => 'Animals', 'route' => 'animals.index', 'active' => ['animals.index', 'animals.show', 'animals.create', 'animals.edit', 'animals.movements.*', 'animals.medical.*', 'animals.diets.*', 'animals.observations.*', 'animals.media.*', 'animals.diary.*', 'medical.*', 'diets.*', 'documents.*']],
            ['label' => 'Shelter view', 'route' => 'animals.shelter', 'active' => ['animals.shelter']],
            ['label' => 'Lost and found', 'route' => 'lost-found.index', 'active' => ['lost-found.*']],
        ],
        'People' => [
            ['label' => 'People', 'route' => 'people.index', 'active' => ['people.*']],
            ['label' => 'Applications', 'route' => 'applications.index', 'active' => ['applications.*']],
        ],
        'Care' => [
            ['label' => 'Diary and tasks', 'route' => 'diary.index', 'active' => ['diary.*']],
        ],
    ];

    if ($role?->isAdmin()) {
        $groups['Admin'] = [
            ['label' => 'Administration', 'route' => 'admin.dashboard', 'active' => ['admin.dashboard']],
            ['label' => 'Settings', 'route' => 'admin.settings.edit', 'active' => ['admin.settings.*']],
            ['label' => 'Custom fields', 'route' => 'admin.custom-fields.index', 'active' => ['admin.custom-fields.*']],
            ['label' => 'Document templates', 'route' => 'admin.document-templates.index', 'active' => ['admin.document-templates.*']],
        ];
    }
@endphp

<aside class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="{{ route('home') }}">
        {{ config('app.name') }}
        <small>Exotic species shelter</small>
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
    </nav>
</aside>
