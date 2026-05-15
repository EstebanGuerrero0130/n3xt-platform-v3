import axios from 'axios';

// Prioridad: 1. Variables de entorno (Producción en Vercel) 2. Host actual (Local)
const hostname = window.location.hostname || '127.0.0.1';
const port = '8000'; // Puerto estándar del Motor N3XT

const API_BASE_URL = import.meta.env.VITE_API_URL || `http://${hostname}:${port}/api`;
const STORAGE_BASE_URL = import.meta.env.VITE_STORAGE_URL || `http://${hostname}:${port}/storage`;

const getHeaders = (includeToken = false, endpoint = '') => {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  
  if (includeToken) {
    const isAdminRoute = endpoint.includes('/admin') || endpoint.includes('/materials') || endpoint.includes('/printers');
    const token = isAdminRoute 
        ? localStorage.getItem('n3xt_admin_token') 
        : (localStorage.getItem('n3xt_customer_token') || localStorage.getItem('n3xt_admin_token'));

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
  }
  return headers;
}

export const api = {
  baseUrl: API_BASE_URL,
  storageUrl: STORAGE_BASE_URL,

  async get(endpoint, secure = false) {
    try {
      const res = await fetch(`${API_BASE_URL}${endpoint}`, {
        method: 'GET',
        headers: getHeaders(secure, endpoint),
      });
      return await this.handleResponse(res, endpoint);
    } catch (err) {
      console.error(`API Get Error [${endpoint}]:`, err);
      throw err;
    }
  },

  async post(endpoint, body, secure = false) {
    const isFormData = body instanceof FormData;
    const token = localStorage.getItem('n3xt_admin_token');
    
    // --- N3XT Security Layer (Trusted Admin Flow) ---
    // En el flujo de administración confiamos en la integridad de los datos para permitir estructuras JSON complejas.
    // Solo realizamos limpieza en formularios públicos (FormData) si fuera necesario.

    const headers = isFormData 
      ? { 'Accept': 'application/json' } 
      : getHeaders(secure, endpoint);
    
    // Add Security Nonce for Bot Protection
    headers['X-N3XT-SECURE-NONCE'] = btoa(`${Date.now()}_${Math.random()}`);

    if (secure && token && isFormData) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    try {
      const res = await fetch(`${API_BASE_URL}${endpoint}`, {
        method: 'POST',
        headers: headers,
        body: isFormData ? body : JSON.stringify(body),
      });
      return await this.handleResponse(res, endpoint);
    } catch (err) {
      console.error(`API Post Error [${endpoint}]:`, err);
      throw err;
    }
  },

  async patch(endpoint, body, secure = true) {
    try {
      const res = await fetch(`${API_BASE_URL}${endpoint}`, {
        method: 'PATCH',
        headers: getHeaders(secure, endpoint),
        body: JSON.stringify(body),
      });
      return await this.handleResponse(res, endpoint);
    } catch (err) {
      console.error(`API Patch Error [${endpoint}]:`, err);
      throw err;
    }
  },

  async delete(endpoint, secure = true) {
    try {
      const res = await fetch(`${API_BASE_URL}${endpoint}`, {
        method: 'DELETE',
        headers: getHeaders(secure, endpoint),
      });
      return await this.handleResponse(res, endpoint);
    } catch (err) {
      console.error(`API Delete Error [${endpoint}]:`, err);
      throw err;
    }
  },

  async handleResponse(res, endpoint = '') {
    if (res.status === 204) return { success: true };
    
    const contentType = res.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await res.text();
      console.error('Respuesta no es JSON:', text.substring(0, 100));
      throw new Error('El servidor no respondió con JSON válido. Verifica la URL de la API.');
    }

    let data;
    try {
      data = await res.json();
    } catch (e) {
      data = { message: 'Error al procesar respuesta del servidor' };
    }

    if (!res.ok) {
      if (res.status === 401) {
          if (endpoint.startsWith('/admin') || endpoint.includes('materials') || endpoint.includes('printers')) {
              console.error(`[AUTH] Sesión expirada en ${endpoint}. Redirigiendo...`);
              localStorage.removeItem('n3xt_admin_token');
              if (!window.location.pathname.includes('/admin/login')) {
                window.location.href = '/admin/login';
              }
              return;
          }
      }
      throw new Error(data.message || `Error del servidor (${res.status})`);
    }
    return data.data !== undefined ? data.data : data;
  }
};
