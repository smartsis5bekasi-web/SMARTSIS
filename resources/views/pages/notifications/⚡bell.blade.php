<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The topbar notification bell (web notifications, `database` channel).
 *
 * Every notification in the app writes the same payload shape — title, body,
 * icon, url — so this renders any of them without knowing which one it is.
 * See {@see App\Notifications\DailyAttendanceReminder}.
 */
new class extends Component {
    /** How many rows the dropdown shows before pointing at the full list. */
    private const VISIBLE = 8;

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): Collection
    {
        return Auth::user()
            ->notifications()
            ->latest()
            ->limit(self::VISIBLE)
            ->get();
    }

    /**
     * Mark one notification read and follow it to wherever it points.
     *
     * The URL comes from our own notification payloads, but it is still data
     * from the database, so anything pointing off-site is ignored rather than
     * followed.
     */
    public function openNotification(string $id): void
    {
        $notification = Auth::user()->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();
        unset($this->unreadCount, $this->notifications);

        $url = (string) ($notification->data['url'] ?? '');

        if ($url !== '' && str_starts_with($url, config('app.url'))) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();

        unset($this->unreadCount, $this->notifications);
    }
}; ?>

<div
    class="relative"
    x-data="{ showPanel: false }"
    x-on:keydown.escape.window="showPanel = false"
    x-on:click.outside="showPanel = false"
    wire:poll.60s
>
    <button
        type="button"
        x-on:click="showPanel = ! showPanel"
        class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-200/60 hover:text-zinc-800"
        x-bind:aria-expanded="showPanel"
        aria-haspopup="true"
        title="{{ __('Notifikasi') }}"
    >
        <span wire:ignore class="inline-flex">
            <ion-icon name="notifications-outline" class="text-xl"></ion-icon>
        </span>

        @if ($this->unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
            <span class="sr-only">{{ __(':count notifikasi belum dibaca', ['count' => $this->unreadCount]) }}</span>
        @endif
    </button>

    <div
        x-show="showPanel"
        x-transition.opacity
        style="display: none"
        class="absolute end-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-lg"
    >
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <p class="text-sm font-semibold text-gray-900">{{ __('Notifikasi') }}</p>

            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-primary-600 transition hover:underline">
                    {{ __('Tandai semua dibaca') }}
                </button>
            @endif
        </div>

        <div class="max-h-96 divide-y divide-gray-100 overflow-y-auto">
            @forelse ($this->notifications as $notification)
                <button
                    type="button"
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="openNotification('{{ $notification->id }}')"
                    class="flex w-full items-start gap-3 px-4 py-3 text-start transition hover:bg-gray-50 {{ $notification->read_at === null ? 'bg-primary-50/50' : '' }}"
                >
                    <span wire:ignore class="mt-0.5 inline-flex text-primary-600">
                        <ion-icon name="{{ $notification->data['icon'] ?? 'notifications-outline' }}" class="text-lg"></ion-icon>
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-gray-900">
                            {{ $notification->data['title'] ?? __('Notifikasi') }}
                        </span>
                        <span class="mt-0.5 block text-xs text-gray-600">
                            {{ $notification->data['body'] ?? '' }}
                        </span>
                        <span class="mt-1 block text-[11px] text-gray-400">
                            {{ $notification->created_at?->locale('id')->diffForHumans() }}
                        </span>
                    </span>

                    @if ($notification->read_at === null)
                        <span class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full bg-primary-600"></span>
                    @endif
                </button>
            @empty
                <p class="px-4 py-10 text-center text-sm text-gray-400">{{ __('Belum ada notifikasi.') }}</p>
            @endforelse
        </div>
    </div>
</div>
