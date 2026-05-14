@echo off
title N3XT_3D_TEST
echo ===================================
echo   N3XT 3D - MODO COMPATIBILIDAD
echo ===================================
echo.
echo Presione una tecla para INICIAR el sistema...
pause

cd /d "%~dp0"

echo 1. Iniciando Backend...
if exist "backend" (
    cd backend
    start cmd /c "php artisan serve --host 0.0.0.0"
    cd ..
)

echo 2. Iniciando Frontend...
if exist "frontend" (
    cd frontend
    start cmd /c "npm run dev"
    cd ..
)

echo.
echo 3. Abriendo navegador en 5 segundos...
timeout /t 5
start http://localhost:5173

echo.
echo TODO LISTO. No cierres esta ventana.
pause
