<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pelanggaran')] class extends Component {
    //
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Pelanggaran')" :subtitle="__('Daftar pelanggaran siswa.')" />
</div>
