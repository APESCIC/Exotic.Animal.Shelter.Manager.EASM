<#
.SYNOPSIS
    Live preview gate, steps 1 and 2: load the latest code, check the Herd
    preview serves it at the version Cloudron runs, and start live reload
    without opening a window. Run before any development work.

.DESCRIPTION
    Reads docs/local/profile.json, then:
    1. Stops if tracked files have uncommitted changes. Fetches origin, fast-
       forwards the branch, and merges origin/main into it (a merge commit, never
       a rebase; a conflict is aborted and reported). Nothing is pushed.
    2. Runs composer install or npm ci when a lock file is newer than the
       installed dependencies, and reports migrations that have not run (it
       never runs them).
    3. Checks Herd's nginx and MySQL are listening and /health shows the
       checked-out revision through FastCGI on the profile's PHP. Removes
       APP_VERSION from .env, so the preview reports the version in VERSION.
    4. Checks the live Cloudron app (liveUrl) reports the same version as the
       preview. Read-only: nothing on Cloudron changes.
    5. Starts Browsersync (npm run dev) in a hidden window, logging to
       storage/logs/browsersync.log, if nothing listens on its port.

    Opening the pages in Cursor's browser is the agent's step 3.

    Exit codes: 0 ready, 1 blocked (the message says why), 3 needs a decision.

.PARAMETER StopDevServer
    Stop the Browsersync server listening on the live-reload port, then exit.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\preview.ps1

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\preview.ps1 -StopDevServer
#>
[CmdletBinding()]
param(
    [switch]$NoSync,
    [switch]$NoMergeMain,
    [switch]$NoDevServer,
    [switch]$StopDevServer,
    [string]$HerdBin = (Join-Path $env:USERPROFILE '.config\herd\bin')
)

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

function Write-Step([string]$Message) { Write-Host "==> $Message" -ForegroundColor Cyan }
function Write-Ok([string]$Message) { Write-Host "    $Message" -ForegroundColor Green }
function Write-Warn([string]$Message) { Write-Host "    WARNING: $Message" -ForegroundColor Yellow }
function Stop-Preview([int]$Code, [string]$Message) { Write-Host "    STOP: $Message" -ForegroundColor Red; exit $Code }

function Invoke-Native([scriptblock]$Command) {
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { $output = & $Command 2>&1 } finally { $ErrorActionPreference = $previous }
    return [pscustomobject]@{ Code = $LASTEXITCODE; Output = ($output | Out-String).Trim() }
}

function Get-Page([string]$Url, [int]$TimeoutSec = 60) {
    try {
        return (Invoke-WebRequest $Url -UseBasicParsing -TimeoutSec $TimeoutSec).Content
    } catch {
        $response = $_.Exception.Response
        if ($null -eq $response) { return $null }
        return (New-Object IO.StreamReader $response.GetResponseStream()).ReadToEnd()
    }
}

function Get-Json([string]$Url, [int]$TimeoutSec = 60) {
    $page = Get-Page $Url $TimeoutSec
    if (-not $page) { return $null }
    try { return $page | ConvertFrom-Json } catch { return $null }
}

function Get-Listener([int]$Port) {
    Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1
}

$repo = Split-Path $PSScriptRoot -Parent
$p = Get-Content (Join-Path $repo 'docs\local\profile.json') -Raw | ConvertFrom-Json
$healthUrl = "$($p.appUrl)/health"

if ($StopDevServer) {
    $listener = Get-Listener $p.devServerPort
    if (-not $listener) { Write-Ok "Nothing listens on port $($p.devServerPort)."; exit 0 }
    Invoke-Native { taskkill /PID $listener.OwningProcess /T /F } | Out-Null
    Write-Ok "Stopped the dev server on port $($p.devServerPort) (process $($listener.OwningProcess))."
    exit 0
}

if (-not (Test-Path (Join-Path $HerdBin 'herd.bat'))) { Stop-Preview 1 "Laravel Herd was not found at $HerdBin. Install Herd for Windows, then run scripts\herd-setup.ps1." }
$phpDir = Join-Path $HerdBin $p.herdPhp
if (-not (Test-Path (Join-Path $phpDir 'php.exe'))) { Stop-Preview 1 "Herd has no PHP $($p.php). Ask the owner before running herd php:install $($p.php)." }
$nodeDir = Get-ChildItem (Join-Path $HerdBin 'nvm') -Directory -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -match "^v$($p.node)\.\d+\.\d+$" } |
    Sort-Object { [version]($_.Name -replace '^v', '') } -Descending | Select-Object -First 1
$env:Path = "$phpDir;$HerdBin;$(if ($nodeDir) { $nodeDir.FullName });" + $env:Path
if (-not $env:XDEBUG_MODE) { $env:XDEBUG_MODE = 'off' }

Push-Location $repo
try {
    # 1. Latest code ------------------------------------------------------------
    Write-Step 'Latest code'
    $before = (git rev-parse HEAD).Trim()
    $branch = (git branch --show-current).Trim()
    if ($NoSync) {
        Write-Warn 'Skipped the sync (-NoSync)'
    } else {
        $dirty = @(git status --porcelain --untracked-files=no)
        if ($dirty.Count -gt 0) { Stop-Preview 3 "$($dirty.Count) tracked files have uncommitted changes. Commit them or decide what to do before syncing." }
        $fetch = Invoke-Native { git fetch origin --prune --tags }
        if ($fetch.Code -ne 0) { Stop-Preview 1 "git fetch failed: $($fetch.Output)" }
        $upstream = Invoke-Native { git rev-parse --abbrev-ref '@{u}' }
        if ($upstream.Code -eq 0) {
            $ff = Invoke-Native { git merge --ff-only '@{u}' }
            if ($ff.Code -ne 0) { Stop-Preview 3 "$branch and $($upstream.Output) have both moved on. Merge them (no rebase), then run this again." }
        }
        if ($branch -ne 'main' -and -not $NoMergeMain) {
            $behind = [int](git rev-list --count 'HEAD..origin/main').Trim()
            if ($behind -gt 0) {
                $merge = Invoke-Native { git merge --no-edit origin/main }
                if ($merge.Code -ne 0) {
                    Invoke-Native { git merge --abort } | Out-Null
                    Stop-Preview 3 "Merging origin/main into $branch conflicts. The merge was aborted; resolve it before starting work."
                }
                Write-Ok "Merged $behind commits from origin/main into $branch (not pushed)"
            }
        }
    }
    $head = (git rev-parse HEAD).Trim()
    $short = $head.Substring(0, 12)
    Write-Ok "$branch at $short"

    # 2. Dependencies and migrations --------------------------------------------
    Write-Step 'Dependencies'
    $installed = Join-Path $repo 'vendor\composer\installed.json'
    if (-not (Test-Path $installed) -or (Get-Item 'composer.lock').LastWriteTime -gt (Get-Item $installed).LastWriteTime) {
        $run = Invoke-Native { php "$HerdBin\composer.phar" install --no-interaction --no-progress }
        if ($run.Code -ne 0) { Stop-Preview 1 "composer install failed: $($run.Output)" }
        # Composer leaves installed.json alone when nothing changed.
        (Get-Item $installed).LastWriteTime = Get-Date
        Write-Ok 'Ran composer install'
    } else { Write-Ok 'Composer dependencies are current' }
    $npmMarker = Join-Path $repo 'node_modules\.package-lock.json'
    if (-not (Test-Path $npmMarker) -or (Get-Item 'package-lock.json').LastWriteTime -gt (Get-Item $npmMarker).LastWriteTime) {
        $run = Invoke-Native { npm ci --no-audit --no-fund }
        if ($run.Code -ne 0) { Stop-Preview 1 "npm ci failed: $($run.Output)" }
        Write-Ok 'Ran npm ci (restart the dev server if it was running)'
    } else { Write-Ok 'npm dependencies are current' }
    $pending = Invoke-Native { php artisan migrate:status --pending --no-ansi }
    if ($pending.Code -eq 0 -and $pending.Output -match '\bPending\b' -and $pending.Output -notmatch 'No pending migrations') {
        Write-Warn 'There are migrations that have not run. Ask before running php artisan migrate.'
    } else { Write-Ok 'No pending migrations' }

    # 3. Herd and the preview ----------------------------------------------------
    Write-Step "Herd preview $($p.appUrl)"
    $missing = @(@(@(80, 'nginx'), @($p.database.port, 'MySQL')) | Where-Object { -not (Get-Listener $_[0]) } | ForEach-Object { "$($_[1]) (port $($_[0]))" })
    if ($missing.Count -gt 0) { Stop-Preview 1 "Not listening: $($missing -join ', '). Start Herd, and the MySQL service in Herd > Services." }
    $envFile = Join-Path $repo '.env'
    $envText = [IO.File]::ReadAllText($envFile)
    $clearCaches = $false
    if ($envText -match '(?m)^APP_VERSION=') {
        [IO.File]::WriteAllText($envFile, ($envText -replace '(?m)^APP_VERSION=[^\r\n]*(\r?\n)?', ''), (New-Object Text.UTF8Encoding $false))
        $clearCaches = $true
        Write-Ok 'Removed APP_VERSION from .env, so the preview reports the version in VERSION'
    }
    $health = Get-Json $healthUrl
    if ($clearCaches -or ($null -ne $health.local -and $health.local.git_revision -ne $short)) {
        Invoke-Native { php artisan optimize:clear } | Out-Null
        $health = Get-Json $healthUrl
    }
    if ($null -eq $health -or $null -eq $health.local) { Stop-Preview 1 "$healthUrl did not answer with the local block. Is APP_ENV=local in .env, and is the $($p.site) site linked in Herd (scripts\herd-setup.ps1)?" }
    if ($health.local.git_revision -ne $short) { Stop-Preview 1 "The preview serves $($health.local.git_revision), not $short. Check Herd's $($p.site) site points at this clone (herd links)." }
    if ($health.local.php_sapi -ne $p.phpSapi) { Stop-Preview 1 "The preview runs PHP as $($health.local.php_sapi), not FastCGI ($($p.phpSapi)). Run scripts\herd-setup.ps1 to check the Herd site." }
    if ($health.local.php -notlike "$($p.php).*") { Write-Warn "The preview runs PHP $($health.local.php), not the profile's $($p.php). Run herd isolate $($p.php) in the repository folder." }
    Write-Ok "Serves $short on PHP $($health.local.php) through FastCGI"
    if ($health.status -ne 'ok') { Write-Warn "/health reports $($health.status). The preview still works; start the Herd service it needs if the task needs it." }

    # 4. The same version as Cloudron --------------------------------------------
    Write-Step "Version against $($p.liveUrl)"
    $fileVersion = (Get-Content (Join-Path $repo 'VERSION') -Raw).Trim()
    if ($health.version -ne $fileVersion) { Stop-Preview 1 "The preview reports $($health.version), not $fileVersion from VERSION. Check APP_VERSION is not set in .env or the Windows environment, then run php artisan optimize:clear." }
    $live = Get-Json "$($p.liveUrl)/health" 20
    if ($null -eq $live -or -not $live.version) { Stop-Preview 1 "$($p.liveUrl)/health did not answer. Check you are on the APES network, then run this again." }
    if ($live.version -eq $health.version) {
        Write-Ok "Cloudron and the preview both run $($health.version)"
    } elseif ((git log -1 --format=%s).Trim() -eq "chore: release v$fileVersion") {
        Write-Warn "Cloudron runs $($live.version); this branch is the v$fileVersion release waiting to ship."
    } elseif ([version]$live.version -lt [version]$health.version) {
        Stop-Preview 3 "Cloudron runs $($live.version), older than this checkout's $($health.version). Deploying v$($health.version) is a ship-gate step; decide before starting work."
    } else {
        Stop-Preview 3 "Cloudron runs $($live.version), newer than this checkout's $($health.version). Merge origin/main into $branch, then run this again."
    }

    # 5. Dev server ------------------------------------------------------------
    Write-Step "Dev server on port $($p.devServerPort)"
    if (Get-Listener $p.devServerPort) {
        Write-Ok 'Already running'
    } elseif ($NoDevServer) {
        Write-Warn "Not running (-NoDevServer). Start it with npm run dev."
    } else {
        if (-not $nodeDir) { Stop-Preview 1 "Herd has no Node $($p.node). Ask the owner before running herd node:install $($p.node)." }
        $certificate = Join-Path $env:USERPROFILE ".config\herd\config\valet\Certificates\$($p.hostname).crt"
        if (-not (Test-Path $certificate)) { Stop-Preview 1 "Herd has no certificate for $($p.hostname), so live reload cannot serve $($p.previewUrl). Ask the owner before running herd secure $($p.site)." }
        $log = Join-Path $repo 'storage\logs\browsersync.log'
        Start-Process -FilePath $env:ComSpec -ArgumentList '/d', '/s', '/c', "`"npm run dev > `"$log`" 2>&1`"" -WorkingDirectory $repo -WindowStyle Hidden
        $deadline = (Get-Date).AddSeconds(60)
        while (-not (Get-Listener $p.devServerPort) -and (Get-Date) -lt $deadline) { Start-Sleep -Milliseconds 500 }
        if (-not (Get-Listener $p.devServerPort)) {
            $tail = if (Test-Path $log) { (Get-Content $log -Tail 15) -join "`n" } else { '(no log)' }
            Stop-Preview 1 "Browsersync did not start on port $($p.devServerPort). $log ends:`n$tail"
        }
        Write-Ok "Started npm run dev in the background (log: storage\logs\browsersync.log; stop it with -StopDevServer)"
    }
    if (-not $NoDevServer -or (Get-Listener $p.devServerPort)) {
        $viaPreview = Get-Json "$($p.previewUrl)/health" 30
        if ($null -eq $viaPreview -or $viaPreview.local.git_revision -ne $short) { Stop-Preview 1 "$($p.previewUrl) does not serve this checkout. Restart live reload with -StopDevServer, then run this again." }
        Write-Ok "$($p.previewUrl) serves $short"
    }

    Write-Step 'Ready'
    Write-Ok "Open $($p.previewUrl) (live reload of $($p.appUrl)) and $($p.liveUrl) in Cursor's browser."
    if ($before -ne $head) { Write-Ok "Code moved from $($before.Substring(0, 12)) to $short during the sync." }
} finally {
    Pop-Location
}
