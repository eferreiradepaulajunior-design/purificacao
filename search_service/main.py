# -*- coding: utf-8 -*-

# =====================================================================================
# AVISO IMPORTANTE DE USO E RESPONSABILIDADE
# =====================================================================================
# 1. TERMOS DE SERVIÇO: O uso de APIs para extrair dados do Google ou LinkedIn pode
#    estar sujeito aos seus respectivos Termos de Serviço.
# 2. LGPD: A coleta e armazenamento de dados pessoais devem estar em conformidade com a
#    Lei Geral de Proteção de Dados (LGPD).
# =====================================================================================

# =====================================================================================
# INSTRUÇÕES DE INSTALAÇÃO (ATUALIZADO)
# =====================================================================================
# 1. Instale o Python 3.8 ou superior.
# 2. Abra o terminal e instale as bibliotecas:
#    pip install fastapi "uvicorn[standard]" pydantic python-dotenv httpx
# 3. CRIE/ATUALIZE SEU ARQUIVO `.env` no mesmo diretório com o seguinte conteúdo:
#    # Obtenha sua chave gratuitamente em https://serpapi.com/
#    SERPAPI_KEY="SUA_CHAVE_API_DA_SERPAPI_AQUI"
# 4. Para rodar a aplicação:
#    uvicorn main:app --reload
# 5. Acesse a interface visual no seu navegador: http://127.0.0.1:8000
# =====================================================================================

import uuid
import os
from dotenv import load_dotenv
import httpx
import asyncio

from fastapi import FastAPI, BackgroundTasks, HTTPException
from fastapi.responses import HTMLResponse
from pydantic import BaseModel, Field
from typing import Dict, Any, List, Optional

# Carrega as variáveis de ambiente do arquivo .env
load_dotenv()

# --- Configuração da Aplicação FastAPI ---
app = FastAPI(
    title="Buscador de Perfis e Empresas",
    description="Uma API para encontrar perfis do LinkedIn e informações de contato de empresas usando SerpAPI.",
    version="3.0.0"
)

# --- "Banco de Dados" em Memória para Tarefas ---
tasks_db: Dict[str, Dict[str, Any]] = {}

# --- Modelos de Dados (Pydantic) ---

# Modelos para Busca de Perfis
class ProfileSearchRequest(BaseModel):
    profession: str = Field(..., description="O cargo ou profissão a ser pesquisado.")
    search_term: str = Field(..., description="O termo de busca adicional, como nome da empresa.")
    num_results: int = Field(default=20, ge=10, le=100, description="Número de resultados a serem buscados.")

class ProfileContact(BaseModel):
    title: str
    link: str

class ProfileTaskStatus(BaseModel):
    task_id: str
    status: str
    message: str
    result: Optional[List[ProfileContact]] = None

# Modelos para Busca de Empresas
class CompanySearchRequest(BaseModel):
    company_name: str = Field(..., description="Nome da empresa a ser buscada.")

class CompanyContactResult(BaseModel):
    instagram_url: Optional[str] = None
    facebook_url: Optional[str] = None
    google_maps_info: Optional[Dict[str, Any]] = None

class CompanyTaskStatus(BaseModel):
    task_id: str
    status: str
    message: str
    result: Optional[CompanyContactResult] = None

# --- Lógica de Busca com SerpAPI ---

async def find_social_profile(platform_domain: str, company_name: str, serpapi_key: str):
    """Busca um perfil social (Instagram, Facebook) usando o Google."""
    query = f'site:{platform_domain} "{company_name}"'
    params = {"engine": "google", "q": query, "api_key": serpapi_key}
    try:
        async with httpx.AsyncClient() as client:
            response = await client.get("https://serpapi.com/search.json", params=params, timeout=20.0)
            data = response.json()
            if "organic_results" in data and len(data["organic_results"]) > 0:
                return data["organic_results"][0].get("link")
    except Exception as e:
        print(f"Erro ao buscar em {platform_domain}: {e}")
    return None

async def find_google_maps_info(company_name: str, serpapi_key: str):
    """Busca informações da empresa no Google Maps."""
    params = {"engine": "google_maps", "q": company_name, "api_key": serpapi_key, "hl": "pt-br"}
    try:
        async with httpx.AsyncClient() as client:
            response = await client.get("https://serpapi.com/search.json", params=params, timeout=20.0)
            data = response.json()
            if "local_results" in data and len(data["local_results"]) > 0:
                # Retorna os campos mais relevantes do primeiro resultado
                first_result = data["local_results"][0]
                return {
                    "title": first_result.get("title"),
                    "address": first_result.get("address"),
                    "phone": first_result.get("phone"),
                    "website": first_result.get("website"),
                    "rating": first_result.get("rating"),
                    "reviews": first_result.get("reviews"),
                }
    except Exception as e:
        print(f"Erro ao buscar no Google Maps: {e}")
    return None

# --- Tarefas em Segundo Plano ---

async def run_profile_search_task(task_id: str, profession: str, search_term: str, num_results: int):
    tasks_db[task_id] = {"status": "em_andamento", "message": "Buscando perfis..."}
    serpapi_key = os.getenv("SERPAPI_KEY")
    query = f'site:br.linkedin.com/in/ "{profession}" "{search_term}"'
    params = {"engine": "google", "q": query, "api_key": serpapi_key, "hl": "pt-br", "num": num_results}
    try:
        async with httpx.AsyncClient() as client:
            response = await client.get("https://serpapi.com/search.json", params=params, timeout=30.0)
            data = response.json()
            if "error" in data: raise Exception(data["error"])

            organic_results = data.get("organic_results", [])
            contacts = [ProfileContact.model_validate(r) for r in organic_results if 'link' in r and 'linkedin.com/in/' in r['link']]

            tasks_db[task_id] = {
                "status": "concluido",
                "message": f"{len(contacts)} perfis encontrados.",
                "result": [c.model_dump() for c in contacts]
            }
    except Exception as e:
        tasks_db[task_id] = {"status": "falhou", "message": str(e)}

async def run_company_search_task(task_id: str, company_name: str):
    tasks_db[task_id] = {"status": "em_andamento", "message": "Buscando informações da empresa..."}
    serpapi_key = os.getenv("SERPAPI_KEY")
    if not serpapi_key:
        tasks_db[task_id] = {"status": "falhou", "message": "Chave da SerpAPI não configurada."}
        return

    try:
        # Executa as buscas em paralelo
        insta_task = find_social_profile("instagram.com", company_name, serpapi_key)
        fb_task = find_social_profile("facebook.com", company_name, serpapi_key)
        maps_task = find_google_maps_info(company_name, serpapi_key)

        insta_url, fb_url, maps_data = await asyncio.gather(insta_task, fb_task, maps_task)

        result = CompanyContactResult(
            instagram_url=insta_url,
            facebook_url=fb_url,
            google_maps_info=maps_data
        )
        tasks_db[task_id] = {
            "status": "concluido",
            "message": "Busca de informações da empresa concluída.",
            "result": result.model_dump()
        }
    except Exception as e:
        tasks_db[task_id] = {"status": "falhou", "message": str(e)}

# --- Endpoints da API ---

@app.post("/iniciar-busca-perfis", response_model=ProfileTaskStatus, status_code=202)
async def start_profile_search(request: ProfileSearchRequest, background_tasks: BackgroundTasks):
    task_id = str(uuid.uuid4())
    background_tasks.add_task(run_profile_search_task, task_id, request.profession, request.search_term, request.num_results)
    tasks_db[task_id] = {"status": "iniciado", "message": "Tarefa de busca de perfis agendada."}
    return ProfileTaskStatus(task_id=task_id, status="iniciado", message="Tarefa agendada.")

@app.post("/iniciar-busca-empresa", response_model=CompanyTaskStatus, status_code=202)
async def start_company_search(request: CompanySearchRequest, background_tasks: BackgroundTasks):
    task_id = str(uuid.uuid4())
    background_tasks.add_task(run_company_search_task, task_id, request.company_name)
    tasks_db[task_id] = {"status": "iniciado", "message": "Tarefa de busca de empresa agendada."}
    return CompanyTaskStatus(task_id=task_id, status="iniciado", message="Tarefa agendada.")


@app.get("/status/perfis/{task_id}", response_model=ProfileTaskStatus)
async def get_profile_task_status(task_id: str):
    task = tasks_db.get(task_id)
    if not task:
        raise HTTPException(status_code=404, detail="Tarefa não encontrada.")
    return ProfileTaskStatus(task_id=task_id, **task)

@app.get("/status/empresa/{task_id}", response_model=CompanyTaskStatus)
async def get_company_task_status(task_id: str):
    task = tasks_db.get(task_id)
    if not task:
        raise HTTPException(status_code=404, detail="Tarefa não encontrada.")
    return CompanyTaskStatus(task_id=task_id, **task)

# --- Endpoint para a Interface Visual ---

@app.get("/", response_class=HTMLResponse)
async def read_root():
    # O HTML foi movido para uma variável separada para melhor organização
    return HTMLResponse(content=HTML_CONTENT)

HTML_CONTENT = """
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://rsms.me/inter/inter.css');
        html { font-family: 'Inter', sans-serif; }
        .loader { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        /* Estilos para abas */
        .tab-btn.active { border-color: #3B82F6; color: #3B82F6; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-4 md:p-8 max-w-3xl">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <h1 class="text-3xl font-bold mb-6 text-center text-blue-600">Buscador Inteligente</h1>

            <!-- Abas de Navegação -->
            <div class="mb-6 border-b border-gray-200">
                <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                    <button id="tab-profiles" onclick="switchTab('profiles')" class="tab-btn active whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Buscar Perfis</button>
                    <button id="tab-company" onclick="switchTab('company')" class="tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">Buscar Empresa</button>
                </nav>
            </div>

            <!-- Conteúdo da Aba "Buscar Perfis" -->
            <div id="content-profiles" class="tab-content">
                <form id="profile-search-form" class="space-y-4">
                     <div>
                        <label for="profession" class="block text-sm font-medium text-gray-700">Profissão / Cargo</label>
                        <input type="text" id="profession" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Ex: Engenheiro de Software">
                    </div>
                    <div>
                        <label for="search_term" class="block text-sm font-medium text-gray-700">Empresa / Setor</label>
                        <input type="text" id="search_term" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Ex: Google">
                    </div>
                     <div>
                        <label for="num_results" class="block text-sm font-medium text-gray-700">Número de Resultados</label>
                        <input type="number" id="num_results" value="20" min="10" max="100" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <button type="submit" id="profile-submit-button" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Buscar Perfis</button>
                </form>
                <div id="profile-status-area" class="mt-6 text-center"></div>
                <div id="profile-results-area" class="mt-6 space-y-3"></div>
                <div class="mt-4 flex justify-center">
                    <button id="profile-download-btn" class="hidden w-full md:w-auto justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">Baixar Lista (CSV)</button>
                </div>
            </div>

            <!-- Conteúdo da Aba "Buscar Empresa" -->
            <div id="content-company" class="tab-content hidden">
                <form id="company-search-form" class="space-y-4">
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-gray-700">Nome da Empresa</label>
                        <input type="text" id="company_name" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Ex: Nubank">
                    </div>
                    <button type="submit" id="company-submit-button" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Buscar Informações</button>
                </form>
                <div id="company-status-area" class="mt-6 text-center"></div>
                <div id="company-results-area" class="mt-6"></div>
            </div>
        </div>
    </div>
    <script>
        // Lógica de Abas
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(`content-${tabName}`).classList.remove('hidden');
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }

        // --- LÓGICA PARA BUSCA DE PERFIS ---
        const profileForm = document.getElementById('profile-search-form');
        const profileStatusArea = document.getElementById('profile-status-area');
        const profileResultsArea = document.getElementById('profile-results-area');
        const profileSubmitBtn = document.getElementById('profile-submit-button');
        const profileDownloadBtn = document.getElementById('profile-download-btn');
        let profileStatusInterval, currentProfileResults = [];

        profileForm.addEventListener('submit', async e => {
            e.preventDefault();
            profileStatusArea.innerHTML = '';
            profileResultsArea.innerHTML = '';
            profileSubmitBtn.disabled = true;
            profileSubmitBtn.innerHTML = '<div class="loader mx-auto"></div>';
            profileDownloadBtn.classList.add('hidden');
            clearInterval(profileStatusInterval);

            const data = {
                profession: document.getElementById('profession').value,
                search_term: document.getElementById('search_term').value,
                num_results: parseInt(document.getElementById('num_results').value)
            };

            const response = await fetch('/iniciar-busca-perfis', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const task = await response.json();
            profileStatusArea.innerHTML = '<p class="text-blue-600">Busca de perfis iniciada...</p>';
            profileStatusInterval = setInterval(() => checkProfileStatus(task.task_id), 3000);
        });

        async function checkProfileStatus(taskId) {
            const response = await fetch(`/status/perfis/${taskId}`);
            const task = await response.json();
            let msg = '';
            if (task.status === 'em_andamento' || task.status === 'iniciado') {
                msg = '<p class="text-gray-600">A busca de perfis está em andamento...</p>';
            } else {
                clearInterval(profileStatusInterval);
                profileSubmitBtn.disabled = false;
                profileSubmitBtn.textContent = 'Buscar Perfis';
                if (task.status === 'concluido') {
                    msg = `<p class="text-green-600 font-semibold">${task.message}</p>`;
                    displayProfileResults(task.result);
                } else {
                    msg = `<p class="text-red-500 font-semibold">Erro: ${task.message}</p>`;
                }
            }
            profileStatusArea.innerHTML = msg;
        }

        function displayProfileResults(results) {
            if (!results || results.length === 0) {
                profileResultsArea.innerHTML = '<p class="text-center text-gray-500">Nenhum perfil encontrado.</p>';
                return;
            }
            currentProfileResults = results;
            profileResultsArea.innerHTML = results.map(contact => `
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="font-semibold text-gray-800">${contact.title}</p>
                    <a href="${contact.link}" target="_blank" class="text-sm text-blue-500 hover:underline break-all">${contact.link}</a>
                </div>
            `).join('');
            profileDownloadBtn.classList.remove('hidden');
        }

        profileDownloadBtn.addEventListener('click', () => {
             const headers = ['Nome', 'Link'];
             const escapeCSV = (str) => `"${(str || '').replace(/"/g, '""')}"`;
             const csvContent = [
                 headers.join(','),
                 ...currentProfileResults.map(c => `${escapeCSV(c.title)},${escapeCSV(c.link)}`)
             ].join('\n');
             const blob = new Blob([`\uFEFF${csvContent}`], { type: 'text/csv;charset=utf-8;' });
             const url = URL.createObjectURL(blob);
             const a = Object.assign(document.createElement('a'), { href: url, download: 'perfis_linkedin.csv' });
             document.body.appendChild(a);
             a.click();
             a.remove();
             URL.revokeObjectURL(url);
        });
        
        // --- LÓGICA PARA BUSCA DE EMPRESAS ---
        const companyForm = document.getElementById('company-search-form');
        const companyStatusArea = document.getElementById('company-status-area');
        const companyResultsArea = document.getElementById('company-results-area');
        const companySubmitBtn = document.getElementById('company-submit-button');
        let companyStatusInterval;
        
        companyForm.addEventListener('submit', async e => {
            e.preventDefault();
            companyStatusArea.innerHTML = '';
            companyResultsArea.innerHTML = '';
            companySubmitBtn.disabled = true;
            companySubmitBtn.innerHTML = '<div class="loader mx-auto"></div>';
            clearInterval(companyStatusInterval);

            const data = { company_name: document.getElementById('company_name').value };
            
            const response = await fetch('/iniciar-busca-empresa', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const task = await response.json();
            companyStatusArea.innerHTML = '<p class="text-blue-600">Busca de empresa iniciada...</p>';
            companyStatusInterval = setInterval(() => checkCompanyStatus(task.task_id), 3000);
        });

        async function checkCompanyStatus(taskId) {
            const response = await fetch(`/status/empresa/${taskId}`);
            const task = await response.json();
            let msg = '';
            if (task.status === 'em_andamento' || task.status === 'iniciado') {
                msg = '<p class="text-gray-600">A busca de empresa está em andamento...</p>';
            } else {
                clearInterval(companyStatusInterval);
                companySubmitBtn.disabled = false;
                companySubmitBtn.textContent = 'Buscar Informações';
                if (task.status === 'concluido') {
                    msg = `<p class="text-green-600 font-semibold">${task.message}</p>`;
                    displayCompanyResults(task.result);
                } else {
                    msg = `<p class="text-red-500 font-semibold">Erro: ${task.message}</p>`;
                }
            }
            companyStatusArea.innerHTML = msg;
        }

        function displayCompanyResults(result) {
            if (!result) {
                companyResultsArea.innerHTML = '<p class="text-center text-gray-500">Nenhuma informação encontrada.</p>';
                return;
            }
            
            let html = '<div class="space-y-4 rounded-lg border border-gray-200 p-4">';
            
            if(result.instagram_url) html += `<div><strong class="font-medium">Instagram:</strong> <a href="${result.instagram_url}" target="_blank" class="text-blue-500 hover:underline">${result.instagram_url}</a></div>`;
            if(result.facebook_url) html += `<div><strong class="font-medium">Facebook:</strong> <a href="${result.facebook_url}" target="_blank" class="text-blue-500 hover:underline">${result.facebook_url}</a></div>`;
            
            if(result.google_maps_info) {
                const maps = result.google_maps_info;
                html += '<div class="pt-4 border-t border-gray-200"><h3 class="text-lg font-semibold mb-2 text-gray-700">Google Maps</h3><div class="space-y-1 text-sm">';
                if(maps.title) html += `<div><strong>Nome:</strong> ${maps.title}</div>`;
                if(maps.address) html += `<div><strong>Endereço:</strong> ${maps.address}</div>`;
                if(maps.phone) html += `<div><strong>Telefone:</strong> ${maps.phone}</div>`;
                if(maps.website) html += `<div><strong>Website:</strong> <a href="${maps.website}" target="_blank" class="text-blue-500 hover:underline">${maps.website}</a></div>`;
                if(maps.rating) html += `<div><strong>Avaliação:</strong> ${maps.rating} (${maps.reviews || 0} avaliações)</div>`;
                html += '</div></div>';
            }

            html += '</div>';
            companyResultsArea.innerHTML = html;
        }

    </script>
</body>
</html>
"""
