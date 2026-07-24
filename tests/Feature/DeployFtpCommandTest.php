<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DeployFtpCommandTest extends TestCase
{
    /**
     * Test the --dry-run option with --all flag.
     */
    public function test_dry_run_lists_tracked_files_with_exclusions(): void
    {
        Process::fake([
            'git ls-files' => Process::result(implode("\n", [
                'composer.json',
                'config/filesystems.php',
                'routes/console.php',
                '.gitignore',
                '.env',
            ])),
            '*' => Process::result(''),
        ]);

        $this->artisan('deploy:ftp', [
            '--all' => true,
            '--dry-run' => true,
            '--host' => 'ftp.example.com',
            '--user' => 'user',
            '--password' => 'secret',
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('[Dry Run] Would upload: composer.json')
            ->expectsOutputToContain('[Dry Run] Would upload: config/filesystems.php')
            ->expectsOutputToContain('[Dry Run] Would upload: routes/console.php')
            ->doesntExpectOutputToContain('[Dry Run] Would upload: .gitignore')
            ->doesntExpectOutputToContain('[Dry Run] Would upload: .env');
    }

    /**
     * Test the --dry-run option with --status flag.
     */
    public function test_dry_run_lists_modified_files_from_git_status(): void
    {
        Process::fake([
            'git status --porcelain' => Process::result(implode("\n", [
                ' M composer.json',
                '?? config/filesystems.php',
                ' D routes/console.php', // Deleted, should be skipped
                ' M .gitignore', // Excluded, should be skipped
            ])),
            '*' => Process::result(''),
        ]);

        $this->artisan('deploy:ftp', [
            '--status' => true,
            '--dry-run' => true,
            '--host' => 'ftp.example.com',
            '--user' => 'user',
            '--password' => 'secret',
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('[Dry Run] Would upload: composer.json')
            ->expectsOutputToContain('[Dry Run] Would upload: config/filesystems.php')
            ->doesntExpectOutputToContain('[Dry Run] Would upload: routes/console.php')
            ->doesntExpectOutputToContain('[Dry Run] Would upload: .gitignore');
    }
}
