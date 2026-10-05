<?php

namespace App\Console\Commands;

use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Removes the student-side demo accounts {@see DemoAccountSeeder} creates
 * outside production — the demo siswa and orang tua — from a server they
 * reached through `migrate --seed`. The staff demo accounts (Super Admin,
 * guru, …) stay, so the school keeps its way in.
 *
 * Only the exact seeded e-mail addresses are touched. A demo siswa goes with
 * their student record and everything hanging off it (absensi, poin, izin,
 * pelanggaran, …); the demo orang tua goes with their profile. The sample
 * master data (jurusan, tahun ajaran, kelas) is left alone because real data
 * may already use it.
 *
 * Shows what it would delete unless run with --force.
 */
class PurgeDemoAccounts extends Command
{
    protected $signature = 'demo:purge
                            {--force : Hapus benar-benar; tanpa opsi ini hanya ditampilkan apa yang akan dihapus}';

    protected $description = 'Delete the demo siswa / orang tua accounts and the demo students\' data';

    public function handle(): int
    {
        $users = User::query()
            ->whereIn('email', DemoAccountSeeder::studentEmails())
            ->with(['roles', 'student', 'parentGuardian'])
            ->orderBy('email')
            ->get();

        if ($users->isEmpty()) {
            $this->info(__('Tidak ada akun siswa / orang tua demo — tidak ada yang dihapus.'));

            return self::SUCCESS;
        }

        $students = Student::query()
            ->whereIn('user_id', $users->modelKeys())
            ->withCount(['attendances', 'pointLogs', 'permits', 'violations', 'achievements', 'warningLetters', 'counselingRecords'])
            ->get();

        $this->showPlan($users, $students);

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn(__('Belum ada yang dihapus. Jalankan `php artisan demo:purge --force` untuk menghapus.'));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($users, $students): void {
            // Absensi, poin, izin, pelanggaran, prestasi, SP, catatan BK and
            // the orang tua link all cascade from the student row.
            $students->each(fn (Student $student) => $student->delete());

            // An orang tua profile outlives its login (parents.user_id is
            // nullOnDelete), so the demo profile is removed by hand.
            ParentGuardian::query()->whereIn('user_id', $users->modelKeys())->get()->each->delete();

            DB::table('sessions')->whereIn('user_id', $users->modelKeys())->delete();

            // Roles are detached by HasRoles; passkeys go with the user row.
            $users->each(function (User $user): void {
                $user->notifications()->delete();
                $user->delete();
            });
        });

        $this->info(__(':users akun siswa / orang tua demo dan :students data siswa demo dihapus. Akun staf demo tetap ada.', [
            'users' => $users->count(),
            'students' => $students->count(),
        ]));

        if (! app()->isProduction()) {
            $this->warn(__('APP_ENV bukan "production": `php artisan migrate --seed` akan membuat akun siswa demo lagi. Set APP_ENV=production di .env server.'));
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Student>  $students
     */
    private function showPlan(Collection $users, Collection $students): void
    {
        $this->table(
            [__('Email'), __('Peran'), __('Profil')],
            $users->map(fn (User $user): array => [
                $user->email,
                $user->getRoleNames()->implode(', '),
                match (true) {
                    $user->student !== null => __('Siswa :name (NIS :nis)', ['name' => $user->student->name, 'nis' => $user->student->nis]),
                    $user->parentGuardian !== null => __('Orang tua :name', ['name' => $user->parentGuardian->name]),
                    default => '—',
                },
            ])->all(),
        );

        if ($students->isEmpty()) {
            return;
        }

        $this->line(__('Data siswa demo yang ikut terhapus:'));

        $this->table(
            [__('Siswa'), __('Absensi'), __('Log Poin'), __('Izin'), __('Pelanggaran'), __('Prestasi'), __('SP'), __('BK')],
            $students->map(fn (Student $student): array => [
                $student->name,
                $student->attendances_count,
                $student->point_logs_count,
                $student->permits_count,
                $student->violations_count,
                $student->achievements_count,
                $student->warning_letters_count,
                $student->counseling_records_count,
            ])->all(),
        );
    }
}
