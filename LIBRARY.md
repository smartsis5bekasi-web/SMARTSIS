# Frontend Libraries

This document is the single source of truth for the third-party frontend libraries
used across SMARTSIS. **Do not introduce alternative libraries** for these concerns —
use the ones below so the UI stays consistent.

| Concern | Library | Why |
| --- | --- | --- |
| Icons | [Ionicons](https://ionic.io/ionicons) | All icons, everywhere |
| Selects | [Slim Select](https://slimselectjs.com/) | All `<select>` need search |
| Rich text | [TinyMCE](https://www.tiny.cloud/) | Any textarea needing formatting |
| Alerts / confirms | [SweetAlert2](https://sweetalert2.github.io/) | Delete confirms + status info |

All libraries are loaded globally via CDN in `resources/views/partials/head.blade.php`.
Per-element initialization lives in the relevant Blade component/page.

> SMARTSIS is **light-mode only** (see brand palette). Configure each library for a
> light theme; do not wire up dark-mode variants.

---

## 1. Ionicons — Icons

Use Ionicons for **every** icon in the app. Do not mix in Heroicons/Lucide for new
markup (Flux components that ship their own icons are the only exception).

### Load (head.blade.php)

```html
{{-- Ionicons --}}
<script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://cdn.jsdelivr.net/npm/ionicons@7/dist/ionicons/ionicons.js"></script>
```

### Usage

```html
{{-- Solid / default --}}
<ion-icon name="trash"></ion-icon>

{{-- Outline variant --}}
<ion-icon name="create-outline"></ion-icon>

{{-- Sharp variant --}}
<ion-icon name="search-sharp"></ion-icon>
```

Browse and copy names from <https://ionic.io/ionicons>.

### Sizing & color

`ion-icon` is a web component styled with CSS — it inherits `color` and scales with
`font-size`. Use Tailwind utilities:

```html
<ion-icon name="trash" class="text-xl text-red-600"></ion-icon>
<button class="inline-flex items-center gap-2">
    <ion-icon name="add-outline" class="text-lg"></ion-icon>
    {{ __('Tambah Data') }}
</button>
```

> Tip: set `font-size` (Tailwind `text-*`) on the icon or a parent, not `width`/`height`.

---

## 2. Slim Select — Searchable selects

Every `<select>` in the app must be searchable. Use the existing
**`<x-slim-select>`** Blade component (`resources/views/components/slim-select.blade.php`)
rather than initializing SlimSelect by hand — it already handles Livewire entanglement,
`wire:ignore`, placeholders, and edit-form value restoration.

### Load (already in head.blade.php)

```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slim-select/2.5.0/slimselect.min.css" ... />
<script src="https://cdnjs.cloudflare.com/ajax/libs/slim-select/2.5.0/slimselect.min.js" defer></script>
```

### Usage — the component

Use it exactly like a native `<select>`:

```blade
{{-- Create form: pass a placeholder so nothing is preselected --}}
<x-slim-select wire:model="role" placeholder="{{ __('Pilih peran') }}">
    <option value="">{{ __('Pilih peran') }}</option>
    @foreach ($roles as $role)
        <option value="{{ $role->id }}">{{ $role->name }}</option>
    @endforeach
</x-slim-select>

{{-- Edit form: omit placeholder, the saved value is restored automatically --}}
<x-slim-select wire:model="classroom_id">
    @foreach ($classrooms as $classroom)
        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
    @endforeach
</x-slim-select>
```

### Behavior notes

- `wire:ignore` + `@entangle` keep Livewire from morphing SlimSelect's injected DOM
  while still syncing the value back to the Livewire property (honors `.live`/`.defer`).
- With a `placeholder`: the empty `<option value="">` becomes the SlimSelect placeholder.
- Without a `placeholder`: the empty option is removed and the first real option is
  auto-selected.
- `allowDeselect` is `false` by default.

If you ever need a raw SlimSelect (no Livewire), initialize inside an Alpine `init()`
mirroring the component, and always wrap the host element in `wire:ignore` when inside
a Livewire view.

---

## 3. TinyMCE — Rich text editor

Use TinyMCE for any textarea that needs formatting (descriptions, announcements,
article bodies, etc.).

### Load (head.blade.php)

```html
{{-- TinyMCE --}}
<script src="https://cdn.tiny.cloud/1/mgnx3lcm1bg1v85bmqfw3ogmz9vjtdxolbcs3pmx800uia9e/tinymce/6/tinymce.min.js"
        referrerpolicy="origin"></script>
```

> The API key above is the project's TinyMCE Cloud key. Keep using it; don't swap in
> another key or a self-hosted build without approval.

### Init

Target a class (not the bare `textarea` selector) so you only enhance editors you mean
to. Initialize on the page/component that owns the field:

```html
<script>
    tinymce.init({
        selector: 'textarea.rich-text',
        height: 500,
        menubar: false,
        plugins: [
            'advlist autolink lists link image charmap print preview anchor textcolor',
            'searchreplace visualblocks code fullscreen',
            'insertdatetime media table contextmenu paste code help wordcount',
            'image'
        ],
        toolbar:
            'insertfile undo redo | image | formatselect | bold italic | ' +
            'alignleft aligncenter alignright alignjustify | ' +
            'bullist numlist outdent indent | removeformat | help',
        content_css: [
            '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i',
            '//www.tiny.cloud/css/codepen.min.css'
        ],
    });
</script>
```

```blade
<textarea class="rich-text" name="content">{!! old('content', $model->content ?? '') !!}</textarea>
```

### Livewire integration

TinyMCE replaces the textarea with its own iframe, so wrap it in `wire:ignore` and push
the content back to Livewire on change instead of relying on `wire:model`:

```blade
<div wire:ignore>
    <textarea id="content-editor" class="rich-text">{!! $content !!}</textarea>
</div>
```

```js
tinymce.init({
    selector: '#content-editor',
    // ...config above...
    setup(editor) {
        editor.on('change keyup', () => {
            // @this is the Livewire component (Livewire v4)
            window.Livewire.find(editor.targetElm.closest('[wire\\:id]').getAttribute('wire:id'))
                .set('content', editor.getContent());
        });
    },
});
```

> Remember to destroy/re-init editors when Livewire re-renders the region (e.g. on a
> `livewire:navigated` listener) to avoid duplicate instances.

---

## 4. SweetAlert2 — Confirmations & status alerts

Use SweetAlert2 for **delete confirmations** ("Apakah Anda yakin?"), update/save
confirmations, and status/info feedback (success / error toasts). Do not use the native
`confirm()`/`alert()`.

### Load (head.blade.php)

```html
{{-- SweetAlert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
```

### Delete confirmation (Livewire)

Confirm in the browser, then call the Livewire action only if the user accepts:

```blade
<button type="button"
    onclick="confirmDelete({{ $row->id }})"
    class="inline-flex items-center gap-1 text-red-600">
    <ion-icon name="trash-outline"></ion-icon>
    {{ __('Hapus') }}
</button>

<script>
    function confirmDelete(id) {
        Swal.fire({
            title: '{{ __('Apakah Anda yakin?') }}',
            text: '{{ __('Data yang dihapus tidak dapat dikembalikan.') }}',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7c3aed', // brand violet
            cancelButtonColor: '#6b7280',
            confirmButtonText: '{{ __('Ya, hapus') }}',
            cancelButtonText: '{{ __('Batal') }}',
        }).then((result) => {
            if (result.isConfirmed) {
                @this.call('delete', id);
            }
        });
    }
</script>
```

### Status feedback from Livewire

Dispatch a browser event from the component and show a toast:

```php
// In the Livewire component, after saving/deleting:
$this->dispatch('swal', [
    'icon' => 'success',
    'title' => __('Data berhasil disimpan'),
]);
```

```js
// Registered once (e.g. in app.js or a layout script):
window.addEventListener('swal', (event) => {
    const { icon = 'success', title } = event.detail[0] ?? event.detail;
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
});
```

### Conventions

- Confirm button uses the brand violet (`#7c3aed`); cancel uses neutral gray.
- All user-facing strings go through `__()` for translation.
- Success/error feedback uses **toast** mode (top-end, auto-dismiss); destructive
  confirms use the centered modal with `showCancelButton: true`.

---

## Where to add CDN tags

All `<script>`/`<link>` tags above belong in
`resources/views/partials/head.blade.php`, alongside the existing SlimSelect tags, so
every page gets them. Per-element `tinymce.init(...)` and `Swal.fire(...)` calls live in
the specific page/component that uses them.
