// utils.js

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

if (window.location.protocol === 'http:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1' && !window.location.hostname.startsWith('192.168.')) {
    window.location.href = window.location.href.replace('http:', 'https:');
}


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

    if (!options.credentials) {
        options.credentials = 'same-origin';
    }

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

/**
 * Valida um CPF (frontend)
 * @param {string} cpf 
 * @returns {boolean}
 */
function validarCPF(cpf) {
    cpf = cpf.replace(/[^\d]+/g, '');
    if (cpf == '' || cpf.length != 11 || /^(\d)\1{10}$/.test(cpf)) return false;
    let add = 0;
    for (let i = 0; i < 9; i++) add += parseInt(cpf.charAt(i)) * (10 - i);
    let rev = 11 - (add % 11);
    if (rev == 10 || rev == 11) rev = 0;
    if (rev != parseInt(cpf.charAt(9))) return false;
    add = 0;
    for (let i = 0; i < 10; i++) add += parseInt(cpf.charAt(i)) * (11 - i);
    rev = 11 - (add % 11);
    if (rev == 10 || rev == 11) rev = 0;
    if (rev != parseInt(cpf.charAt(10))) return false;
    return true;
}

/**
 * Aplica máscara de CPF num input
 * @param {HTMLInputElement} input 
 */
function mascaraCPF(input) {
    let v = input.value.replace(/\D/g, "");
    if (v.length <= 11) {
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
        input.value = v;
    }
}
