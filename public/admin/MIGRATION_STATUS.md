# Status Migrasi UI Admin ke Template Baru

**Last Updated:** <?php echo date('Y-m-d H:i:s'); ?>

## ✅ Yang Sudah Diupdate (5/25)

### Dashboard & Core
- [x] **index.php** - Dashboard dengan stat cards modern, info cards, aktivitas terkini
- [x] **kategori_menu.php** - CRUD sederhana untuk kategori menu
- [x] **kategori_bahan.php** - CRUD sederhana untuk kategori bahan
- [x] **profile.php** - Form update profil dengan password validation
- [x] **informasi.php** - CRUD dengan upload image, view detail modal

## 🔧 Components Baru

### PHP Includes
- [x] **_header_new.php** - Navbar modern dengan dark mode toggle, notifications, user dropdown
- [x] **_sidebar_new.php** - Sidebar collapsible dengan role-based menu (Admin/Kasir/Dapur)
- [x] **_scripts_new.php** - JavaScript (sidebar toggle, dark mode, Select2, auto-dismiss alerts)

### CSS & Assets
- [x] **newadmin.css** - Modern styling dengan gradient hijau teal (#075E54) & animations
- [x] Select2 integration (single & multiple searchable dropdowns)
- [x] Bootstrap 5.3.2 (CDN) - Dengan dark mode support
- [x] Bootstrap Icons 1.11.1 (CDN)

### Documentation & Tools
- [x] **UPDATE_GUIDE.md** - Panduan lengkap cara update halaman lain
- [x] **MIGRATION_STATUS.md** - Status tracking migrasi
- [x] **bulk_convert.sh** - Bash script untuk bulk conversion (⚠️ experimental)

## ⏳ Yang Perlu Diupdate (21/25)

### Produk (2/5)
- [ ] bahan.php - Complex (ada modal biaya)
- [ ] menu.php - Complex (ada upload image)
- [ ] resep.php

### Master (0/6)
- [ ] resto.php
- [ ] pegawai.php  
- [ ] konsumen.php
- [ ] vendor.php
- [ ] meja.php
- [ ] metode_pembayaran.php

### Pembelian (0/2)
- [ ] pembelian.php
- [ ] pembayaran_pembelian.php

### Penjualan (0/5)
- [ ] shift_kasir.php
- [ ] promo.php
- [ ] biaya_lain.php
- [ ] harga_rilis.php
- [ ] pembatalan.php

### Laporan (0/3)
- [ ] laporan_transaksi.php
- [ ] laporan_pengeluaran.php
- [ ] laporan_kuantitas.php

### Lainnya (2/2)
- ✅ **informasi.php** - DONE
- ✅ **profile.php** - DONE

## 📊 Progress: 20% (5/25 halaman)

## 🚀 Cara Update Halaman Lain

### Option 1: Manual (Recommended untuk Complex Pages)

**Step 1:** Backup file lama
```bash
cd /var/www/html/_resto007/public/admin
cp namafile.php namafile_old_backup.php
```

**Step 2:** Edit file, ganti bagian HEAD
```php
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Title - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="../css/newadmin.css" rel="stylesheet">
</head>
<body>
```

**Step 3:** Ganti navbar & sidebar
```php
    <?php include '_header_new.php'; ?>
    
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <!-- ISI CONTENT DI SINI -->
        </div>
    </main>
```

**Step 4:** Ganti scripts di bagian bawah
```php
    <?php include '_scripts_new.php'; ?>
</body>
</html>
```

### Option 2: Copy dari Template (untuk Simple CRUD)

**Untuk halaman CRUD simple:**
1. Copy `kategori_menu.php` as template
2. Find & Replace:
   - Table name (kategori_menu → nama_table_anda)
   - Labels & titles
   - Column names
3. Adjust form fields

**Contoh:**
```bash
cd /var/www/html/_resto007/public/admin
cp kategori_menu.php nama_file_baru.php
# Edit dengan text editor, replace nama table & fields
```

### Option 3: Bulk Script (untuk Simple Pages)

**⚠️ WARNING: Test di development dulu!**

```bash
cd /var/www/html/_resto007/public/admin
./bulk_convert.sh
```

Script ini akan update files:
- resto.php
- pegawai.php
- konsumen.php
- vendor.php
- meja.php
- metode_pembayaran.php
- promo.php
- informasi.php
- profile.php

Backup otomatis dibuat dengan extension `_old_backup.php`

## 🎨 Design Guidelines

### Warna
- Primary: `#075E54` (Teal Green)
- Danger: `#FF2D2D` (Red)

### Classes Penting

**Cards:**
- `.card-modern` - Card dengan border-radius & shadow
- `.card-stat` - Stat card untuk dashboard
- `.table-modern` - Table dengan header gradient

**Forms:**
- `.form-modern` - Form dengan styling modern
- `.select2-search` - Single select searchable
- `.select2-multiple` - Multiple select searchable

**Alerts:**
- Auto-dismiss after 5 seconds
- Icon di awal pesan

### Responsive
- Desktop: Sidebar bisa collapsed (state tersimpan)
- Mobile: Sidebar overlay dengan backdrop

## 🐛 Known Issues
- None yet

## 📝 Notes
- Semua file backup tersimpan dengan extension `_old_backup.php`
- Dark mode state tersimpan di localStorage
- Sidebar collapse state tersimpan di localStorage
- Select2 init otomatis untuk class `.select2-search` dan `.select2-multiple`

## 💡 Tips
1. Test setiap halaman setelah update
2. Check console browser untuk JavaScript errors
3. Test dark mode di setiap halaman
4. Test responsive di mobile view
5. Backup database sebelum deploy ke production

## 🔗 References
- Template Source: `/var/www/html/_resto007/public/template/index.html`
- CSS Source: `/var/www/html/_resto007/public/css/newadmin.css`
- Dokumentasi: `/var/www/html/_resto007/public/admin/UPDATE_GUIDE.md`

---
**Progress akan diupdate secara berkala**
