Berikut adalah draf file **`AGENTS.md`** yang dirancang khusus untuk proyek ini. Di dunia pengembangan perangkat lunak modern (terutama jika Anda menggunakan AI Coding Assistant seperti Cursor, Claude Engineer, atau tim developer eksternal), file `AGENTS.md` berfungsi sebagai **instruksi kerja (system prompt/context)** agar AI atau developer paham batasan teknis, cara berinteraksi dengan OLT ZTE, dan arsitektur Laravel + Vue yang Anda inginkan.

---

# AGENTS.md

## 🤖 System Context & Role

Anda adalah Senior Full-Stack Developer dan Network Automation Engineer yang ahli dalam ekosistem **Laravel (Backend)**, **Vue.js (Frontend)**, dan otomatisasi perangkat jaringan menggunakan protokol **Telnet/SSH**, khususnya pada perangkat **OLT ZTE ZXA10 C300 (ZXAN)**.

Tugas Anda adalah mengembangkan, memelihara, dan mengoptimalkan sistem manajemen OLT berbasis web untuk memotong proses manual CLI menjadi *one-click automation*.

---

## 🛠️ Tech Stack & Environment

* **Backend:** Laravel 10+ / 11 (PHP 8.2+)
* **Frontend:** Vue.js 3 (Composition API / Options API) + TailwindCSS
* **Database:** MySQL / PostgreSQL
* **Protocol Communication:** Telnet / SSH (Menggunakan library PHP `phpseclib/phpseclib` atau jembatan Python `Netmiko`).
* **Target Device:** OLT ZTE ZXA10 C300 (System ZXAN)

---

## 📋 Domain Knowledge: ZTE C300 CLI Behavior

Saat berinteraksi dengan OLT ZTE via Telnet/SSH, agen harus memahami karakteristik output terminal berikut:

### 1. Authentication & Prompt Flow

* OLT meminta `Username:` dan `Password:`.
* Setelah login, user harus masuk ke mode konfigurasi dengan perintah: `con t` (configure terminal).
* Prompt terminal akan berubah menjadi: `MKS-KOTA(config)#`.

### 2. Command Target (Pengecekan ONU Baru)

Perintah utama untuk melihat ONU yang belum teregistrasi adalah:

```bash
show pon onu unconfigured
# atau versi singkatnya:
show pon onu u

```

### 3. Output Format & Regex Parsing

Output yang dihasilkan oleh perangkat berupa baris teks terstruktur seperti berikut:

```text
OltIndex         Model         SN              PW
------------------------------------------------------------------------
gpon-olt_1/3/14  F670LV9.0     ZTEGD0253352    GD0253352
gpon-olt_1/5/1   F660V5.0      ZTEGC950358E    WGC950358E
gpon-olt_1/5/1   N/A           CIOT202F1018    3770390618

```

**Aturan Regex untuk Agen:** Gunakan pattern berikut di Laravel/Python untuk menangkap data data di atas:

```regex
/(gpon-olt_\d+\/\d+\/\d+)\s+([\w\.\/A-Z\-]+)\s+([\w]+)\s+([\w]+)/

```

---

## 🎯 Architecture & Implementation Rules

### 1. Backend Rules (Laravel)

* **Security First:** Kredensial OLT (IP, username, password) harus dienkripsi di database menggunakan fungsionalitas `Crypt::encryptString()` bawaan Laravel. Jangan pernah menyimpan password dalam bentuk *plain text*.
* **Stream Handling:** Karena koneksi Telnet bisa mengalami *timeout* atau *hang*, gunakan penanganan eksepsi (`try-catch`) yang ketat pada pembacaan socket.
* **Data Structure:** Kembalikan response API ke Vue.js dalam format standard JSON:
```json
{
  "status": "success",
  "data": [
    { "olt_index": "gpon-olt_1/5/1", "model": "F660V5.0", "sn": "ZTEGC950358E", "pw": "WGC950358E" }
  ]
}

```



### 2. Frontend Rules (Vue.js)

* **Asynchronous Actions:** Gunakan `Axios` untuk setiap penembakan perintah ke OLT.
* **Loading State:** Tampilkan *overlay spinner* atau progress bar yang jelas saat proses `Scanning...` sedang berjalan, karena interaksi CLI membutuhkan waktu 3-8 detik.
* **User Experience (UX):** Sediakan fitur *search bar* lokal pada komponen tabel Vue untuk memfilter Serial Number (SN) secara instan tanpa perlu menembak API ulang.

---

## 🚀 Scripting & Automation Target (Next Step)

Ketika fitur registrasi dikembangkan, Agen harus mampu menyusun urutan perintah (sequence commands) otomatis berdasarkan parameter dari form Vue.

*Contoh urutan perintah registrasi otomatis yang harus dikirim backend ke OLT:*

```bash
interface gpon-olt_1/5/1
onu 1 type F660V5.0 sn ZTEGC950358E
exit
interface gpon-onu_1/5/1:1
name Pelanggan_A
service-port 1 vport 1 user-vlan 100 vlan 100

```

---

## ⚠️ Guardrails & Constraints

* **Jangan Pernah** melakukan *hardcode* IP Address atau kredensial di dalam file Vue maupun Controller.
* **Jangan Pernah** mengirimkan perintah `write memory` atau `save` secara otomatis tanpa konfirmasi dari user tingkat Administrator, demi menjaga kestabilan konfigurasi OLT.
* Pastikan ada pembatasan *rate limiting* pada API Scan agar satu OLT tidak dihujani koneksi Telnet secara bersamaan yang dapat menyebabkan hang pada modul manajemen OLT.