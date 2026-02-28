@echo off
setlocal

REM --- Project root (works even from UNC paths via pushd) ---
set "PROJECT_DIR=%~dp0"
pushd "%PROJECT_DIR%" >nul 2>&1
if errorlevel 1 (
  echo Failed to access project directory: %PROJECT_DIR%
  exit /b 1
)

REM --- Config: edit if needed ---
set "MYSQL_EXE=C:\xampp\mysql\bin\mysql.exe"
set "MYSQL_USER=root"
set "MYSQL_PASS="
set "DB_NAME=hums"
set "PHP_HOST=127.0.0.1"
set "PHP_PORT=8080"

if not exist "%MYSQL_EXE%" (
  echo MySQL client not found at: %MYSQL_EXE%
  echo Please install XAMPP or update MYSQL_EXE in run_local.bat
  popd
  exit /b 1
)

if "%MYSQL_PASS%"=="" (
  set "MYSQL_AUTH=-u %MYSQL_USER%"
) else (
  set "MYSQL_AUTH=-u %MYSQL_USER% -p%MYSQL_PASS%"
)

echo Creating database if not exists...
"%MYSQL_EXE%" %MYSQL_AUTH% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
  echo Failed to create DB. Ensure MySQL is running in XAMPP and credentials are correct.
  popd
  exit /b 1
)

echo Importing schema...
type "sql\schema.sql" | "%MYSQL_EXE%" %MYSQL_AUTH% %DB_NAME%
if errorlevel 1 (
  echo Failed to import schema.sql
  popd
  exit /b 1
)

echo Starting PHP dev server at http://%PHP_HOST%:%PHP_PORT%
start "" "http://%PHP_HOST%:%PHP_PORT%/public/index.html"
php -S %PHP_HOST%:%PHP_PORT%

popd
exit /b 0

