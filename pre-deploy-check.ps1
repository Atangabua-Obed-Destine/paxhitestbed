# PRE-DEPLOYMENT CHECKLIST
# Run this on your LOCAL machine BEFORE deploying to production

Write-Host "🔍 PRE-DEPLOYMENT CHECKLIST" -ForegroundColor Cyan
Write-Host "============================`n" -ForegroundColor Cyan

# Check 1: Clear all cache
Write-Host "[1/6] Clearing all cache..." -ForegroundColor Yellow
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
Write-Host "✅ Cache cleared`n" -ForegroundColor Green

# Check 2: Remove cached files
Write-Host "[2/6] Removing cached config files..." -ForegroundColor Yellow
$cacheFiles = @(
    "bootstrap\cache\config.php",
    "bootstrap\cache\services.php",
    "bootstrap\cache\packages.php"
)

foreach ($file in $cacheFiles) {
    if (Test-Path $file) {
        Remove-Item $file -Force
        Write-Host "  ✅ Removed: $file" -ForegroundColor Green
    } else {
        Write-Host "  ℹ️  Not found: $file (OK)" -ForegroundColor Gray
    }
}

# Check 3: Verify .env is in .gitignore
Write-Host "`n[3/6] Verifying .env is in .gitignore..." -ForegroundColor Yellow
$gitignore = Get-Content .gitignore
if ($gitignore -match "\.env") {
    Write-Host "✅ .env is ignored`n" -ForegroundColor Green
} else {
    Write-Host "⚠️  WARNING: .env is NOT in .gitignore!" -ForegroundColor Red
}

# Check 4: Verify cache folders are in .gitignore
Write-Host "[4/6] Verifying cache folders are ignored..." -ForegroundColor Yellow
$requiredIgnores = @(
    "/bootstrap/cache/*.php",
    "/storage/framework/views/*",
    "/storage/logs/*"
)

$allGood = $true
foreach ($pattern in $requiredIgnores) {
    if ($gitignore -match [regex]::Escape($pattern)) {
        Write-Host "  ✅ $pattern" -ForegroundColor Green
    } else {
        Write-Host "  ❌ Missing: $pattern" -ForegroundColor Red
        $allGood = $false
    }
}

if (-not $allGood) {
    Write-Host "`n⚠️  WARNING: Some cache patterns are missing from .gitignore!" -ForegroundColor Red
}

# Check 5: Check for sensitive files in git
Write-Host "`n[5/6] Checking for sensitive files in git..." -ForegroundColor Yellow
$sensitiveFiles = @(".env", "bootstrap/cache/config.php")
$foundSensitive = $false

foreach ($file in $sensitiveFiles) {
    $result = git ls-files $file 2>&1
    if ($result -and $result -ne "") {
        Write-Host "  ⚠️  FOUND IN GIT: $file" -ForegroundColor Red
        $foundSensitive = $true
    }
}

if (-not $foundSensitive) {
    Write-Host "✅ No sensitive files in git`n" -ForegroundColor Green
} else {
    Write-Host "`n⚠️  WARNING: Sensitive files found in git repository!" -ForegroundColor Red
    Write-Host "   Run: git rm --cached .env" -ForegroundColor Yellow
    Write-Host "   Run: git rm --cached bootstrap/cache/config.php" -ForegroundColor Yellow
}

# Check 6: Git status
Write-Host "[6/6] Current git status..." -ForegroundColor Yellow
git status --short

Write-Host "`n============================`n" -ForegroundColor Cyan
Write-Host "📋 DEPLOYMENT READINESS:" -ForegroundColor Cyan

if ($allGood -and -not $foundSensitive) {
    Write-Host "✅ READY TO DEPLOY!" -ForegroundColor Green
    Write-Host "`n📝 Next steps:" -ForegroundColor White
    Write-Host "   1. Commit your changes: git add . && git commit -m 'Your message'" -ForegroundColor Gray
    Write-Host "   2. Push to repository: git push origin main" -ForegroundColor Gray
    Write-Host "   3. SSH to production server" -ForegroundColor Gray
    Write-Host "   4. Run deployment script or manual commands" -ForegroundColor Gray
} else {
    Write-Host "⚠️  NOT READY - Fix the warnings above first!" -ForegroundColor Red
}

Write-Host "`n🔗 See DEPLOYMENT_FIX_URGENT.md for detailed instructions" -ForegroundColor Cyan
