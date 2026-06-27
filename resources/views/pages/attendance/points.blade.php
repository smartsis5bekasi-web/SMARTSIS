<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Point')] class extends Component {
    //
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Point')" :subtitle="__('Rekap poin disiplin siswa.')" />
</div>
