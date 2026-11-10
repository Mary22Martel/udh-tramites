@echo off
chcp 65001 >nul
echo Esto BORRA todos los datos y vuelve a cargar los datos de demostracion.
pause
php artisan migrate:fresh --seed --force
echo Listo.
pause
