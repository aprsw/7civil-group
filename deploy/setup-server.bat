@echo off
REM ===========================================================================
REM  Setup for the office server — run ONCE, as Administrator.
REM  Installs Apache as a Windows service (so it starts with the machine) and
REM  opens port 80 on the firewall (so other PCs on the network can connect).
REM ===========================================================================
setlocal

set "XAMPP=C:\xampp"
set "PROJECT=C:\7civil-group"

echo.
echo === 1/4  Building the frontend ===============================
pushd "%PROJECT%\frontend" || goto :missing
call npm install || goto :fail
call npm run build || goto :fail
popd

echo.
echo === 2/4  Creating the database and sample data ===============
pushd "%PROJECT%\backend" || goto :missing
"%XAMPP%\php\php.exe" bin\console install || goto :fail
popd

echo.
echo === 3/4  Installing Apache as a Windows service ==============
REM Starts automatically on boot; no one has to log in to the server.
"%XAMPP%\apache\bin\httpd.exe" -k install -n "Apache7Civil"
sc config "Apache7Civil" start= auto
net start "Apache7Civil"

echo.
echo === 4/4  Opening port 80 for the local network ===============
netsh advfirewall firewall delete rule name="7CivilGroup Automation" >nul 2>&1
netsh advfirewall firewall add rule name="7CivilGroup Automation" ^
      dir=in action=allow protocol=TCP localport=80

echo.
echo === Done =====================================================
echo  This server:    http://localhost/
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do (
  echo  Other PCs:      http://%%a/
)
echo.
echo  NOTE: give the server a STATIC IP, otherwise that address changes
echo        and everyone's bookmark breaks.
echo.
pause
exit /b 0

:missing
echo [X] Path not found. Edit XAMPP / PROJECT at the top of this file.
pause & exit /b 1
:fail
echo [X] A step failed - see the message above.
pause & exit /b 1
