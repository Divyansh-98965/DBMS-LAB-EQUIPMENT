@echo off
setlocal
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (
  echo PHP was not found. Install PHP 8.1+ and add php.exe to your Windows PATH.
  echo Then reopen this window and run start.bat again.
  pause
  exit /b 1
)
php -m | findstr /i "pdo_sqlite" >nul
if errorlevel 1 (
  echo The PDO SQLite extension is not enabled in this PHP installation.
  echo Enable extension=pdo_sqlite in php.ini, then reopen this window.
  pause
  exit /b 1
)
start "LabTrace" http://localhost:8000
php -S localhost:8000
