#!/usr/bin/env python3
import os
import sys
import subprocess
import argparse
import ftplib
import getpass

def run_git(args, base_dir):
    """Runs a git command in the project directory and returns (success, output)."""
    try:
        result = subprocess.run(
            ['git'] + args,
            cwd=base_dir,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=True
        )
        return True, result.stdout.strip()
    except subprocess.CalledProcessError as e:
        return False, e.stderr.strip() or e.stdout.strip()

def load_env(base_dir):
    """Parses environment variables from the Laravel .env file."""
    env_vars = {}
    env_path = os.path.join(base_dir, '.env')
    if os.path.exists(env_path):
        with open(env_path, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#'):
                    continue
                if '=' in line:
                    key, val = line.split('=', 1)
                    val = val.strip().strip('"').strip("'")
                    env_vars[key.strip()] = val
    return env_vars

def is_excluded(file_path, custom_excludes):
    """Returns True if the file path matches any exclusion pattern."""
    file_path = file_path.replace('\\', '/')
    
    # Explicitly exclude any file named .gitignore
    if os.path.basename(file_path) == '.gitignore':
        return True
        
    default_excludes = [
        '.gitattributes',
        '.gitmodules',
        '.env',
        '.env.example',
        '.env.local',
        '.env.production',
        '.env.development',
        '.env.staging'
    ]
    
    if os.path.basename(file_path) in default_excludes:
        return True
        
    exclude_paths = [
        '.git/',
        'node_modules/',
        'vendor/',
        'storage/',
        'bootstrap/cache/'
    ] + custom_excludes
    
    for exclude in exclude_paths:
        exclude = exclude.replace('\\', '/')
        if file_path.startswith(exclude) or f"/{exclude}" in file_path:
            return True
            
    return False

def ensure_remote_dir_exists(ftp, remote_dir):
    """Recursively creates remote directories on the FTP server."""
    if not remote_dir or remote_dir in ('.', '/', ''):
        return True
        
    remote_dir = remote_dir.replace('\\', '/')
    parts = [p for p in remote_dir.split('/') if p]
    
    original_dir = ftp.pwd()
    if not original_dir:
        original_dir = '/'
        
    success = True
    for part in parts:
        try:
            ftp.cwd(part)
        except Exception:
            try:
                ftp.mkd(part)
                ftp.cwd(part)
            except Exception as e:
                print(f"Failed to create or change to directory '{part}': {e}")
                success = False
                break
                
    ftp.cwd(original_dir)
    return success

def get_files_to_deploy(args, base_dir):
    """Gathers the list of files to deploy based on command arguments."""
    # Option 1: All tracked files
    if args.all:
        print("Gathering all tracked files from Git...")
        success, output = run_git(['ls-files'], base_dir)
        if not success:
            print(f"Error: Failed to run git ls-files: {output}", file=sys.stderr)
            return []
        return [f for f in output.split('\n') if f]
        
    # Option 2: Status (uncommitted changes)
    if args.status:
        print("Gathering modified and untracked files from Git status...")
        success, output = run_git(['status', '--porcelain'], base_dir)
        if not success:
            print(f"Error: Failed to run git status: {output}", file=sys.stderr)
            return []
            
        files = []
        for line in output.split('\n'):
            if not line:
                continue
            status = line[:2]
            file = line[2:].strip()
            
            if 'D' in status:
                continue
                
            if ' -> ' in file:
                file = file.split(' -> ')[-1]
                
            files.append(file.strip('"'))
        return files

    # Option 3: Diff between references
    tag_name = args.tag or 'deployed-latest'
    start_ref = args.from_ref
    
    if not start_ref:
        # Check if the tag exists
        success, _ = run_git(['show-ref', '--tags', '--quiet', tag_name], base_dir)
        if success:
            start_ref = tag_name
            print(f"Using Git tag '{tag_name}' as the starting reference.")
        else:
            print(f"Git tag '{tag_name}' does not exist yet.")
            try:
                response = input("Perform a full deployment of all tracked files? (y/n) [y]: ").strip().lower()
            except (KeyboardInterrupt, EOFError):
                response = 'n'
            if response in ('', 'y', 'yes'):
                success, output = run_git(['ls-files'], base_dir)
                if not success:
                    print(f"Error: Failed to run git ls-files: {output}", file=sys.stderr)
                    return []
                return [f for f in output.split('\n') if f]
            else:
                print("Diffing from the previous commit (HEAD~1)...")
                start_ref = 'HEAD~1'

    to_ref = args.to_ref or 'HEAD'
    print(f"Diffing changes from '{start_ref}' to '{to_ref}'...")
    success, output = run_git(['diff', '--name-only', '--diff-filter=d', start_ref, to_ref], base_dir)
    if not success:
        print(f"Error: Failed to run git diff: {output}", file=sys.stderr)
        return []
        
    return [f for f in output.split('\n') if f]

def main():
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    env_vars = load_env(base_dir)

    parser = argparse.ArgumentParser(description="Deploys files modified in Git to a cPanel shared hosting server via FTP.")
    
    # Git filtering options
    parser.add_argument('--from', dest='from_ref', help='Starting commit hash or tag to diff from.')
    parser.add_argument('--to', dest='to_ref', default='HEAD', help='Ending commit hash to diff to (default: HEAD).')
    parser.add_argument('--all', action='store_true', help='Deploy all tracked files (initial deploy).')
    parser.add_argument('--status', action='store_true', help='Deploy modified and untracked files from git status.')
    parser.add_argument('--dry-run', action='store_true', help='Dry run mode. Only list files that would be uploaded.')
    
    # FTP connection options
    parser.add_argument('--host', help='FTP Host.')
    parser.add_argument('--user', help='FTP Username.')
    parser.add_argument('--password', help='FTP Password.')
    parser.add_argument('--port', type=int, help='FTP Port (default: 21).')
    parser.add_argument('--ssl', action='store_true', help='Use SSL/TLS connection (FTPS).')
    parser.add_argument('--root', help='FTP root directory path (e.g. /public_html).')
    
    # Deployment tag options
    parser.add_argument('--tag', default='deployed-latest', help='Git tag name for tracking the latest deployment.')
    parser.add_argument('--push', action='store_true', help='Push the deployment tag to the remote git origin.')
    parser.add_argument('--exclude', help='Additional comma-separated file/folder paths to exclude.')
    
    args = parser.parse_args()

    # Resolve FTP params: argument -> environment -> prompt
    host = args.host or env_vars.get('FTP_DEPLOY_HOST')
    user = args.user or env_vars.get('FTP_DEPLOY_USERNAME')
    password = args.password or env_vars.get('FTP_DEPLOY_PASSWORD')
    
    # Port default logic
    port_str = env_vars.get('FTP_DEPLOY_PORT', '21')
    port = args.port or (int(port_str) if port_str.isdigit() else 21)
    
    ssl = args.ssl or (env_vars.get('FTP_DEPLOY_SSL', 'false').lower() in ('true', '1', 'yes'))
    root = args.root or env_vars.get('FTP_DEPLOY_ROOT', '/')

    if not host:
        try:
            host = input("Enter FTP Host: ").strip()
        except (KeyboardInterrupt, EOFError):
            print("\nAborted.")
            sys.exit(1)
    if not user:
        try:
            user = input("Enter FTP Username: ").strip()
        except (KeyboardInterrupt, EOFError):
            print("\nAborted.")
            sys.exit(1)
    if not password:
        try:
            password = getpass.getpass("Enter FTP Password: ")
        except (KeyboardInterrupt, EOFError):
            print("\nAborted.")
            sys.exit(1)

    if not host or not user or not password:
        print("Error: FTP Host, Username, and Password are required.", file=sys.stderr)
        sys.exit(1)

    files = get_files_to_deploy(args, base_dir)
    if not files:
        print("No files found to deploy.")
        sys.exit(0)

    # Filter files
    custom_excludes = args.exclude.split(',') if args.exclude else []
    files_to_upload = []
    
    for file in files:
        if is_excluded(file, custom_excludes):
            print(f"Excluded: {file}")
            continue
            
        local_path = os.path.join(base_dir, file)
        if not os.path.exists(local_path):
            print(f"Skipped (Not found locally): {file}")
            continue
            
        files_to_upload.append(file)

    if not files_to_upload:
        print("No files left to deploy after applying exclusions.")
        sys.exit(0)

    print(f"Found {len(files_to_upload)} file(s) to deploy.")

    if args.dry_run:
        print("Dry Run Mode: Simulating deployment.")
        for file in files_to_upload:
            print(f"[Dry Run] Would upload: {file}")
        sys.exit(0)

    print(f"Connecting to FTP host: {host} on port {port}...")
    ftp = None
    try:
        if ssl:
            ftp = ftplib.FTP_TLS()
        else:
            ftp = ftplib.FTP()
            
        ftp.connect(host, port, timeout=90)
        ftp.login(user, password)
        
        if ssl:
            ftp.prot_p() # secure data connection
            
        ftp.set_pasv(True)
        print("FTP connection established and passive mode enabled.")

        # Root change
        root = root.rstrip('/')
        if root and root not in ('', '/'):
            print(f"Changing remote directory to root: {root}...")
            try:
                ftp.cwd(root)
            except Exception:
                if not ensure_remote_dir_exists(ftp, root):
                    print(f"Error: Failed to change or create remote root: {root}", file=sys.stderr)
                    sys.exit(1)
                ftp.cwd(root)

        uploaded_count = 0
        failed_count = 0

        for file in files_to_upload:
            local_path = os.path.join(base_dir, file)
            remote_path = file.replace('\\', '/')
            remote_dir = os.path.dirname(remote_path)
            
            print(f"Uploading: {file}...")
            
            if ensure_remote_dir_exists(ftp, remote_dir):
                try:
                    with open(local_path, 'rb') as f:
                        ftp.storbinary(f"STOR {remote_path}", f)
                    print(f"Uploaded successfully: {file}")
                    uploaded_count += 1
                except Exception as e:
                    print(f"Error: Failed to upload file content for {file}: {e}", file=sys.stderr)
                    failed_count += 1
            else:
                print(f"Error: Failed to create remote folder structure for {file}", file=sys.stderr)
                failed_count += 1

        print(f"Deployment complete. Successful uploads: {uploaded_count}, Failures: {failed_count}")
        
        if failed_count > 0:
            print("Error: Some files failed to upload.", file=sys.stderr)
            sys.exit(1)

        # Update Git tag
        success, head_hash = run_git(['rev-parse', 'HEAD'], base_dir)
        if not success:
            print("Error: Failed to get current Git HEAD hash.", file=sys.stderr)
            sys.exit(1)
            
        tag_name = args.tag or 'deployed-latest'
        print(f"Tagging current commit {head_hash} with tag '{tag_name}'...")
        success, tag_output = run_git(['tag', '-f', tag_name, 'HEAD'], base_dir)
        
        if success:
            print(f"Successfully moved tag '{tag_name}' to HEAD.")
            if args.push:
                print(f"Pushing tag '{tag_name}' to remote repository...")
                success, push_output = run_git(['push', 'origin', tag_name, '-f'], base_dir)
                if success:
                    print("Tag pushed to remote repository successfully.")
                else:
                    print(f"Warning: Failed to push tag to remote: {push_output}")
        else:
            print(f"Error: Failed to tag current commit: {tag_output}", file=sys.stderr)

    except Exception as e:
        print(f"Error: FTP operation failed: {e}", file=sys.stderr)
        sys.exit(1)
    finally:
        if ftp:
            try:
                ftp.quit()
            except Exception:
                pass

if __name__ == '__main__':
    main()
