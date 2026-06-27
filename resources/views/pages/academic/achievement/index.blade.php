<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Prestasi')] class extends Component {
    //
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Prestasis')" :subtitle="__('Daftar prestasi siswa.')" />
</div>
