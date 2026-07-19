<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <nav class="flex flex-col gap-6 px-2">
                    <div class="flex flex-col gap-1">
                        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Platform') }}</p>
                        <x-ui.sidebar-item icon="grid-outline" :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            {{ __('Dashboard') }}
                        </x-ui.sidebar-item>
                        @role(\App\Enums\UserRole::WaliKelas->value)
                            <x-ui.sidebar-item icon="person-outline" :href="route('wali-kelas.students.index')" :active="request()->routeIs('wali-kelas.students*')">
                                {{ __('Siswa') }}
                            </x-ui.sidebar-item>
                        @endrole
                    </div>

                    @canany([\App\Enums\Permission::ViewAttendance->value, \App\Enums\Permission::ViewPoint->value, \App\Enums\Permission::ViewPermit->value])
                        <div class="flex flex-col gap-1">
                            <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Kehadiran') }}</p>
                            @can(\App\Enums\Permission::ViewAttendance->value)
                                <x-ui.sidebar-item icon="checkbox-outline" :href="route('attendance.absensi')" :active="request()->routeIs('attendance.absensi*')">
                                    {{ __('Absensi') }}
                                </x-ui.sidebar-item>
                            @endcan
                            @can(\App\Enums\Permission::ViewPoint->value)
                                <x-ui.sidebar-item icon="star-outline" :href="route('attendance.points')" :active="request()->routeIs('attendance.points*')">
                                    {{ __('Point') }}
                                </x-ui.sidebar-item>
                            @endcan
                            @can(\App\Enums\Permission::ViewPermit->value)
                                <x-ui.sidebar-item icon="document-text-outline" :href="route('permits.index')" :active="request()->routeIs('permits.*')">
                                    {{ __('Perizinan') }}
                                </x-ui.sidebar-item>
                            @endcan
                        </div>
                    @endcanany

                    @canany([\App\Enums\Permission::ViewAchievement->value, \App\Enums\Permission::ViewViolation->value, \App\Enums\Permission::ViewWarning->value])
                        <div class="flex flex-col gap-1">
                            <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Academic') }}</p>
                            @can(\App\Enums\Permission::ViewAchievement->value)
                                <x-ui.sidebar-item icon="trophy-outline" :href="route('academic.achievements')" :active="request()->routeIs('academic.achievements*')">
                                    {{ __('Prestasi') }}
                                </x-ui.sidebar-item>
                            @endcan
                            @can(\App\Enums\Permission::ViewViolation->value)
                                <x-ui.sidebar-item icon="warning-outline" :href="route('academic.violations')" :active="request()->routeIs('academic.violations*')">
                                    {{ __('Pelanggaran') }}
                                </x-ui.sidebar-item>
                            @endcan
                            @can(\App\Enums\Permission::ViewWarning->value)
                                <x-ui.sidebar-item icon="mail-open-outline" :href="route('warnings.index')" :active="request()->routeIs('warnings.*')">
                                    {{ __('Surat Peringatan') }}
                                </x-ui.sidebar-item>
                            @endcan
                        </div>
                    @endcanany

                    @can(\App\Enums\Permission::ManageMasterData->value)
                        <div class="flex flex-col gap-1">
                            <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Master Data') }}</p>
                            <x-ui.sidebar-item icon="calendar-outline" :href="route('master-data.academic-years')" :active="request()->routeIs('master-data.academic-years*')">
                                {{ __('Tahun Ajaran') }}
                            </x-ui.sidebar-item>
                            <x-ui.sidebar-item icon="school-outline" :href="route('master-data.majors')" :active="request()->routeIs('master-data.majors*')">
                                {{ __('Jurusan') }}
                            </x-ui.sidebar-item>
                            <x-ui.sidebar-item icon="business-outline" :href="route('master-data.classrooms')" :active="request()->routeIs('master-data.classrooms*')">
                                {{ __('Kelas') }}
                            </x-ui.sidebar-item>
                            <x-ui.sidebar-item icon="people-outline" :href="route('master-data.teachers')" :active="request()->routeIs('master-data.teachers*')">
                                {{ __('Guru') }}
                            </x-ui.sidebar-item>
                            <x-ui.sidebar-item icon="person-outline" :href="route('master-data.students.index')" :active="request()->routeIs('master-data.students*')">
                                {{ __('Siswa') }}
                            </x-ui.sidebar-item>
                        </div>
                    @endcan
                </nav>
            </flux:sidebar.nav>
        </flux:sidebar>

        <!-- Top header with the user menu -->
        <flux:header sticky class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        {{-- Renders alerts flashed via realrashid/sweet-alert's toast()/alert() helpers (see UI_STANDARDS.md). --}}
        @include('sweetalert::alert')

        @fluxScripts
    </body>
</html>
