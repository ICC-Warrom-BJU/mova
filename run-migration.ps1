# Railway Migration Script
# Pastikan MYSQL_URL sudah di-set di dashboard Railway
# Cara pakai: .\run-migration.ps1

$migrateFile = "D:\AI\mova\railway-migrate.php"
$migrateSeed = "D:\AI\mova\migrations\seed.php"

Write-Host "MOVA Railway Migration Runner" -ForegroundColor Green
Write-Host "==============================" -ForegroundColor Green

# Cek MYSQL_URL
$url = $env:MYSQL_URL
if (-not $url) {
    Write-Host "MYSQL_URL belum di-set. Pastikan MySQL service aktif." -ForegroundColor Yellow
    Write-Host "Atau set manual: $env:MYSQL_URL='mysql://root:pass@host:port/railway'" -ForegroundColor Yellow
    exit 1
}

php $migrateFile
php $migrateSeed