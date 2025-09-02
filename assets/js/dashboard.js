
/* ARQUIVO: assets/js/dashboard.js */
/* Lógica JavaScript para o novo dashboard com modo dark/light e botão de sincronização. */

document.addEventListener('DOMContentLoaded', function() {
    // --- Lógica do Tema (Dark/Light Mode) ---
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
    const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        if(themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
    } else {
        if(themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
    }

    if(themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function() {
            if(themeToggleDarkIcon) themeToggleDarkIcon.classList.toggle('hidden');
            if(themeToggleLightIcon) themeToggleLightIcon.classList.toggle('hidden');
            if (localStorage.getItem('color-theme')) {
                if (localStorage.getItem('color-theme') === 'light') {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('color-theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('color-theme', 'light');
                }
            } else {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('color-theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('color-theme', 'dark');
                }
            }
        });
    }

    // --- Lógica do Dashboard ---
    const clientListBody = document.getElementById('client-list-body');
    const loader = document.getElementById('loader');
    const clientListContainer = document.getElementById('client-list-container');
    const emptyState = document.getElementById('empty-state');
    const refreshBtn = document.getElementById('refresh-btn');
    const runWorkerBtn = document.getElementById('run-worker-btn');

    const statusBadges = {
        'pending': '<span class="bg-yellow-200 text-yellow-800 text-xs font-semibold mr-2 px-2.5 py-1 rounded-full dark:bg-yellow-900 dark:text-yellow-300">Pendente</span>',
        'processing': '<span class="bg-blue-200 text-blue-800 text-xs font-semibold mr-2 px-2.5 py-1 rounded-full dark:bg-blue-900 dark:text-blue-300">Processando</span>',
        'enriched': '<span class="bg-green-200 text-green-800 text-xs font-semibold mr-2 px-2.5 py-1 rounded-full dark:bg-green-900 dark:text-green-300">Enriquecido</span>',
        'error': '<span class="bg-red-200 text-red-800 text-xs font-semibold mr-2 px-2.5 py-1 rounded-full dark:bg-red-900 dark:text-red-300">Erro</span>'
    };

    async function fetchClients() {
        if(loader) loader.style.display = 'flex';
        if(clientListContainer) clientListContainer.style.display = 'none';
        if(emptyState) emptyState.style.display = 'none';

        try {
            const response = await fetch('api/get_clients.php');
            if (!response.ok) throw new Error('A resposta da rede não foi OK');
            const data = await response.json();
            updateStats(data.stats);
            renderClients(data.clients);
        } catch (error) {
            console.error('Erro ao buscar clientes:', error);
            if(clientListBody) clientListBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-red-500 dark:text-red-400">Falha ao carregar dados.</td></tr>`;
        } finally {
            if(loader) loader.style.display = 'none';
        }
    }
    
    function updateStats(stats) {
        if(document.getElementById('stat-total')) document.getElementById('stat-total').textContent = stats.total || 0;
        if(document.getElementById('stat-enriched')) document.getElementById('stat-enriched').textContent = stats.enriched || 0;
        if(document.getElementById('stat-pending')) document.getElementById('stat-pending').textContent = stats.pending || 0;
        if(document.getElementById('stat-error')) document.getElementById('stat-error').textContent = stats.error || 0;
    }

    function renderClients(clients) {
        if(!clientListBody) return;
        clientListBody.innerHTML = '';
        if (clients.length === 0) {
            if(emptyState) emptyState.style.display = 'block';
            if(clientListContainer) clientListContainer.style.display = 'none';
        } else {
             if(clientListContainer) clientListContainer.style.display = 'block';
             if(emptyState) emptyState.style.display = 'none';
             clients.forEach(client => {
                const row = `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="py-4 px-4 font-medium text-gray-800 dark:text-gray-200">${client.nomeFantasia || client.nomeEmpresa}</td>
                        <td class="py-4 px-4 text-gray-600 dark:text-gray-400">${client.cnpj}</td>
                        <td class="py-4 px-4 text-gray-600 dark:text-gray-400">${client.cidade}/${client.uf}</td>
                        <td class="py-4 px-4 text-center">${statusBadges[client.status] || client.status}</td>
                        <td class="py-4 px-4 text-center">
                            <button class="text-blue-500 hover:underline" onclick="viewDetails(${client.id})">Detalhes</button>
                        </td>
                    </tr>`;
                clientListBody.innerHTML += row;
            });
        }
    }

    window.viewDetails = function(clientId) {
        alert('Função de ver detalhes para o cliente ID: ' + clientId + '.');
    }

    if(refreshBtn) refreshBtn.addEventListener('click', fetchClients);

    if(runWorkerBtn) {
        runWorkerBtn.addEventListener('click', async function() {
            runWorkerBtn.disabled = true;
            runWorkerBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Iniciando...</span>`;

            try {
                const response = await fetch('api/run_worker.php', { method: 'POST' });
                const responseText = await response.text(); // Pega a resposta como texto primeiro
                
                // Tenta converter para JSON. Se falhar, mostra o texto do erro.
                try {
                    const result = JSON.parse(responseText);
                    alert(result.message || 'Ocorreu um erro.');
                } catch (e) {
                    // Se não for JSON, é um erro do PHP. Mostra o erro bruto.
                    alert('Erro do servidor:\n\n' + responseText);
                }

                setTimeout(() => { fetchClients(); }, 2000); // Atualiza a lista após 2 segundos
            } catch (error) {
                console.error('Erro ao executar o worker:', error);
                alert('Ocorreu um erro de rede ao tentar iniciar a sincronização.');
            } finally {
                runWorkerBtn.disabled = false;
                runWorkerBtn.innerHTML = `
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Sincronizar</span>`;
            }
        });
    }

    if (document.getElementById('client-list-body')) {
        fetchClients();
    }
});