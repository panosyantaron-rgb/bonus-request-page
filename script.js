const STORAGE_KEY = 'bonuses_data';
const REQUESTS_KEY = 'bonus_requests';
const ADMIN_SESSION_KEY = 'admin_session';
const ADMIN_PASSWORD = 'admin123'; // Change this to your desired password

// Default bonuses
const DEFAULT_BONUSES = [
    { id: 1, name: 'Welcome Bonus', description: 'New player bonus' },
    { id: 2, name: 'Reload Bonus', description: 'Deposit bonus' },
    { id: 3, name: 'Referral Bonus', description: 'Invite friends' }
];

function initPage() {
    const path = window.location.pathname;
    const isAdminPath = path.includes('adminka');
    const isAdminSession = sessionStorage.getItem(ADMIN_SESSION_KEY) === 'true';

    if (isAdminPath) {
        if (isAdminSession) {
            showAdminPanel();
        } else {
            showLoginPage();
        }
    } else {
        showUserPage();
    }
}

function showUserPage() {
    document.getElementById('userPage').style.display = 'block';
    document.getElementById('loginPage').style.display = 'none';
    document.getElementById('adminPage').style.display = 'none';

    loadBonuses();
    renderBonusCards();
    setupCategoryTabs();
}

function showLoginPage() {
    document.getElementById('userPage').style.display = 'none';
    document.getElementById('loginPage').style.display = 'block';
    document.getElementById('adminPage').style.display = 'none';
}

function showAdminPanel() {
    document.getElementById('userPage').style.display = 'none';
    document.getElementById('loginPage').style.display = 'none';
    document.getElementById('adminPage').style.display = 'block';

    loadBonuses();
    renderBonusList();
    renderRequestsList();
}

function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

    document.getElementById(tab + '-tab').classList.add('active');
    event.target.classList.add('active');

    if (tab === 'requests') {
        renderRequestsList();
    }
}

function handleAdminLogin(event) {
    event.preventDefault();
    const password = document.getElementById('adminPassword').value;

    if (password === ADMIN_PASSWORD) {
        sessionStorage.setItem(ADMIN_SESSION_KEY, 'true');
        showAdminPanel();
    } else {
        showAdminMessage('Incorrect password', 'error');
        document.getElementById('adminPassword').value = '';
        document.getElementById('adminPassword').focus();
    }
}

function logoutAdmin() {
    sessionStorage.removeItem(ADMIN_SESSION_KEY);
    window.location.href = '/';
}

function getBonuses() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
        try {
            return JSON.parse(stored);
        } catch {
            return DEFAULT_BONUSES;
        }
    }
    return DEFAULT_BONUSES;
}

function saveBonuses(bonuses) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(bonuses));
}

function loadBonuses() {
    const bonuses = getBonuses();
    if (bonuses.length === 0) {
        saveBonuses(DEFAULT_BONUSES);
    }
}

function getRequests() {
    const stored = localStorage.getItem(REQUESTS_KEY);
    if (stored) {
        try {
            return JSON.parse(stored);
        } catch {
            return [];
        }
    }
    return [];
}

function saveRequest(request) {
    const requests = getRequests();
    requests.push(request);
    localStorage.setItem(REQUESTS_KEY, JSON.stringify(requests));
}

function renderBonusOptions() {
    const bonuses = getBonuses();
    const container = document.getElementById('bonusOptions');

    if (bonuses.length === 0) {
        container.innerHTML = '<p style="color: var(--color-text-secondary);">No bonuses available. Please try again later.</p>';
        return;
    }

    container.innerHTML = bonuses.map(bonus => `
        <label class="bonus-card" id="bonus-${bonus.id}">
            <div class="bonus-label">
                <input type="radio" name="bonus" value="${bonus.id}" onchange="selectBonus(${bonus.id})">
                <div class="bonus-content">
                    <span class="bonus-name">${bonus.name}</span>
                    <span class="bonus-description">${bonus.description}</span>
                </div>
            </div>
        </label>
    `).join('');
}

function renderBonusCards() {
    const bonuses = getBonuses();
    const container = document.getElementById('bonusContainer');

    if (bonuses.length === 0) {
        container.innerHTML = '<p style="color: #64748b; font-size: 0.875rem; grid-column: 1 / -1;">No bonuses available.</p>';
        return;
    }

    container.innerHTML = bonuses.map(bonus => `
        <div class="bonus-card-item" id="bonus-card-${bonus.id}" onclick="selectBonusCard(${bonus.id})">
            <div class="bonus-card-name">${bonus.name}</div>
            <div class="bonus-card-desc">${bonus.description}</div>
            <div class="bonus-card-amount">+Bonus</div>
        </div>
    `).join('');
}

function selectBonusCard(bonusId) {
    document.querySelectorAll('.bonus-card-item').forEach(card => {
        card.classList.remove('selected');
    });
    document.getElementById(`bonus-card-${bonusId}`).classList.add('selected');
    document.getElementById('currentSelectedBonus').value = bonusId;
}

function switchCategory(tabElement) {
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    tabElement.classList.add('active');
}

function setupCategoryTabs() {
    const tabs = document.querySelectorAll('.category-tab');
    if (tabs.length > 0) {
        tabs[0].classList.add('active');
    }
}

function selectBonus(bonusId) {
    document.querySelectorAll('.bonus-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.getElementById(`bonus-${bonusId}`).classList.add('selected');
}

function submitRequest() {
    const selectedCardId = document.getElementById('currentSelectedBonus').value;

    if (!selectedCardId) {
        showMessage('Please select a bonus', 'error');
        return;
    }

    const bonuses = getBonuses();
    const bonusData = bonuses.find(b => b.id === parseInt(selectedCardId));

    if (!bonusData) {
        showMessage('Selected bonus not found', 'error');
        return;
    }

    const request = {
        bonus: bonusData.name,
        bonusId: bonusData.id,
        description: bonusData.description,
        timestamp: new Date().toISOString()
    };

    // Save to localStorage
    saveRequest(request);

    console.log('Bonus Request Submitted:', request);

    if (window.parent !== window) {
        window.parent.postMessage({
            type: 'BONUS_REQUEST_SUBMITTED',
            data: request
        }, '*');
    }

    showMessage(`✓ Bonus request submitted for "${bonusData.name}"`, 'success');
    setTimeout(resetForm, 2000);
}

function resetForm() {
    document.querySelectorAll('.bonus-card-item').forEach(card => card.classList.remove('selected'));
    if (document.getElementById('currentSelectedBonus')) {
        document.getElementById('currentSelectedBonus').value = '';
    }
    const messageDiv = document.getElementById('successMessage');
    if (messageDiv) {
        messageDiv.innerHTML = '';
    }
}

function showMessage(text, type) {
    const messageDiv = document.getElementById('successMessage');
    messageDiv.className = `message ${type}`;
    messageDiv.textContent = text;
}

function showAdminMessage(text, type) {
    const messageDiv = document.getElementById('adminMessage') || document.getElementById('loginMessage');
    if (messageDiv) {
        messageDiv.className = `message ${type}`;
        messageDiv.textContent = text;
    }
}

function addBonus() {
    const name = document.getElementById('bonusName').value.trim();
    const desc = document.getElementById('bonusDesc').value.trim();

    if (!name) {
        showAdminMessage('Please enter a bonus name', 'error');
        return;
    }

    const bonuses = getBonuses();
    const newBonus = {
        id: Math.max(...bonuses.map(b => b.id), 0) + 1,
        name: name,
        description: desc || 'No description'
    };

    bonuses.push(newBonus);
    saveBonuses(bonuses);

    document.getElementById('bonusName').value = '';
    document.getElementById('bonusDesc').value = '';

    renderBonusList();
    showAdminMessage(`✓ Bonus "${name}" added`, 'success');
}

function removeBonus(id) {
    if (!confirm('Remove this bonus?')) return;

    let bonuses = getBonuses();
    bonuses = bonuses.filter(b => b.id !== id);
    saveBonuses(bonuses);

    renderBonusList();
}

function renderBonusList() {
    const bonuses = getBonuses();
    const container = document.getElementById('bonusList');

    if (bonuses.length === 0) {
        container.innerHTML = '<p style="color: var(--color-text-secondary); font-size: 0.875rem;">No bonuses yet</p>';
        return;
    }

    container.innerHTML = bonuses.map(bonus => `
        <div class="bonus-item">
            <div class="bonus-item-info">
                <div class="bonus-item-name">${bonus.name}</div>
                <div class="bonus-item-desc">${bonus.description}</div>
            </div>
            <button class="btn-remove" onclick="removeBonus(${bonus.id})">Remove</button>
        </div>
    `).join('');
}

function renderRequestsList() {
    const requests = getRequests();
    const container = document.getElementById('requestsList');

    if (requests.length === 0) {
        container.innerHTML = '<div class="empty-state">No bonus requests yet</div>';
        return;
    }

    const html = `
        <table class="requests-table">
            <thead>
                <tr>
                    <th>Bonus</th>
                    <th>Description</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                ${requests.map(req => `
                    <tr>
                        <td>${req.bonus}</td>
                        <td>${req.description || '-'}</td>
                        <td>${new Date(req.timestamp).toLocaleString()}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    container.innerHTML = html;
}

function exportCSV() {
    const requests = getRequests();
    if (requests.length === 0) {
        showAdminMessage('No requests to export', 'error');
        return;
    }

    let csv = 'Bonus,Description,Timestamp\n';
    requests.forEach(req => {
        csv += `"${req.bonus}","${req.description || ''}","${req.timestamp}"\n`;
    });

    downloadFile(csv, 'bonus-requests.csv', 'text/csv');
    showAdminMessage(`✓ Exported ${requests.length} requests as CSV`, 'success');
}

function exportJSON() {
    const requests = getRequests();
    if (requests.length === 0) {
        showAdminMessage('No requests to export', 'error');
        return;
    }

    const json = JSON.stringify(requests, null, 2);
    downloadFile(json, 'bonus-requests.json', 'application/json');
    showAdminMessage(`✓ Exported ${requests.length} requests as JSON`, 'success');
}

function downloadFile(content, filename, type) {
    const blob = new Blob([content], { type: type });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

function clearAllRequests() {
    if (!confirm('Clear all bonus requests? This cannot be undone.')) return;

    localStorage.removeItem(REQUESTS_KEY);
    renderRequestsList();
    showAdminMessage('✓ All requests cleared', 'success');
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', initPage);
