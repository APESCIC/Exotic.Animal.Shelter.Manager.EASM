<#
.SYNOPSIS
    Set up EASM as its own Laravel Herd site. Safe to run more than once.

.DESCRIPTION
    Reads docs/local/profile.json, then:
      1. finds Herd's PHP 8.3, Composer, Node 22 and MySQL 8.4 and puts them
         first on PATH for this PowerShell session only, and checks the PHP
         extensions;
      2. checks Herd serves this clone as http://easm.test on PHP 8.3; -Link
         links and isolates it (herd link easm, herd isolate 8.3);
      3. copies .env.herd.example to .env if .env is missing;
      4. installs Composer and npm dependencies, and generates APP_KEY if blank;
      5. creates this project's database if missing, and runs the migrations;
      6. offers to load the fake demo data (php artisan demo:seed).

    It only touches this project's Herd site, .env and database.

.PARAMETER SeedDemo
    Load the demo data without asking.

.PARAMETER NoDemo
    Skip the demo data without asking.

.PARAMETER Link
    Link this clone as the Herd site and isolate it to the profile's PHP.
    Changes Herd's sites, so run it only when the owner has agreed.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\herd-setup.ps1 -Link -SeedDemo
#>
[CmdletBinding()]
param(
    [switch]$SeedDemo,
    [switch]$NoDemo,
    [switch]$Link,
    [string]$HerdBin = (Join-Path $env:USERPROFILE '.config\herd\bin')
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$Message) { Write-Host "==> $Message" -ForegroundColor Cyan }
function Write-Ok([string]$Message) { Write-Host "    $Message" -ForegroundColor Green }
function Write-Warn([string]$Message) { Write-Host "    WARNING: $Message" -ForegroundColor Yellow }

function Get-NewestFolder([string]$Parent, [string]$Pattern) {
    Get-ChildItem $Parent -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match $Pattern } |
        Sort-Object { [version]($_.Name -replace '^v', '') } -Descending |
        Select-Object -First 1
}

# Windows PowerShell turns a native tool's stderr into errors, so run tools
# with 'Continue' and judge them by their exit code.
function Invoke-Checked([string]$What, [scriptblock]$Command) {
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { & $Command } finally { $ErrorActionPreference = $previous }
    if ($LASTEXITCODE -ne 0) { throw "$What failed (exit $LASTEXITCODE)." }
}

function Get-EnvValue([string]$Path, [string]$Key) {
    $line = Get-Content $Path | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if ($null -eq $line) { return $null }
    return ($line -replace "^$Key=", '').Trim('"')
}

if ($SeedDemo -and $NoDemo) { throw 'Use -SeedDemo or -NoDemo, not both.' }

$repo = Split-Path $PSScriptRoot -Parent
$p = Get-Content (Join-Path $repo 'docs\local\profile.json') -Raw | ConvertFrom-Json

Write-Step "EASM Herd set-up ($($p.appUrl))"

# 1. Herd tools ---------------------------------------------------------------
Write-Step 'Checking Herd'
$herd = Join-Path $HerdBin 'herd.bat'
if (-not (Test-Path $herd)) { throw "Laravel Herd was not found at $HerdBin. Install Herd for Windows (https://herd.laravel.com/windows), or pass -HerdBin." }

$phpDir = Join-Path $HerdBin $p.herdPhp
if (-not (Test-Path (Join-Path $phpDir 'php.exe'))) { throw "Herd has no PHP $($p.php). Ask the owner before running: herd php:install $($p.php)" }
$composerPhar = Join-Path $HerdBin 'composer.phar'
if (-not (Test-Path $composerPhar)) { throw "Composer was not found at $composerPhar." }
$nodeDir = Get-NewestFolder (Join-Path $HerdBin 'nvm') "^v$($p.node)\.\d+\.\d+$"
if (-not $nodeDir) { throw "Herd has no Node $($p.node). Ask the owner before running: herd node:install $($p.node)" }
$mysqlDir = Get-NewestFolder (Join-Path $HerdBin 'services\mysql') "^$([regex]::Escape($p.database.version))\.\d+$"
if (-not $mysqlDir) { throw "Herd has no MySQL $($p.database.version) service. Add it in Herd > Services." }

$env:Path = "$phpDir;$HerdBin;$($nodeDir.FullName);$($mysqlDir.FullName)\bin;" + $env:Path
if (-not $env:XDEBUG_MODE) { $env:XDEBUG_MODE = 'off' }

$phpVersion = "$(php -r 'echo PHP_VERSION;')".Trim()
Write-Ok "PHP $phpVersion  ($phpDir)"
Write-Ok "Node $("$(node -v)".Trim()), MySQL $($mysqlDir.Name)"
if ($phpVersion -notlike "$($p.php).*") { throw "PHP on PATH is $phpVersion, not $($p.php)." }

$loaded = "$(php -r "echo implode(',', array_map('strtolower', get_loaded_extensions()));")".Split(',')
$missing = @($p.phpExtensions | Where-Object { $loaded -notcontains $_ })
if ($missing.Count -gt 0) { throw "PHP $phpVersion is missing these extensions: $($missing -join ', '). Turn them on with herd ini $($p.php), then herd restart php." }
Write-Ok "PHP extensions: $($p.phpExtensions -join ', ')"

# 2. Herd site ----------------------------------------------------------------
Write-Step "Herd site $($p.appUrl)"
Push-Location $repo
try {
    if ($Link) {
        Invoke-Checked 'herd link' { & $herd link $p.site }
        Invoke-Checked 'herd isolate' { & $herd isolate $p.php }
        Write-Ok "Linked this clone as $($p.hostname) on PHP $($p.php)"
    }
    $links = "$(& $herd links 2>&1 | Out-String)"
    $repoFull = (Resolve-Path $repo).Path.TrimEnd('\')
    $row = $links -split "`r?`n" | Where-Object { $_ -match "^\|\s*$([regex]::Escape($p.site))\s*\|" } | Select-Object -First 1
    if (-not $row) {
        Write-Warn "Herd does not serve $($p.hostname) yet. Run this script with -Link (it changes Herd's sites, so ask the owner first)."
    } elseif ($row -notmatch [regex]::Escape($repoFull)) {
        throw "Herd's $($p.site) site points at another folder: $($row.Trim()). Unlink it yourself (herd unlink $($p.site)), then run this again."
    } elseif ($row -notmatch "\|\s*$([regex]::Escape($p.php))\s*\|") {
        Write-Warn "Herd serves $($p.hostname) but not on PHP $($p.php). Run: herd isolate $($p.php)"
    } else {
        Write-Ok "Herd serves this clone on PHP $($p.php)"
    }
} finally {
    Pop-Location
}

Push-Location $repo
try {
    # 3. .env -------------------------------------------------------------------
    Write-Step '.env'
    $envFile = Join-Path $repo '.env'
    if (Test-Path $envFile) { Write-Ok '.env already exists, left as it is' }
    else { Copy-Item (Join-Path $repo '.env.herd.example') $envFile; Write-Ok 'Copied .env.herd.example to .env' }
    foreach ($check in @(@('APP_ENV', 'local'), @('APP_URL', $p.appUrl), @('DB_DATABASE', $p.database.name), @('DB_HOST', $p.database.host), @('MAIL_PORT', "$($p.mail.port)"))) {
        $value = Get-EnvValue $envFile $check[0]
        if ($value -ne $check[1]) { Write-Warn "$($check[0]) in .env is '$value'. The local profile expects '$($check[1])'." }
    }

    # 4. Dependencies -----------------------------------------------------------
    Write-Step 'Dependencies'
    Invoke-Checked 'composer install' { php $composerPhar install --no-interaction --no-progress }
    if (Test-Path (Join-Path $repo 'node_modules')) { Write-Ok 'node_modules already there. Run npm ci yourself after package-lock.json changes.' }
    else { Invoke-Checked 'npm ci' { npm ci --no-audit --no-fund } }
    if (-not (Get-EnvValue $envFile 'APP_KEY')) { Invoke-Checked 'key:generate' { php artisan key:generate --no-interaction } }
    else { Write-Ok 'APP_KEY already set' }

    # 5. Database ---------------------------------------------------------------
    $dbName = Get-EnvValue $envFile 'DB_DATABASE'
    $dbHost = Get-EnvValue $envFile 'DB_HOST'
    $dbPort = Get-EnvValue $envFile 'DB_PORT'
    $dbUser = Get-EnvValue $envFile 'DB_USERNAME'
    Write-Step "Database $dbName"
    if ($dbName -notmatch '^[A-Za-z0-9_]+$') { throw "DB_DATABASE '$dbName' must use only letters, numbers and underscores." }
    if ((Get-EnvValue $envFile 'DB_PASSWORD')) { throw 'DB_PASSWORD is set in .env. Herd MySQL uses root with no password; the script only creates databases that way.' }
    try { Invoke-Checked 'mysqladmin ping' { mysqladmin -u $dbUser -h $dbHost -P $dbPort --connect-timeout=5 ping 2>$null | Out-Null } }
    catch { throw "MySQL is not answering on ${dbHost}:${dbPort}. Start the MySQL service in Herd > Services, then run this again." }
    Invoke-Checked 'CREATE DATABASE' { mysql -u $dbUser -h $dbHost -P $dbPort -e "CREATE DATABASE IF NOT EXISTS ``$dbName`` CHARACTER SET $($p.database.charset) COLLATE $($p.database.collation);" }
    Write-Ok "$dbName is ready"
    Invoke-Checked 'migrate' { php artisan migrate --no-interaction }

    # 6. Demo data --------------------------------------------------------------
    Write-Step 'Demo data'
    $loadDemo = $SeedDemo.IsPresent
    if (-not $SeedDemo -and -not $NoDemo) { $loadDemo = (Read-Host '    Load the fake demo data now? [y/N]') -match '^(y|yes)$' }
    if ($loadDemo) { Invoke-Checked 'demo:seed' { php artisan demo:seed --no-interaction } }
    else { Write-Ok 'Skipped. Load it any time with: php artisan demo:seed' }
} finally {
    Pop-Location
}

Write-Step 'Done'
Write-Ok "Before any work: powershell -NoProfile -ExecutionPolicy Bypass -File scripts\preview.ps1"
Write-Ok "It checks the version against $($p.liveUrl) and starts live reload hidden (Browsersync on port $($p.devServerPort), proxying $($p.appUrl))."
