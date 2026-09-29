# Kodhe View

Package view/template hasil refaktor sistem View CodeIgniter 3. Berisi `ViewFactory` yang mendukung beberapa engine template (**PHP native**, **Blade** via `eftec/bladeone`, dan **Twig**) dalam satu API, ditambah manajemen layout, tema, varian, dan aset (`AssetManager`). Namespace PSR-4: `Kodhe\Framework\View\`.

## Instalasi

```bash
composer require kodhe/view
```

Persyaratan: PHP >= 8.1. Opsional: `twig/twig` bila memakai engine Twig (Blade sudah tercakup lewat `eftec/bladeone`).

## Quick Start

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Kodhe\Framework\View\ViewFactory;

$view = new ViewFactory(['templatePath' => __DIR__ . '/app/views']);

// Render langsung ke output
echo $view->render('welcome', ['title' => 'Halo Dunia']);

// Atau kembalikan sebagai string (mis. untuk dikemas response)
$html = $view->view('welcome', ['title' => 'Halo'], return: true);
```

Dengan helper global (dimuat otomatis oleh composer `files` autoload):

```php
echo view('welcome', ['title' => 'Halo Dunia']);
```

## Struktur Direktori

```
src/
├── ViewFactory.php        # Entry point: load engine, render, layout
├── Engine/                # PhpEngine, BladeEngine, TwigEngine, EngineFactory
├── AssetManager.php       # Registrasi & rendering CSS/JS
├── ThemeManager.php       # Dukungan tema multi-look
├── VariantManager.php     # Varian template (mis. mobile/desktop)
└── helpers/               # template.php, template_asset.php
```

## Penggunaan

### 1. Layout / template induk

```php
$view->layout('layouts/app');            // set layout default
echo $view->render('pages/contact');     // konten dibungkus layout
```

### 2. Menyiapkan data bertahap

```php
$view->set('user', $currentUser);        // key–value
$view->set(['siteName' => 'Kodhe', 'year' => 2026]); // array
```

### 3. Multi-engine dalam satu proyek

```php
$view->loadEngine('blade');
echo $view->render('dashboard.blade', $data);          // Blade-style

echo $view->render('email.twig', $data, engine: 'twig'); // sekali pakai Twig
```

### 4. Mengecek keberadaan template

```php
if ($view->exists('pages/about')) {
    echo $view->render('pages/about');
}
```

## Konfigurasi (umum)

| Opsi | Tipe | Default | Keterangan |
|------|------|---------|------------|
| `templatePath` | string | `APPPATH/views` | Folder dasar template |
| `engine` | string | `php` | `php`, `blade`, atau `twig` |
| `theme` | string | `''` | Nama tema aktif (lihat `ThemeManager`) |

## Kompatibilitas CodeIgniter 3

- Gaya pemanggilan `$this->load->view($name, $data, $return)` dipertahankan melalui `render($view, $data, $return)`.
- Helper prosedural pada `src/helpers/` dimuat via composer `files` sehingga kode lama yang memanggil `view()` tetap jalan.

## Catatan & Batasan

- `src/View.php` saat ini kosong (0 byte) — gunakan `ViewFactory` sebagai entry point resmi; file tersebut kandidat untuk dihapus.
- Engine Twig tidak di-bundle; install `twig/twig` sendiri bila dibutuhkan.

## Pengujian

```bash
cd view && composer install && vendor/bin/phpunit
```

## Lisensi

MIT. Lihat [LICENSE](../LICENSE).
