# UI Standards

The single source of truth for **how we build screens** in SMARTSIS. Pair this
with `LIBRARY.md` (which covers the third-party libs themselves). These rules
apply to **all new development**; existing master-data pages already follow them
and are the reference implementation.

> Stack note: our reference look comes from a vanilla-Laravel project, but
> SMARTSIS is **Livewire 4** (SFC Volt pages under `resources/views/pages/**`).
> Copy the *markup/layout*, but the behavior is always Livewire — no `<form
> method=POST>` controllers, no full-page reloads. Use `wire:model`,
> `wire:submit`, computed properties, and `$this->redirectRoute(..., navigate: true)`.

---

## 1. No Flux UI for new markup

We are **off Flux** for page content. Build tables, forms, buttons, badges,
headings, and the sidebar nav with **pure Tailwind** utilities.

- The Flux *app shell* (`flux:sidebar` container, `flux:header`, user dropdown)
  is the only remaining exception — it's interactive chrome, not page content.
- Brand colors live in `resources/css/app.css`: `primary-*` (violet, brand) and
  `secondary-*` (yellow). Use `primary-600` for primary actions.
- Light mode only — never add `dark:` variants.

## 2. Icons — Ionicons only

Every icon is an `<ion-icon>` (loaded globally in `partials/head.blade.php`).
Size/color with Tailwind (`text-xl`, `text-primary-600`), never `width/height`.
Do **not** use Flux icons or Heroicons in new markup.

Common names: `add-outline` (create), `create-outline` (edit), `trash-outline`
(delete), `arrow-back-outline` (back), `checkmark-circle-outline`,
`grid-outline`, `calendar-outline`, `school-outline`, `business-outline`,
`people-outline`.

## 3. Selects — Slim Select

Every `<select>` uses `<x-slim-select>` (searchable, Livewire-entangled). See
`LIBRARY.md` §2. Pass a `placeholder` on create forms; omit it on edit forms so
the saved value restores.

## 4. Create & Edit are pages, never modals

CRUD create/update is a **dedicated page**, not a modal. Each entity gets a
folder named after its context, with files named by action:

```
resources/views/pages/master-data/teachers.blade.php          # index (list/table)
resources/views/pages/master-data/teachers/create.blade.php   # create page
resources/views/pages/master-data/teachers/edit.blade.php     # edit page
```

Route names follow the same shape: `master-data.teachers`,
`master-data.teachers.create`, `master-data.teachers.edit`.

## 5. Delete — always SweetAlert first

Never delete without a SweetAlert confirm. Use the `<x-ui.delete-button>`
component in table rows — it confirms, then calls the Livewire method only on
confirm:

```blade
<x-ui.delete-button :wire-id="$teacher->id"
    :text="__('Akun login terkait juga akan dihapus.')" />
```

Under the hood it calls `window.confirmDelete()` (`resources/js/app.js`) →
`$wire.delete(id)`.

## 6. Status feedback — SweetAlert toast

No `flux:toast`. Report success/errors via SweetAlert toasts.

- **Stay-on-page** action (e.g. delete on an index): dispatch a browser event.
  ```php
  $this->dispatch('swal', icon: 'success', title: __('Data dihapus.'));
  $this->dispatch('swal', icon: 'error', title: __('Tidak dapat dihapus.'));
  ```
- **Redirect** action (create/edit save): flash to the session, then redirect.
  The layout bridges the flash to a toast on the next page.
  ```php
  session()->flash('swal', ['icon' => 'success', 'title' => __('Tersimpan.')]);
  $this->redirectRoute('master-data.teachers', navigate: true);
  ```

The toast listener and `confirmDelete` helper live in `resources/js/app.js`; the
flash bridge lives in `layouts/app/sidebar.blade.php`.

## 7. Sidebar active state spans a whole section

A nav item stays highlighted across that section's index/create/edit pages. Use
a **wildcard** route check via `<x-ui.sidebar-item>`:

```blade
<x-ui.sidebar-item icon="people-outline"
    :href="route('master-data.teachers')"
    :active="request()->routeIs('master-data.teachers*')">
    {{ __('Guru') }}
</x-ui.sidebar-item>
```

## 8. Reusable components

Prefer these before hand-rolling markup (`resources/views/components/ui/`):

| Component | Purpose |
| --- | --- |
| `<x-ui.button>` | Buttons & button-links. `variant`: `primary` / `secondary` / `danger`; optional `icon` / `iconTrailing` (Ionicon names); `href` makes it an `<a>`. |
| `<x-ui.page-header>` | Page title + subtitle, with an `actions` slot for buttons. |
| `<x-ui.delete-button>` | SweetAlert delete action for table rows. |
| `<x-ui.sidebar-item>` | Sidebar nav link with Ionicon + active state. |

## 9. Standard table markup

White card wrapper, pure-Tailwind table, Ionicon row actions right-aligned,
`->links()` for pagination. See any `master-data/*.blade.php` index page as the
canonical example. All user-facing strings go through `__()`.
