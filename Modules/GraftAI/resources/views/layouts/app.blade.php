<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GraftAI') — AI-Driven Farm Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-gray-100 antialiased">

{{-- Top nav --}}
<header class="sticky top-0 z-30 border-b border-white/5 bg-gray-950/80 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center gap-6 px-6 py-3">

        {{-- Logo --}}
        <a href="/" class="flex items-center gap-2 text-sm font-semibold tracking-tight">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500 text-xs font-bold text-white">G</span>
            <span class="text-white">Graft<span class="text-emerald-400">AI</span></span>
        </a>

        {{-- Nav links --}}
        <nav class="flex items-center gap-1 text-sm">
            <a href="/"
               class="rounded-md px-3 py-1.5 transition {{ request()->is('/') ? 'bg-white/10 text-white' : 'text-gray-400 hover:text-white' }}">
                Smart Automations
            </a>
            <a href="/governance"
               class="rounded-md px-3 py-1.5 transition {{ request()->is('governance') ? 'bg-white/10 text-white' : 'text-gray-400 hover:text-white' }}">
                Governance
            </a>
        </nav>

        <div class="ml-auto flex items-center gap-3">
            {{-- Tenant selector --}}
            @if(isset($tenants) && $tenants->count())
            <form method="GET" action="{{ request()->url() }}">
                <select name="tenant_id"
                        onchange="this.form.submit()"
                        class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs text-gray-200 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    @foreach($tenants as $t)
                        <option value="{{ $t->id }}" {{ isset($activeTenant) && $activeTenant->id === $t->id ? 'selected' : '' }}>
                            {{ $t->name }}
                        </option>
                    @endforeach
                </select>
            </form>
            @endif

            {{-- DSL version badge --}}
            <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-400 ring-1 ring-emerald-500/20">
                DSL {{ $dslVersion ?? '1.0' }}
            </span>
        </div>
    </div>
</header>

{{-- Page content --}}
<main class="mx-auto max-w-7xl px-6 py-8">
    @yield('content')
</main>

</body>
</html>
