# Panduan Update UI Admin dengan Template Baru

## Yang Sudah Dikerjakan ✅

### 1. **CSS & Assets**
- ✅ Copy `newadmin.css` dari template ke `public/css/`
- ✅ CSS menggunakan warna primary: `#075E54` (hijau teal)
- ✅ Mendukung dark mode dengan `data-bs-theme`
- ✅ Collapsible sidebar (desktop) dan mobile responsive

### 2. **Template Components**
File baru yang dibuat:
- ✅ `_header_new.php` - Navbar dengan toggle sidebar, dark mode, notifications, user dropdown
- ✅ `_sidebar_new.php` - Sidebar collapsible dengan role-based menu (Admin, Kasir, Dapur)
- ✅ `_scripts_new.php` - JavaScript untuk sidebar toggle, dark mode, Select2, auto-dismiss alerts

### 3. **Halaman yang Sudah Diupdate**
- ✅ **index.php** (Dashboard) - Dashboard dengan stat cards modern, info cards, aktivitas
- ✅ **kategori_menu.php** (CRUD Example) - Table modern dengan search, filter, modal add/edit

### 4. **Fitur Baru**
- ✅ **Dark Mode Toggle** - Switch antara light/dark theme dengan local storage
- ✅ **Collapsible Sidebar** - Sidebar bisa collapsed untuk space lebih luas
- ✅ **Select2 Integration** - Dropdown searchable (ready untuk form yang kompleks)
- ✅ **Modern Table Design** - Table tanpa border dengan hover effect
- ✅ **Stat Cards** - Cards untuk menampilkan statistik dengan gradient
- ✅ **Auto-dismiss Alerts** - Alert otomatis hilang setelah 5 detik

## Cara Update Halaman Lain

### Struktur HTML Baru
```php
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Judul Halaman - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="../css/newadmin.css" rel="stylesheet">
</head>
<body>
    <?php include '_header_new.php'; ?>
    
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <!-- Content disini -->
        </div>
    </main>

    <?php include '_scripts_new.php'; ?>
</body>
</html>
```

### Class CSS Penting

#### Cards
```html
<!-- Modern Card -->
<div class="card-modern">
    <div class="card-header">
        <i class="bi bi-icon me-2"></i>Header Title
    </div>
    <div class="card-body">
        Content here
    </div>
</div>

<!-- Stat Card -->
<div class="card-stat">
    <div class="d-flex align-items-center">
        <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <i class="bi bi-icon"></i>
        </div>
        <div class="ms-3 flex-grow-1">
            <h6 class="text-muted mb-1">Label</h6>
            <h3>Value</h3>
        </div>
    </div>
</div>
```

#### Tables
```html
<!-- STRUKTUR YANG BENAR - Menggunakan card-modern -->
<div class="card-modern">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <i class="bi bi-table me-2"></i>
            <span>Table Title</span>
        </div>
        <div class="d-flex gap-2">
            <!-- Search & Filter -->
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Column 1</th>
                        <th>Column 2</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Rows here -->
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-light border-top py-3 px-4">
        <!-- Pagination -->
    </div>
</div>
```

**⚠️ PENTING:** 
- Gunakan `card-modern` BUKAN `table-modern`
- Harus ada `card-body p-0` wrapper untuk table
- Footer menggunakan `card-footer bg-light border-top py-3 px-4`
- Ini akan memberikan header hijau gradient dengan padding yang benar

#### Forms
```html
<form class="form-modern">
    <div class="mb-3">
        <label class="form-label">Label <span class="text-danger">*</span></label>
        <input type="text" class="form-control" required>
    </div>
    
    <!-- Select2 Dropdown -->
    <div class="mb-3">
        <label class="form-label">Select</label>
        <select class="form-select select2-search">
            <option value="">-- Pilih --</option>
            <option value="1">Option 1</option>
        </select>
    </div>
    
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-save me-2"></i>Simpan
    </button>
</form>
```

#### Alerts
```html
<!-- Success -->
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>Success message
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<!-- Error -->
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>Error message
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

## Halaman yang Perlu Diupdate

Masih menggunakan template lama (_header.php & _sidebar.php):
- [ ] bahan.php
- [ ] menu.php
- [ ] resto.php
- [ ] pegawai.php
- [ ] konsumen.php
- [ ] vendor.php
- [ ] meja.php
- [ ] metode_pembayaran.php
- [ ] kategori_bahan.php
- [ ] resep.php
- [ ] pembelian.php
- [ ] pembayaran_pembelian.php
- [ ] shift_kasir.php
- [ ] promo.php
- [ ] biaya_lain.php
- [ ] harga_rilis.php
- [ ] pembatalan.php
- [ ] laporan_transaksi.php
- [ ] laporan_pengeluaran.php
- [ ] laporan_kuantitas.php
- [ ] informasi.php
- [ ] profile.php

## Tips

1. **Backup** file lama sebelum update (rename jadi *_old_backup.php)
2. **Copy structure** dari `index.php` atau `kategori_menu.php` sebagai template
3. **Test** setiap halaman setelah update
4. **Dark mode** akan otomatis bekerja jika menggunakan Bootstrap 5 classes yang benar
5. **Sidebar state** tersimpan di localStorage

## Warna Primary
```css
--primary-color: #075E54 (Teal Green)
--danger-color: #FF2D2D (Red)
```

## Browser Support
- Chrome/Edge (Latest)
- Firefox (Latest)
- Safari (Latest)
- Mobile Browsers (iOS Safari, Chrome Mobile)

---
**Created:** <?php echo date('Y-m-d H:i:s'); ?>
**Template Source:** /var/www/html/_resto007/public/template/index.html
