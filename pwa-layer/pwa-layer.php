<?php
/**
 * PWA-Layer: Архитектура изолированного прикладного слоя
 * 
 * Создание нативно воспринимаемого интерфейса на базе веб-платформы
 * 
 * Уровни:
 * 1. Регистрация SW по контексту
 * 2. Кэширование статики
 * 3. Динамическое кэширование HTML
 * 4. Оффлайн и splash
 * 5. Установка и активация
 * 
 * © 2025 — Максим Кырченков | t.me/kyrchenkov
 */

// ========================================
// УРОВЕНЬ 1: Регистрация Service Worker по контексту
// ========================================
// Источник: header.twig

/*
 * Регистрация:
 * 
 * const isPWA = matchMedia('(display-mode: standalone)').matches ||
 *               (typeof navigator.standalone !== 'undefined' && navigator.standalone);
 * 
 * if (isPWA) {
 *     navigator.serviceWorker.register('/service-worker.js');
 * } else {
 *     navigator.serviceWorker.register('/browser/browser-service-worker.js', { scope: '/browser/' });
 * }
 */

// ========================================
// УРОВЕНЬ 2: Кэширование статики — service-worker.js
// ========================================
// Источник: service-worker.js

/*
 * PWA-SW: service-worker.js
 * 
 * const CACHE_NAME = 'app-pwa-cache-v1';
 * const staticResources = [
 *   '/', '/index.php', '/manifest.json', '/offline.html',
 *   '/image/app_icon/custom_splash.webp',
 *   '/catalog/view/css/fonts.css',
 *   '/catalog/view/javascript/font-awesome/css/font-awesome.min.css',
 *   '/catalog/view/javascript/bootstrap/css/bootstrap.min.css',
 *   '/catalog/view/theme/default/stylesheet/stylesheet.css',
 *   '/catalog/view/javascript/jquery/jquery-2.1.1.min.js',
 *   '/catalog/view/theme/default/js/surface/rise-footer.js.sfc',
 *   // ... другие статические ресурсы
 * ];
 */

/*
 * Browser-SW: browser-service-worker.js
 * 
 * const BROWSER_CACHE_NAME = 'app-browser-cache-v1';
 * const browserStaticResources = [
 *   '/', '/index.php',
 *   '/catalog/view/css/fonts.css',
 *   '/catalog/view/javascript/font-awesome/css/font-awesome.min.css',
 *   '/catalog/view/javascript/bootstrap/css/bootstrap.min.css',
 *   '/catalog/view/theme/default/stylesheet/stylesheet.css',
 *   '/catalog/view/javascript/jquery/jquery-2.1.1.min.js',
 *   '/catalog/view/theme/default/js/surface/rise-footer.js.sfc',
 *   // ... другие ресурсы
 * ];
 */

// ========================================
// УРОВЕНЬ 3: Динамическое кэширование — DYNAMIC_PAGE_CACHE
// ========================================
// Источник: service-worker.js, browser-service-worker.js

/*
 * Общий механизм:
 * 
 * const DYNAMIC_PAGE_CACHE = 'dynamic-pages-v1';
 * 
 * async function precacheDynamicPage(url) {
 *     if (isExcluded(url)) return;
 * 
 *     try {
 *         if (dynamicFetchController) dynamicFetchController.abort();
 *         dynamicFetchController = new AbortController();
 * 
 *         const response = await fetch(url, { signal: dynamicFetchController.signal });
 *         if (!response.ok) return;
 * 
 *         const html = await response.text();
 *         const pageCache = await caches.open(DYNAMIC_PAGE_CACHE);
 *         await pageCache.put(url, new Response(html, { headers: response.headers }));
 *     } catch (e) {
 *         if (e.name !== 'AbortError') {
 *             console.warn('⚠️ Ошибка при предкэшировании:', url, e);
 *         }
 *     } finally {
 *         dynamicFetchController = null;
 *     }
 * }
 */

/*
 * Источник данных:
 * 
 * self.addEventListener('message', (event) => {
 *     if (event.data.type === 'nav_stats') {
 *         const urls = Object.keys(event.data.data)
 *             .map(chain => chain.split(' → ').pop())
 *             .filter(u => 
 *                 u && 
 *                 u !== '/' && 
 *                 !isExcluded(self.location.origin + u)
 *             );
 * 
 *         urls.forEach(url => {
 *             precacheDynamicPage(self.location.origin + url);
 *         });
 *     }
 * });
 */

// ========================================
// УРОВЕНЬ 4: Оффлайн и splash-экран
// ========================================
// Источник: manifest.json, offline.html, header.twig

/*
 * manifest.json:
 * 
 * {
 *   "name": "App Name",
 *   "short_name": "App",
 *   "start_url": "/",
 *   "display": "standalone",
 *   "background_color": "#ffffff",
 *   "theme_color": "#ffffff",
 *   "icons": [
 *     { "src": "/image/app_icon/web_app_logo_192_any.png", "sizes": "192x192" },
 *     { "src": "/image/app_icon/web_app_logo_512_maskable.png", "sizes": "512x512" }
 *   ],
 *   "splash_screen": {
 *     "image": "/image/app_icon/custom_splash.webp",
 *     "background_color": "#ffffff"
 *   }
 * }
 */

/*
 * iOS Splash (из header.twig):
 * 
 * const startupImages = [
 *   { href: '/image/app_icon/startup-640x1136.webp', media: '(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2)' },
 *   { href: '/image/app_icon/startup-750x1334.webp', media: '(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)' },
 *   { href: '/image/app_icon/startup-1170x2532.webp', media: '(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)' }
 * ];
 * 
 * startupImages.forEach(img => {
 *     const link = document.createElement('link');
 *     link.rel = 'apple-touch-startup-image';
 *     link.href = img.href;
 *     if (img.media) link.media = img.media;
 *     document.head.appendChild(link);
 * });
 */

// ========================================
// УРОВЕНЬ 5: Установка и активация
// ========================================
// Источник: activated.php, installation_count.php, footer.twig, log-install.php

// --- activated.php ---
class ControllerPWAActivated extends Controller {
    public function index() {
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $json = json_decode(file_get_contents('php://input'), true);

        if ($json && !empty($json['activated'])) {
            $this->session->data['is_pwa_user'] = true;
            $this->session->data['pwa_activated'] = true;
            $this->session->data['pwa_activated_at'] = date('Y-m-d H:i:s');
            $this->session->data['pwa_sw_version'] = $json['sw_version'] ?? '';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['success' => true]));
    }
}

// --- installation_count.php ---
class ControllerPwaInstallationCount extends Controller {
    public function index() {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "pwa_install");

        if ($this->db->countAffected()) {
            $count = $query->row['total'];
        } else {
            $count = 0;
        }

        $today = new DateTime('today');
        $monthNames = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];
        $formattedDate = $today->format('j ') . $monthNames[(int)$today->format('n')];

        $data['count'] = $count;
        $data['today'] = $formattedDate;

        $this->response->setOutput($this->load->view('pwa/counter', $data));
    }
}

// --- log-install.php ---
// Данный файл находится в корне проекта и обрабатывает установку PWA.
// Путь: /project-root/log-install.php
// Не встроен в layer.php, так как это отдельный скрипт.
// См. README.md для реализации.

// --- footer.twig (логика установки) ---
/*
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    setTimeout(() => {
        if (deferredPrompt && !localStorage.getItem('install_shown')) {
            showInstallModal();
            localStorage.setItem('install_shown', '1');
        }
    }, 90000);
});

function getDeviceId() {
    let id = localStorage.getItem('pwa_device_id');
    if (!id) {
        id = 'pwa_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('pwa_device_id', id);
    }
    return id;
}

async function installApp() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        if (outcome === 'accepted') {
            fetch('/log-install.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ device_id: getDeviceId() })
            });
        }
        deferredPrompt = null;
    }
    closeModal();
}
*/

// ========================================
// ТОЧКА ЗАПУСКА
// ========================================

// Регистрация SW → Кэширование → Динамический кэш → Установка → Активация → Оффлайн