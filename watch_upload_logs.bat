@echo off
echo === SURVEILLANCE DES LOGS D'UPLOAD ===
echo.
echo Uploadez maintenant une image via l'interface web.
echo Ce script affichera les logs en temps réel.
echo.
echo Appuyez sur Ctrl+C pour arrêter.
echo.
echo ====================================
echo.

REM Créer le fichier de log s'il n'existe pas
if not exist "C:\Tools\OpenCloud\cloudserve\var\uploads\upload_debug.log" (
    echo. > "C:\Tools\OpenCloud\cloudserve\var\uploads\upload_debug.log"
)

REM Afficher le contenu du fichier et continuer à surveiller
powershell -Command "Get-Content 'C:\Tools\OpenCloud\cloudserve\var\uploads\upload_debug.log' -Wait -Tail 20"
