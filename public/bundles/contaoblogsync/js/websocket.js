(function () {
    'use strict';

    // Read config from window.BLOG_SYNC_CONFIG (injected by DCA onload_callback)
    var config = window.BLOG_SYNC_CONFIG;

    if (!config || !config.connectionId) {
        return;
    }

    var connectionId = config.connectionId;
    var wsUrl = config.wsUrl || 'https://app.agency-powerstack.com';
    var syncTriggerUrl = config.syncTriggerUrl || '/contao/blog-sync/trigger-sync';
    var requestToken = config.requestToken || '';

    // Load Socket.io client from CDN
    var socketScript = document.createElement('script');
    socketScript.src = 'https://cdn.socket.io/4.7.5/socket.io.min.js';
    socketScript.onload = function () {
        initWebSocket();
    };
    document.head.appendChild(socketScript);

    function initWebSocket() {
        if (typeof io === 'undefined') {
            console.warn('BlogSync: Socket.io not available');
            return;
        }

        var socket = io(wsUrl, {
            path: '/ws/contao',
            transports: ['websocket', 'polling'],
            auth: {
                connection_id: connectionId
            },
            reconnection: true,
            reconnectionDelay: 5000,
            reconnectionAttempts: 10
        });

        socket.on('connect', function () {
            console.log('BlogSync: WebSocket connected');
            updateStatusIndicator(true);
        });

        socket.on('disconnect', function () {
            console.log('BlogSync: WebSocket disconnected');
            updateStatusIndicator(false);
        });

        socket.on('new_blogs', function (data) {
            console.log('BlogSync: New blogs notification received', data);
            showNotification('Neue Blog-Beiträge verfügbar! Synchronisation wird gestartet...');
            triggerSync();
        });

        socket.on('sync_required', function (data) {
            console.log('BlogSync: Sync required', data);
            showNotification('Synchronisation erforderlich...');
            triggerSync();
        });

        socket.on('connection_deleted', function (data) {
            console.log('BlogSync: Connection deleted by backend', data);
            showNotification('Verbindung wurde vom Agency Powerstack entfernt. Lokale Konfiguration wird gelöscht...');
            triggerDisconnect();
            socket.disconnect();
        });

        socket.on('connect_error', function (err) {
            console.warn('BlogSync: WebSocket connection error', err.message);
            updateStatusIndicator(false);
        });
    }

    function triggerSync() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', syncTriggerUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    var result = JSON.parse(xhr.responseText);
                    if (result.imported > 0) {
                        showNotification(result.imported + ' Blog-Beiträge importiert!');
                        // Reload the page to show updated data
                        setTimeout(function () {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showNotification('Keine neuen Blog-Beiträge.');
                    }
                } catch (e) {
                    console.error('BlogSync: Error parsing sync response', e);
                }
            } else {
                console.error('BlogSync: Sync trigger failed', xhr.status);
                showNotification('Fehler bei der Synchronisation.');
            }
        };
        xhr.send('connection_id=' + encodeURIComponent(connectionId) + '&REQUEST_TOKEN=' + encodeURIComponent(requestToken));
    }

    function triggerDisconnect() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/contao/blog-sync/disconnect', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            if (xhr.status === 200) {
                showNotification('Lokale Konfiguration wurde entfernt.');
                setTimeout(function () {
                    window.location.reload();
                }, 2000);
            } else {
                console.error('BlogSync: Disconnect failed', xhr.status);
                showNotification('Fehler beim Entfernen der lokalen Konfiguration.');
            }
        };
        xhr.send('connection_id=' + encodeURIComponent(connectionId) + '&REQUEST_TOKEN=' + encodeURIComponent(requestToken));
    }

    function showNotification(message) {
        // Use Contao's built-in notification system if available
        if (typeof Backend !== 'undefined' && typeof Backend.showMessage === 'function') {
            Backend.showMessage(message);
            return;
        }

        // Fallback: create a simple notification banner
        var existing = document.getElementById('blog-sync-notification');
        if (existing) {
            existing.remove();
        }

        var notification = document.createElement('div');
        notification.id = 'blog-sync-notification';
        notification.style.cssText = 'position:fixed;top:60px;right:20px;z-index:99999;padding:12px 20px;' +
            'background:#1a73e8;color:white;border-radius:6px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.15);' +
            'transition:opacity 0.3s;cursor:pointer;';
        notification.textContent = message;
        notification.onclick = function () {
            notification.remove();
        };

        document.body.appendChild(notification);

        setTimeout(function () {
            if (notification.parentNode) {
                notification.style.opacity = '0';
                setTimeout(function () {
                    notification.remove();
                }, 300);
            }
        }, 5000);
    }

    function updateStatusIndicator(connected) {
        var indicator = document.getElementById('blog-sync-ws-status');
        if (!indicator) {
            // Create status indicator in the header area
            indicator = document.createElement('span');
            indicator.id = 'blog-sync-ws-status';
            indicator.style.cssText = 'display:inline-block;width:8px;height:8px;border-radius:50%;margin-left:6px;vertical-align:middle;';
            indicator.title = connected ? 'WebSocket verbunden' : 'WebSocket getrennt';

            var headerTitle = document.querySelector('.tl_listing_container .tl_header, h1');
            if (headerTitle) {
                headerTitle.appendChild(indicator);
            }
        }

        indicator.style.background = connected ? '#4caf50' : '#f44336';
        indicator.title = connected ? 'WebSocket verbunden' : 'WebSocket getrennt';
    }
})();
