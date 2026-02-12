/**
 * News Associations - Modal Search Handler
 * Handles AJAX search and selection of entities for news associations
 * Singleton Pattern to avoid Turbo Drive re-initialization conflicts
 */

if (typeof NewsAssociationSearch === 'undefined') {
    class NewsAssociationSearch {
        constructor() {
            if (NewsAssociationSearch.instance) {
                return NewsAssociationSearch.instance;
            }
            NewsAssociationSearch.instance = this;

            this.modal = null;
            this.currentType = null;
            this.selectedItems = {};
            this.debounceTimer = null;

            // Filter state
            this.filterType = null;
            this.filterId = null;
            this.filterName = null;

            this.init();
        }

        init() {
            // Re-scan selected items from DOM (useful if page reloaded/navigated)
            ['players', 'teams', 'leagues', 'coaches', 'venues', 'fixtures'].forEach(type => {
                // Only reset if empty or needed? 
                // Better to always read from DOM to sync with current page state
                this.selectedItems[type] = [];
                const hidden = document.getElementById(`${type}Ids-hidden`);
                if (hidden && hidden.value) {
                    const ids = hidden.value.split(',').filter(id => id.trim());
                    ids.forEach(id => {
                        this.selectedItems[type].push({ id: parseInt(id.trim()), name: `ID: ${id.trim()}` });
                    });
                    this.renderChips(type);
                }
            });

            // Bind modal buttons delegate
            // Remove old listener if exists to prevent duplicates?
            // Actually, we can just add a new one if it's not bound, but since 'body' might be replaced by Turbo,
            // we should re-bind.
            // But we need to be careful not to double-bind if body wasn't replaced.

            if (!document.body.dataset.newsSearchBound) {
                document.body.addEventListener('click', (e) => {
                    // Delegate for open buttons
                    if (e.target.matches('.btn-open-search-modal')) {
                        this.openModal(e.target.dataset.type);
                    }
                    // Delegate for remove chips (if dynamically added)
                    if (e.target.closest('.btn-remove-chip')) {
                        const btn = e.target.closest('.btn-remove-chip');
                        this.removeItem(btn.dataset.type, parseInt(btn.dataset.id));
                    }
                });
                document.body.dataset.newsSearchBound = 'true';
            }
        }

        openModal(type) {
            console.log('Opening modal for type:', type); // Debug
            this.currentType = type;
            const modalEl = document.getElementById('searchModal');
            if (!modalEl) return;

            // Reset modal inputs
            const searchInput = document.getElementById('modalSearchInput');
            if (searchInput) searchInput.value = '';

            const resultsList = document.getElementById('modalResultsList');
            if (resultsList) resultsList.innerHTML = '<p class="text-muted">Escribe al menos 2 caracteres para buscar...</p>';

            const title = document.getElementById('modalTitle');
            if (title) title.textContent = `Buscar ${this.getTypeName(type)}`;

            // UI tweaks for Player filters
            const playerFilters = document.getElementById('playerFilters');
            if (playerFilters) {
                if (type === 'players') {
                    playerFilters.style.display = 'block';
                    this.resetPlayerFilters();
                } else {
                    playerFilters.style.display = 'none';
                }
            }

            // Handle ARIA attributes manually
            modalEl.removeAttribute('aria-hidden');
            modalEl.setAttribute('aria-modal', 'true');
            modalEl.setAttribute('role', 'dialog');

            // Opens
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    this.modal = bootstrap.Modal.getInstance(modalEl);
                    if (!this.modal) {
                        this.modal = new bootstrap.Modal(modalEl);
                    }
                    this.modal.show();
                } else {
                    // Fallback
                    modalEl.classList.add('show');
                    modalEl.style.display = 'block';
                    modalEl.style.zIndex = '1055';
                    document.body.classList.add('modal-open');

                    let backdrop = document.getElementById('modal-backdrop-custom');
                    if (!backdrop) {
                        backdrop = document.createElement('div');
                        backdrop.className = 'modal-backdrop fade show';
                        backdrop.id = 'modal-backdrop-custom';
                        backdrop.style.zIndex = '1050';
                        document.body.appendChild(backdrop);
                    }
                    backdrop.onclick = () => this.closeModal();

                    this.modal = {
                        hide: () => {
                            modalEl.classList.remove('show');
                            modalEl.style.display = 'none';
                            modalEl.setAttribute('aria-hidden', 'true');
                            modalEl.removeAttribute('aria-modal');
                            document.body.classList.remove('modal-open');
                            const bd = document.getElementById('modal-backdrop-custom');
                            if (bd) bd.remove();
                        }
                    };
                }
            } catch (e) {
                console.error('Error opening modal:', e);
            }

            if (searchInput) setTimeout(() => searchInput.focus(), 300);
        }

        closeModal() {
            if (this.modal && typeof this.modal.hide === 'function') {
                this.modal.hide();
            } else {
                const modalEl = document.getElementById('searchModal');
                if (modalEl) {
                    modalEl.classList.remove('show');
                    modalEl.style.display = 'none';
                    modalEl.setAttribute('aria-hidden', 'true');
                    modalEl.removeAttribute('aria-modal');
                    document.body.classList.remove('modal-open');
                    const bd = document.getElementById('modal-backdrop-custom');
                    if (bd) bd.remove();
                    const bsBd = document.querySelector('.modal-backdrop');
                    if (bsBd && !bd) bsBd.remove();
                }
            }
        }

        getTypeName(type) {
            const names = {
                'players': 'Jugadores',
                'teams': 'Equipos',
                'leagues': 'Ligas',
                'coaches': 'Entrenadores',
                'venues': 'Estadios',
                'fixtures': 'Partidos'
            };
            return names[type] || type;
        }

        search(query) {
            console.log('Searching:', query, 'Type:', this.currentType); // Debug

            if (!this.currentType) {
                console.error('Error: currentType is null. Cannot search.');
                const list = document.getElementById('modalResultsList');
                if (list) list.innerHTML = '<p class="text-danger">Error interno: Tipo de búsqueda no definido. Cierra y abre el modal de nuevo.</p>';
                return;
            }

            if (query.length < 2) {
                const list = document.getElementById('modalResultsList');
                if (list) list.innerHTML = '<p class="text-warning">Escribe al menos 2 caracteres para buscar.</p>';
                return;
            }

            if (this.currentType === 'players' && !this.filterId) {
                const list = document.getElementById('modalResultsList');
                if (list) list.innerHTML = '<p class="text-warning">⚠️ Primero debes seleccionar una liga o equipo arriba.</p>';
                return;
            }

            const list = document.getElementById('modalResultsList');
            if (list) list.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div><p class="mt-2">Buscando...</p></div>';

            let url = `/admin/api/search/${this.currentType}?q=${encodeURIComponent(query)}`;

            if (this.currentType === 'players' && this.filterId) {
                if (this.filterType === 'league') {
                    url += `&league=${this.filterId}`;
                } else if (this.filterType === 'team') {
                    url += `&team=${this.filterId}`;
                }
            }

            fetch(url)
                .then(response => response.json())
                .then(data => this.renderResults(data.results))
                .catch(error => {
                    console.error('Error searching:', error);
                    if (list) list.innerHTML = '<p class="text-danger">Error al buscar. Inténtalo de nuevo.</p>';
                });
        }

        renderResults(results) {
            const container = document.getElementById('modalResultsList');
            if (!container) return;

            if (!results || results.length === 0) {
                container.innerHTML = '<p class="text-muted">No se encontraron resultados.</p>';
                return;
            }

            let html = '<div class="list-group">';
            results.forEach(item => {
                const isSelected = this.selectedItems[this.currentType] && this.selectedItems[this.currentType].some(s => s.id === item.id);
                html += `
                    <label class="list-group-item list-group-item-action d-flex align-items-center" style="cursor: pointer">
                        <input type="checkbox" class="form-check-input me-3" 
                               data-id="${item.id}" 
                               data-name="${item.name}"
                               data-photo="${item.photo || ''}"
                               ${isSelected ? 'checked disabled' : ''}>
                        ${item.photo ? `<img src="${item.photo}" class="rounded-circle me-2" width="32" height="32" alt="">` : ''}
                        <div class="flex-grow-1">
                            <strong>${item.name}</strong>
                            ${item.extra ? `<small class="text-muted d-block">${item.extra}</small>` : ''}
                        </div>
                        ${isSelected ? '<span class="badge bg-success">Añadido</span>' : ''}
                    </label>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        addSelected() {
            const checkboxes = document.querySelectorAll('#modalResultsList input[type="checkbox"]:checked:not(:disabled)');
            checkboxes.forEach(cb => {
                const item = {
                    id: parseInt(cb.dataset.id),
                    name: cb.dataset.name,
                    photo: cb.dataset.photo
                };
                if (!this.selectedItems[this.currentType].some(s => s.id === item.id)) {
                    this.selectedItems[this.currentType].push(item);
                }
            });
            this.renderChips(this.currentType);
            this.updateHiddenInput(this.currentType);
            this.closeModal();
        }

        removeItem(type, id) {
            if (this.selectedItems[type]) {
                this.selectedItems[type] = this.selectedItems[type].filter(item => item.id !== id);
                this.renderChips(type);
                this.updateHiddenInput(type);
            }
        }

        renderChips(type) {
            const container = document.getElementById(`${type}Ids-chips`);
            if (!container) return;
            if (!this.selectedItems[type] || this.selectedItems[type].length === 0) {
                container.innerHTML = '<span class="text-muted">Ninguno seleccionado</span>';
                return;
            }
            let html = '';
            this.selectedItems[type].forEach(item => {
                html += `
                    <span class="badge bg-primary me-1 mb-1" style="font-size: 0.85rem">
                        ${item.photo ? `<img src="${item.photo}" class="rounded-circle me-1" width="16" height="16" alt="">` : ''}
                        ${item.name}
                        <button type="button" class="btn-close btn-close-white ms-1 btn-remove-chip" 
                                style="font-size: 0.6rem" 
                                data-type="${type}" data-id="${item.id}"></button>
                    </span>
                `;
            });
            container.innerHTML = html;
        }

        updateHiddenInput(type) {
            const hidden = document.getElementById(`${type}Ids-hidden`);
            if (hidden) {
                hidden.value = this.selectedItems[type].map(item => item.id).join(',');
            }
        }

        // --- FILTER HELPERS ---
        searchLeagueForFilter(query) {
            if (query.length < 2) return;
            const resDiv = document.getElementById('leagueFilterResults');
            if (resDiv) resDiv.innerHTML = '<small class="text-muted">Buscando...</small>';

            fetch(`/admin/api/search/leagues?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    let html = '';
                    (data.results || []).slice(0, 5).forEach(item => {
                        html += `<button type="button" class="btn btn-sm btn-outline-secondary w-100 text-start mb-1" 
                                  onclick="window.newsSearch.selectFilter('league', ${item.id}, '${item.name.replace(/'/g, "\\'")}')">
                                  ${item.photo ? `<img src="${item.photo}" height="16" class="me-1">` : ''}
                                  ${item.name} <small class="text-muted">${item.extra || ''}</small>
                                </button>`;
                    });
                    if (resDiv) resDiv.innerHTML = html || '<small class="text-muted">No encontrado</small>';
                });
        }

        searchTeamForFilter(query) {
            if (query.length < 2) return;
            const resDiv = document.getElementById('teamFilterResults');
            if (resDiv) resDiv.innerHTML = '<small class="text-muted">Buscando...</small>';

            fetch(`/admin/api/search/teams?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    let html = '';
                    (data.results || []).slice(0, 5).forEach(item => {
                        html += `<button type="button" class="btn btn-sm btn-outline-secondary w-100 text-start mb-1" 
                                  onclick="window.newsSearch.selectFilter('team', ${item.id}, '${item.name.replace(/'/g, "\\'")}')">
                                  ${item.photo ? `<img src="${item.photo}" height="16" class="me-1">` : ''}
                                  ${item.name} <small class="text-muted">${item.extra || ''}</small>
                                </button>`;
                    });
                    if (resDiv) resDiv.innerHTML = html || '<small class="text-muted">No encontrado</small>';
                });
        }

        selectFilter(type, id, name) {
            this.filterType = type;
            this.filterId = id;
            this.filterName = name;
            this.updateFilterUI_Selected(name, type === 'league' ? '🏆 Liga' : '⚽ Equipo');
        }

        resetPlayerFilters() {
            this.filterType = null;
            this.filterId = null;
            this.filterName = null;

            const listToClear = ['leagueFilterResults', 'teamFilterResults'];
            listToClear.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.innerHTML = '';
            });

            const inputsToClear = ['leagueSearchInput', 'teamSearchInput'];
            inputsToClear.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });

            const selectedFilter = document.getElementById('selectedFilter');
            if (selectedFilter) selectedFilter.style.display = 'none';

            const helpText = document.getElementById('searchHelpText');
            if (helpText) helpText.textContent = 'Primero selecciona una liga o equipo arriba';
        }

        updateFilterUI_Selected(name, labelPrefix) {
            const listToClear = ['leagueFilterResults', 'teamFilterResults'];
            listToClear.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.innerHTML = '';
            });

            const inputsToClear = ['leagueSearchInput', 'teamSearchInput'];
            inputsToClear.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });

            const badge = document.getElementById('selectedFilterBadge');
            if (badge) badge.textContent = `${labelPrefix}: ${name}`;

            const selectedFilter = document.getElementById('selectedFilter');
            if (selectedFilter) selectedFilter.style.display = 'block';

            const helpText = document.getElementById('searchHelpText');
            if (helpText) helpText.textContent = 'Ahora busca el nombre del jugador';

            const mainInput = document.getElementById('modalSearchInput');
            if (mainInput) mainInput.focus();
        }
    }

    window.NewsAssociationSearch = NewsAssociationSearch;
}

/**
 * Initialization function compatible with Turbo
 * Uses Singleton pattern: reuses existing instance if present, 
 * but re-runs init() to bind to new DOM elements.
 */
function initNewsSearch() {
    if (!window.newsSearch) {
        window.newsSearch = new window.NewsAssociationSearch();
    } else {
        // If instance exists, we MUST re-bind to the new DOM (Turbo replaced body)
        window.newsSearch.init();
    }
}

// Turbo Load (Navigation)
document.addEventListener('turbo:load', initNewsSearch);

// Initial Load / Hard Refresh
if (document.readyState !== 'loading') {
    initNewsSearch();
} else {
    document.addEventListener('DOMContentLoaded', initNewsSearch);
}
