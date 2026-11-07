@echo off
REM Copy the database and the uploaded files somewhere safe. Schedule this in
REM Task Scheduler (daily) — the SQLite file IS the whole letter archive.
setlocal
set "PROJECT=C:\7civil-group"
set "DEST=D:\backups\7civil"

for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set DT=%%I
set "STAMP=%DT:~0,8%-%DT:~8,6%"
set "OUT=%DEST%\%STAMP%"

mkdir "%OUT%" 2>nul
REM Copy the WAL sidecar files too, or the backup can miss the newest commits.
copy "%PROJECT%\backend\storage\database.sqlite*" "%OUT%\" >nul
xcopy "%PROJECT%\backend\public\uploads" "%OUT%\uploads\" /E /I /Q >nul
xcopy "%PROJECT%\backend\storage\uploads"  "%OUT%\attachments\" /E /I /Q >nul

echo Backup written to %OUT%
