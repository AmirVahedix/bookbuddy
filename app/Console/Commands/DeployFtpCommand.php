<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

#[Signature('deploy:ftp 
    {--from= : The starting Git commit or tag to diff from} 
    {--to=HEAD : The ending Git commit to diff to} 
    {--all : Deploy all tracked files (initial deploy)} 
    {--status : Deploy modified and untracked files from git status} 
    {--dry-run : Print the list of files to upload without uploading them} 
    {--host= : FTP Host} 
    {--user= : FTP Username} 
    {--password= : FTP Password} 
    {--port= : FTP Port (defaults to 21)} 
    {--ssl : Use SSL/TLS connection (FTPS)} 
    {--root= : FTP root directory path (e.g. /public_html)} 
    {--tag=deployed-latest : The Git tag name to track the last deployed commit} 
    {--push : Push the deployment tag to the remote git origin}
    {--exclude= : Additional comma-separated files or directory paths to exclude}
')]
#[Description('Deploys files modified in Git to a cPanel shared hosting server via FTP')]
class DeployFtpCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Starting FTP Git deployment...');

        // 1. Gather FTP configuration
        $host = $this->option('host') ?: env('FTP_DEPLOY_HOST');
        $user = $this->option('user') ?: env('FTP_DEPLOY_USERNAME');
        $password = $this->option('password') ?: env('FTP_DEPLOY_PASSWORD');
        $port = (int) ($this->option('port') ?: env('FTP_DEPLOY_PORT', 21));
        $ssl = $this->option('ssl') || env('FTP_DEPLOY_SSL', false);
        $root = $this->option('root') ?: env('FTP_DEPLOY_ROOT', '/');

        if (empty($host)) {
            $host = $this->ask('Enter FTP Host');
        }
        if (empty($user)) {
            $user = $this->ask('Enter FTP Username');
        }
        if (empty($password)) {
            $password = $this->secret('Enter FTP Password');
        }

        if (empty($host) || empty($user) || empty($password)) {
            $this->error('FTP Host, Username, and Password are required.');

            return 1;
        }

        // 2. Identify files to deploy
        $files = $this->getFilesToDeploy();

        if (empty($files)) {
            $this->info('No files found to deploy.');

            return 0;
        }

        // 3. Filter files (exclude .gitignore, .env, storage, etc.)
        $customExcludes = $this->option('exclude') ? explode(',', $this->option('exclude')) : [];
        $filesToUpload = [];

        foreach ($files as $file) {
            if ($this->isExcluded($file, $customExcludes)) {
                $this->line("<comment>Excluded:</comment> {$file}");

                continue;
            }

            if (! file_exists(base_path($file))) {
                $this->line("<comment>Skipped (Not found locally):</comment> {$file}");

                continue;
            }

            $filesToUpload[] = $file;
        }

        if (empty($filesToUpload)) {
            $this->info('No files left to deploy after applying exclusions.');

            return 0;
        }

        $this->components->info(sprintf('Found %d file(s) to deploy.', count($filesToUpload)));

        // 4. FTP Connection and Upload
        $conn = null;
        try {
            if ($this->option('dry-run')) {
                $this->components->info('Dry Run Mode: Simulating deployment.');
                foreach ($filesToUpload as $file) {
                    $this->line("<info>[Dry Run]</info> Would upload: {$file}");
                }

                return 0;
            }

            $this->line("Connecting to FTP host: {$host} on port {$port}...");
            $conn = $ssl ? @ftp_ssl_connect($host, $port, 90) : @ftp_connect($host, $port, 90);

            if (! $conn) {
                $this->error("Could not connect to FTP host: {$host} on port {$port}");

                return 1;
            }

            $this->line("Logging in as user: {$user}...");
            if (! @ftp_login($conn, $user, $password)) {
                $this->error("FTP login failed for user: {$user}");

                return 1;
            }

            $this->line('Setting passive mode...');
            if (! @ftp_pasv($conn, true)) {
                $this->warn('Could not set passive mode, continuing anyway.');
            }

            // Normalise and change to root directory if specified
            $root = rtrim($root, '/');
            if ($root !== '' && $root !== '/' && $root !== '.') {
                $this->line("Changing remote directory to root: {$root}...");
                if (! @ftp_chdir($conn, $root)) {
                    // Try to create it if it doesn't exist
                    if (! $this->ensureRemoteDirExists($conn, $root)) {
                        $this->error("Failed to change or create remote root directory: {$root}");

                        return 1;
                    }
                    if (! @ftp_chdir($conn, $root)) {
                        $this->error("Failed to change to remote root directory: {$root}");

                        return 1;
                    }
                }
            }

            $uploadedCount = 0;
            $failedCount = 0;

            foreach ($filesToUpload as $file) {
                $localPath = base_path($file);
                $remotePath = str_replace('\\', '/', $file);

                $this->line("Uploading: {$file}...");

                if ($this->ensureRemoteDirExists($conn, dirname($remotePath))) {
                    if (@ftp_put($conn, $remotePath, $localPath, FTP_BINARY)) {
                        $this->line("<info>Uploaded successfully:</info> {$file}");
                        $uploadedCount++;
                    } else {
                        $this->error("Failed to upload file content: {$file}");
                        $failedCount++;
                    }
                } else {
                    $this->error("Failed to create remote folders for: {$file}");
                    $failedCount++;
                }
            }

            $this->components->info(sprintf('Deployment complete. Successful uploads: %d, Failures: %d', $uploadedCount, $failedCount));

            if ($failedCount > 0) {
                $this->error('Some files failed to upload.');

                return 1;
            }

            // 5. Update Git Tag
            $this->updateGitTag();

        } catch (\Throwable $e) {
            $this->error("An error occurred during deployment: {$e->getMessage()}");

            return 1;
        } finally {
            if ($conn) {
                @ftp_close($conn);
            }
        }

        return 0;
    }

    /**
     * Retrieve the list of file paths to deploy based on Git options.
     *
     * @return array<int, string>
     */
    protected function getFilesToDeploy(): array
    {
        // Option 1: Deploy all tracked files
        if ($this->option('all')) {
            $this->line('Gathering all tracked files from Git...');
            [$success, $output] = $this->runGit('ls-files');
            if (! $success) {
                $this->error("Failed to run git ls-files: {$output}");

                return [];
            }

            return array_filter(explode("\n", trim($output)));
        }

        // Option 2: Deploy modified files from Git status
        if ($this->option('status')) {
            $this->line('Gathering modified and untracked files from Git status...');
            [$success, $output] = $this->runGit('status --porcelain');
            if (! $success) {
                $this->error("Failed to run git status: {$output}");

                return [];
            }

            $files = [];
            foreach (explode("\n", trim($output)) as $line) {
                if (empty($line)) {
                    continue;
                }

                // Status code is first 2 characters
                $status = substr($line, 0, 2);
                $file = trim(substr($line, 2));

                // Exclude deleted files
                if (str_contains($status, 'D')) {
                    continue;
                }

                // Handle renamed files (e.g. "R  old.php -> new.php")
                if (str_contains($file, ' -> ')) {
                    $parts = explode(' -> ', $file);
                    $file = end($parts);
                }

                $files[] = trim($file, '"');
            }

            return $files;
        }

        // Option 3: Deploy diff between specific refs
        $from = $this->option('from');
        $to = $this->option('to') ?: 'HEAD';
        $tag = $this->option('tag') ?: 'deployed-latest';

        if (empty($from)) {
            // Default: Check if the Git tag exists
            [$hasTag] = $this->runGit("show-ref --tags --quiet {$tag}");
            if ($hasTag) {
                $from = $tag;
                $this->line("Using Git tag '{$tag}' as the starting reference.");
            } else {
                $this->warn("Git tag '{$tag}' does not exist yet.");
                if ($this->confirm('Perform a full deployment of all tracked files?', true)) {
                    [$success, $output] = $this->runGit('ls-files');
                    if (! $success) {
                        $this->error("Failed to run git ls-files: {$output}");

                        return [];
                    }

                    return array_filter(explode("\n", trim($output)));
                } else {
                    $this->line('Diffing from the previous commit (HEAD~1)...');
                    $from = 'HEAD~1';
                }
            }
        }

        $this->line("Diffing changes from '{$from}' to '{$to}'...");
        [$success, $output] = $this->runGit("diff --name-only --diff-filter=d {$from} {$to}");
        if (! $success) {
            $this->error("Failed to run git diff: {$output}");

            return [];
        }

        return array_filter(explode("\n", trim($output)));
    }

    /**
     * Checks if the given file path matches exclusions.
     *
     * @param  array<int, string>  $customExcludes
     */
    protected function isExcluded(string $file, array $customExcludes): bool
    {
        $file = str_replace('\\', '/', $file);

        // Explicitly exclude any file named .gitignore
        if (basename($file) === '.gitignore') {
            return true;
        }

        $defaultExcludes = [
            '.gitattributes',
            '.gitmodules',
            '.env',
            '.env.example',
            '.env.local',
            '.env.production',
            '.env.development',
            '.env.staging',
        ];

        if (in_array(basename($file), $defaultExcludes)) {
            return true;
        }

        $excludePaths = array_merge([
            '.git/',
            'node_modules/',
            'vendor/',
            'storage/',
            'bootstrap/cache/',
        ], $customExcludes);

        foreach ($excludePaths as $exclude) {
            $exclude = str_replace('\\', '/', $exclude);
            if (str_starts_with($file, $exclude) || str_contains($file, '/'.$exclude)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure the remote directory path exists, creating subfolders recursively.
     *
     * @param  resource  $conn
     */
    protected function ensureRemoteDirExists($conn, string $dir): bool
    {
        if ($dir === '.' || $dir === '/' || $dir === '') {
            return true;
        }

        $dir = str_replace('\\', '/', $dir);
        $parts = explode('/', $dir);
        $originalDir = @ftp_pwd($conn);

        if ($originalDir === false) {
            $originalDir = '/';
        }

        $success = true;

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            // Try changing to the subdirectory
            if (! @ftp_chdir($conn, $part)) {
                // Try creating the subdirectory
                if (! @ftp_mkdir($conn, $part)) {
                    $success = false;
                    break;
                }
                // Try changing into it again
                if (! @ftp_chdir($conn, $part)) {
                    $success = false;
                    break;
                }
            }
        }

        // Change back to the starting folder
        @ftp_chdir($conn, $originalDir);

        return $success;
    }

    /**
     * Move the deployment tag to the current HEAD and optionally push it to origin.
     */
    protected function updateGitTag(): void
    {
        $tag = $this->option('tag') ?: 'deployed-latest';

        [$headSuccess, $headHash] = $this->runGit('rev-parse HEAD');
        if (! $headSuccess) {
            $this->error('Failed to get current Git HEAD hash.');

            return;
        }

        $headHash = trim($headHash);

        $this->line("Tagging current commit {$headHash} with tag '{$tag}'...");
        [$tagSuccess, $tagOutput] = $this->runGit("tag -f {$tag} HEAD");

        if ($tagSuccess) {
            $this->info("Successfully moved tag '{$tag}' to HEAD.");

            if ($this->option('push')) {
                $this->line("Pushing tag '{$tag}' to remote repository...");
                [$pushSuccess, $pushOutput] = $this->runGit("push origin {$tag} -f");
                if ($pushSuccess) {
                    $this->info('Tag pushed to remote repository successfully.');
                } else {
                    $this->warn("Failed to push tag to remote origin: {$pushOutput}");
                }
            }
        } else {
            $this->error("Failed to tag current commit: {$tagOutput}");
        }
    }

    /**
     * Run a Git command in the project root.
     *
     * @return array{0: bool, 1: string}
     */
    protected function runGit(string $args): array
    {
        $result = Process::path(base_path())->run("git {$args}");

        return [
            $result->successful(),
            trim($result->output() ?: $result->errorOutput()),
        ];
    }
}
