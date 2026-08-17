/**
 * MT Data Automated Acknowledgements & Notifications Engine
 */

function toggleNotificationDropdown(e) {
    if (e) e.stopPropagation();
    const dropdown = document.getElementById('notificationDropdown');
    if (!dropdown) return;

    const isHidden = dropdown.classList.contains('hidden');
    
    // Close other dropdowns if open
    const userMenu = document.getElementById('dropdownContent');
    if (userMenu && !userMenu.classList.contains('hidden')) {
        userMenu.classList.add('hidden');
    }

    if (isHidden) {
        dropdown.classList.remove('hidden');
        loadNotificationsData();
    } else {
        dropdown.classList.add('hidden');
    }
}

async function loadNotificationsData() {
    const container = document.getElementById('notifListContainer');
    if (!container) return;

    try {
        const rootPath = window.location.pathname.includes('/user/') || window.location.pathname.includes('/admin/') ? '../' : './';
        const res = await fetch(`${rootPath}api/notifications.php`);
        const data = await res.json();

        if (!data.success) {
            container.innerHTML = `<div class="p-6 text-center text-slate-400">Failed to load notifications.</div>`;
            return;
        }

        // Update badge
        updateBadges(data.unread_count);

        if (!data.notifications || data.notifications.length === 0) {
            container.innerHTML = `
                <div class="p-8 text-center text-slate-400">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto text-xl mb-3">
                        <i class="fa-solid fa-bell-slash"></i>
                    </div>
                    <p class="font-bold text-slate-700 text-sm">No Notifications Yet</p>
                    <p class="text-xs text-slate-400 mt-0.5">Receipts and alerts will show up here automatically.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = data.notifications.map(n => {
            const isUnread = parseInt(n.is_read) === 0;
            let iconClass = 'fa-bolt text-brand-600 bg-brand-50';
            if (n.service_type === 'Airtime') iconClass = 'fa-phone-volume text-emerald-600 bg-emerald-50';
            if (n.service_type === 'Data') iconClass = 'fa-wifi text-indigo-600 bg-indigo-50';
            if (n.service_type === 'Cable' || n.service_type === 'Cable TV') iconClass = 'fa-tv text-amber-600 bg-amber-50';
            if (n.service_type === 'Wallet Funding') iconClass = 'fa-wallet text-emerald-600 bg-emerald-50';

            return `
                <div class="p-3.5 flex items-start gap-3 transition-colors ${isUnread ? 'bg-indigo-50/40 hover:bg-indigo-50/70' : 'hover:bg-slate-50'}">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-xs shrink-0 ${iconClass}">
                        <i class="fa-solid ${iconClass.split(' ')[0]}"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1 mb-0.5">
                            <span class="font-bold text-slate-900 truncate">${escapeHtml(n.title)}</span>
                            ${isUnread ? '<span class="w-2 h-2 rounded-full bg-brand-600 shrink-0"></span>' : ''}
                        </div>
                        <p class="text-slate-600 text-[11px] leading-relaxed line-clamp-2">${escapeHtml(n.message)}</p>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1.5 pt-1 border-t border-slate-100">
                            <span><i class="fa-regular fa-clock mr-1"></i>${formatTimeAgo(n.created_at)}</span>
                            <span class="font-semibold text-emerald-600"><i class="fa-solid fa-paper-plane text-[9px] mr-1"></i>Receipt Sent</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

    } catch (e) {
        console.error('Error loading notifications:', e);
        container.innerHTML = `<div class="p-6 text-center text-slate-400">Failed to connect to notification server.</div>`;
    }
}

async function loadSmsLogsData() {
    const container = document.getElementById('smsListContainer');
    if (!container) return;

    try {
        const rootPath = window.location.pathname.includes('/user/') || window.location.pathname.includes('/admin/') ? '../' : './';
        const res = await fetch(`${rootPath}api/sms_logs.php`);
        const data = await res.json();

        if (!data.success || !data.sms_logs || data.sms_logs.length === 0) {
            container.innerHTML = `
                <div class="p-8 text-center text-slate-400">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto text-xl mb-3">
                        <i class="fa-solid fa-comment-sms"></i>
                    </div>
                    <p class="font-bold text-slate-700 text-sm">No SMS Receipts Yet</p>
                    <p class="text-xs text-slate-400 mt-0.5">Automated SMS dispatches to recipient phone numbers appear here.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = data.sms_logs.map(sms => `
            <div class="p-3.5 hover:bg-slate-50 transition-colors">
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-1.5 font-bold text-slate-900 text-xs">
                        <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-mono">${escapeHtml(sms.sender_id)}</span>
                        <span>to ${escapeHtml(sms.phone_number)}</span>
                    </div>
                    <span class="text-[10px] text-emerald-600 font-semibold bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                        <i class="fa-solid fa-circle-check mr-0.5"></i> ${escapeHtml(sms.status)}
                    </span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 font-mono text-[11px] text-slate-700 leading-relaxed">
                    ${escapeHtml(sms.message)}
                </div>
                <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1.5">
                    <span><i class="fa-regular fa-clock mr-1"></i>${formatTimeAgo(sms.created_at)}</span>
                    <span class="text-slate-400">Automated Dispatch</span>
                </div>
            </div>
        `).join('');

    } catch (e) {
        console.error('Error loading SMS logs:', e);
        container.innerHTML = `<div class="p-6 text-center text-slate-400">Failed to load SMS acknowledgements.</div>`;
    }
}

async function markAllNotificationsRead(e) {
    if (e) e.stopPropagation();
    try {
        const rootPath = window.location.pathname.includes('/user/') || window.location.pathname.includes('/admin/') ? '../' : './';
        const res = await fetch(`${rootPath}api/notifications.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_all_read' })
        });
        const data = await res.json();
        if (data.success) {
            updateBadges(0);
            loadNotificationsData();
        }
    } catch (e) {
        console.error('Error marking read:', e);
    }
}

function updateBadges(unreadCount) {
    const navBadge = document.getElementById('navNotifBadge');
    const drawerBadge = document.getElementById('drawerUnreadBadge');

    if (navBadge) {
        if (unreadCount > 0) {
            navBadge.textContent = unreadCount;
            navBadge.classList.remove('hidden');
        } else {
            navBadge.classList.add('hidden');
        }
    }

    if (drawerBadge) {
        if (unreadCount > 0) {
            drawerBadge.textContent = `${unreadCount} new`;
            drawerBadge.classList.remove('hidden');
        } else {
            drawerBadge.classList.add('hidden');
        }
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatTimeAgo(dateString) {
    if (!dateString) return 'Just now';
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);

    if (seconds < 60) return 'Just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
}

// Close notifications when clicking outside
document.addEventListener('click', function(e) {
    const widget = document.getElementById('notificationWidget');
    const dropdown = document.getElementById('notificationDropdown');
    if (widget && dropdown && !widget.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

// Periodic background unread count check
document.addEventListener('DOMContentLoaded', function() {
    const rootPath = window.location.pathname.includes('/user/') || window.location.pathname.includes('/admin/') ? '../' : './';
    fetch(`${rootPath}api/notifications.php`)
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                updateBadges(data.unread_count);
            }
        })
        .catch(() => {});
});
