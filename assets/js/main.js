/* ============================================================
   main.js — Shared utilities for FK Club System
   ============================================================ */

const BASE = '/WEB PROJECT';

/* ── API fetch wrapper ── */
async function api(url, options = {}) {
    const defaults = {
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
    };
    const res = await fetch(BASE + url, { ...defaults, ...options });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
}

async function apiPost(url, body = {}) {
    return api(url, {
        method: 'POST',
        body: JSON.stringify(body),
    });
}

async function apiForm(url, formData) {
    const res = await fetch(BASE + url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
}

/* ── Session guard: redirect to login if not authenticated ── */
async function guardSession(expectedRole = null) {
    try {
        const user = await api('/api/auth/session.php');
        if (!user.loggedIn) { window.location.href = BASE + '/index.html'; return null; }
        if (expectedRole && user.role !== expectedRole) {
            window.location.href = BASE + '/index.html'; return null;
        }
        return user;
    } catch {
        window.location.href = BASE + '/index.html';
        return null;
    }
}

/* ── Load sidebar user info ── */
function loadSidebarUser(user) {
    const nameEl  = document.getElementById('sidebar-user-name');
    const roleEl  = document.getElementById('sidebar-user-role');
    const photoEl = document.getElementById('sidebar-user-photo');
    if (nameEl) nameEl.textContent = user.name;
    if (roleEl) roleEl.textContent = capitalise(user.role);
    if (photoEl) {
        if (user.photo) {
            photoEl.innerHTML = `<img src="${BASE}/assets/images/uploads/${user.photo}" alt="Photo">`;
        } else {
            photoEl.textContent = initials(user.name);
        }
    }
}

/* ── Toast notifications ── */
function toast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const icons = { success: '✅', error: '❌', warning: '⚠️' };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${message}</span>`;
    container.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(120%)';
        setTimeout(() => t.remove(), 300); }, 3500);
}

/* ── Modal helpers ── */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

// Close modal when clicking overlay backdrop
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

/* ── Logout ── */
async function logout() {
    try {
        const d = await api('/api/auth/logout.php');
        window.location.href = BASE + '/index.html';
    } catch {
        window.location.href = BASE + '/index.html';
    }
}

/* ── Confirm delete dialog ── */
function confirmDelete(message, onConfirm) {
    if (confirm(message || 'Are you sure you want to delete this?')) onConfirm();
}

/* ── Utilities ── */
function capitalise(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function initials(name) {
    return name.trim().split(' ').slice(0,2).map(w => w[0]?.toUpperCase() || '').join('');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day:'2-digit', month:'short', year:'numeric' });
}

function formatDateTime(dtStr) {
    if (!dtStr) return '-';
    return new Date(dtStr).toLocaleString('en-MY', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

function badgeHTML(text, cssClass) {
    return `<span class="badge ${cssClass}">${text}</span>`;
}

function emptyState(message = 'No data found', icon = '📭') {
    return `<tr><td colspan="100">
        <div class="empty-state">
            <div class="empty-icon">${icon}</div>
            <p>${message}</p>
        </div>
    </td></tr>`;
}
