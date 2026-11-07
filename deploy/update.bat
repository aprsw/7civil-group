@echo off
REM Re-deploy after pulling new code: rebuild the frontend and let the schema
REM self-heal. Does NOT touch existing data.
setlocal
set "XAMPP=C:\xampp"
set "PROJECT=C:\7civil-group"

pushd "%PROJECT%\frontend" && call npm install && call npm run build && popd
REM New tables are created on the next request by ensureSchema(); this only
REM makes that happen now rather than on a user's first click.
pushd "%PROJECT%\backend" && "%XAMPP%\php\php.exe" bin\console migrate && popd

net stop "Apache7Civil" && net start "Apache7Civil"
echo Updated.
pause
