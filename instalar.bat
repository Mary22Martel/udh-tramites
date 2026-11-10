@echo off
chcp 65001 >nul
title Instalar - Sistema de Tramites UDH
echo.
echo  =====================================================
echo    Sistema de Gestion de Tramites - Universidad de Huanuco
echo    Instalacion (solo se hace UNA vez)
echo  =====================================================
echo.

where php >nul 2>nul || (echo [ERROR] No se encontro PHP. Instala Laravel Herd: https://herd.laravel.com/windows & pause & exit /b 1)
where composer >nul 2>nul || (echo [ERROR] No se encontro Composer. Instala Laravel Herd: https://herd.laravel.com/windows & pause & exit /b 1)

echo [1/5] Descargando librerias de PHP (composer install)...
call composer install --no-interaction
if errorlevel 1 (echo [ERROR] Fallo composer install. Revisa tu conexion a internet. & pause & exit /b 1)

echo [2/5] Creando archivo de configuracion .env...
if not exist .env copy .env.example .env >nul
call php artisan key:generate --force

echo [3/5] Creando la base de datos SQLite...
if not exist database\database.sqlite type nul > database\database.sqlite

echo [4/5] Creando tablas y cargando datos de demostracion (seeders)...
call php artisan migrate:fresh --seed --force
if errorlevel 1 (echo [ERROR] Fallo la migracion. Revisa que la extension pdo_sqlite este activa. & pause & exit /b 1)

echo [5/5] Frontend React...
where npm >nul 2>nul && (
    call npm install
    call npm run build
) || (
    echo      npm no encontrado: se usara el frontend ya compilado que viene incluido.
)

echo.
echo  Listo! Ahora ejecuta  iniciar.bat  para abrir el sistema.
echo.
pause
