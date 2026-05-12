public function up(): void

    {

        Schema::create('users', function (Blueprint $table) {

            $table->id();

            $table->string('name');

            $table->string('email')->unique();

            $table->string('google_id')->nullable()->unique(); // Untuk SSO Google

            $table->timestamp('email_verified_at')->nullable();

            $table->string('password')->nullable(); // Nullable karena ada login Google

            $table->rememberToken();

            $table->timestamps();

        });



        Schema::create('password_reset_tokens', function (Blueprint $table) {

            $table->string('email')->primary();

            $table->string('token');

            $table->timestamp('created_at')->nullable();

        });



        Schema::create('sessions', function (Blueprint $table) {

            $table->string('id')->primary();

            $table->foreignId('user_id')->nullable()->index();

            $table->string('ip_address', 45)->nullable();

            $table->text('user_agent')->nullable();

            $table->longText('payload');

            $table->integer('last_activity')->index();

        });

    }



public function up(): void

    {

        Schema::create('cache', function (Blueprint $table) {

            $table->string('key')->primary();

            $table->mediumText('value');

            $table->bigInteger('expiration')->index();

        });



        Schema::create('cache_locks', function (Blueprint $table) {

            $table->string('key')->primary();

            $table->string('owner');

            $table->bigInteger('expiration')->index();

        });

    }

public function up(): void

    {

        Schema::create('jobs', function (Blueprint $table) {

            $table->id();

            $table->string('queue')->index();

            $table->longText('payload');

            $table->unsignedSmallInteger('attempts');

            $table->unsignedInteger('reserved_at')->nullable();

            $table->unsignedInteger('available_at');

            $table->unsignedInteger('created_at');

        });



        Schema::create('job_batches', function (Blueprint $table) {

            $table->string('id')->primary();

            $table->string('name');

            $table->integer('total_jobs');

            $table->integer('pending_jobs');

            $table->integer('failed_jobs');

            $table->longText('failed_job_ids');

            $table->mediumText('options')->nullable();

            $table->integer('cancelled_at')->nullable();

            $table->integer('created_at');

            $table->integer('finished_at')->nullable();

        });



        Schema::create('failed_jobs', function (Blueprint $table) {

            $table->id();

            $table->string('uuid')->unique();

            $table->text('connection');

            $table->text('queue');

            $table->longText('payload');

            $table->longText('exception');

            $table->timestamp('failed_at')->useCurrent();

        });

    }

public function up(): void

    {

        $teams = config('permission.teams');

        $tableNames = config('permission.table_names');

        $columnNames = config('permission.column_names');

        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';

        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';



        throw_if(empty($tableNames), 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        throw_if($teams && empty($columnNames['team_foreign_key'] ?? null), 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');



        /**

         * See `docs/prerequisites.md` for suggested lengths on 'name' and 'guard_name' if "1071 Specified key was too long" errors are encountered.

         */

        Schema::create($tableNames['permissions'], static function (Blueprint $table) {

            $table->id(); // permission id

            $table->string('name');

            $table->string('guard_name');

            $table->timestamps();



            $table->unique(['name', 'guard_name']);

        });



        /**

         * See `docs/prerequisites.md` for suggested lengths on 'name' and 'guard_name' if "1071 Specified key was too long" errors are encountered.

         */

        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teams, $columnNames) {

            $table->id(); // role id

            if ($teams || config('permission.testing')) { // permission.testing is a fix for sqlite testing

                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();

                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');

            }

            $table->string('name');

            $table->string('guard_name');

            $table->timestamps();

            if ($teams || config('permission.testing')) {

                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);

            } else {

                $table->unique(['name', 'guard_name']);

            }

        });



        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams) {

            $table->unsignedBigInteger($pivotPermission);



            $table->string('model_type');

            $table->unsignedBigInteger($columnNames['model_morph_key']);

            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');



            $table->foreign($pivotPermission)

                ->references('id') // permission id

                ->on($tableNames['permissions'])

                ->cascadeOnDelete();

            if ($teams) {

                $table->unsignedBigInteger($columnNames['team_foreign_key']);

                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');



                $table->primary([$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],

                    'model_has_permissions_permission_model_type_primary');

            } else {

                $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'],

                    'model_has_permissions_permission_model_type_primary');

            }

        });



        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams) {

            $table->unsignedBigInteger($pivotRole);



            $table->string('model_type');

            $table->unsignedBigInteger($columnNames['model_morph_key']);

            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');



            $table->foreign($pivotRole)

                ->references('id') // role id

                ->on($tableNames['roles'])

                ->cascadeOnDelete();

            if ($teams) {

                $table->unsignedBigInteger($columnNames['team_foreign_key']);

                $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');



                $table->primary([$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],

                    'model_has_roles_role_model_type_primary');

            } else {

                $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'],

                    'model_has_roles_role_model_type_primary');

            }

        });



        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {

            $table->unsignedBigInteger($pivotPermission);

            $table->unsignedBigInteger($pivotRole);



            $table->foreign($pivotPermission)

                ->references('id') // permission id

                ->on($tableNames['permissions'])

                ->cascadeOnDelete();



            $table->foreign($pivotRole)

                ->references('id') // role id

                ->on($tableNames['roles'])

                ->cascadeOnDelete();



            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');

        });



        app('cache')

            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)

            ->forget(config('permission.cache.key'));

    }

public function up(): void

    {

        Schema::create('units', function (Blueprint $table) {

            $table->id();

            $table->string('kode_unit', 20)->unique()->nullable(); // Contoh: LPPM, FEB, IF

            $table->string('nama_unit'); 

            

            // Hirarki unit (Misal Prodi di bawah Fakultas)

            $table->foreignId('parent_id')->nullable()->constrained('units')->nullOnDelete();

            

            // Kepala Unit (Langsung relasi ke users)

            $table->foreignId('kepala_unit_id')->nullable()->constrained('users')->nullOnDelete();

            

            $table->timestamps();

            $table->softDeletes();

        });

    }

public function up(): void

    {

        Schema::create('positions', function (Blueprint $table) {

            $table->id();

            $table->string('nama_jabatan'); 

            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();

            $table->integer('level_otoritas'); 

            $table->string('kategori'); 

            $table->timestamps();

        });

    }

public function up(): void

    {

        Schema::create('unit_user', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();

            

            $table->foreignId('position_id')->nullable()->constrained('positions')->cascadeOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            

            // Mencegah duplikasi data

            $table->unique(['user_id', 'unit_id']);

        });

    }

public function up(): void

    {

        Schema::create('periodes', function (Blueprint $table) {

            $table->id();

            $table->string('nama_periode'); // Contoh: "TA 2024/2025" atau "2024"

            $table->date('tanggal_mulai');

            $table->date('tanggal_selesai');

            $table->enum('status', ['planning', 'active', 'closed'])->default('planning');

            $table->boolean('is_current')->default(false);

            $table->timestamps();

        });

    }

public function up(): void

    {

        Schema::create('performance_indicators', function (Blueprint $table) {

            $table->id();

            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();

            $table->string('kode_indikator', 20); 

            $table->text('nama_indikator');

            $table->enum('kategori', ['IKU', 'IKT']); 

            $table->timestamps();



            // TAMBAHKAN BARIS INI: Agar kode unik per periode, bukan global

            $table->unique(['periode_id', 'kode_indikator']);

        });

    }

public function up(): void

    {

        // 1. Tabel Utama Program Kerja

        Schema::create('work_programs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();

            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();

            

            $table->string('nama_proker');

            $table->text('deskripsi')->nullable();

            $table->decimal('anggaran_rencana', 15, 2);

            

            // Status approval dari Rapat Kerja

            $table->enum('status', ['draft', 'review_lpm', 'disetujui', 'ditolak'])->default('draft');

            $table->timestamps();

        });



        // 2. Tabel Pivot: 1 Proker mendukung IKU/IKT yang mana saja

        Schema::create('work_program_indicators', function (Blueprint $table) {

            $table->id();

            $table->foreignId('work_program_id')->constrained('work_programs')->cascadeOnDelete();

            $table->foreignId('indicator_id')->constrained('performance_indicators')->cascadeOnDelete();

            

            // Target capaian untuk IKU tersebut

            $table->float('target_angka'); 

            $table->string('satuan_target', 50); // Contoh: '%', 'Dokumen', 'Mitra'

        });

    }



public function up(): void

    {

        Schema::create('fund_submissions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            

            // Link ke Proker (Wajib agar terukur)

            $table->foreignId('work_program_id')->nullable()->constrained('work_programs')->nullOnDelete();

            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();

            

            $table->enum('tipe_pengajuan', ['pribadi', 'lembaga'])->default('pribadi');

            $table->decimal('nominal', 15, 2);

            $table->text('keperluan');

            $table->string('file_lampiran')->nullable(); 

            

            // Status Alur Pengajuan

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->text('catatan_verifikator')->nullable();

            

            // Bagian LPJ

            $table->enum('status_lpj', ['belum', 'menunggu_verifikasi', 'selesai'])->default('belum');

            $table->decimal('nominal_realisasi', 15, 2)->nullable();

            $table->string('file_lpj')->nullable();

            $table->timestamp('waktu_pengembalian')->nullable();

            $table->string('catatan_pengembalian')->nullable();



            $table->timestamps();

            $table->softDeletes(); 

        });

    }

public function up(): void

    {

        Schema::create('logbooks', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            // Tautkan ke Proker jika logbook ini adalah progres pengerjaan proker

            $table->foreignId('work_program_id')->nullable()->constrained('work_programs')->nullOnDelete();

            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();



            

            $table->date('tanggal');

            $table->time('jam_mulai');

            $table->time('jam_selesai');

            

            $table->string('kategori')->default('tugas_utama');

            $table->text('deskripsi_aktivitas');

            $table->string('output')->nullable(); 

            $table->string('file_bukti')->nullable(); 

            $table->string('link_bukti')->nullable(); 

            

            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');

            $table->text('catatan_verifikator')->nullable();

            

            // Verifikator harian

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('verified_at')->nullable();

            

            $table->timestamps();

            $table->softDeletes();



            // Indexing agar dashboard cepat saat memuat ribuan data logbook

            $table->index('tanggal');

            $table->index('status');

            $table->index(['unit_id', 'status']); 

        });

    }