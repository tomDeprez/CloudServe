# CloudServe - Fonctionnalités

CloudServe est un serveur cloud personnel permettant à un utilisateur de stocker, gérer et télécharger ses fichiers depuis une interface sécurisée.

## Approche d'authentification

Authentification par **session/token personnalisé** via header `X-Auth-Token` (token = base64 de l'email).

## Statut des fonctionnalités

### ✅ 1. Authentification utilisateur

Routes API :
- `POST /register` - Création d'un compte
- `POST /login` - Connexion et récupération du token
- `GET /me` - Informations du compte connecté

Pages Web :
- `GET /login-page` - Page de connexion
- `GET /register-page` - Page d'inscription

**Commandes curl :**

```bash
# Inscription
curl -X POST http://localhost:8000/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'

# Connexion
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'

# Récupération des informations (remplacer TOKEN par le token reçu)
curl -X GET http://localhost:8000/me \
  -H "X-Auth-Token: TOKEN"
```

### ✅ 2. Gestion de fichiers

Routes :
- `POST /files/upload` - Upload d'un fichier
- `POST /files/upload-multiple` - Upload de plusieurs fichiers en même temps
- `GET /files` - Liste des fichiers de l'utilisateur
- `GET /files?parent_id={id}` - Liste des fichiers dans un dossier
- `GET /files/{id}/download` - Téléchargement d'un fichier
- `DELETE /files/{id}` - Suppression d'un fichier

**Commandes curl :**

```bash
# Upload d'un fichier (remplacer TOKEN)
curl -X POST http://localhost:8000/files/upload \
  -H "X-Auth-Token: TOKEN" \
  -F "file=@/path/to/your/file.pdf"

# Upload de plusieurs fichiers en même temps
curl -X POST http://localhost:8000/files/upload-multiple \
  -H "X-Auth-Token: TOKEN" \
  -F "files[]=@/path/to/file1.pdf" \
  -F "files[]=@/path/to/file2.jpg" \
  -F "files[]=@/path/to/file3.txt"

# Upload dans un dossier spécifique
curl -X POST http://localhost:8000/files/upload-multiple \
  -H "X-Auth-Token: TOKEN" \
  -F "files[]=@/path/to/file.pdf" \
  -F "parent_id=FOLDER_ID"

# Liste des fichiers
curl -X GET http://localhost:8000/files \
  -H "X-Auth-Token: TOKEN"

# Liste des fichiers dans un dossier
curl -X GET "http://localhost:8000/files?parent_id=FOLDER_ID" \
  -H "X-Auth-Token: TOKEN"

# Téléchargement d'un fichier (remplacer FILE_ID)
curl -X GET http://localhost:8000/files/FILE_ID/download \
  -H "X-Auth-Token: TOKEN" \
  -o downloaded_file.pdf

# Suppression d'un fichier (remplacer FILE_ID)
curl -X DELETE http://localhost:8000/files/FILE_ID \
  -H "X-Auth-Token: TOKEN"
```

### ✅ 2.1. Gestion des dossiers

Routes :
- `POST /files/folder` - Créer un dossier
- `PATCH /files/{id}/move` - Déplacer un fichier/dossier vers un autre dossier

**Commandes curl :**

```bash
# Créer un dossier à la racine
curl -X POST http://localhost:8000/files/folder \
  -H "X-Auth-Token: TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Documents"}'

# Créer un sous-dossier
curl -X POST http://localhost:8000/files/folder \
  -H "X-Auth-Token: TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Photos","parent_id":PARENT_FOLDER_ID}'

# Déplacer un fichier dans un dossier
curl -X PATCH http://localhost:8000/files/FILE_ID/move \
  -H "X-Auth-Token: TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"parent_id":FOLDER_ID}'

# Déplacer un fichier à la racine (retirer du dossier)
curl -X PATCH http://localhost:8000/files/FILE_ID/move \
  -H "X-Auth-Token: TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"parent_id":null}'
```

### ✅ 2.2. Fichiers texte éditables

Routes :
- `POST /files/text` - Créer un fichier texte
- `GET /files/{id}/content` - Récupérer le contenu d'un fichier texte
- `PUT /files/{id}/content` - Modifier le contenu d'un fichier texte

Formats supportés : .txt, .md, .json, .xml, .csv, .log, .html, .css, .js, .php, .yml, .yaml

**Commandes curl :**

```bash
# Créer un fichier texte
curl -X POST http://localhost:8000/files/text \
  -H "X-Auth-Token: TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"filename":"notes.txt","content":"Mes notes importantes"}'

# Créer un fichier texte dans un dossier
curl -X POST http://localhost:8000/files/text \
  -H "X-Auth-Token: TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"filename":"readme.md","content":"# Mon README","parent_id":FOLDER_ID}'

# Récupérer le contenu d'un fichier texte
curl -X GET http://localhost:8000/files/FILE_ID/content \
  -H "X-Auth-Token: TOKEN"

# Modifier le contenu d'un fichier texte
curl -X PUT http://localhost:8000/files/FILE_ID/content \
  -H "X-Auth-Token: TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Nouveau contenu du fichier"}'
```

### ✅ 2.3. Miniatures et visualisation

Routes :
- `GET /files/{id}/thumbnail` - Récupérer la miniature d'une image
- `GET /files/{id}/view` - Visualiser un fichier (inline)
- `GET /viewer/{id}` - Ouvrir le viewer de fichiers (page web)

**Commandes curl :**

```bash
# Télécharger la miniature d'une image
curl -X GET http://localhost:8000/files/FILE_ID/thumbnail \
  -H "X-Auth-Token: TOKEN" \
  -o thumbnail.jpg

# Visualiser un fichier (affichage inline)
curl -X GET http://localhost:8000/files/FILE_ID/view \
  -H "X-Auth-Token: TOKEN"

# Pour les fichiers texte, la réponse est JSON avec le contenu
curl -X GET http://localhost:8000/files/TEXT_FILE_ID/view \
  -H "X-Auth-Token: TOKEN" \
  | jq '.content'
```

**Fonctionnalités du viewer web :**
- Visualisation de tous les types de fichiers : images (PNG, JPG, GIF, WEBP), vidéos (MP4), audio (MP3, WAV), PDF
- Navigation entre fichiers avec touches fléchées ou boutons
- Édition en direct des fichiers texte
- Téléchargement direct depuis le viewer
- Responsive (mobile et desktop)

### ✅ 3. Espace personnel (Dashboard)

Page : `GET /dashboard`

Interface web complète avec :
- **Affichage des fichiers et dossiers** : Vue en grille avec miniatures pour les images
- **Navigation dans les dossiers** : Fil d'ariane (breadcrumb) pour naviguer dans l'arborescence
- **Upload multiple** : Glisser-déposer plusieurs fichiers ou sélection multiple
- **Création de dossiers et fichiers texte** : Boutons dédiés avec modals
- **Drag & Drop entre dossiers** : Déplacer des fichiers en les glissant sur un dossier
- **Visualisation inline** : Cliquer sur un fichier ouvre le viewer
- **Statistiques d'utilisation** : Espace utilisé / quota total
- **Actions rapides** : Suppression, téléchargement, déplacement
- **Responsive** : S'adapte au mobile et au desktop

### ✅ 4. Sécurité et droits d'accès

Implémentation :
- **FileVoter** : Contrôle l'accès aux fichiers (VIEW, DELETE, DOWNLOAD)
- **SessionAuthenticator** : Authentification via header X-Auth-Token
- Vérification que l'utilisateur ne peut accéder qu'à ses propres fichiers
- Les administrateurs ont accès à tous les fichiers

### ✅ 5. Système de quota

Règles :
- Quota par défaut : **2 Go** par utilisateur
- Vérification avant chaque upload
- Mise à jour automatique de l'espace utilisé
- Message d'erreur si quota dépassé (HTTP 507)

**Test du quota :**

```bash
# Tentative d'upload dépassant le quota
curl -X POST http://localhost:8000/files/upload \
  -H "X-Auth-Token: TOKEN" \
  -F "file=@/path/to/large/file.zip"

# Réponse attendue si quota dépassé :
# {"error":"Quota exceeded","quota":"2147483648","usedSpace":"...","fileSize":"..."}
```

### ✅ 6. Authentification administrateur & interface admin

Routes :
- `POST /admin/login` - Connexion admin (même endpoint que /login)
- `GET /admin/dashboard` - Tableau de bord global
- `GET /admin/users` - Liste JSON des utilisateurs (API)

**Commandes curl :**

```bash
# Connexion admin (premier utilisateur créé = admin automatiquement)
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"adminpass"}'

# Liste des utilisateurs (API)
curl -X GET http://localhost:8000/admin/users \
  -H "X-Auth-Token: ADMIN_TOKEN"
```

### ✅ 7. Gestion des quotas (admin)

Route : `PATCH /admin/users/{id}/quota`

Body : `{ "quota": "3G" }`

Formats acceptés : "2G", "500M", "1024K" ou nombre d'octets

**Commandes curl :**

```bash
# Modification du quota d'un utilisateur (remplacer USER_ID et ADMIN_TOKEN)
curl -X PATCH http://localhost:8000/admin/users/USER_ID/quota \
  -H "X-Auth-Token: ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"quota":"5G"}'

# Autres exemples
curl -X PATCH http://localhost:8000/admin/users/2/quota \
  -H "X-Auth-Token: ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"quota":"500M"}'
```

### ✅ 8. Suspension / Réactivation d'un utilisateur (admin)

Routes :
- `PATCH /admin/users/{id}/suspend` - Suspendre un utilisateur
- `PATCH /admin/users/{id}/activate` - Réactiver un utilisateur

Règles :
- Les utilisateurs suspendus ne peuvent pas se connecter
- Les fichiers sont conservés
- Les administrateurs ne peuvent pas être suspendus

**Commandes curl :**

```bash
# Suspension d'un utilisateur
curl -X PATCH http://localhost:8000/admin/users/USER_ID/suspend \
  -H "X-Auth-Token: ADMIN_TOKEN"

# Réactivation d'un utilisateur
curl -X PATCH http://localhost:8000/admin/users/USER_ID/activate \
  -H "X-Auth-Token: ADMIN_TOKEN"
```

## Scénario de test complet

```bash
# 1. Créer le premier utilisateur (sera admin automatiquement)
curl -X POST http://localhost:8000/register \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@cloudserve.local","password":"admin123"}'

# 2. Se connecter et récupérer le token
RESPONSE=$(curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@cloudserve.local","password":"admin123"}')

TOKEN=$(echo $RESPONSE | jq -r '.token')
echo "Token: $TOKEN"

# 3. Vérifier les infos de l'utilisateur
curl -X GET http://localhost:8000/me \
  -H "X-Auth-Token: $TOKEN"

# 4. Créer une structure de dossiers
curl -X POST http://localhost:8000/files/folder \
  -H "X-Auth-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Documents"}'

# Récupérer l'ID du dossier créé
FOLDER_RESPONSE=$(curl -X GET http://localhost:8000/files \
  -H "X-Auth-Token: $TOKEN")

FOLDER_ID=$(echo $FOLDER_RESPONSE | jq -r '.files[] | select(.filename=="Documents") | .id')
echo "Folder ID: $FOLDER_ID"

# 5. Créer un fichier texte dans le dossier
curl -X POST http://localhost:8000/files/text \
  -H "X-Auth-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"filename\":\"notes.txt\",\"content\":\"Mes notes importantes\",\"parent_id\":$FOLDER_ID}"

# 6. Upload de plusieurs fichiers en même temps
echo "Test file 1" > test1.txt
echo "Test file 2" > test2.txt
curl -X POST http://localhost:8000/files/upload-multiple \
  -H "X-Auth-Token: $TOKEN" \
  -F "files[]=@test1.txt" \
  -F "files[]=@test2.txt" \
  -F "parent_id=$FOLDER_ID"

# 7. Lister les fichiers dans le dossier
curl -X GET "http://localhost:8000/files?parent_id=$FOLDER_ID" \
  -H "X-Auth-Token: $TOKEN"

# 8. Récupérer l'ID du fichier texte et modifier son contenu
TEXT_FILE_ID=$(curl -X GET "http://localhost:8000/files?parent_id=$FOLDER_ID" \
  -H "X-Auth-Token: $TOKEN" | jq -r '.files[] | select(.filename=="notes.txt") | .id')

curl -X PUT http://localhost:8000/files/$TEXT_FILE_ID/content \
  -H "X-Auth-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Contenu mis à jour!"}'

# 9. Déplacer un fichier vers la racine
FILE_TO_MOVE=$(curl -X GET "http://localhost:8000/files?parent_id=$FOLDER_ID" \
  -H "X-Auth-Token: $TOKEN" | jq -r '.files[] | select(.filename=="test1.txt") | .id')

curl -X PATCH http://localhost:8000/files/$FILE_TO_MOVE/move \
  -H "X-Auth-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"parent_id":null}'

# 10. Créer un second utilisateur
curl -X POST http://localhost:8000/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user2@cloudserve.local","password":"user123"}'

# 11. (En tant qu'admin) Modifier le quota du second utilisateur
curl -X PATCH http://localhost:8000/admin/users/2/quota \
  -H "X-Auth-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"quota":"1G"}'

# 12. (En tant qu'admin) Suspendre le second utilisateur
curl -X PATCH http://localhost:8000/admin/users/2/suspend \
  -H "X-Auth-Token: $TOKEN"

# 13. Nettoyer les fichiers de test
rm test1.txt test2.txt
```

## Démarrer le serveur

```bash
# Avec PHP built-in server
php -S localhost:8000 -t public

# Ou avec Symfony CLI (si installé)
symfony server:start
```

## Exécuter les tests

```bash
# Tous les tests
php bin/phpunit

# Tests unitaires seulement
php bin/phpunit tests/Unit

# Tests fonctionnels seulement
php bin/phpunit tests/Functional

# Avec couverture de code
php bin/phpunit --coverage-text
```

## Architecture technique

- **Framework** : Symfony 7.2
- **Base de données** : MySQL (localhost, root, pas de mot de passe)
- **Authentification** : Sessions Symfony + token personnalisé (base64)
- **Stockage fichiers** : Système de fichiers local (`var/uploads/`)
- **Tests** : PHPUnit 11
- **Interface** : HTML5 + CSS3 pur (pas de framework CSS)
