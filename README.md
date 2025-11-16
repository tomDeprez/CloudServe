# CloudServe

<div align="center">
  <h3>☁️ Serveur de stockage cloud personnel et sécurisé</h3>
  <p>Une solution complète de gestion de fichiers avec interface web moderne, compression automatique et API REST</p>
</div>

---

## 📋 Table des matières

- [À propos](#-à-propos)
- [Fonctionnalités](#-fonctionnalités)
- [Prérequis](#-prérequis)
- [Installation Développement](#-installation-développement)
- [Installation Production](#-installation-production)
- [Configuration](#-configuration)
- [Utilisation](#-utilisation)
- [Tests](#-tests)
- [Sécurité](#-sécurité)
- [Architecture](#-architecture)

---

## 🎯 À propos

CloudServe est une application de stockage cloud auto-hébergée construite avec Symfony 7.2. Elle permet aux utilisateurs de :

- **Stocker des fichiers** de manière sécurisée avec gestion des quotas
- **Compression automatique** des images, vidéos et audio sans perte de qualité
- **Miniatures automatiques** pour tous les fichiers images
- **Gérer plusieurs utilisateurs** avec système d'administration
- **Interface moderne** avec modales, toasts et loaders élégants
- **Détection de doublons** par hashing SHA-256
- **Navigation par dossiers** avec drag & drop
- **Accéder via une API REST complète** pour l'intégration avec d'autres services

Le projet utilise une architecture moderne avec :
- Symfony 7.2 (PHP 8.1+)
- MySQL pour la persistence
- Authentication par token personnalisée
- Interface utilisateur moderne (HTML/CSS/JS pur)
- Compression automatique (GD, FFmpeg)
- Design responsive mobile-first

---

## ✨ Fonctionnalités

### 👤 Gestion des utilisateurs
- ✅ Inscription et connexion sécurisées
- ✅ Authentification par token (X-Auth-Token)
- ✅ Gestion des profils utilisateurs
- ✅ Système de quotas personnalisables (2 GB par défaut)
- ✅ Suspension/activation de comptes

### 📁 Gestion des fichiers
- ✅ Upload de fichiers avec validation de quota
- ✅ **Détection de doublons** par hash SHA-256
- ✅ **Compression automatique** des images (WebP, JPEG, PNG)
- ✅ **Compression vidéo** (H.264, CRF 23, max 1920x1080)
- ✅ **Compression audio** (AAC 192kbps, 44.1kHz)
- ✅ **Miniatures automatiques** pour toutes les images
- ✅ Navigation par dossiers avec arborescence
- ✅ Drag & drop entre dossiers
- ✅ Sélection multiple avec lasso
- ✅ Téléchargement sécurisé
- ✅ Suppression récursive (dossiers + contenu)
- ✅ Support de tous types de fichiers

### 🎨 Interface utilisateur moderne
- ✅ **Modales personnalisées** (plus de popups natives)
- ✅ **Notifications toast** élégantes
- ✅ **Loaders animés** pour toutes les opérations
- ✅ **Progress bar** pour les uploads
- ✅ **Gestionnaire de fichiers** en popup flottante
- ✅ Design moderne et professionnel
- ✅ Responsive (mobile, tablette, desktop)
- ✅ Dashboard avec statistiques en temps réel
- ✅ Animations fluides

### 👑 Administration
- ✅ Dashboard d'administration complet
- ✅ Gestion des utilisateurs (quotas, suspension)
- ✅ **Régénération manuelle des miniatures**
- ✅ Statistiques globales
- ✅ Premier utilisateur = admin automatique
- ✅ Interface responsive desktop/mobile

### 🔐 Sécurité
- ✅ Hash SHA-256 pour détection de doublons
- ✅ Suppression automatique des miniatures
- ✅ Isolation complète des utilisateurs
- ✅ Validation stricte des quotas
- ✅ Protection contre les uploads malveillants

---

## 🔧 Prérequis

### Développement
- PHP 8.1 ou supérieur
- Composer 2.x
- MySQL 5.7+ ou MariaDB 10.3+
- Extensions PHP requises :
  - `pdo_mysql` - Base de données
  - `mbstring` - Manipulation de chaînes
  - `xml` - Parsing XML
  - `ctype` - Validation de caractères
  - `iconv` - Conversion d'encodage
  - **`gd`** - ⚠️ **OBLIGATOIRE** pour miniatures et compression images
  - `fileinfo` - Détection MIME type

### Production
- Serveur web (Apache 2.4+ ou Nginx 1.18+)
- PHP 8.1+ avec PHP-FPM
- MySQL 5.7+ ou MariaDB 10.3+
- **FFmpeg** (optionnel mais recommandé pour compression vidéo/audio)
- Certificat SSL (recommandé)
- Au moins 1 Go de RAM (2 Go recommandé)
- Espace disque selon vos besoins de stockage

### Extensions PHP pour production

**OBLIGATOIRES** :
```bash
# Debian/Ubuntu
sudo apt install php8.1-fpm php8.1-mysql php8.1-gd php8.1-xml php8.1-mbstring php8.1-curl

# CentOS/RHEL
sudo yum install php-fpm php-mysqlnd php-gd php-xml php-mbstring php-curl
```

**Vérifier les extensions** :
```bash
php -m | grep -E 'gd|mysql|mbstring|xml'
```

**Vérifier le support WebP dans GD** :
```bash
php -r "var_dump(function_exists('imagewebp'));"
# Doit retourner : bool(true)
```

### FFmpeg pour compression vidéo/audio (optionnel)

```bash
# Debian/Ubuntu
sudo apt install ffmpeg

# CentOS/RHEL
sudo yum install epel-release
sudo yum install ffmpeg

# Vérifier l'installation
ffmpeg -version
```

---

## 🚀 Installation Développement

### 1. Cloner le projet

```bash
git clone <votre-repo>
cd cloudserve
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer l'environnement

```bash
# Copier le fichier d'environnement
cp .env .env.local

# Éditer .env.local
nano .env.local
```

```env
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=changez_cette_cle_secrete
DATABASE_URL="mysql://root:@127.0.0.1:3306/cloudserve"
```

### 4. Créer la base de données

```bash
# Créer la base
php bin/console doctrine:database:create

# Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Créer les dossiers nécessaires

```bash
# Le dossier uploads est maintenant dans public/
mkdir -p public/uploads/thumbnails
chmod -R 777 public/uploads
```

### 6. Démarrer le serveur

```bash
# Avec PHP built-in server
php -S localhost:8000 -t public

# Ou avec Symfony CLI
symfony server:start
```

### 7. Accéder à l'application

- Interface : http://localhost:8000
- Inscription : http://localhost:8000/register-page
- Dashboard : http://localhost:8000/dashboard
- Admin : http://localhost:8000/admin/dashboard

**Important** : Le premier utilisateur créé devient automatiquement administrateur.

---

## 🌐 Installation Production

### Option 1 : Apache (Recommandé)

#### 1. Préparer l'environnement

```bash
# Se positionner dans le dossier web
cd /var/www/html

# Cloner le projet
git clone <votre-repo> cloudserve
cd cloudserve

# Installer les dépendances (production)
composer install --no-dev --optimize-autoloader

# Créer le fichier .env.local pour la production
cp .env .env.local
```

#### 2. Configurer .env.local

```bash
nano .env.local
```

```env
# Mode production
APP_ENV=prod
APP_DEBUG=0

# Générer une clé secrète sécurisée (minimum 32 caractères)
# Utiliser : php -r "echo bin2hex(random_bytes(32));"
APP_SECRET=votre_cle_secrete_64_caracteres_hexadecimaux

# Base de données production
DATABASE_URL="mysql://cloudserve_user:mot_de_passe_securise@localhost:3306/cloudserve_prod?serverVersion=8.0&charset=utf8mb4"
```

#### 3. Configurer MySQL

```bash
mysql -u root -p
```

```sql
-- Créer la base de données avec bon charset
CREATE DATABASE cloudserve_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Créer un utilisateur dédié avec mot de passe fort
CREATE USER 'cloudserve_user'@'localhost' IDENTIFIED BY 'VotreMotDePasseTresSecurise123!';

-- Donner les permissions nécessaires
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON cloudserve_prod.* TO 'cloudserve_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 4. Exécuter les migrations

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

#### 5. Configurer les permissions (IMPORTANT)

```bash
# Créer le dossier uploads dans public/
mkdir -p public/uploads/thumbnails

# Donner les permissions appropriées
# www-data pour Apache/Nginx, nginx pour Nginx seul
chown -R www-data:www-data public/uploads/
chown -R www-data:www-data var/

chmod -R 775 public/uploads/
chmod -R 775 var/

# Sécuriser les fichiers sensibles
chmod 600 .env.local
chown root:root .env.local
```

#### 6. Configuration PHP pour production

Éditer `/etc/php/8.1/fpm/php.ini` (ou `/etc/php/8.1/apache2/php.ini` si mod_php) :

```ini
; Uploads de fichiers volumineux
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M

; Extensions requises (vérifier qu'elles sont activées)
extension=gd
extension=pdo_mysql
extension=mbstring
extension=xml
extension=fileinfo

; Sécurité
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Sessions
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

Vérifier la configuration GD :

```bash
php -i | grep -A 10 "GD Support"
```

Vous devez voir :
```
GD Support => enabled
WebP Support => enabled
JPEG Support => enabled
PNG Support => enabled
```

#### 7. Configurer Apache

Créer le fichier de configuration :

```bash
sudo nano /etc/apache2/sites-available/cloudserve.conf
```

```apache
<VirtualHost *:80>
    ServerName cloudserve.example.com
    ServerAdmin admin@example.com
    DocumentRoot /var/www/html/cloudserve/public

    # Configuration du répertoire public
    <Directory /var/www/html/cloudserve/public>
        AllowOverride All
        Require all granted

        # Activer la réécriture d'URL
        FallbackResource /index.php

        # Sécurité des en-têtes
        Header set X-Content-Type-Options "nosniff"
        Header set X-Frame-Options "SAMEORIGIN"
        Header set X-XSS-Protection "1; mode=block"
    </Directory>

    # Augmenter la limite d'upload
    LimitRequestBody 104857600

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/cloudserve_error.log
    CustomLog ${APACHE_LOG_DIR}/cloudserve_access.log combined

    # Niveau de log
    LogLevel warn

    # Sécurité : bloquer l'accès aux fichiers sensibles
    <Directory /var/www/html/cloudserve>
        <FilesMatch "\.(env|git|yml|yaml)$">
            Require all denied
        </FilesMatch>
    </Directory>

    # Bloquer l'accès aux dossiers sensibles
    <DirectoryMatch "/var/www/html/cloudserve/(var|config|src|migrations)">
        Require all denied
    </DirectoryMatch>
</VirtualHost>
```

#### 8. Activer le site et les modules

```bash
# Activer les modules nécessaires
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod ssl

# Activer le site
sudo a2ensite cloudserve.conf

# Désactiver le site par défaut (optionnel)
sudo a2dissite 000-default.conf

# Tester la configuration Apache
sudo apache2ctl configtest

# Recharger Apache
sudo systemctl reload apache2
```

#### 9. Configurer SSL avec Let's Encrypt (OBLIGATOIRE en production)

```bash
# Installer Certbot
sudo apt install certbot python3-certbot-apache

# Obtenir un certificat SSL (suivez les instructions)
sudo certbot --apache -d cloudserve.example.com

# Vérifier le renouvellement automatique
sudo certbot renew --dry-run
```

Le fichier sera automatiquement mis à jour avec la configuration SSL.

---

### Option 2 : Nginx + PHP-FPM

#### Configuration Nginx

```bash
sudo nano /etc/nginx/sites-available/cloudserve
```

```nginx
server {
    listen 80;
    server_name cloudserve.example.com;
    root /var/www/html/cloudserve/public;

    # Logs
    error_log /var/log/nginx/cloudserve_error.log;
    access_log /var/log/nginx/cloudserve_access.log;

    # Upload de fichiers volumineux
    client_max_body_size 100M;
    client_body_timeout 300s;

    # Timeouts
    fastcgi_read_timeout 300s;

    # Index
    index index.php;

    # Gestion des URLs
    location / {
        try_files $uri /index.php$is_args$args;
    }

    # PHP-FPM
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param PATH_INFO $fastcgi_path_info;

        # Augmenter les timeouts
        fastcgi_read_timeout 300s;
        fastcgi_send_timeout 300s;

        internal;
    }

    # Bloquer les autres fichiers .php
    location ~ \.php$ {
        return 404;
    }

    # Sécurité : bloquer les fichiers sensibles
    location ~ /\. {
        deny all;
    }

    location ~ \.(yml|yaml|env)$ {
        deny all;
    }

    # Sécurité : bloquer les dossiers sensibles
    location ~ ^/(var|config|src|migrations) {
        deny all;
    }

    # En-têtes de sécurité
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
```

```bash
# Créer un lien symbolique
sudo ln -s /etc/nginx/sites-available/cloudserve /etc/nginx/sites-enabled/

# Tester la configuration
sudo nginx -t

# Recharger Nginx
sudo systemctl reload nginx

# SSL avec Certbot
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d cloudserve.example.com
```

---

### 10. Configuration PHP-FPM pour production

Éditer `/etc/php/8.1/fpm/pool.d/www.conf` :

```ini
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.1-fpm.sock
listen.owner = www-data
listen.group = www-data

; Augmenter les processus pour gérer les uploads simultanés
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; Limites
request_terminate_timeout = 300s
```

Redémarrer PHP-FPM :

```bash
sudo systemctl restart php8.1-fpm
sudo systemctl status php8.1-fpm
```

---

### 11. Optimisation de la production

```bash
# Vider et réchauffer le cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Optimiser l'autoloader
composer dump-autoload --optimize --no-dev --classmap-authoritative

# Vérifier les permissions finales
ls -la public/uploads/
ls -la var/
```

---

### 12. Installation FFmpeg pour compression (optionnel mais recommandé)

```bash
# Debian/Ubuntu
sudo apt update
sudo apt install ffmpeg

# Vérifier l'installation et les codecs
ffmpeg -version
ffmpeg -codecs | grep -E 'h264|aac|libopus'
```

Configuration des services de compression :
- **Vidéo** : H.264, CRF 23, max 1920x1080, AAC 128kbps
- **Audio** : AAC 192kbps, 44.1kHz

Si FFmpeg n'est pas installé, les compressions vidéo/audio seront simplement ignorées.

---

### 13. Vérification finale de l'installation

```bash
# Vérifier que toutes les extensions sont chargées
php -m | grep -E 'gd|pdo_mysql|mbstring|xml|fileinfo'

# Vérifier le support WebP
php -r "var_dump(function_exists('imagewebp'));"

# Vérifier FFmpeg (optionnel)
which ffmpeg

# Vérifier les permissions
ls -la public/uploads/
ls -la var/

# Tester la génération de miniature
php -r "
\$img = imagecreatetruecolor(100, 100);
imagewebp(\$img, '/tmp/test.webp');
echo file_exists('/tmp/test.webp') ? 'WebP OK' : 'WebP FAILED';
imagedestroy(\$img);
"
```

---

## ⚙️ Configuration

### Variables d'environnement (.env.local)

```env
# Application
APP_ENV=prod                    # dev ou prod
APP_DEBUG=0                     # 0 en production, 1 en dev
APP_SECRET=64_caracteres_hex    # Généré avec bin2hex(random_bytes(32))

# Base de données
DATABASE_URL="mysql://user:pass@host:3306/dbname?serverVersion=8.0&charset=utf8mb4"

# Quotas par défaut (optionnel, 2GB par défaut)
# Modifier dans src/Entity/User.php si besoin
```

### Génération de clé secrète sécurisée

```bash
# Générer une clé secrète de 64 caractères hexadécimaux
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

### Personnalisation du quota

Pour modifier le quota par défaut, éditer `src/Entity/User.php` :

```php
#[ORM\Column(type: 'bigint', options: ['default' => 2147483648])]
private string $quota = '2147483648'; // 2 GB = 2 * 1024 * 1024 * 1024
```

Exemples de quotas :
- 1 GB : `1073741824`
- 5 GB : `5368709120`
- 10 GB : `10737418240`

---

## 📖 Utilisation

### Interface Web

1. **Inscription** : `http://votre-domaine.com/register-page`
   - Le premier utilisateur devient automatiquement administrateur

2. **Connexion** : `http://votre-domaine.com/login-page`

3. **Dashboard** : `http://votre-domaine.com/dashboard`
   - Upload de fichiers (drag & drop supporté)
   - Navigation par dossiers
   - Sélection multiple avec lasso
   - Gestionnaire de fichiers flottant
   - Détection automatique des doublons

4. **Admin** : `http://votre-domaine.com/admin/dashboard`
   - Gestion des utilisateurs
   - Modification des quotas
   - Suspension/activation de comptes
   - Régénération des miniatures

### Fonctionnalités de l'interface

- **Upload** : Progress bar animée, détection de doublons
- **Compression automatique** : Images, vidéos, audio optimisés
- **Miniatures** : Générées automatiquement pour les images
- **Notifications** : Toasts élégants au lieu des popups natives
- **Modales** : Confirmations modernes avec animations
- **Loaders** : Indicateurs de chargement sur toutes les opérations

### API REST

Voir [features.md](features.md) pour la documentation complète de l'API avec exemples curl.

#### Exemples rapides

```bash
# Inscription
curl -X POST https://votre-domaine.com/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"SecurePass123!"}'

# Connexion
curl -X POST https://votre-domaine.com/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"SecurePass123!"}'
# Retourne : {"token":"dXNlckBleGFtcGxlLmNvbQ==", ...}

# Upload de fichier (compression automatique)
curl -X POST https://votre-domaine.com/files/upload \
  -H "X-Auth-Token: dXNlckBleGFtcGxlLmNvbQ==" \
  -F "file=@photo.jpg"

# Lister les fichiers
curl https://votre-domaine.com/files \
  -H "X-Auth-Token: dXNlckBleGFtcGxlLmNvbQ=="

# Télécharger un fichier
curl https://votre-domaine.com/files/123/download \
  -H "X-Auth-Token: dXNlckBleGFtcGxlLmNvbQ==" \
  -o fichier_telecharge.pdf
```

---

## 🧪 Tests

```bash
# Tous les tests
php bin/phpunit

# Tests unitaires
php bin/phpunit tests/Unit

# Tests fonctionnels
php bin/phpunit tests/Functional

# Avec couverture de code
php bin/phpunit --coverage-html coverage/
```

---

## 🔒 Sécurité

### Fonctionnalités de sécurité

- ✅ **Authentification par token** : Header `X-Auth-Token`
- ✅ **Hachage des mots de passe** : bcrypt via Symfony
- ✅ **Contrôle d'accès** : Voters pour les fichiers
- ✅ **Validation des quotas** : Avant chaque upload
- ✅ **Hash SHA-256** : Détection de doublons et intégrité
- ✅ **Isolation des utilisateurs** : Chaque utilisateur accède uniquement à ses fichiers
- ✅ **Suppression sécurisée** : Suppression récursive avec cleanup des miniatures
- ✅ **Protection CSRF** : Pour les formulaires
- ✅ **Fichiers sensibles protégés** : .env, config/ non accessibles via web
- ✅ **En-têtes de sécurité** : X-Frame-Options, X-Content-Type-Options, etc.

### Checklist de sécurité pour la production

- [ ] **HTTPS activé** avec certificat SSL valide
- [ ] **APP_SECRET** généré avec 64 caractères hexadécimaux aléatoires
- [ ] **APP_DEBUG=0** en production
- [ ] **Base de données** avec utilisateur dédié et mot de passe fort (16+ caractères)
- [ ] **Permissions** correctes (775 pour public/uploads/, 600 pour .env.local)
- [ ] **Firewall** configuré (ports 80/443 ouverts uniquement)
- [ ] **Sauvegardes automatiques** de la BDD et public/uploads/
- [ ] **Logs surveillés** régulièrement (var/log/)
- [ ] **Mises à jour** Symfony et dépendances appliquées
- [ ] **Limite d'upload** configurée dans PHP et serveur web
- [ ] **Session sécurisée** (cookie_secure, httponly)

### Surveillance et maintenance

```bash
# Vérifier les logs d'erreur
tail -f var/log/prod.log

# Vérifier les logs Apache/Nginx
tail -f /var/log/apache2/cloudserve_error.log
tail -f /var/log/nginx/cloudserve_error.log

# Vérifier l'espace disque
df -h

# Vérifier la taille des uploads
du -sh public/uploads/

# Nettoyer le cache si nécessaire
php bin/console cache:clear --env=prod
```

---

## 🏗️ Architecture

### Structure du projet

```
cloudserve/
├── config/                     # Configuration Symfony
│   ├── packages/              # Configuration des bundles
│   ├── routes/                # Routes de l'application
│   └── services.yaml          # Services de l'application
├── public/                     # Point d'entrée web
│   ├── index.php              # Front controller
│   ├── css/                   # CSS
│   │   └── ui-components.css  # Styles des composants UI
│   ├── js/                    # JavaScript
│   │   └── ui-components.js   # Composants UI (modales, toasts, loaders)
│   └── uploads/               # 📁 Fichiers utilisateurs
│       └── thumbnails/        # 🖼️ Miniatures générées automatiquement
├── src/
│   ├── Controller/            # Contrôleurs HTTP
│   │   ├── AuthController.php          # Authentification
│   │   ├── FileController.php          # Gestion des fichiers
│   │   ├── DashboardController.php     # Dashboard utilisateur
│   │   └── AdminController.php         # Administration
│   ├── Entity/                # Entités Doctrine
│   │   ├── User.php           # Utilisateur avec quota
│   │   └── File.php           # Fichier avec hash, miniature, arborescence
│   ├── Repository/            # Repositories Doctrine
│   │   ├── UserRepository.php
│   │   └── FileRepository.php
│   ├── Security/              # Authentification et autorisation
│   │   ├── SessionAuthenticator.php
│   │   └── FileVoter.php
│   └── Service/               # Services métier
│       ├── FileStorageService.php       # Stockage des fichiers
│       ├── RawFileUploadService.php     # Upload avec hash SHA-256
│       ├── ThumbnailService.php         # Génération miniatures WebP
│       ├── ImageCompressionService.php  # Compression images (GD)
│       ├── VideoCompressionService.php  # Compression vidéo (FFmpeg H.264)
│       └── AudioCompressionService.php  # Compression audio (FFmpeg AAC)
├── templates/                  # Templates Twig
│   ├── base.html.twig
│   ├── auth/                  # Authentification (login, register)
│   ├── dashboard/             # Dashboard utilisateur moderne
│   │   └── index.html.twig    # Interface complète avec UI components
│   ├── admin/                 # Dashboard administrateur
│   └── viewer/                # Visualiseur de fichiers
├── tests/
│   ├── Unit/                  # Tests unitaires
│   └── Functional/            # Tests fonctionnels
├── var/
│   ├── cache/                 # Cache Symfony
│   └── log/                   # Logs
├── migrations/                 # Migrations Doctrine
├── .env                       # Configuration par défaut
├── .env.local                 # Configuration locale (à créer)
├── composer.json              # Dépendances PHP
├── features.md                # Documentation API complète
└── README.md                  # Ce fichier
```

### Technologies utilisées

- **Framework** : Symfony 7.2
- **Langage** : PHP 8.1+
- **Base de données** : MySQL 5.7+ / MariaDB 10.3+
- **ORM** : Doctrine
- **Template** : Twig
- **Tests** : PHPUnit
- **Authentification** : Système personnalisé par token
- **Frontend** : HTML5, CSS3, JavaScript (pur, sans framework)
- **Compression images** : PHP GD avec WebP
- **Compression vidéo/audio** : FFmpeg (optionnel)

### Flux de traitement des fichiers

1. **Upload** → Validation quota → Calcul hash SHA-256
2. **Détection doublons** → Confirmation utilisateur si doublon trouvé
3. **Stockage** → `public/uploads/[unique_id].[ext]`
4. **Compression automatique** :
   - Images → WebP/JPEG/PNG optimisé (GD)
   - Vidéo → H.264 CRF 23 max 1920x1080 (FFmpeg)
   - Audio → AAC 192kbps 44.1kHz (FFmpeg)
5. **Miniature** → Génération WebP 200x200 pour images
6. **Sauvegarde BDD** → Entité File avec hash, miniature, taille

---

## 🐛 Dépannage

### Miniatures ne s'affichent pas

```bash
# Vérifier l'extension GD
php -m | grep gd

# Vérifier le support WebP
php -r "var_dump(function_exists('imagewebp'));"

# Vérifier les permissions
ls -la public/uploads/thumbnails/

# Régénérer manuellement via admin
# http://votre-domaine.com/admin/dashboard → Bouton "Régénérer miniatures"
```

### Compression vidéo/audio ne fonctionne pas

```bash
# Vérifier FFmpeg
which ffmpeg
ffmpeg -version

# Les services continueront de fonctionner sans FFmpeg
# mais sans compression vidéo/audio
```

### Erreur "Upload too large"

```bash
# Vérifier php.ini
php -i | grep -E 'upload_max_filesize|post_max_size|memory_limit'

# Augmenter les limites dans php.ini
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 512M

# Redémarrer le service
sudo systemctl restart php8.1-fpm  # ou apache2
```

### Erreur de permissions

```bash
# Réparer les permissions
sudo chown -R www-data:www-data public/uploads/
sudo chmod -R 775 public/uploads/

# Vérifier
ls -la public/uploads/
```

---

## 📄 Licence

Ce projet est fourni tel quel, sans garantie. Utilisez-le librement pour vos besoins personnels ou professionnels.

---

## 🤝 Support

Pour toute question ou problème :
- Consultez [features.md](features.md) pour la documentation API
- Vérifiez les logs dans `var/log/`
- Consultez les tests dans `tests/`
- Vérifiez la section [Dépannage](#-dépannage)

---

## 🎉 Nouveautés

### Version actuelle (2025)

- ✨ Interface utilisateur moderne avec composants UI élégants
- 🖼️ Compression automatique des images, vidéos et audio
- 🔍 Détection de doublons par hash SHA-256
- 📁 Navigation par dossiers avec drag & drop
- 🎨 Miniatures automatiques pour toutes les images
- 🚀 Performances optimisées avec stockage dans `public/uploads`
- 💾 Suppression récursive sécurisée
- 📊 Gestionnaire de fichiers en popup flottante

---

<div align="center">
  <p>Fait avec ❤️ en Symfony</p>
  <p><strong>CloudServe</strong> - Votre cloud personnel, sécurisé et moderne</p>
  <p>🌟 Compression automatique | 🖼️ Miniatures | 🔐 Sécurité renforcée 🌟</p>
</div>
