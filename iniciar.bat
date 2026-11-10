@echo off
chcp 65001 >nul
title Sistema de Tramites UDH - http://localhost:8000
echo.
echo  Sistema de Tramites UDH en  http://localhost:8000
echo  (deja esta ventana abierta; para detenerlo presiona Ctrl + C)
echo.
start "" cmd /c "timeout /t 3 >nul & start http://localhost:8000"
php -d upload_max_filesize=20M -d post_max_size=25M artisan serve --port=8000
