$ErrorActionPreference = "Stop"

$logDir = "D:\wamp64\www\TE-\temp"
New-Item -ItemType Directory -Force -Path $logDir | Out-Null

$existing = Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 8000 -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Port 8000 already has a listener."
    exit 0
}

$process = Start-Process -FilePath "php" `
    -ArgumentList @("-S", "127.0.0.1:8000", "-t", "D:\wamp64\www") `
    -WindowStyle Hidden `
    -RedirectStandardOutput (Join-Path $logDir "php_server_stdout.log") `
    -RedirectStandardError (Join-Path $logDir "php_server_stderr.log") `
    -PassThru

Write-Host "Started PHP built-in server PID $($process.Id) on http://127.0.0.1:8000"
Start-Sleep -Seconds 2
