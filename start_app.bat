@echo off
setlocal EnableDelayedExpansion
cd /d "%~dp0"
title OrganizAPP Launcher

REM ======================================================================
REM  OrganizAPP Launcher - Robustecido
REM ======================================================================

call :CHECK_PREREQS
if errorlevel 1 (
  echo [Launcher] CHECK_PREREQS fallo. Abortando.
  goto :END_ERROR
)

:MENU
cls
echo ==========================================
echo         Iniciando OrganizAPP
echo ==========================================
echo.
echo Selecciona el modo de inicio:
echo.
echo [d] Modo Desarrollo (Cliente + Backend concurrently)
echo [p] Modo Produccion (Solo cliente, build + preview)
echo [s] Salir
echo.
set /p mode="Elige una opcion (d/p/s): "

if /i "%mode%"=="d" (
  set "appMode=development"
  set "modeLabel=Desarrollo"
  goto :DATAPROVIDER_MENU
)
if /i "%mode%"=="p" (
  set "appMode=production"
  set "modeLabel=Produccion"
  goto :DATAPROVIDER_MENU
)
if /i "%mode%"=="s" (
  echo Saliendo...
  goto :END_OK
)
goto :MENU

:DATAPROVIDER_MENU
cls
echo ==========================================
echo    Iniciando en Modo !modeLabel!
echo ==========================================
echo.
echo Selecciona el proveedor de datos del Frontend:
echo.
echo [r] Remoto (API Express + Backend MongoDB/Supabase)
echo [s] Supabase Directo (sin Express, usando ANON KEY + RLS)
echo [l] Local (localStorage, sin Backend requerido)
echo [v] Volver al menu principal
echo.
set /p dpChoice="Elige una opcion (r/s/l/v): "

if /i "%dpChoice%"=="r" (
  set "dataProvider=remote"
  set "dpLabel=API Remota"
  goto :BACKEND_MENU
)
if /i "%dpChoice%"=="s" (
  set "dataProvider=supabase"
  set "dpLabel=Supabase (Directo)"
  set "dbProvider=supabase"
  goto :START_APP
)
if /i "%dpChoice%"=="l" (
  set "dataProvider=local"
  set "dpLabel=Datos locales"
  goto :START_APP
)
if /i "%dpChoice%"=="v" goto :MENU
goto :DATAPROVIDER_MENU

:BACKEND_MENU
cls
echo ==========================================
echo    Proveedor de Datos: !dpLabel!
echo ==========================================
echo.
echo Selecciona el proveedor de Base de Datos del Backend:
echo.
echo [m] MongoDB  (predeterminado)
echo [s] Supabase (registra factory via api/repositories/supabase)
echo [v] Volver al menu anterior
echo.
set /p dbChoice="Elige una opcion (m/s/v): "

if /i "%dbChoice%"=="m" (
  set "dbProvider=mongodb"
  set "dbLabel=MongoDB"
  goto :START_APP
)
if /i "%dbChoice%"=="s" (
  set "dbProvider=supabase"
  set "dbLabel=Supabase"
  goto :START_APP
)
if /i "%dbChoice%"=="v" goto :DATAPROVIDER_MENU
goto :BACKEND_MENU

:START_APP
cls
echo ==========================================
echo            Iniciando OrganizAPP
echo ==========================================
echo.
echo Modo:                 !modeLabel!
echo Frontend - Datos:    !dpLabel!
if /i "!dataProvider!"=="remote" (
  echo Backend - Base de Datos: !dbLabel!
)
echo.

if /i "!dataProvider!"=="local" (
  echo IMPORTANTE: Modo Local
  echo   - No se requiere servidor backend
  echo   - Los datos se guardan en localStorage del navegador
  echo   - Puedes cambiar de modo luego desde la URL o appConfig
  echo.
) else if /i "!dataProvider!"=="supabase" (
  echo IMPORTANTE: Modo Supabase (Directo)
  echo   - No se requiere servidor backend
  echo   - Cliente se conecta DIRECTAMENTE a *.supabase.co
  echo   - Usa ANON KEY + ROW LEVEL SECURITY (ver supabase_schema.sql)
  echo   - Credenciales: VITE_SUPABASE_URL y VITE_SUPABASE_ANON_KEY (en .env)
  echo.
  call :LOAD_SUPABASE_VITE_VARS
  echo   [OK] VITE_SUPABASE_URL=!VITE_SUPABASE_URL!
  echo   [OK] VITE_SUPABASE_ANON_KEY=!VITE_SUPABASE_ANON_KEY:~0,10!...
  echo.
) else (
  echo IMPORTANTE: Modo Remoto
  echo   - Backend Express corriendo en puerto 3001
  echo   - Variable DB_PROVIDER=!dbProvider!
  if /i "!dbProvider!"=="supabase" (
    echo   - Asegurate de configurar SUPABASE_URL y SUPABASE_SERVICE_ROLE_KEY en el .env del backend
  ) else (
    echo   - MongoDB debe estar disponible via MONGO_URI o MongoDB Memory Server
  )
  echo.
)

set "startUrl=http://localhost:5173/?mode=!appMode!^&dataProvider=!dataProvider!^&backend=!dbProvider!"
echo URL de inicio: !startUrl!
echo.
echo Esperando 2s antes de abrir navegador (da tiempo a Vite a iniciar)...
timeout /t 2 /nobreak > nul
start "" "!startUrl!"

if /i "!appMode!"=="development" (
  if /i "!dataProvider!"=="local" (
    echo.
    echo [Launcher] Ejecutando solo el cliente (Vite dev)...
    call npm run client:dev
    set "LAST_EXIT=!errorlevel!"
  ) else if /i "!dataProvider!"=="supabase" (
    echo.
    echo [Launcher] Ejecutando solo el cliente (Supabase Directo, sin Express)...
    call npm run client:dev
    set "LAST_EXIT=!errorlevel!"
  ) else (
    echo.
    echo [Launcher] Ejecutando cliente + backend (concurrently)...
    echo [Launcher] Seteando DB_PROVIDER=!dbProvider!
    set "DB_PROVIDER=!dbProvider!"
    call npm run dev
    set "LAST_EXIT=!errorlevel!"
  )
) else (
  echo.
  echo [Launcher] Modo Produccion: build + preview (Vite)...
  call npm run build
  set "LAST_EXIT=!errorlevel!"
  if "!LAST_EXIT!"=="0" (
    call npm run preview
    set "LAST_EXIT=!errorlevel!"
  )
)

REM ==========================================================
REM  PAUSE INCONDICIONAL (nunca mas se cierra la ventana sola
REM ==========================================================
echo.
echo [Launcher] ================================================================
if "!LAST_EXIT!"=="0" (
  echo [Launcher]  PROCESO FINALIZADO OK  (salida = !LAST_EXIT!)
) else (
  echo [Launcher]  PROCESO FINALIZADO CON ERROR  (salida = !LAST_EXIT!)
  echo [Launcher]  Revisa el LOG DE ARRIBA para ver el error real de npm/Vite.
)
echo [Launcher] ================================================================
echo [Launcher]  Para cerrar esta ventana, presiona CUALQUIER TECLA.
pause > nul
goto :EOF

REM ======================================================================
REM  Subrutinas
REM ======================================================================

:CHECK_PREREQS
echo [Launcher] Verificando prerequisitos...
echo [Launcher] Directorio del launcher: %~dp0
echo [Launcher] Directorio actual de trabajo:  %cd%
echo.

echo [1/3] Buscando Node.js...
where node >"%TEMP%\org_node.out" 2>&1
if errorlevel 1 (
  echo       FALLO: Node.js NO se encontro en el PATH.
  echo       Output de "where node":
  type "%TEMP%\org_node.out"
  echo       Solucion: Instala Node.js desde https://nodejs.org y reinicia el equipo.
  del "%TEMP%\org_node.out" 2>nul
  exit /b 1
)
echo       OK.
del "%TEMP%\org_node.out" 2>nul

echo [2/3] Buscando npm...
where npm >"%TEMP%\org_npm.out" 2>&1
if errorlevel 1 (
  echo       FALLO: npm NO se encontro en el PATH.
  echo       Output de "where npm":
  type "%TEMP%\org_npm.out"
  echo       Solucion: Normalmente se instala junto con Node.js. Reinicia y vuelve a probar.
  del "%TEMP%\org_npm.out" 2>nul
  exit /b 1
)
echo       OK.
del "%TEMP%\org_npm.out" 2>nul

set "NM_DIR=%~dp0node_modules"
echo [3/3] Verificando carpeta node_modules en  "!NM_DIR!"
if not exist "!NM_DIR!" (
  echo       FALLO: No existe la carpeta "node_modules" dentro del proyecto.
  echo       Solucion: Abre una consola CMD/PowerShell en:
  echo           %~dp0
  echo       Y ejecuta:
  echo           npm install
  echo.
  exit /b 1
)
echo       OK.
echo.

REM --- Versiones (sin for/f para evitar errorlevel fantasma en algunos entornos) ---
set "nodeVer=?"
set "npmVer=?"
for /f "usebackq delims=" %%v in (`node -v 2^>nul`) do set "nodeVer=%%v"
for /f "usebackq delims=" %%v in (`npm  -v 2^>nul`) do set "npmVer=%%v"
echo [Launcher] Node !nodeVer!  |  npm !npmVer!
echo [Launcher] node_modules detectado correctamente.
exit /b 0

:LOAD_SUPABASE_VITE_VARS
REM --- Lee SUPABASE_URL / SUPABASE_ANON_KEY desde .env (dotenv simple via for/f)
set "ENV_FILE=%~dp0.env"
if not exist "!ENV_FILE!" (
  echo   [WARN] No existe el archivo .env — se usaran placeholders vacios.
  echo          Crea .env basandote en .env.example y pega tus credenciales.
  set "VITE_SUPABASE_URL="
  set "VITE_SUPABASE_ANON_KEY="
  exit /b 0
)
set "ENV_URL="
set "ENV_ANON="
for /f "usebackq tokens=1,* delims==" %%a in ("!ENV_FILE!") do (
  set "k=%%a"
  set "v=%%b"
  if "!k!"=="SUPABASE_URL" set "ENV_URL=!v!"
  if "!k!"=="SUPABASE_ANON_KEY" set "ENV_ANON=!v!"
  if "!k!"=="VITE_SUPABASE_URL" set "VITE_SUPABASE_URL=!v!"
  if "!k!"=="VITE_SUPABASE_ANON_KEY" set "VITE_SUPABASE_ANON_KEY=!v!"
)
if not defined VITE_SUPABASE_URL if defined ENV_URL set "VITE_SUPABASE_URL=!ENV_URL!"
if not defined VITE_SUPABASE_ANON_KEY if defined ENV_ANON set "VITE_SUPABASE_ANON_KEY=!ENV_ANON!"
exit /b 0

:END_OK
echo.
echo [Launcher] Finalizado OK.
echo.
pause
goto :EOF

:END_ERROR
echo.
echo [Launcher] Ocurrio un error. Revisa los mensajes de arriba.
echo.
pause
goto :EOF
