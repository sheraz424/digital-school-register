@echo off
title Stop DSR - Digital School Register
color 0C

echo ========================================
echo    Stopping DSR Services
echo ========================================
echo.

echo Stopping Apache...
taskkill /f /im httpd.exe 2>nul
if "%ERRORLEVEL%"=="0" (
    echo Apache stopped successfully.
) else (
    echo Apache was not running.
)

echo.
echo Stopping MySQL...
taskkill /f /im mysqld.exe 2>nul
if "%ERRORLEVEL%"=="0" (
    echo MySQL stopped successfully.
) else (
    echo MySQL was not running.
)

echo.
echo ========================================
echo    DSR Services Stopped
echo ========================================
echo.
echo You can now close this window.
echo.
pause