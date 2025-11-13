# CloudServe

<div align="center">
  <h3>☁️ Serveur de stockage cloud personnel et sécurisé</h3>
  <p>Une solution complète de gestion de fichiers avec interface web moderne et API REST</p>
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
- **Gérer plusieurs utilisateurs** avec système d'administration
- **Accéder via une interface web moderne** responsive et intuitive
- **Utiliser une API REST complète** pour l'intégration avec d'autres services

Le projet utilise une architecture moderne avec :
- Symfony 7.2 (PHP)
- MySQL pour la persistence
- Authentication par token personnalisée
- Interface utilisateur sans framework CSS (HTML/CSS pur)
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
- ✅ Téléchargement sécurisé
- ✅ Liste et recherche de fichiers
- ✅ Suppression de fichiers
- ✅ Stockage dans `var/uploads/`
- ✅ Support de tous types de fichiers

### 👑 Administration
- ✅ Dashboard d'administration complet
- ✅ Gestion des utilisateurs (quotas, suspension)
- ✅ Statistiques globales
- ✅ Premier utilisateur = admin automatique
- ✅ Interface responsive desktop/mobile

### 🎨 Interface utilisateur
- ✅ Design moderne et professionnel
- ✅ Responsive (mobile, tablette, desktop)
- ✅ Dashboard utilisateur avec statistiques
- ✅ Interface d'upload intuitive
- ✅ Gestion visuelle des fichiers

---

## 🔧 Prérequis

### Développement
- PHP 8.1 ou supérieur
- Composer 2.x
- MySQL 5.7+ ou MariaDB 10.3+
- Extensions PHP : `pdo_mysql`, `mbstring`, `xml`, `ctype`, `iconv`

### Production
- Serveur web (Apache 2.4+ ou Nginx 1.18+)
- PHP 8.1+ avec PHP-FPM
- MySQL 5.7+ ou MariaDB 10.3+
- Certificat SSL (recommandé)
- Au moins 512 Mo de RAM
- Espace disque selon vos besoins de stockage

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
# DATABASE_URL="mysql://root:@127.0.0.1:3306/cloudserve"
```

### 4. Créer la base de données

```bash
# Créer la base
php bin/console doctrine:database:create

# Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Démarrer le serveur

```bash
# Avec PHP built-in server
php -S localhost:8000 -t public

# Ou avec Symfony CLI
symfony server:start
```

### 6. Accéder à l'application

- Interface : http://localhost:8000
- Dashboard : http://localhost:8000/dashboard
- Admin : http://localhost:8000/admin/dashboard

**Important** : Le premier utilisateur créé devient automatiquement administrateur.

---

## 🌐 Installation Production

### Option 1 : Apache

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
# Éditer .env.local
nano .env.local
```

```env
# Mode production
APP_ENV=prod
APP_DEBUG=0

# Générer une clé secrète sécurisée
# Utiliser : php -r "echo bin2hex(random_bytes(16));"
APP_SECRET=votre_cle_secrete_32_caracteres_minimum

# Base de données production
DATABASE_URL="mysql://cloudserve_user:mot_de_passe_securise@localhost:3306/cloudserve_prod?serverVersion=8.0"
```

#### 3. Configurer MySQL

```bash
mysql -u root -p
```

```sql
-- Créer la base de données
CREATE DATABASE cloudserve_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Créer un utilisateur dédié
CREATE USER 'cloudserve_user'@'localhost' IDENTIFIED BY 'mot_de_passe_securise';

-- Donner les permissions
GRANT ALL PRIVILEGES ON cloudserve_prod.* TO 'cloudserve_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 4. Exécuter les migrations

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

#### 5. Configurer les permissions

```bash
# Créer le dossier uploads
mkdir -p var/uploads

# Donner les permissions appropriées
chown -R www-data:www-data var/
chmod -R 775 var/

# Sécuriser les fichiers sensibles
chmod 600 .env.local
```

#### 6. Configurer Apache

Créer le fichier de configuration :

```bash
sudo nano /etc/apache2/sites-available/cloudserve.conf
```

```apache
<VirtualHost *:80>
    ServerName cloudserve.example.com
    DocumentRoot /var/www/html/cloudserve/public

    <Directory /var/www/html/cloudserve/public>
        AllowOverride All
        Require all granted

        # Activer la réécriture d'URL
        FallbackResource /index.php
    </Directory>

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/cloudserve_error.log
    CustomLog ${APACHE_LOG_DIR}/cloudserve_access.log combined

    # Sécurité : désactiver l'accès aux fichiers sensibles
    <Directory /var/www/html/cloudserve>
        <Files ".env*">
            Require all denied
        </Files>
    </Directory>
</VirtualHost>
```

#### 7. Activer le site et les modules

```bash
# Activer les modules nécessaires
sudo a2enmod rewrite
sudo a2enmod headers

# Activer le site
sudo a2ensite cloudserve.conf

# Recharger Apache
sudo systemctl reload apache2
```

#### 8. Configurer SSL (recommandé)

```bash
# Installer Certbot
sudo apt install certbot python3-certbot-apache

# Obtenir un certificat SSL
sudo certbot --apache -d cloudserve.example.com

# Le renouvellement automatique est configuré par défaut
```

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

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    # Sécurité : bloquer les fichiers sensibles
    location ~ /\. {
        deny all;
    }

    # Upload de fichiers volumineux
    client_max_body_size 100M;

    error_log /var/log/nginx/cloudserve_error.log;
    access_log /var/log/nginx/cloudserve_access.log;
}
```

```bash
# Activer le site
sudo ln -s /etc/nginx/sites-available/cloudserve /etc/nginx/sites-enabled/

# Tester la configuration
sudo nginx -t

# Recharger Nginx
sudo systemctl reload nginx

# SSL avec Certbot
sudo certbot --nginx -d cloudserve.example.com
```

### 9. Optimisation de la production

```bash
# Vider et réchauffer le cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Optimiser l'autoloader
composer dump-autoload --optimize --no-dev --classmap-authoritative
```

### 10. Configuration PHP pour uploads

Éditer `/etc/php/8.1/fpm/php.ini` ou `/etc/php/8.1/apache2/php.ini` :

```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M
```

Redémarrer le service :

```bash
# Pour PHP-FPM
sudo systemctl restart php8.1-fpm

# Pour Apache avec mod_php
sudo systemctl restart apache2
```

---

## ⚙️ Configuration

### Variables d'environnement (.env.local)

```env
# Application
APP_ENV=prod                    # dev ou prod
APP_DEBUG=0                     # 0 en production, 1 en dev
APP_SECRET=32_caracteres_min    # Clé secrète unique

# Base de données
DATABASE_URL="mysql://user:pass@host:3306/dbname"

# Quotas par défaut (optionnel, 2GB par défaut)
# DEFAULT_USER_QUOTA=2147483648
```

### Personnalisation

Pour modifier le quota par défaut, éditer `src/Entity/User.php` ligne 47 :

```php
#[ORM\Column(type: 'bigint', options: ['default' => 2147483648])]
private string $quota = '2147483648'; // 2 GB
```

---

## 📖 Utilisation

### Interface Web

1. **Inscription** : http://votre-domaine.com/register-page
2. **Connexion** : http://votre-domaine.com/login-page
3. **Dashboard** : http://votre-domaine.com/dashboard
4. **Admin** : http://votre-domaine.com/admin/dashboard

### API REST

Voir [features.md](features.md) pour la documentation complète de l'API avec exemples curl.

#### Exemples rapides

```bash
# Inscription
curl -X POST http://votre-domaine.com/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'

# Connexion
curl -X POST http://votre-domaine.com/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'
# Retourne : {"token":"dXNlckBleGFtcGxlLmNvbQ==", ...}

# Upload de fichier
curl -X POST http://votre-domaine.com/files/upload \
  -H "X-Auth-Token: dXNlckBleGFtcGxlLmNvbQ==" \
  -F "file=@document.pdf"

# Lister les fichiers
curl http://votre-domaine.com/files \
  -H "X-Auth-Token: dXNlckBleGFtcGxlLmNvbQ=="
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
- ✅ **Isolation des utilisateurs** : Chaque utilisateur accède uniquement à ses fichiers
- ✅ **Protection CSRF** : Pour les formulaires
- ✅ **Fichiers sensibles protégés** : .env non accessible via web

### Bonnes pratiques en production

1. **Utilisez HTTPS** (SSL/TLS) obligatoirement
2. **Générez une APP_SECRET unique** et complexe
3. **Configurez APP_DEBUG=0** en production
4. **Sécurisez votre base de données** (utilisateur dédié, mot de passe fort)
5. **Limitez les permissions** des fichiers (775 pour var/, 644 pour les autres)
6. **Sauvegardez régulièrement** la base de données et var/uploads/
7. **Surveillez les logs** (var/log/prod.log)
8. **Mettez à jour** régulièrement Symfony et les dépendances

---

## 🏗️ Architecture

### Structure du projet

```
cloudserve/
├── config/                 # Configuration Symfony
│   ├── packages/          # Configuration des bundles
│   └── services.yaml      # Services de l'application
├── public/                # Point d'entrée web
│   └── index.php         # Front controller
├── src/
│   ├── Controller/       # Contrôleurs HTTP
│   │   ├── AuthController.php
│   │   ├── FileController.php
│   │   ├── DashboardController.php
│   │   └── AdminController.php
│   ├── Entity/          # Entités Doctrine
│   │   ├── User.php
│   │   └── File.php
│   ├── Repository/      # Repositories Doctrine
│   ├── Security/        # Authentification et autorisation
│   │   ├── SessionAuthenticator.php
│   │   └── FileVoter.php
│   └── Service/         # Services métier
│       ├── FileStorageService.php
│       └── RawFileUploadService.php
├── templates/           # Templates Twig
│   ├── base.html.twig
│   ├── auth/           # Authentification
│   ├── dashboard/      # Dashboard utilisateur
│   └── admin/          # Dashboard admin
├── tests/
│   ├── Unit/           # Tests unitaires
│   └── Functional/     # Tests fonctionnels
├── var/
│   ├── cache/         # Cache Symfony
│   ├── log/           # Logs
│   └── uploads/       # 📁 Fichiers utilisateurs
├── .env               # Configuration par défaut
├── composer.json      # Dépendances PHP
├── features.md        # Documentation API complète
└── README.md          # Ce fichier
```

### Technologies utilisées

- **Framework** : Symfony 7.2
- **Base de données** : MySQL 5.7+ / MariaDB 10.3+
- **ORM** : Doctrine
- **Template** : Twig
- **Tests** : PHPUnit
- **Authentification** : Système personnalisé par token
- **Frontend** : HTML5, CSS3 (pur, sans framework)

---

## 📄 Licence

Ce projet est fourni tel quel, sans garantie. Utilisez-le librement pour vos besoins personnels ou professionnels.

---

## 🤝 Support

Pour toute question ou problème :
- Consultez [features.md](features.md) pour la documentation API
- Vérifiez les logs dans `var/log/`
- Consultez les tests dans `tests/`

---

<div align="center">
  <p>Fait avec ❤️ en Symfony</p>
  <p>CloudServe - Votre cloud personnel et sécurisé</p>
</div>