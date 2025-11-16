@echo off
echo === LOGS PHP - Dernières lignes ===
echo.

REM Afficher les derniers logs PHP
php -r "echo ini_get('error_log');"
echo.

REM Si vous utilisez le serveur Symfony
echo Logs du serveur Symfony :
echo Uploadez une image maintenant et regardez les logs apparaitre...
echo.

REM Tail du fichier de log PHP par défaut
tail -f C:\Tools\OpenCloud\cloudserve\var\log\dev.log 2>nul || echo "Fichier de log Symfony non trouve"
