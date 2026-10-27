# Prefer Laragon PHP and Composer on the PATH for this shell session.
# Usage (from repo root):
#   . .\scripts\local\use-laragon.ps1
#
# Picks the newest PHP under bin\php that has a php.ini (builds without one load no
# extensions). Set LARAGON_PHP to a folder name or full path to pin a version.

$ErrorActionPreference = 'Stop'

$laragonRoot = if ($env:LARAGON_ROOT) { $env:LARAGON_ROOT } else { 'C:\laragon' }

if (-not (Test-Path -LiteralPath $laragonRoot)) {
    Write-Error "Laragon not found at $laragonRoot. Set LARAGON_ROOT or install Laragon."
}

$phpRoot = Join-Path $laragonRoot 'bin\php'

if ($env:LARAGON_PHP) {
    $phpBin = if (Test-Path -LiteralPath $env:LARAGON_PHP) { $env:LARAGON_PHP } else { Join-Path $phpRoot $env:LARAGON_PHP }

    if (-not (Test-Path -LiteralPath (Join-Path $phpBin 'php.ini'))) {
        Write-Error "LARAGON_PHP=$env:LARAGON_PHP has no php.ini at $phpBin"
    }
} else {
    $phpDirs = @(Get-ChildItem -LiteralPath $phpRoot -Directory -ErrorAction SilentlyContinue |
        Where-Object { Test-Path -LiteralPath (Join-Path $_.FullName 'php.ini') } |
        Sort-Object Name -Descending)

    if ($phpDirs.Count -eq 0) {
        Write-Error "No PHP with a php.ini under $phpRoot. Copy php.ini-development to php.ini in a PHP folder or set LARAGON_PHP."
    }

    $phpBin = $phpDirs[0].FullName
}
$composerBin = Join-Path $laragonRoot 'bin\composer'

$prepend = @($phpBin)
if (Test-Path -LiteralPath $composerBin) {
    $prepend += $composerBin
}

$env:Path = ($prepend -join ';') + ';' + $env:Path

$phpExe = Join-Path $phpBin 'php.exe'
Write-Host "Laragon PHP: $(& $phpExe -r 'echo PHP_VERSION;')"
Write-Host "Preview: http://easm.test (start Laragon Apache + MySQL first)"
