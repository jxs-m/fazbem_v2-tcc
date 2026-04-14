// utils.js
// Funções utilitárias comuns ao frontend do projeto fazbem

/**
 * Escapa caracteres HTML perigosos para evitar Stored e Reflected XSS.
 * Deve ser usado sempre que variáveis forem inseridas no DOM via innerHTML.
 * 
 * @param {string} str Texto de entrada
 * @returns {string} Texto seguro (escapado)
 */
function escapeHTML(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// ============================================
// CSRF Protection 
// ============================================

let csrfToken = null;
let csrfPromise = null;
const originalFetch = window.fetch;

async function fetchCSRFToken() {
    if (csrfToken) return csrfToken;
    if (csrfPromise) return csrfPromise;

    csrfPromise = originalFetch('api_csrf.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                csrfToken = data.csrf_token;
            }
            return csrfToken;
        })
        .catch(err => console.error("Falha ao obter CSRF", err));

    return csrfPromise;
}


window.fetch = async function (url, options = {}) {
    const method = options.method ? options.method.toUpperCase() : 'GET';
    const requiresCsrf = ['POST', 'PUT', 'DELETE'].includes(method);

    if (requiresCsrf && url !== 'api_csrf.php') {
        const token = await fetchCSRFToken();

        if (!options.headers) {
            options.headers = {};
        }

        if (options.headers instanceof Headers) {
            options.headers.append('X-CSRF-Token', token);
        } else {
            options.headers['X-CSRF-Token'] = token;
        }
    }

    return originalFetch.call(this, url, options);
};
