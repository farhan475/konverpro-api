<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AiReferensiSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed deskripsi singkat kurikulum
        $this->seedKurikulumDescriptions();

        // 2. Seed library referensi AI
        $this->seedAiKeywords();
    }

    private function seedKurikulumDescriptions(): void
    {
        $descriptions = [
            '%organisasi%arsitektur%komputer%' => 'Mempelajari struktur CPU, instruksi mesin, memori, bus, dan arsitektur perangkat keras komputer.',
            '%jaringan komputer%' => 'Mempelajari protokol jaringan, topologi, routing, switching, IP addressing, dan komunikasi antar perangkat.',
            '%komunikasi data%' => 'Mempelajari transmisi data, protokol komunikasi, topologi jaringan, routing, dan konektivitas perangkat.',
            '%pemrograman berbasis web%' => 'Mempelajari pembuatan aplikasi web menggunakan HTML, CSS, JavaScript, routing, dan interaksi browser.',
            '%pemrograman web%' => 'Mempelajari pembuatan aplikasi web menggunakan HTML, CSS, JavaScript, routing, dan interaksi browser.',
            '%pemrograman lanjut%' => 'Mempelajari konsep lanjutan pemrograman, modularisasi, abstraksi, pola desain, dan optimasi kode.',
            '%pemrograman berorientasi objek%' => 'Mempelajari class, object, inheritance, polymorphism, encapsulation, dan desain program berbasis objek.',
            '%algoritma%' => 'Mempelajari logika algoritma, struktur kontrol, dan dasar pemecahan masalah dengan kode.',
            '%dasar%pemrograman%' => 'Mempelajari logika algoritma, struktur kontrol, dan dasar pemecahan masalah dengan kode.',
            '%basis data%' => 'Mempelajari perancangan tabel, relasi, normalisasi, SQL, transaksi, dan pengelolaan database.',
            '%struktur data%' => 'Mempelajari array, linked list, stack, queue, tree, graph, dan kompleksitas operasi data.',
            '%sistem operasi%' => 'Mempelajari proses, thread, memori, file system, scheduling, dan manajemen sumber daya komputer.',
            '%rekayasa perangkat lunak%' => 'Mempelajari siklus pengembangan software, requirement, desain, testing, deployment, dan manajemen proyek.',
            '%kecerdasan buatan%' => 'Mempelajari pencarian, representasi pengetahuan, machine learning dasar, inferensi, dan sistem cerdas.',
            '%data mining%' => 'Mempelajari ekstraksi pola data, klasifikasi, clustering, asosiasi, preprocessing, dan evaluasi model.',
            '%visualisasi data%' => 'Mempelajari eksplorasi data, grafik, dashboard, insight, dan komunikasi visual berbasis data.',
            '%pendukung keputusan%' => 'Mempelajari model keputusan, kriteria, alternatif, pembobotan, dan sistem rekomendasi manajerial.',
            '%e commerce%' => 'Mempelajari bisnis digital, transaksi online, marketplace, pembayaran elektronik, dan strategi perdagangan internet.',
            '%e-commerce%' => 'Mempelajari bisnis digital, transaksi online, marketplace, pembayaran elektronik, dan strategi perdagangan internet.',
            '%matematika diskrit%' => 'Mempelajari logika, himpunan, relasi, fungsi, kombinatorika, graf, dan struktur matematika komputer.',
            '%statistika%' => 'Mempelajari peluang, distribusi, estimasi, uji hipotesis, korelasi, dan analisis statistik.',
            '%bahasa inggris%' => 'Mempelajari komunikasi bahasa Inggris akademik, membaca teks, kosakata, grammar, dan presentasi.',
            '%english%' => 'Mempelajari komunikasi bahasa Inggris akademik, membaca teks, kosakata, grammar, dan presentasi.',
            '%bahasa indonesia%' => 'Mempelajari penulisan ilmiah, tata bahasa, paragraf, ejaan, dan komunikasi akademik Indonesia.',
            '%pancasila%' => 'Mempelajari ideologi Pancasila, nilai kebangsaan, konstitusi, dan etika warga negara.',
            '%kewarganegaraan%' => 'Mempelajari hak kewajiban warga negara, demokrasi, konstitusi, nasionalisme, dan hukum dasar.',
        ];

        foreach ($descriptions as $pattern => $desc) {
            DB::table('kurikulum_mk')
                ->whereRaw('LOWER(nama_mk) LIKE ?', [strtolower($pattern)])
                ->whereNull('deskripsi_singkat')
                ->update(['deskripsi_singkat' => $desc]);
        }
    }

    private function seedAiKeywords(): void
    {
        $categories = [
            'pancasila_kewarganegaraan' => [
                'patterns' => ['%pancasila%', '%kewarganegaraan%'],
                'keywords' => [
                    ['Pancasila', 'pancasila', 100],
                    ['Pendidikan Pancasila', 'pancasila', 95],
                    ['Pendidikan Kewarganegaraan', 'kewarganegaraan', 92],
                    ['Kewarganegaraan', 'kewarganegaraan', 90],
                    ['PPKN', 'ppkn', 88],
                    ['PKN', 'pkn', 88],
                    ['Nasionalisme', 'nasionalisme', 84],
                    ['Ideologi Negara', 'ideologi negara', 84],
                    ['Falsafah Negara', 'falsafah negara', 82],
                    ['Civic Education', 'civic education', 82],
                ]
            ],
            'algoritma_pemrograman' => [
                'patterns' => ['%algoritma%', '%pemrograman%'],
                'keywords' => [
                    ['Algoritma dan Pemrograman', 'algoritma pemrograman', 100],
                    ['Algoritma Pemrograman', 'algoritma pemrograman', 96],
                    ['Dasar Pemrograman', 'pemrograman', 90],
                    ['Dasar Dasar Pemrograman', 'pemrograman', 88],
                    ['Pemrograman Dasar', 'pemrograman', 88],
                    ['Alpro', 'alpro', 84],
                    ['Programming Logic', 'programming logic', 82],
                ]
            ],
            'basis_data' => [
                'patterns' => ['%basis data%', '%database%'],
                'keywords' => [
                    ['Basis Data', 'basis data', 100],
                    ['Sistem Basis Data', 'basis data', 96],
                    ['Database', 'database', 94],
                    ['Database Management', 'database management', 88],
                    ['Manajemen Basis Data', 'manajemen basis data', 88],
                    ['DBMS', 'dbms', 86],
                    ['SQL', 'sql', 78],
                ]
            ],
            'sistem_operasi' => [
                'patterns' => ['%sistem operasi%'],
                'keywords' => [
                    ['Sistem Operasi', 'sistem operasi', 100],
                    ['Operating System', 'operating system', 92],
                    ['OS', 'os', 82],
                ]
            ],
            'bahasa_inggris' => [
                'patterns' => ['%bahasa inggris%', '%english%'],
                'keywords' => [
                    ['Bahasa Inggris', 'bahasa inggris', 100],
                    ['English', 'english', 94],
                    ['English Language', 'english language', 90],
                    ['General English', 'general english', 84],
                ]
            ],
            'bahasa_indonesia' => [
                'patterns' => ['%bahasa indonesia%'],
                'keywords' => [
                    ['Bahasa Indonesia', 'bahasa indonesia', 100],
                    ['Indonesian Language', 'indonesian language', 90],
                    ['Bahasa Nasional', 'bahasa nasional', 82],
                ]
            ],
            'matematika_diskrit' => [
                'patterns' => ['%matematika diskrit%'],
                'keywords' => [
                    ['Matematika Diskrit', 'matematika diskrit', 100],
                    ['Discrete Mathematics', 'discrete mathematics', 94],
                    ['Matematika Komputasi', 'matematika komputasi', 82],
                ]
            ],
            'jaringan_komputer' => [
                'patterns' => ['%jaringan komputer%', '%komunikasi data%'],
                'keywords' => [
                    ['Jaringan Komputer', 'jaringan komputer', 100],
                    ['Komunikasi Data dan Jaringan Komputer', 'komunikasi data jaringan komputer', 96],
                    ['Computer Network', 'computer network', 92],
                    ['Networking', 'networking', 84],
                ]
            ],
        ];

        foreach ($categories as $cat) {
            $mks = DB::table('kurikulum_mk');
            foreach ($cat['patterns'] as $i => $pattern) {
                if ($i === 0) {
                    $mks->whereRaw('LOWER(nama_mk) LIKE ?', [strtolower($pattern)]);
                } else {
                    $mks->orWhereRaw('LOWER(nama_mk) LIKE ?', [strtolower($pattern)]);
                }
            }
            
            $mkIds = $mks->pluck('id');

            foreach ($mkIds as $id) {
                foreach ($cat['keywords'] as $kw) {
                    DB::table('mk_referensi_ai')->updateOrInsert(
                        ['id_kurikulum_mk' => $id, 'keyword_normalized' => $kw[1]],
                        [
                            'keyword' => $kw[0],
                            'weight' => $kw[2],
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
