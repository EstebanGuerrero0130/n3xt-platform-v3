@echo off
title N3XT 3D SYSTEM
cd /d "%~dp0"

set BACKEND_PORT=8000
set FRONTEND_PORT=4173

echo =============================================
echo      N3XT 3D - INICIAR SISTEMA
echo =============================================
echo.
echo  Puertos:
echo    Backend  → http://localhost:%BACKEND_PORT%
echo    Frontend → http://localhost:%FRONTEND_PORT%
echo    Admin    → http://localhost:%FRONTEND_PORT%/admin/login
echo.

:: Matar procesos previos
taskkill /F /IM php.exe >nul 2>&1
taskkill /F /IM node.exe >nul 2>&1
timeout /t 2 >nul

echo [1/3] Iniciando Backend Laravel...
cd /d "%~dp0backend"
start cmd /c "php artisan serve --port=%BACKEND_PORT%"
cd /d "%~dp0"

echo [2/3] Iniciando Frontend (preview)...
cd /d "%~dp0frontend"
start cmd /c "npx vite preview --port=%FRONTEND_PORT%"
cd /d "%~dp0"

echo [3/3] Abriendo navegador...
timeout /t 4 >nul
start http://localhost:%FRONTEND_PORT%/admin/login

echo.
echo =============================================
echo  SISTEMA INICIADO CORRECTAMENTE
echo  Admin: http://localhost:%FRONTEND_PORT%/admin/login
echo  Web:   http://localhost:%FRONTEND_PORT%
echo =============================================
echo.
echo  Presiona cualquier tecla para DETENER los servidores
echo  (o cierra esta ventana)
pause >nul

:: Al cerrar, matar servidores
taskkill /F /IM php.exe >nul 2>&1
taskkill /F /IM node.exe >nul 2>&1
echo Servidores detenidos.
timeout /t 2 >nul
