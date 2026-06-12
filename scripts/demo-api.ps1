# Paysera Transfer API - Live Demo Script
# Prerequisites: Docker Desktop running, then: docker compose up -d --build

$BaseUrl = "http://localhost:8080"
$ErrorActionPreference = "Stop"

Write-Host "`n=== Paysera Transfer API Demo ===" -ForegroundColor Cyan

# 1. Health check
Write-Host "`n[1] Checking API..." -ForegroundColor Yellow
try {
    Invoke-WebRequest -Uri "$BaseUrl/api/v1/auth/login" -Method POST -UseBasicParsing -ErrorAction Stop | Out-Null
} catch {
    if ($_.Exception.Response.StatusCode -ne 401 -and $_.Exception.Message -notmatch "400") {
        Write-Host "API not reachable. Start Docker first:" -ForegroundColor Red
        Write-Host "  cd c:\paysera-transfer-api" -ForegroundColor White
        Write-Host "  docker compose up -d --build" -ForegroundColor White
        exit 1
    }
}
Write-Host "API is up." -ForegroundColor Green

# 2. Login
Write-Host "`n[2] Login (get JWT token)..." -ForegroundColor Yellow
$loginBody = '{"username":"paysera_api","password":"PayseraDemo123!"}'
$loginResp = Invoke-RestMethod -Uri "$BaseUrl/api/v1/auth/login" -Method POST `
    -ContentType "application/json" -Body $loginBody
$token = $loginResp.token
Write-Host "Token received: $($token.Substring(0, [Math]::Min(40, $token.Length)))..." -ForegroundColor Green

$headers = @{
    Authorization = "Bearer $token"
    "Content-Type" = "application/json"
}

# 3. Get accounts from database via API (seeded accounts)
Write-Host "`n[3] Fetching seeded accounts from DB..." -ForegroundColor Yellow
$dbAccounts = docker compose exec -T mysql mysql -upaysera -ppaysera paysera_transfer -N -e "
SELECT CONCAT(
  'UUID: ', LOWER(CONCAT(
    SUBSTR(HEX(id),1,8),'-',SUBSTR(HEX(id),9,4),'-',SUBSTR(HEX(id),13,4),'-',
    SUBSTR(HEX(id),17,4),'-',SUBSTR(HEX(id),21,12)
  )),
  ' | ', account_number, ' | Balance: ', balance, ' ', currency
) FROM accounts ORDER BY account_number;
" 2>$null

if ($dbAccounts) {
    $lines = $dbAccounts -split "`n" | Where-Object { $_.Trim() }
    $i = 0
    foreach ($line in $lines) {
        Write-Host "  Account $($i+1): $line" -ForegroundColor White
        $i++
    }
    # Parse first two UUIDs for transfer
    $uuidPattern = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'
    $uuids = [regex]::Matches(($lines -join ' '), $uuidPattern) | ForEach-Object { $_.Value }
    $sourceId = $uuids[0]
    $destId = $uuids[1]
} else {
    Write-Host "  Seeding accounts..." -ForegroundColor Yellow
    docker compose exec -T app php bin/console app:seed-accounts
    Start-Sleep -Seconds 2
    $raw = docker compose exec -T mysql mysql -upaysera -ppaysera paysera_transfer -N -e "SELECT LOWER(CONCAT(SUBSTR(HEX(id),1,8),'-',SUBSTR(HEX(id),9,4),'-',SUBSTR(HEX(id),13,4),'-',SUBSTR(HEX(id),17,4),'-',SUBSTR(HEX(id),21,12))) FROM accounts ORDER BY account_number LIMIT 2;"
    $ids = ($raw -split "`n" | Where-Object { $_.Trim() })
    $sourceId = $ids[0].Trim()
    $destId = $ids[1].Trim()
}

# 4. GET account
Write-Host "`n[4] GET /api/v1/accounts/$sourceId" -ForegroundColor Yellow
$account = Invoke-RestMethod -Uri "$BaseUrl/api/v1/accounts/$sourceId" -Method GET -Headers $headers
Write-Host "  Account: $($account.account_number) | Balance: $($account.balance) $($account.currency) | Status: $($account.status)" -ForegroundColor Green

# 5. POST transfer
Write-Host "`n[5] POST /api/v1/transfers (transfer 25.50 EUR)..." -ForegroundColor Yellow
$idemKey = "demo-$(Get-Date -Format 'yyyyMMddHHmmss')"
$transferBody = @{
    source_account_id = $sourceId
    destination_account_id = $destId
    amount = "25.5000"
} | ConvertTo-Json

$transferHeaders = $headers.Clone()
$transferHeaders["Idempotency-Key"] = $idemKey

$transfer = Invoke-RestMethod -Uri "$BaseUrl/api/v1/transfers" -Method POST `
    -Headers $transferHeaders -Body $transferBody
Write-Host "  Transfer ID: $($transfer.id)" -ForegroundColor Green
Write-Host "  Status: $($transfer.status) | Amount: $($transfer.amount) $($transfer.currency)" -ForegroundColor Green

# 6. GET transfer
Write-Host "`n[6] GET /api/v1/transfers/$($transfer.id)" -ForegroundColor Yellow
$fetched = Invoke-RestMethod -Uri "$BaseUrl/api/v1/transfers/$($transfer.id)" -Method GET -Headers $headers
Write-Host "  Confirmed status: $($fetched.status)" -ForegroundColor Green

# 7. Show DB entries
Write-Host "`n[7] Database entries after transfer:" -ForegroundColor Yellow
Write-Host "`n  --- ACCOUNTS ---" -ForegroundColor Cyan
docker compose exec -T mysql mysql -upaysera -ppaysera paysera_transfer -e "
SELECT LOWER(CONCAT(SUBSTR(HEX(id),1,8),'-',SUBSTR(HEX(id),9,4),'-',SUBSTR(HEX(id),13,4),'-',SUBSTR(HEX(id),17,4),'-',SUBSTR(HEX(id),21,12))) AS id,
       account_number, balance, currency, status FROM accounts;
" 2>$null

Write-Host "`n  --- TRANSFERS ---" -ForegroundColor Cyan
docker compose exec -T mysql mysql -upaysera -ppaysera paysera_transfer -e "
SELECT LOWER(CONCAT(SUBSTR(HEX(id),1,8),'-',SUBSTR(HEX(id),9,4),'-',SUBSTR(HEX(id),13,4),'-',SUBSTR(HEX(id),17,4),'-',SUBSTR(HEX(id),21,12))) AS id,
       amount, currency, status, idempotency_key, created_at FROM transfers;
" 2>$null

Write-Host "`n  --- LEDGER ENTRIES (audit trail) ---" -ForegroundColor Cyan
docker compose exec -T mysql mysql -upaysera -ppaysera paysera_transfer -e "
SELECT type, amount, balance_after, created_at FROM ledger_entries ORDER BY created_at;
" 2>$null

Write-Host "`n=== Demo complete ===" -ForegroundColor Cyan
Write-Host "`nBrowse database visually:" -ForegroundColor Yellow
Write-Host "  Open http://localhost:8081" -ForegroundColor White
Write-Host "  System: MySQL | Server: mysql | User: paysera | Password: paysera | Database: paysera_transfer" -ForegroundColor White
Write-Host "`nAPI base URL: http://localhost:8080" -ForegroundColor White
