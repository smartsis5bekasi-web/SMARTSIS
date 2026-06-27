<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Absensi')] class extends Component {
    //
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Absensi')" :subtitle="__('Rekap absensi siswa.')" />
</div>
