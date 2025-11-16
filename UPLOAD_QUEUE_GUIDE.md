# 📤 Système d'Upload Asynchrone avec Queue

## Vue d'ensemble

Le système d'upload asynchrone permet de traiter les fichiers en arrière-plan pour éviter de saturer le serveur lors de l'upload de nombreux fichiers. Le traitement (compression, génération de thumbnails) se fait de manière asynchrone avec des mises à jour en temps réel via Server-Sent Events (SSE).

## Architecture

```
┌──────────────┐
│   Frontend   │
│   Upload     │
└──────┬───────┘
       │ POST /files/queue-upload
       ▼
┌──────────────────┐
│  FileController  │
│  queueUpload()   │
└──────┬───────────┘
       │ Sauvegarde en DB
       ▼
┌──────────────────┐
│  upload_queue    │ ◄─────┐
│    (Table DB)    │       │
└──────┬───────────┘       │
       │ Lecture           │ Mise à jour
       ▼                   │
┌─────────────────────────┬┘
│  ProcessUploadQueue     │
│  Command (PHP CLI)      │
└──────┬──────────────────┘
       │ Traitement async
       │ (compression, thumbnails)
       ▼
┌──────────────────┐
│   File Entity    │
│  (public/uploads)│
└──────────────────┘

       ┌──────────────────┐
       │    Frontend      │
       │  EventSource     │
       └────────┬─────────┘
                │ GET /files/upload-status
                ▼
       ┌──────────────────┐
       │  FileController  │
       │  uploadStatus()  │
       │  (SSE Stream)    │
       └──────────────────┘
```

## 📁 Fichiers créés

### Backend

1. **`src/Entity/UploadQueue.php`**
   - Entité Doctrine pour stocker les uploads en attente
   - Propriétés : filename, tempPath, size, status, progress, etc.

2. **`src/Repository/UploadQueueRepository.php`**
   - Méthodes pour récupérer les uploads en attente
   - Méthodes de statistiques et de nettoyage

3. **`src/Command/ProcessUploadQueueCommand.php`**
   - Commande PHP CLI pour traiter la queue
   - Support du mode daemon (processus continu)
   - Traitement par batch avec progression

4. **`src/Controller/FileController.php`** (modifié)
   - Nouvelle route : `POST /files/queue-upload`
   - Nouvelle route : `GET /files/upload-status` (SSE)

5. **`migrations/Version20251116000000.php`**
   - Migration de la table `upload_queue`

6. **`config/services.yaml`** (modifié)
   - Configuration du `$projectDir` pour la commande et le controller

### Frontend (À implémenter)

Le frontend doit être modifié pour :
1. Envoyer à `/files/queue-upload` au lieu de `/files/upload-multiple`
2. Écouter les événements SSE depuis `/files/upload-status`
3. Afficher la progression en temps réel

## 🚀 Démarrage

### 1. Lancer le worker de queue

**Option 1 : Mode unique (traite la queue une fois)**
```bash
php bin/console app:process-upload-queue
```

**Option 2 : Mode daemon (recommandé)**
```bash
php bin/console app:process-upload-queue --daemon
```

**Option 3 : Mode daemon avec options personnalisées**
```bash
php bin/console app:process-upload-queue \
  --daemon \
  --sleep=1 \       # Délai entre les vérifications (secondes)
  --batch=10        # Nombre de fichiers à traiter par lot
```

### 2. Garder le worker actif (Production)

Pour que le worker tourne en permanence, utilisez **Supervisor** (Linux) ou **NSSM** (Windows).

#### Configuration Supervisor (Linux)
```ini
[program:cloudserve-upload-queue]
command=/usr/bin/php /path/to/cloudserve/bin/console app:process-upload-queue --daemon
directory=/path/to/cloudserve
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/cloudserve-queue.err.log
stdout_logfile=/var/log/cloudserve-queue.out.log
```

Puis :
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cloudserve-upload-queue
```

#### Configuration NSSM (Windows)
```bash
nssm install CloudServeUploadQueue "C:\path\to\php.exe" "C:\Tools\OpenCloud\cloudserve\bin\console app:process-upload-queue --daemon"
nssm start CloudServeUploadQueue
```

## 📡 API Endpoints

### POST /files/queue-upload

Ajoute des fichiers à la queue de traitement.

**Headers:**
```
X-Auth-Token: <token>
Content-Type: multipart/form-data
```

**Body (FormData):**
```
files[]: File (multiple)
parent_id: number (optional)
```

**Response:**
```json
{
  "success": true,
  "queued": 3,
  "items": [
    {
      "id": 1,
      "filename": "photo.jpg",
      "size": "2048576",
      "status": "queued"
    }
  ],
  "errors": []
}
```

### GET /files/upload-status

Stream SSE pour recevoir les mises à jour en temps réel.

**Headers:**
```
X-Auth-Token: <token>
```

**Response Stream (SSE):**
```
data: {"uploads":[{"id":1,"filename":"photo.jpg","size":"2048576","status":"processing","progress":45}],"stats":{"pending":2,"processing":1,"completed":0,"failed":0},"timestamp":1699999999}

data: {"uploads":[{"id":1,"filename":"photo.jpg","size":"2048576","status":"completed","progress":100,"fileId":123}],"stats":{"pending":1,"processing":0,"completed":1,"failed":0},"timestamp":1700000001}
```

## 🎨 Frontend Implementation Example

```javascript
async function uploadFilesAsync(files) {
    const formData = new FormData();

    for (let file of files) {
        formData.append('files[]', file);
    }

    if (currentFolder) {
        formData.append('parent_id', currentFolder.id);
    }

    // 1. Envoyer les fichiers à la queue
    const response = await fetch('/files/queue-upload', {
        method: 'POST',
        headers: { 'X-Auth-Token': token },
        body: formData
    });

    const result = await response.json();

    if (!response.ok) {
        throw new Error(result.error);
    }

    ui.toast(`${result.queued} fichier(s) ajouté(s) à la file d'attente`, 'success');

    // 2. Écouter les mises à jour en temps réel
    const eventSource = new EventSource('/files/upload-status?' + new URLSearchParams({
        'X-Auth-Token': token
    }));

    const progressOverlay = createProgressOverlay();

    eventSource.onmessage = (event) => {
        const data = JSON.parse(event.data);

        // Mettre à jour l'interface avec la progression
        data.uploads.forEach(upload => {
            updateUploadProgress(upload.id, upload.filename, upload.progress, upload.status);
        });

        // Si tous les uploads sont terminés
        if (data.stats.pending === 0 && data.stats.processing === 0) {
            eventSource.close();
            progressOverlay.remove();

            ui.toast(`Upload terminé ! ${data.stats.completed} réussi(s), ${data.stats.failed} échec(s)`,
                     data.stats.failed > 0 ? 'warning' : 'success');

            // Recharger la liste des fichiers
            loadFiles(currentFolder?.id);
            loadUserData();
        }
    };

    eventSource.onerror = (error) => {
        console.error('SSE Error:', error);
        eventSource.close();
    };
}

function createProgressOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'upload-progress-overlay';
    overlay.style.cssText = 'position:fixed;bottom:20px;right:20px;background:white;padding:20px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.15);z-index:9999;min-width:300px;';
    overlay.innerHTML = `
        <h3 style="margin:0 0 15px 0;">📤 Uploads en cours</h3>
        <div id="upload-items"></div>
    `;
    document.body.appendChild(overlay);
    return overlay;
}

function updateUploadProgress(id, filename, progress, status) {
    const container = document.getElementById('upload-items');
    let item = document.getElementById(`upload-item-${id}`);

    if (!item) {
        item = document.createElement('div');
        item.id = `upload-item-${id}`;
        item.style.cssText = 'margin-bottom:10px;';
        container.appendChild(item);
    }

    const statusIcon = {
        'pending': '⏳',
        'processing': '⚙️',
        'completed': '✅',
        'failed': '❌'
    }[status] || '❓';

    item.innerHTML = `
        <div style="font-weight:600;font-size:0.875rem;margin-bottom:5px;">${statusIcon} ${filename}</div>
        <div style="background:#f0f0f0;height:6px;border-radius:3px;overflow:hidden;">
            <div style="background:${status === 'failed' ? '#f44336' : '#4caf50'};height:100%;width:${progress}%;transition:width 0.3s;"></div>
        </div>
        <div style="font-size:0.75rem;color:#666;margin-top:3px;">${progress}% - ${status}</div>
    `;

    if (status === 'completed' || status === 'failed') {
        setTimeout(() => {
            item.style.opacity = '0';
            setTimeout(() => item.remove(), 300);
        }, 2000);
    }
}
```

## 🔧 Base de données

### Table `upload_queue`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT | ID unique |
| `user_id` | INT | Utilisateur propriétaire |
| `parent_folder_id` | INT | Dossier parent (nullable) |
| `result_file_id` | INT | Fichier résultant (nullable) |
| `filename` | VARCHAR(255) | Nom du fichier |
| `temp_path` | VARCHAR(255) | Chemin temporaire |
| `mime_type` | VARCHAR(100) | Type MIME |
| `size` | BIGINT | Taille en octets |
| `hash` | VARCHAR(64) | Hash SHA256 |
| `status` | VARCHAR(20) | Status : pending, processing, completed, failed |
| `error_message` | TEXT | Message d'erreur (nullable) |
| `created_at` | DATETIME | Date de création |
| `processed_at` | DATETIME | Date de traitement (nullable) |
| `progress` | INT | Progression 0-100 |

### Statuts possibles

- `pending` : En attente de traitement
- `processing` : En cours de traitement
- `completed` : Traitement réussi
- `failed` : Échec du traitement

## 📊 Monitoring

### Statistiques de la queue

```php
$stats = $uploadQueueRepository->getStatsByUser($user);
// Returns: ['pending' => 5, 'processing' => 2, 'completed' => 120, 'failed' => 3]
```

### Nettoyage des anciennes entrées

```bash
# Via la console PHP
php bin/console doctrine:query:sql "DELETE FROM upload_queue WHERE status IN ('completed', 'failed') AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
```

Ou via le repository :
```php
$before = new \DateTimeImmutable('-7 days');
$deletedCount = $uploadQueueRepository->cleanupOld($before);
```

## ⚡ Performances

### Optimisations

1. **Batch Processing** : Traite plusieurs fichiers en parallèle (configurable avec `--batch`)
2. **Sleep intelligent** : Le daemon attend seulement quand la queue est vide
3. **Compression asynchrone** : Les fichiers volumineux ne bloquent plus l'interface
4. **SSE efficace** : Updates toutes les secondes au lieu de polling constant

### Limites

- **Timeout SSE** : 5 minutes max (configurable dans `uploadStatus()`)
- **Fichiers temporaires** : Stockés dans `/var/tmp/uploads/`
- **Batch size** : 5 fichiers par défaut (peut être augmenté)

## 🐛 Debugging

### Vérifier l'état de la queue

```bash
php bin/console doctrine:query:sql "SELECT * FROM upload_queue WHERE status='pending'"
```

### Voir les logs de la commande

```bash
php bin/console app:process-upload-queue --daemon -vv
```

### Tester SSE manuellement

```bash
curl -N -H "X-Auth-Token: YOUR_TOKEN" http://localhost:8000/files/upload-status
```

## 🔄 Migration depuis l'ancien système

L'ancien endpoint `/files/upload-multiple` reste disponible pour la compatibilité. Pour migrer :

1. ✅ Backend : déjà prêt (route `/files/queue-upload` disponible)
2. ⏳ Frontend : modifier `uploadFiles()` pour utiliser `uploadFilesAsync()`
3. ⏳ Lancer le worker : `php bin/console app:process-upload-queue --daemon`
4. ⏳ Tester avec quelques fichiers
5. ⏳ Déployer en production avec Supervisor/NSSM

## 📝 Notes importantes

- Le worker doit **toujours être actif** pour traiter les uploads
- Sans worker actif, les fichiers resteront en status `pending`
- Les fichiers temporaires sont dans `/var/tmp/uploads/` et supprimés après traitement
- Le système SSE se ferme automatiquement après 5 minutes ou quand tous les uploads sont terminés
- La table `upload_queue` doit être nettoyée régulièrement (recommandation : cron hebdomadaire)

## ✅ Avantages

- ✅ Pas de timeout lors de l'upload de nombreux fichiers
- ✅ Interface utilisateur réactive (pas de blocage)
- ✅ Mise à jour en temps réel de la progression
- ✅ Retry automatique possible (en cas d'échec)
- ✅ Meilleure gestion de la charge serveur
- ✅ Logs détaillés du traitement
