# Instructions de débogage des miniatures

## Problème actuel
Les miniatures ne sont pas générées automatiquement lors de l'upload, mais elles fonctionnent quand on utilise le bouton "Regénérer miniatures" de l'admin.

## Tests effectués ✅
1. ✅ Le service ThumbnailService fonctionne parfaitement en isolation
2. ✅ La compression d'image suivie de la génération de miniature fonctionne en CLI
3. ✅ GD extension est installée avec support WebP
4. ✅ Les miniatures existent (60 dans var/uploads/thumbnails)

## Logging ajouté
Un système de logging détaillé a été ajouté dans `FileController.php` qui enregistre dans :
`var/uploads/upload_debug.log`

Ce log contient :
- Quand une tentative de génération de miniature commence
- Le type de fichier détecté
- Le MIME type
- Si la génération réussit ou échoue
- Les exceptions complètes avec stack trace

## Comment tester
1. **Lancer le monitoring des logs** (optionnel) :
   ```
   watch_upload_logs.bat
   ```

2. **Uploader une image** via l'interface web

3. **Vérifier le fichier de log** :
   ```
   type var\uploads\upload_debug.log
   ```

4. **Vérifier si la miniature a été générée** :
   ```
   dir var\uploads\thumbnails
   ```

5. **Vérifier dans la base de données** si le champ `thumbnail` a été sauvegardé

## Problème potentiel identifié : Symlink public/uploads

⚠️ **IMPORTANT** : Le répertoire `public/uploads` est actuellement un **répertoire normal**, pas un symlink vers `var/uploads`.

Situation actuelle :
- `var/uploads` : 41 fichiers (source)
- `var/uploads/thumbnails` : 60 miniatures
- `public/uploads` : 54 fichiers (copie non synchronisée)
- `public/uploads/thumbnails` : 59 miniatures

### Solution pour Windows

Pour créer un vrai symlink sur Windows, vous avez 2 options :

**Option 1 : Activer le Mode Développeur** (recommandé)
1. Allez dans Paramètres Windows > Mise à jour et sécurité > Pour les développeurs
2. Activez "Mode développeur"
3. Supprimez le répertoire actuel :
   ```
   rmdir /s /q public\uploads
   ```
4. Créez le symlink :
   ```
   mklink /D public\uploads ..\var\uploads
   ```

**Option 2 : Utiliser une Junction** (ne nécessite pas de privilèges admin)
1. Supprimez le répertoire actuel :
   ```
   rmdir /s /q public\uploads
   ```
2. Créez une junction :
   ```
   mklink /J public\uploads ..\var\uploads
   ```

**Option 3 : Copier automatiquement** (solution de contournement)
Si vous ne pouvez pas créer de symlink, vous pouvez copier les fichiers après chaque upload.

## Prochaines étapes
1. Uploadez une image test
2. Vérifiez le contenu de `upload_debug.log`
3. Partagez le contenu du log pour diagnostic
4. Vérifiez si le champ `thumbnail` est NULL dans la base de données pour les nouveaux uploads
