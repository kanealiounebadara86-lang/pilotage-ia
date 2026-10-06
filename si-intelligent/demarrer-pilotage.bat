@echo off
REM Démarre tout Pilotage.IA en un double-clic (MySQL doit être démarré dans XAMPP).
title Pilotage.IA - demarrage
echo Demarrage des 3 services (3 fenetres vont s'ouvrir)...

start "Laravel 8000 (navigateur + telephone)" cmd /k "cd /d C:\xampp\htdocs\si-intelligent && php artisan serve --host=0.0.0.0 --port=8000"
start "Laravel 8002 (appels internes IA)" cmd /k "cd /d C:\xampp\htdocs\si-intelligent && php artisan serve --host=127.0.0.1 --port=8002"
start "Service IA 8001" cmd /k "call C:\Users\pc\anaconda3\Scripts\activate.bat si-intelligent-ai && cd /d C:\xampp\htdocs\ai-service && uvicorn app.main:app --port 8001"

echo.
echo Adresse de votre PC sur le Wi-Fi (ligne "Adresse IPv4") :
ipconfig | findstr /i "IPv4"
echo.
echo Sur le telephone, ouvrir : http://ADRESSE-IPV4:8000
pause
