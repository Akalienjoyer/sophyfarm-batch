@echo off
for /f "tokens=2 delims== " %%a in ('findstr /i "project_path" config.ini') do (
    set project_path=%%a
)
set project_path=%project_path:"=%
cd /d %project_path%

wget64.exe http://localhost/sophyfarm_batch/mandarSMS.php
