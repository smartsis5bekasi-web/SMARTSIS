<?php

namespace App\Livewire\Concerns;

use App\Models\User;

trait TogglesUserActiveStatus
{
    public function toggleActive(int $userId): void
    {
        $user = User::find($userId);

        if (! $user) {
            $this->dispatch('swal', icon: 'error', title: __('Akun tidak ditemukan.'));

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);

        $this->dispatch('swal', icon: 'success', title: __(':status', [
            'status' => $user->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.',
        ]));
    }
}
