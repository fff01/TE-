$ErrorActionPreference = "Stop"

$baseUrl = "http://127.0.0.1/TE-"
$casesPath = "docs\eval\agent_plugin_live_cases.jsonl"
$combinedDir = "docs\eval\runs\agent_plugin_live_targeted"
$model = "deepseek-v4-pro"
$timeout = "1800"
$pollInterval = "2"
$throttle = 4

$caseIds = @()
Get-Content -LiteralPath $casesPath -Encoding UTF8 | ForEach-Object {
    if ($_.Trim() -ne "") {
        $case = $_ | ConvertFrom-Json
        $caseIds += [string]$case.case_id
    }
}

New-Item -ItemType Directory -Force -Path $combinedDir | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $combinedDir "raw_events") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $combinedDir "logs") | Out-Null
Remove-Item -LiteralPath (Join-Path $combinedDir "raw_events\*.json") -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath (Join-Path $combinedDir "logs\*.log") -Force -ErrorAction SilentlyContinue

$jobs = @()
foreach ($caseId in $caseIds) {
    while (($jobs | Where-Object { $_.State -eq "Running" }).Count -ge $throttle) {
        Start-Sleep -Seconds 5
        foreach ($done in @($jobs | Where-Object { $_.State -ne "Running" -and -not $_.HasMoreData })) {
            $null = $done
        }
    }

    $outDir = "docs\eval\runs\agent_plugin_live_targeted_$caseId"
    $logPath = Join-Path $combinedDir ("logs\$caseId.log")
    $jobs += Start-Job -Name $caseId -ScriptBlock {
        param($baseUrl, $casesPath, $caseId, $outDir, $model, $timeout, $pollInterval, $logPath)
        Set-Location "D:\wamp64\www\TE-"
        $args = @(
            "scripts\eval\run_dt_agent_live_eval.py",
            "--base-url", $baseUrl,
            "--cases", $casesPath,
            "--case-id", $caseId,
            "--agent-only",
            "--model", $model,
            "--out-dir", $outDir,
            "--timeout", $timeout,
            "--poll-interval", $pollInterval
        )
        & python @args *>&1 | Tee-Object -FilePath $logPath
        exit $LASTEXITCODE
    } -ArgumentList $baseUrl, $casesPath, $caseId, $outDir, $model, $timeout, $pollInterval, $logPath
}

$failed = @()
foreach ($job in $jobs) {
    Wait-Job $job | Out-Null
    Receive-Job $job
    if ($job.State -ne "Completed") {
        $failed += $job.Name
    }
}

foreach ($caseId in $caseIds) {
    $raw = "docs\eval\runs\agent_plugin_live_targeted_$caseId\raw_events\$caseId.json"
    if (Test-Path -LiteralPath $raw) {
        Copy-Item -LiteralPath $raw -Destination (Join-Path $combinedDir "raw_events\$caseId.json") -Force
    }
}

if ($failed.Count -gt 0) {
    Write-Host "Failed jobs: $($failed -join ', ')"
    exit 1
}

python scripts\eval\check_agent_plugin_live_results.py --cases $casesPath --run-dir $combinedDir
