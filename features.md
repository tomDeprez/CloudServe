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
- `GET /files` - Liste des fichiers de l'utilisateur
- `GET /files/{id}/download` - Téléchargement d'un fichier
- `DELETE /files/{id}` - Suppression d'un fichier

**Commandes curl :**

```bash
# Upload d'un fichier (remplacer TOKEN)
curl -X POST http://localhost:8000/files/upload \
  -H "X-Auth-Token: TOKEN" \
  -F "file=@/path/to/your/file.pdf"

# Liste des fichiers
curl -X GET http://localhost:8000/files \
  -H "X-Auth-Token: TOKEN"

# Téléchargement d'un fichier (remplacer FILE_ID)
curl -X GET http://localhost:8000/files/FILE_ID/download \
  -H "X-Auth-Token: TOKEN" \
  -o downloaded_file.pdf

# Suppression d'un fichier (remplacer FILE_ID)
curl -X DELETE http://localhost:8000/files/FILE_ID \
  -H "X-Auth-Token: TOKEN"
```

### ✅ 3. Espace personnel (Dashboard)

Page : `GET /dashboard`

Interface web listant :
- Liste des fichiers de l'utilisateur
- Taille et date d'upload de chaque fichier
- Boutons de suppression / téléchargement
- Statistiques d'utilisation de l'espace
- Formulaire d'upload

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

# 4. Uploader un fichier
echo "Test file content" > test.txt
curl -X POST http://localhost:8000/files/upload \
  -H "X-Auth-Token: $TOKEN" \
  -F "file=@test.txt"

# 5. Lister les fichiers
curl -X GET http://localhost:8000/files \
  -H "X-Auth-Token: $TOKEN"

# 6. Créer un second utilisateur
curl -X POST http://localhost:8000/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user2@cloudserve.local","password":"user123"}'

# 7. (En tant qu'admin) Modifier le quota du second utilisateur
curl -X PATCH http://localhost:8000/admin/users/2/quota \
  -H "X-Auth-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"quota":"1G"}'

# 8. (En tant qu'admin) Suspendre le second utilisateur
curl -X PATCH http://localhost:8000/admin/users/2/suspend \
  -H "X-Auth-Token: $TOKEN"

# 9. Nettoyer le fichier de test
rm test.txt
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
