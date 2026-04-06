<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Edit User</h1>
        </div>
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <x-input-label for="name" value="Account Name" />
                        <x-text-input id="name" name="name" class="mt-2" :value="old('name', $user->name)" required />
                    </div>

                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-2" :value="old('email', $user->email)" required />
                    </div>
                </div>

                <div class="paper-note">
                    <label class="theme-checkbox-label flex items-center gap-3 text-sm">
                        <input type="checkbox" name="is_admin" value="1" class="theme-checkbox" @checked(old('is_admin', $user->is_admin))>
                        Grant admin access
                    </label>
                </div>

                <div class="paper-note text-sm leading-6">
                    Linked character: {{ $user->character?->name ?? 'No character assigned' }}
                </div>

                <x-primary-button>Save User</x-primary-button>
            </form>
        </article>
    </div>
</x-app-layout>
