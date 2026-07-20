@echo off
setlocal
cd %~dp0..
echo Creating adaptivepractice.zip...
if exist adaptivepractice.zip del adaptivepractice.zip
powershell.exe -NoProfile -Command "Compress-Archive -Path .\adaptivepractice -DestinationPath .\adaptivepractice.zip -Force"
echo.
echo ========================================================
echo ZIP file created successfully at:
echo %~dp0..\adaptivepractice.zip
echo ========================================================
echo Use this file to install on your other Moodle site.
pause
