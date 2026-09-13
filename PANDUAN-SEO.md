# Panduan Maintenance & Optimasi SEO — snorkelpenida.com

Panduan ini untuk **Anda** (pemilik/admin situs), bukan untuk developer. Semua langkah di sini dilakukan lewat halaman Admin di browser — tidak ada yang perlu edit kode.

Konten situs (17 halaman) sudah dibuat dalam Bahasa Spanyol, ditargetkan untuk wisatawan berbahasa Spanyol yang mencari info snorkeling di Nusa Penida. Meta title, meta description, dan target keyword **sudah terisi penuh** untuk semua halaman — lihat tabel referensi di Bagian 4. Tugas Anda yang tersisa: **isi foto**, lalu jalankan checklist maintenance rutin di Bagian 7.

---

## Daftar Isi

1. [Ringkasan apa yang sudah dibangun](#1-ringkasan-apa-yang-sudah-dibangun)
2. [Cara edit meta title, meta description & target keyword](#2-cara-edit-meta-title-meta-description--target-keyword)
3. [Cara mengisi/mengganti gambar](#3-cara-mengisimengganti-gambar)
4. [Referensi lengkap: 17 halaman, keyword, title, meta description](#4-referensi-lengkap-17-halaman-keyword-title-meta-description)
5. [Cara menambah halaman atau artikel blog baru](#5-cara-menambah-halaman-atau-artikel-blog-baru)
6. [Yang sudah otomatis — tidak perlu dikerjakan manual](#6-yang-sudah-otomatis--tidak-perlu-dikerjakan-manual)
7. [Rutinitas maintenance (mingguan / bulanan / triwulan)](#7-rutinitas-maintenance-mingguan--bulanan--triwulan)
8. [Panduan optimasi lanjutan untuk hasil maksimal](#8-panduan-optimasi-lanjutan-untuk-hasil-maksimal)
9. [Checklist sebelum deploy ke domain produksi](#9-checklist-sebelum-deploy-ke-domain-produksi)
10. [Larangan — jangan lakukan ini](#10-larangan--jangan-lakukan-ini)

---

## 1. Ringkasan apa yang sudah dibangun

| Komponen | Status |
|---|---|
| 11 halaman komersial (Snorkeling, Manta Bay, Manta Point, Private, Precios, dll.) | ✅ Live, bisa diedit |
| 6 artikel blog | ✅ Live, bisa diedit |
| Nomor WhatsApp | ✅ Satu sumber terpusat di **Admin → Settings → Contact** |
| Meta title / meta description / focus keyword per halaman | ✅ Sudah terisi, bisa diedit kapan saja |
| Schema (Article, FAQPage, BreadcrumbList) | ✅ Otomatis dari isi konten, tidak perlu disentuh |
| Sitemap.xml & robots.txt | ✅ Update otomatis setiap kali Anda publish/edit |
| Breadcrumb (navigasi "Beranda / Halaman induk / Judul") | ✅ Otomatis di setiap halaman |
| Foto | ⬜ **Ini yang perlu Anda isi** — lihat Bagian 3 |

---

## 2. Cara edit meta title, meta description & target keyword

Setiap halaman punya panel **SEO** bawaan yang sudah terbuka begitu Anda membuka halaman untuk diedit — tidak perlu klik-klik mencari.

**Langkah:**

1. Masuk ke **Admin → Pages** (untuk 11 halaman komersial) atau **Admin → Posts** (untuk 6 artikel blog).
2. Klik halaman yang ingin diedit.
3. Di sidebar kanan, tab **"Page"/"Post"** sudah aktif secara default. Scroll ke bawah sampai panel **"SEO"** — di sana ada badge kecil warna (hijau/kuning/merah) yang menunjukan skor SEO halaman itu saat ini.
4. Field yang tersedia:
   - **Focus keyword** — kata kunci utama yang ditarget halaman ini (sudah diisi, lihat Bagian 4).
   - **SEO title** — judul yang muncul di hasil pencarian Google (beda dari judul halaman kalau perlu).
   - **Meta description** — deskripsi 1-2 kalimat yang muncul di bawah judul di Google. Idealnya 120–155 karakter — counter-nya sudah muncul otomatis di bawah kotak teks.
   - **Canonical URL** — biarkan **kosong** kecuali Anda tahu persis kenapa perlu diisi manual (sistem sudah otomatis mengisi URL halaman itu sendiri).
   - **Social share image** — biarkan **kosong**; sistem otomatis memakai foto utama (Featured Image, Bagian 3) sebagai gambar yang muncul saat link dibagikan ke WhatsApp/Facebook.
5. Klik **"Analyze now"** untuk melihat skor SEO dan saran perbaikan real-time (contoh: "tambahkan focus keyword ke paragraf pertama").
6. Klik **Update** di pojok kanan atas untuk menyimpan.

> Perubahan ini **langsung live** — tidak perlu proses deploy tambahan.

---

## 3. Cara mengisi/mengganti gambar

Ini bagian yang sudah dirancang supaya benar-benar semudah mungkin: **satu foto per halaman** sudah cukup untuk hasil maksimal, karena foto itu otomatis dipakai di dua tempat sekaligus.

**Langkah:**

1. Buka halaman yang ingin diisi fotonya (Admin → Pages/Posts → Edit).
2. Di sidebar kanan, cari panel **"Featured image"** (letaknya di atas panel SEO).
3. Klik tombol **"Choose"** → pilih foto dari Media Library, atau upload foto baru.
4. Selesai. Foto ini otomatis:
   - Muncul sebagai gambar utama di bagian atas halaman.
   - Menjadi **alt text otomatis dari judul halaman** (sudah SEO-friendly, tidak perlu isi manual) — misalnya foto di halaman "Snorkeling en Manta Bay" otomatis dapat alt text "Snorkeling en Manta Bay, Nusa Penida".
   - Menjadi gambar yang muncul saat link halaman ini dibagikan di WhatsApp/Facebook (Open Graph image) — **selama field "Social share image" di panel SEO dibiarkan kosong** (lihat Bagian 2).

**Opsional — foto tambahan di dalam isi konten:** kalau ingin lebih dari satu foto per halaman (misalnya di tengah artikel blog), klik ikon **"+"** di posisi yang diinginkan di dalam editor, pilih blok **"Image"**, lalu upload/pilih foto. Untuk blok ini, **isi kolom "Alt text" secara manual** dengan deskripsi singkat dan natural — contoh yang benar dan yang salah:

| ✅ Benar | ❌ Salah (keyword stuffing) |
|---|---|
| `Grupo haciendo snorkel en Manta Bay, Nusa Penida` | `snorkeling nusa penida barato snorkeling manta bay snorkeling manta point precio` |

### Checklist foto per halaman

Gunakan tabel ini sebagai daftar belanja foto. Semua sudah otomatis dapat alt text dari judul halaman — Anda tinggal upload.

| Halaman | Saran subjek foto |
|---|---|
| Snorkeling en Nusa Penida | Foto hero: grup snorkeling di air jernih Nusa Penida |
| Snorkeling en Manta Bay | Snorkeling di Manta Bay |
| Snorkeling en Manta Point | Snorkeling di Manta Point |
| Private Snorkeling | Barco privado / grup kecil eksklusif |
| Precios | Grup snorkeling yang ceria (foto umum) |
| Qué incluye | Equipo de snorkel — máscara, aletas, chaleco tersusun rapi |
| Horarios | Barco berangkat pagi/sore hari |
| Cómo reservar | Guía/tim menyambut tamu di titik kumpul |
| Preguntas frecuentes | Foto umum grup wisatawan senang |
| Qué llevar | Perlengkapan: sunscreen, toalla, traje de baño |
| Manta Bay vs Manta Point | Peta Nusa Penida yang menandai kedua titik, atau kolase dua foto |
| *(Blog)* Cómo hacer snorkeling | Wisatawan pemula memakai equipo untuk pertama kali |
| *(Blog)* Cuánto cuesta | Grup besar vs. grup kecil/privado (ilustrasi perbedaan harga) |
| *(Blog)* Manta Bay: guía para viajeros | Manta Bay dari sudut yang berbeda dari halaman komersial |
| *(Blog)* Manta Point: qué esperar | Manta Point dari sudut yang berbeda |
| *(Blog)* Mejor horario | Laut saat pagi/sore hari (matahari terbit/terbenam) |
| *(Blog)* Consejos | Guía memberi briefing keselamatan sebelum masuk air |

---

## 4. Referensi lengkap: 17 halaman, keyword, title, meta description

Semua sudah live. Gunakan tabel ini kalau ingin meninjau atau menyesuaikan lagi.

| URL | Focus keyword | Meta title |
|---|---|---|
| `/snorkeling-nusa-penida` | snorkeling Nusa Penida | Snorkeling en Nusa Penida \| Manta Bay y Manta Point |
| `/snorkeling-manta-bay` | snorkeling Manta Bay Nusa Penida | Snorkeling en Manta Bay, Nusa Penida \| Reserva por WhatsApp |
| `/manta-point-nusa-penida` | Manta Point Nusa Penida | Snorkeling en Manta Point, Nusa Penida |
| `/private-snorkeling-nusa-penida` | private snorkeling Nusa Penida | Private Snorkeling en Nusa Penida \| Solicitar precio |
| `/precios` | precio snorkeling Nusa Penida | Precios del Snorkeling en Nusa Penida |
| `/que-incluye` | qué incluye snorkeling Nusa Penida | Qué Incluye el Snorkeling en Nusa Penida |
| `/horarios` | horarios snorkeling Nusa Penida | Horarios del Snorkeling en Nusa Penida \| Salida y Check-in |
| `/como-reservar` | reservar snorkeling Nusa Penida | Cómo Reservar Snorkeling en Nusa Penida |
| `/preguntas-frecuentes` | preguntas frecuentes snorkeling Nusa Penida | Preguntas Frecuentes sobre Snorkeling en Nusa Penida |
| `/que-llevar` | qué llevar para hacer snorkel en Nusa Penida | Qué Llevar para Hacer Snorkel en Nusa Penida |
| `/manta-bay-vs-manta-point` | Manta Bay vs Manta Point | Manta Bay vs Manta Point en Nusa Penida |
| `/blog/como-hacer-snorkeling-en-nusa-penida` | cómo hacer snorkeling en Nusa Penida | Cómo Hacer Snorkeling en Nusa Penida: Guía Paso a Paso |
| `/blog/cuanto-cuesta-snorkeling-nusa-penida` | cuánto cuesta snorkeling Nusa Penida | ¿Cuánto Cuesta Hacer Snorkeling en Nusa Penida? |
| `/blog/manta-bay-nusa-penida-guia-viajeros` | Manta Bay Nusa Penida guía | Manta Bay en Nusa Penida: Guía para Viajeros |
| `/blog/manta-point-nusa-penida-que-esperar` | Manta Point Nusa Penida qué esperar | Manta Point en Nusa Penida: Qué Esperar |
| `/blog/mejor-horario-snorkeling-nusa-penida` | mejor horario snorkeling Nusa Penida | Mejor Horario para Hacer Snorkeling en Nusa Penida |
| `/blog/consejos-snorkeling-nusa-penida` | consejos snorkeling Nusa Penida | Consejos para Hacer Snorkeling en Nusa Penida |

Meta description masing-masing halaman ada di panel SEO tiap halaman (Bagian 2) — sengaja tidak diulang di sini supaya tabel ini tetap ringkas dan tidak jadi sumber duplikat yang harus disinkronkan manual.

---

## 5. Cara menambah halaman atau artikel blog baru

Pola yang sama bisa dipakai untuk menambah konten baru kapan saja:

1. **Admin → Pages → Create** (untuk halaman komersial) atau **Admin → Posts → Create** (untuk artikel blog).
2. Isi **judul** — slug URL otomatis terbentuk dari judul (bisa diedit manual di panel "Summary").
3. Tulis isi konten lewat block editor (klik "+" untuk menambah paragraf, judul, gambar, tabel, tombol CTA, dll).
4. Isi panel **SEO**: focus keyword, SEO title, meta description (Bagian 2).
5. Isi **Featured image** (Bagian 3).
6. Ubah **Status** ke **Published**.
7. Klik **Publish**.

**Tips untuk otomatis dapat FAQ Schema (kotak jawaban langsung di Google):** tulis pertanyaan sebagai **Heading** yang diakhiri tanda tanya "?" (contoh: `¿Cuánto cuesta el snorkeling?`), lalu langsung diikuti satu blok **Paragraph** berisi jawabannya, tanpa blok lain di antaranya. Kalau ada 2 pasangan tanya-jawab seperti ini atau lebih dalam satu halaman, sistem otomatis menambahkan FAQ Schema — tidak perlu setting tambahan.

**Tips CTA WhatsApp:** tambahkan blok **"CTA"**, isi judul + teks singkat + link `https://wa.me/<nomor>?text=<pesan>`. Supaya nomor WhatsApp selalu konsisten dan mudah diganti di satu tempat kalau nomor berubah, developer sudah menyiapkan nomor terpusat di **Admin → Settings → Contact** — link WhatsApp yang sudah ada di 17 halaman semuanya membaca dari sana.

---

## 6. Yang sudah otomatis — tidak perlu dikerjakan manual

Supaya tidak menghabiskan waktu untuk hal yang sudah beres:

- **Sitemap.xml** — regenerasi otomatis setiap kali halaman/artikel di-publish atau diedit.
- **robots.txt** — sudah benar (mengizinkan Google mengakses semua halaman publik, memblokir folder admin).
- **Canonical URL** — otomatis menunjuk ke URL halaman itu sendiri kalau field-nya dikosongkan.
- **Schema markup** (Article, FAQPage, BreadcrumbList) — otomatis dibuat dari isi konten setiap kali disimpan.
- **Breadcrumb** ("Inicio / Snorkeling en Nusa Penida / [Judul halaman]") — muncul otomatis di setiap halaman dan artikel.
- **Nomor WhatsApp** — satu sumber di Admin → Settings, dipakai semua tombol WhatsApp di seluruh situs.

---

## 7. Rutinitas maintenance (mingguan / bulanan / triwulan)

### Mingguan (5 menit)
- [ ] Cek **Google Search Console** untuk error baru (halaman tidak terindeks, error 404).
- [ ] Klik salah satu tombol WhatsApp di situs untuk memastikan masih membuka chat dengan benar.

### Bulanan (30 menit)
- [ ] Buka setiap halaman di Admin, lihat badge **skor SEO** — kalau merah/kuning, klik "Analyze now" dan ikuti sarannya.
- [ ] Cek dashboard notifikasi Admin untuk **404 spike** (lonjakan link rusak) — kalau ada, tambahkan redirect di **Admin → SEO → Redirects**.
- [ ] Perbarui satu artikel blog lama dengan info terbaru (harga, jadwal) kalau ada perubahan.

### Triwulan (1-2 jam)
- [ ] Buka Google Search Console → **Performance** → lihat kata kunci apa yang benar-benar mendatangkan pengunjung (bukan hanya yang ditarget). Kalau ada kata kunci relevan yang sering muncul tapi belum ada halaman/artikelnya, itu ide artikel blog berikutnya.
- [ ] Review apakah harga, jadwal, dan daftar "termasuk/tidak termasuk" di semua halaman masih sesuai kondisi bisnis saat ini.
- [ ] Kalau sudah ada review asli dari pelanggan (Google/TripAdvisor), tambahkan sebagai testimoni — lihat Bagian 10 soal larangan review palsu.

---

## 8. Panduan optimasi lanjutan untuk hasil maksimal

**Riset kata kunci (gratis):**
- **Google Search Console** (wajib, gratis) — sumber terbaik karena berdasarkan pencarian nyata yang sudah membawa pengunjung ke situs Anda.
- **Google Keyword Planner** atau **Ubersuggest** (versi gratis) — untuk ide kata kunci long-tail berbahasa Spanyol, misalnya *"snorkel con mantarrayas Bali"*, *"mejor precio snorkel Nusa Penida"*, *"excursión snorkel Nusa Penida desde Sanur"*.
- Fokus pada **long-tail keyword** (frasa 3-5 kata) — persaingannya lebih rendah dan intent-nya lebih jelas dibanding kata kunci umum seperti "snorkeling Bali".

**Local SEO:**
- Daftarkan **Google Business Profile** untuk bisnis ini — sangat membantu muncul di Google Maps saat wisatawan mencari "snorkeling Nusa Penida" dari HP mereka, dan gratis.
- Pastikan nama bisnis, area layanan, dan link WhatsApp konsisten antara Google Business Profile dan situs.

**Kecepatan halaman (Core Web Vitals):**
- Sebelum upload foto, kompres dulu (target di bawah 500KB–1MB per foto) — sistem otomatis membuat versi WebP dan beberapa ukuran, tapi foto sumber yang terlalu besar (>3-5MB) tetap memperlambat proses upload dan penyimpanan.
- Jangan menambahkan plugin, skrip pelacak (tracker), atau widget pihak ketiga tanpa alasan kuat — setiap tambahan memperlambat halaman.
- Setelah deploy ke domain produksi, jalankan [PageSpeed Insights](https://pagespeed.web.dev/) pada 2-3 halaman utama untuk baseline.

**Kepercayaan (trust signals):**
- Begitu ada review asli, tambahkan — jangan sebelum itu.
- Jaga agar informasi "termasuk/tidak termasuk", harga, dan jadwal selalu akurat — ini bagian dari sinyal kepercayaan yang dibaca Google maupun pengunjung.

**Internal linking:**
- Setiap artikel blog baru sebaiknya menautkan ke minimal satu halaman komersial yang relevan (sudah menjadi pola di 6 artikel yang ada — pertahankan pola ini).
- Hindari menautkan dengan teks yang sama persis berulang-ulang (misalnya selalu "snorkeling Nusa Penida" sebagai anchor text) — variasikan secara natural.

---

## 9. Checklist sebelum deploy ke domain produksi

- [ ] Ubah `baseURL` di konfigurasi environment (`.env` / `app/Config/App.php`) dari `http://localhost:8080/` menjadi `https://snorkelpenida.com/`.
- [ ] Isi foto (Featured Image) untuk 17 halaman — lihat checklist Bagian 3.
- [ ] Isi **Default social share image** di **Admin → Settings** sebagai cadangan untuk halaman yang belum punya foto.
- [ ] Setelah domain live, submit `sitemap.xml` ke **Google Search Console** dan **Bing Webmaster Tools**.
- [ ] Cek `https://snorkelpenida.com/robots.txt` bisa diakses dan isinya benar.
- [ ] Tes semua tombol WhatsApp (tombol mengambang, footer, dan CTA di dalam isi halaman) dari HP.
- [ ] Jalankan PageSpeed Insights pada halaman utama + 2 halaman komersial lain.

---

## 10. Larangan — jangan lakukan ini

Aturan ini berlaku permanen untuk semua konten baru yang ditambahkan di masa depan, bukan hanya 17 halaman yang sudah ada:

- ❌ **Jangan menjanjikan atau menggaransi** melihat mantarraya ("manta garantizada", "100% verás mantas"). Selalu gunakan bahasa yang jujur: *"zona conocida por la presencia de mantarrayas, sin garantía de avistamiento."*
- ❌ **Jangan membuat review, rating bintang, atau angka pelanggan palsu** ("+10.000 clientes", "4.9 estrellas") kecuali datanya benar-benar nyata dan bisa diverifikasi.
- ❌ **Jangan menampilkan harga yang belum dikonfirmasi** — terutama untuk private snorkeling, selalu gunakan "Solicitar precio" / "Consultar precio" kecuali harga pastinya sudah dikonfirmasi.
- ❌ **Jangan keyword stuffing** — mengulang kata kunci yang sama berlebihan di setiap kalimat. Google justru menghukum ini.
- ❌ **Jangan menjanjikan layanan yang tidak ditawarkan** (transport, makan, penginapan) sebagai bagian dari paket snorkeling, kecuali memang benar-benar disediakan.

---

*Dibuat sebagai bagian dari implementasi arsitektur SEO snorkelpenida.com — lihat juga [laporan audit teknis](https://claude.ai/code/artifact/48b4f5cc-9a7c-4f7f-ae7c-17d8004e0f4a) untuk detail implementasi (schema, internal linking, WhatsApp terpusat, dll).*
