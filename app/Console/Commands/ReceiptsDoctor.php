<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Why can a payment receipt not be opened?
 *
 * Run on the server that serves the site. A receipt row naming a file proves
 * the file was written: the upload runs before the row is created and throws
 * if the move fails. So a row whose file is absent means the file was removed
 * after the fact — and the usual culprit is a deploy, because public/uploads
 * is deliberately outside version control and a deploy that replaces the
 * directory takes the uploads with it.
 *
 * This reports what is actually on disk, so the question stops being "is it
 * the code or the server" and becomes a list of files to restore.
 */
class ReceiptsDoctor extends Command
{
    protected $signature = 'receipts:doctor {--list : Name every missing file}';

    protected $description = 'Check that uploaded payment receipts are present and servable';

    /** Each place receipts are stored, and the rows that point at it. */
    protected const SOURCES = [
        ['table' => 'payment_receipts', 'column' => 'receipt_file', 'dir' => 'payment-receipts'],
        ['table' => 'installment_payment_receipts', 'column' => 'receipt_file', 'dir' => 'installment-receipts'],
        ['table' => 'multi_payments', 'column' => 'receipt_path', 'dir' => 'multi-payments'],
    ];

    public function handle(): int
    {
        $this->line('');
        $this->info('Receipt storage check');
        $this->line(str_repeat('=', 60));

        $this->line('Application root : ' . base_path());
        $this->line('Public root      : ' . public_path());
        $this->line('Uploads root     : ' . public_path('uploads'));
        $this->line('');

        $this->reportDisk();

        $totalRows = 0;
        $totalMissing = 0;
        $missingByDir = [];

        foreach (self::SOURCES as $source) {
            if (!Schema::hasTable($source['table'])
                || !Schema::hasColumn($source['table'], $source['column'])) {
                continue;
            }

            $this->line('');
            $this->info($source['dir']);
            $this->line(str_repeat('-', 60));

            $this->reportDirectory(public_path('uploads/' . $source['dir']));

            $rows = DB::table($source['table'])
                ->whereNotNull($source['column'])
                ->where($source['column'], '!=', '')
                ->get(['id', $source['column']]);

            $missing = [];
            $legacy = [];

            foreach ($rows as $row) {
                $name = basename((string) $row->{$source['column']});
                $found = \App\Support\ReceiptFile::locate($name, $source['dir']);

                if (!$found) {
                    $missing[] = ['id' => $row->id, 'file' => $name];
                } elseif ($found['legacy']) {
                    $legacy[] = ['id' => $row->id, 'file' => $name, 'dir' => $found['dir']];
                }
            }

            $totalRows += $rows->count();
            $totalMissing += count($missing);
            $missingByDir[$source['dir']] = $missing;

            $this->line(sprintf('  Records naming a file : %d', $rows->count()));

            if ($legacy !== []) {
                $this->warn(sprintf('  In an older folder    : %d (they open, but are filed in uploads/%s)',
                    count($legacy), $legacy[0]['dir']));
            }

            if ($rows->count() === 0) {
                $this->line('  Nothing stored here yet.');
            } elseif ($missing === []) {
                $this->info(sprintf('  Present on disk       : %d  — all of them', $rows->count()));
            } else {
                $this->warn(sprintf('  MISSING from disk     : %d of %d', count($missing), $rows->count()));

                $show = $this->option('list') ? $missing : array_slice($missing, 0, 5);

                foreach ($show as $item) {
                    $this->line(sprintf('     #%-6s %s', $item['id'], $item['file']));
                }

                if (!$this->option('list') && count($missing) > 5) {
                    $this->line(sprintf('     ...and %d more (run with --list)', count($missing) - 5));
                }
            }
        }

        $this->line('');
        $this->line(str_repeat('=', 60));

        if ($totalMissing === 0) {
            $this->info(sprintf('Every one of the %d receipt files is present.', $totalRows));
            $this->line('If a receipt still will not open, the files are there but not being');
            $this->line('served: check that the web root is the public/ directory, and that');
            $this->line('the web server user can read uploads/.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d of %d receipt files are missing.', $totalMissing, $totalRows));
        $this->line('');
        $this->line('A record naming a file proves the file was written: the upload runs');
        $this->line('before the record is created and fails loudly if it cannot write. So');
        $this->line('these were removed afterwards. On a VPS that almost always means a');
        $this->line('deploy replaced the application directory — public/uploads is outside');
        $this->line('version control, so it does not come back with the code.');
        $this->line('');
        $this->line('To stop it happening again, keep uploads outside the deployed tree');
        $this->line('and link them in, e.g.:');
        $this->line('');
        $this->line('   mkdir -p /var/www/shared/uploads');
        $this->line('   mv ' . public_path('uploads') . '/* /var/www/shared/uploads/   # once');
        $this->line('   rm -rf ' . public_path('uploads'));
        $this->line('   ln -s /var/www/shared/uploads ' . public_path('uploads'));
        $this->line('');
        $this->line('and make sure your deploy never deletes or overwrites that link.');

        return self::FAILURE;
    }

    protected function reportDisk(): void
    {
        $free = @disk_free_space(public_path());
        $total = @disk_total_space(public_path());

        if ($free === false || $total === false) {
            return;
        }

        $this->line(sprintf('Disk free        : %s of %s (%.1f%% used)',
            $this->bytes($free), $this->bytes($total),
            $total > 0 ? (1 - $free / $total) * 100 : 0));

        // A full disk is one of the few ways an upload can vanish without a
        // deploy, so it is worth saying plainly.
        if ($total > 0 && $free / $total < 0.05) {
            $this->error('  The disk is nearly full. Uploads will start failing.');
        }
    }

    protected function reportDirectory(string $path): void
    {
        if (!is_dir($path)) {
            $this->error('  Directory does not exist: ' . $path);
            $this->line('    The upload code creates it on first use, so this means it was');
            $this->line('    removed, or this is a different server from the one that has it.');

            return;
        }

        $files = @scandir($path);
        $count = $files === false ? 0 : count(array_diff($files, ['.', '..']));

        $this->line(sprintf('  Directory             : %s', $path));
        $this->line(sprintf('  Files in it           : %d', $count));
        $this->line(sprintf('  Readable / writable   : %s / %s',
            is_readable($path) ? 'yes' : 'NO',
            is_writable($path) ? 'yes' : 'NO'));

        $perms = @fileperms($path);

        if ($perms !== false) {
            $owner = function_exists('posix_getpwuid')
                ? (posix_getpwuid(@fileowner($path))['name'] ?? @fileowner($path))
                : @fileowner($path);

            $this->line(sprintf('  Permissions / owner   : %s / %s',
                substr(sprintf('%o', $perms), -4), $owner));
        }

        if (!is_writable($path)) {
            $this->error('  The web server cannot write here — new uploads will fail.');
        }
    }

    protected function bytes($value): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return sprintf('%.1f %s', $value, $units[$i]);
    }
}
