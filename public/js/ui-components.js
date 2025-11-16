/**
 * Système de notifications modernes
 */
class UIComponents {
    constructor() {
        this.init();
    }

    init() {
        // Créer le conteneur de toasts
        if (!document.getElementById('toast-container')) {
            const toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }

        // Créer le conteneur de modal
        if (!document.getElementById('modal-container')) {
            const modalContainer = document.createElement('div');
            modalContainer.id = 'modal-container';
            document.body.appendChild(modalContainer);
        }

        // Créer le loader global
        if (!document.getElementById('global-loader')) {
            const loader = document.createElement('div');
            loader.id = 'global-loader';
            loader.className = 'global-loader';
            loader.innerHTML = `
                <div class="loader-backdrop"></div>
                <div class="loader-content">
                    <div class="spinner"></div>
                    <p class="loader-text"></p>
                </div>
            `;
            document.body.appendChild(loader);
        }
    }

    /**
     * Afficher un toast (notification)
     */
    toast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} toast-enter`;

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-message">${message}</div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;

        container.appendChild(toast);

        // Animation d'entrée
        setTimeout(() => toast.classList.remove('toast-enter'), 10);

        // Suppression automatique
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.add('toast-exit');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        return toast;
    }

    /**
     * Afficher une modale de confirmation
     */
    confirm(title, message, confirmText = 'Confirmer', cancelText = 'Annuler') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal modal-enter';
            modal.innerHTML = `
                <div class="modal-backdrop" onclick="this.parentElement.remove(); arguments[0].stopPropagation(); ${resolve.toString()}(false)"></div>
                <div class="modal-content modal-confirm">
                    <div class="modal-header">
                        <h3>${title}</h3>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary modal-cancel">${cancelText}</button>
                        <button class="btn btn-danger modal-confirm-btn">${confirmText}</button>
                    </div>
                </div>
            `;

            document.getElementById('modal-container').appendChild(modal);

            // Animation d'entrée
            setTimeout(() => modal.classList.remove('modal-enter'), 10);

            // Bouton annuler
            modal.querySelector('.modal-cancel').onclick = () => {
                modal.classList.add('modal-exit');
                setTimeout(() => modal.remove(), 200);
                resolve(false);
            };

            // Bouton confirmer
            modal.querySelector('.modal-confirm-btn').onclick = () => {
                modal.classList.add('modal-exit');
                setTimeout(() => modal.remove(), 200);
                resolve(true);
            };

            // Escape key
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    modal.classList.add('modal-exit');
                    setTimeout(() => modal.remove(), 200);
                    resolve(false);
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        });
    }

    /**
     * Afficher une modale de prompt
     */
    prompt(title, message, defaultValue = '', placeholder = '') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal modal-enter';
            modal.innerHTML = `
                <div class="modal-backdrop" onclick="this.parentElement.remove(); arguments[0].stopPropagation(); ${resolve.toString()}(null)"></div>
                <div class="modal-content modal-prompt">
                    <div class="modal-header">
                        <h3>${title}</h3>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                        <input type="text" class="form-input" value="${defaultValue}" placeholder="${placeholder}" autofocus>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary modal-cancel">Annuler</button>
                        <button class="btn btn-primary modal-ok">OK</button>
                    </div>
                </div>
            `;

            document.getElementById('modal-container').appendChild(modal);
            setTimeout(() => modal.classList.remove('modal-enter'), 10);

            const input = modal.querySelector('input');
            input.focus();
            input.select();

            const submit = () => {
                const value = input.value.trim();
                if (value) {
                    modal.classList.add('modal-exit');
                    setTimeout(() => modal.remove(), 200);
                    resolve(value);
                }
            };

            modal.querySelector('.modal-cancel').onclick = () => {
                modal.classList.add('modal-exit');
                setTimeout(() => modal.remove(), 200);
                resolve(null);
            };

            modal.querySelector('.modal-ok').onclick = submit;
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') submit();
            });
        });
    }

    /**
     * Afficher le loader global
     */
    showLoader(text = 'Chargement...', icon = null) {
        const loader = document.getElementById('global-loader');
        if (!loader) {
            console.error('Global loader not found');
            return;
        }

        // Personnaliser avec une icône si fournie
        const loaderContent = loader.querySelector('.loader-content');
        if (icon) {
            loaderContent.innerHTML = `
                <div style="font-size: 4rem; margin-bottom: 1rem; animation: bounce 0.6s ease-in-out infinite alternate;">${icon}</div>
                <div class="spinner"></div>
                <p class="loader-text" style="margin-top: 1rem;">${text}</p>
            `;

            // Ajouter l'animation bounce si elle n'existe pas
            if (!document.getElementById('bounce-animation')) {
                const style = document.createElement('style');
                style.id = 'bounce-animation';
                style.textContent = `
                    @keyframes bounce {
                        from { transform: translateY(0) scale(1); }
                        to { transform: translateY(-10px) scale(1.1); }
                    }
                `;
                document.head.appendChild(style);
            }
        } else {
            const loaderText = loader.querySelector('.loader-text');
            if (loaderText) {
                loaderText.textContent = text;
            }
        }

        loader.classList.add('active');
        loader.style.display = 'flex';
    }

    /**
     * Masquer le loader global
     */
    hideLoader() {
        const loader = document.getElementById('global-loader');
        if (!loader) {
            console.error('Global loader not found');
            return;
        }
        loader.classList.remove('active');
        // Petit délai pour l'animation avant de cacher complètement
        setTimeout(() => {
            if (!loader.classList.contains('active')) {
                loader.style.display = 'none';
            }
        }, 300);
    }

    /**
     * Progress bar pour upload
     */
    createProgressBar(text = 'Upload...') {
        const progressBar = document.createElement('div');
        progressBar.className = 'progress-bar-container';
        progressBar.innerHTML = `
            <div class="progress-bar-header">
                <span class="progress-bar-text">${text}</span>
                <span class="progress-bar-percent">0%</span>
            </div>
            <div class="progress-bar-track">
                <div class="progress-bar-fill"></div>
            </div>
        `;

        document.getElementById('toast-container').appendChild(progressBar);

        return {
            element: progressBar,
            update: (percent) => {
                const fill = progressBar.querySelector('.progress-bar-fill');
                const percentText = progressBar.querySelector('.progress-bar-percent');
                fill.style.width = percent + '%';
                percentText.textContent = Math.round(percent) + '%';
            },
            setText: (text) => {
                progressBar.querySelector('.progress-bar-text').textContent = text;
            },
            remove: () => {
                progressBar.classList.add('progress-bar-exit');
                setTimeout(() => progressBar.remove(), 300);
            }
        };
    }
}

// Instance globale
window.ui = new UIComponents();
