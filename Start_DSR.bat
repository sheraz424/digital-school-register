@echo off
title DSR - Digital School Register
color 0A

echo ========================================
echo    DSR - Digital School Register
echo    School Management System
echo ========================================
echo.

:: Try different possible XAMPP paths
if exist "D:\XAMMP\apache\bin\httpd.exe" (
    set XAMPP_PATH=D:\XAMMP
    echo XAMPP found at: D:\XAMMP
) else if exist "C:\xampp\apache\bin\httpd.exe" (
    set XAMPP_PATH=C:\xampp
    echo XAMPP found at: C:\xampp
) else if exist "D:\xampp\apache\bin\httpd.exe" (
    set XAMPP_PATH=D:\xampp
    echo XAMPP found at: D:\xampp
) else (
    echo ERROR: XAMPP not found!
    echo Please install XAMPP or update the path in this file.
    pause
    exit
)

echo.

:: Check if Apache is running
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo Apache is already running.
) else (
    echo Starting Apache...
    start "" "%XAMPP_PATH%\apache\bin\httpd.exe"
    echo Waiting for Apache to start...
    timeout /t 3 /nobreak > nul
)

:: Check if MySQL is running
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo MySQL is already running.
) else (
    echo Starting MySQL...
    start "" "%XAMPP_PATH%\mysql\bin\mysqld.exe"
    echo Waiting for MySQL to start...
    timeout /t 3 /nobreak > nul
)

echo.
echo Opening DSR Application...

:: Try to open in default browser
start http://localhost/dsr_attendance/login.php

echo.
echo ========================================
echo    DSR is Running!
echo    URL: http://localhost/dsr_attendance/login.php
echo ========================================
echo.
echo IMPORTANT: Keep this window open while using DSR
echo.
echo To stop DSR:
echo   1. Close this window
echo   2. Run Stop_DSR.bat (or close Apache/MySQL from XAMPP)
echo.
pause