<?php
// Curated facts, not scraped instructions or training labels for admission outcomes.
$date = '2026-11-14';
$source = fn ($url, $authority) => ['url'=>$url,'authority'=>$authority,'checked_at'=>$date];
$manual = fn ($code,$label) => ['code'=>$code,'label'=>$label];
$umSource=$source('https://seleksi.um.ac.id/sm-jalur-prestasi/','Universitas Negeri Malang');
$umRequirements=[
 $manual('lulusan','Rujukan seleksi 2026: lulusan tiga tahun terakhir SMA/MA/SMK terakreditasi, tidak pernah tinggal kelas dan tidak lulus SNBP; periksa ketentuan daftar ulang SNBT.'),
 $manual('jalur_prestasi','Salah satu jalur: peringkat 1–3 provinsi/nasional/internasional dalam tiga tahun terakhir dari penyelenggara kredibel; ATAU sertifikat bahasa Inggris sesuai ketentuan. Syarat kedua jalur tidak dijumlahkan sebagai kewajiban bersama.'),
 $manual('seleksi','Seleksi administrasi dan unjuk prestasi/wawancara; lolos jalur prestasi tidak otomatis memperoleh beasiswa.'),
 $manual('dokumen','Ijazah/SKL, bukti prestasi, rapor semester 1–5 dan dokumen keluarga/ekonomi sesuai daftar pada laman resmi.'),
];
return [
 'version'=>'verified-scholarships-20261114-v1',
 'items'=>[
 ['id'=>4,'name'=>'KIP-K','provider'=>'Pemerintah Republik Indonesia — Program KIP Kuliah',
  'description'=>'KIP Kuliah adalah bantuan pendidikan untuk calon mahasiswa dengan potensi akademik dan keterbatasan ekonomi. Rujukan 2026: lulusan 2024–2026, diterima pada perguruan tinggi/program studi sesuai ketentuan. Bukti ekonomi merupakan alternatif: KIP, DTSEN maksimum desil 4, asal panti sosial/asuhan, atau pendapatan gabungan orang tua/wali di bawah UMP domisili asal disertai SKTM. Tidak ada ambang nilai rapor universal pada FAQ ini. Dukungan mencakup biaya pendidikan dan bantuan biaya hidup. Penetapan penerima memerlukan verifikasi perguruan tinggi; rekomendasi bukan keputusan penerimaan.',
  'sources'=>[$source('https://kip-kuliah.kemdiktisaintek.go.id/panduan','Kemdiktisaintek — KIP Kuliah')],
  'manual_requirements'=>[
   $manual('lulusan','Rujukan 2026: lulus SMA/sederajat pada 2024, 2025 atau 2026.'),
   $manual('akademik','Potensi akademik baik dengan bukti sah; tidak menetapkan batas rapor universal yang tidak disebut sumber.'),
   $manual('ekonomi','Salah satu bukti: KIP; DTSEN maksimum desil 4; panti sosial/asuhan; ATAU pendapatan gabungan di bawah UMP domisili asal serta SKTM. Tanpa data UMP, domisili dan bukti, hasil belum diketahui.'),
   $manual('penerimaan_pt','Diterima di PTN/PTS pada prodi A/Unggul atau B/Baik Sekali; C/Baik hanya dengan pertimbangan sesuai ketentuan.'),
   $manual('verifikasi_pt','Verifikasi dan penetapan penerima oleh perguruan tinggi. Jadwal harus diperiksa per periode.')],
  'scope_checks'=>[]],
 ['id'=>6,'name'=>'Bebas IPI','provider'=>'Universitas Negeri Malang',
  'description'=>'Pembebasan IPI pada Seleksi Mandiri Jalur Prestasi Universitas Negeri Malang. Rujukan 2026 menawarkan alternatif jalur kejuaraan penalaran, olahraga, seni atau keagamaan; atau kompetensi bahasa Inggris. Hasil seleksi dapat berupa bebas IPI 100% dan UKT dua semester, bebas IPI 100%, bebas IPI 50%, atau tanpa beasiswa. Penetapan berdasarkan kualitas prestasi saat unjuk prestasi/wawancara, sehingga penerimaan kuliah bukan jaminan pembebasan biaya. Pembebasan IPI merupakan biaya awal; jangan disamakan dengan pembebasan UKT seluruh masa kuliah.',
  'sources'=>[$umSource],'manual_requirements'=>$umRequirements,
  'scope_checks'=>[['field'=>'nama_perguruan_tinggi','values'=>['Universitas Negeri Malang','UM']]]],
 ['id'=>5,'name'=>'Beasiswa TELADAN','provider'=>'UIN Maulana Malik Ibrahim Malang',
  'description'=>'Beasiswa TELADAN UIN Maulana Malik Ibrahim Malang, berbeda dari TELADAN Tanoto Foundation. Rujukan 2026: calon mahasiswa dengan NISN dan lulusan 2024–2026, memenuhi salah satu kategori tahfidz Al-Quran 30 juz; qiraatul kutub dan Alfiyah Ibnu Malik; hafalan 100 hadist ahkam; atau prestasi seni/olahraga. Seleksi mencakup TPA dan tes prestasi. Manfaat berupa UKT sampai Rp0 selama delapan semester serta bebas biaya pengembangan kelembagaan dan pendidikan mahad dua semester pertama, tunduk kewajiban penerima. IP setiap semester minimal 3,00 adalah kewajiban selama studi, bukan syarat IPK siswa SMA.',
  'sources'=>[$source('https://pmb.uin-malang.ac.id/ar/beasiswa-teladan/','PMB UIN Maulana Malik Ibrahim Malang')],
  'manual_requirements'=>[
   $manual('identitas_lulusan','NISN dan lulusan SMA/SMK/MA atau Pesantren Muaddalah 2024–2026 untuk penawaran 2026.'),
   $manual('kategori_kompetensi','Salah satu dari empat kategori kompetensi sesuai pengumuman resmi UIN, bukan syarat program Tanoto.'),
   $manual('seleksi','Bukti kompetensi asli dan kelulusan TPA serta tes prestasi.'),
   $manual('kewajiban','Kewajiban pembinaan, relawan, mahad/UKM sesuai kategori. IP minimal 3,00 berlaku setelah kuliah.'),
   $manual('periode','Jadwal dasar dan perpanjangan harus diperiksa pada pengumuman UIN; jangan menganggap pendaftaran sekarang terbuka.')],
  'scope_checks'=>[['field'=>'nama_perguruan_tinggi','values'=>['UIN Maulana Malik Ibrahim Malang','Universitas Islam Negeri Maulana Malik Ibrahim Malang']]]],
 ['id'=>7,'name'=>'PRESTASI BASKET','provider'=>'Universitas Negeri Malang',
  'description'=>'Catatan penerima sekolah menyebut PRESTASI BASKET pada Universitas Negeri Malang. Rujukan publik yang relevan adalah SM Jalur Prestasi UM 2026, yang mencantumkan basket sebagai cabang olahraga. Kategori kejuaraan memerlukan peringkat 1–3 provinsi/nasional/internasional dalam tiga tahun terakhir dari penyelenggara kredibel. Seleksi administrasi dan unjuk prestasi/wawancara menentukan bantuan IPI/UKT; prestasi basket atau diterima di UM tidak otomatis memastikan beasiswa. Nama catatan sekolah dipertahankan; manfaat individual penerima tidak ditebak.',
  'sources'=>[$umSource],'manual_requirements'=>array_merge($umRequirements,[$manual('basket','Verifikasi cabang basket, tingkat kejuaraan, peringkat, tahun dan sertifikat; jumlah prestasi saja tidak membuktikan kategori basket.')]),
  'scope_checks'=>[['field'=>'nama_perguruan_tinggi','values'=>['Universitas Negeri Malang','UM']]]],
 ['id'=>8,'name'=>'Beasiswa dengan ikatan dinas Universitas Pelita Harapan','provider'=>'Universitas Pelita Harapan',
  'description'=>'Rujukan resmi UPH untuk keperawatan menyebut bantuan pendidikan penuh dengan resiprokasi dan ikatan dinas, meliputi biaya studi, tempat tinggal, makan dan transportasi. Ini rujukan cakupan program, bukan ketentuan pendaftaran 2026 yang lengkap. Kontrak, lama ikatan dinas, syarat kesehatan, nilai minimum dan jadwal harus diperiksa pada penawaran/kontrak yang berlaku. Catatan sekolah terkait Faculty of Nursing/Profesi Ners tetap dipertahankan; dokumen publik ini tidak membuktikan nominal atau kontrak individual alumni.',
  'sources'=>[$source('https://www.uph.edu/wp-content/uploads/fliphtml5/309/files/basic-html/page3.html','Universitas Pelita Harapan — brosur keperawatan')],
  'manual_requirements'=>[
   $manual('program_studi','Verifikasi penerimaan dan jenjang keperawatan/profesi yang tercakup dalam penawaran.'),
   $manual('kontrak','Verifikasi persetujuan resiprokasi/ikatan dinas dan ketentuan kontrak individual.'),
   $manual('seleksi','Syarat nilai, kesehatan, dokumen dan seleksi mengikuti penawaran terbaru; brosur cakupan tidak menetapkan angka ambang.'),
   $manual('periode','Tanggal dan cakupan penawaran saat ini belum diverifikasi; sumber brosur tidak cukup untuk menyatakan pendaftaran dibuka.')],
  'scope_checks'=>[['field'=>'nama_perguruan_tinggi','values'=>['Universitas Pelita Harapan','UPH']]]],
 ]];
