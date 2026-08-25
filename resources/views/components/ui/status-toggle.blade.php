@props(['user', 'method' => 'toggleActive'])

@if ($user)
    @php
        $action = $user->is_active ? 'nonaktifkan' : 'aktifkan';
    @endphp
    <button
        type="button"
        x-data
        @click="confirmDelete(() => $wire.{{ $method }}({{ $user->id }}), {{ Illuminate\Support\Js::from([
            'title' => __('Yakin ingin :action akun ini?', ['action' => $action]),  'text' => '',
        ]) }})"
        class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $user->is_active ? 'bg-primary-600' : 'bg-gray-300' }}"
        title="{{ $user->is_active ? __('Aktif') : __('Nonaktif') }}"
    >
        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $user->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
    </button>
@else
    <span class="text-xs text-gray-400" title="{{ __('Siswa belum punya akun login') }}">—</span>
@endif