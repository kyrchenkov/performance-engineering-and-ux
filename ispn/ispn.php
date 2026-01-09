<?php
/**
 * Интеллектуальная Система Предиктивной Навигации (ISPN)
 * 
 * Предзагрузка на основе поведения пользователей
 * 
 * Уровни:
 * 1. Сбор поведения (tracker.js.track)
 * 2. Анализ цепочек (analyze-paths.php)
 * 3. Прогрев кэша (warm-cache.php)
 * 4. Клиентская предзагрузка (prefetch.js.pref)
 * 5. Динамическое кэширование (Service Worker)
 * 
 * Работает по двум приоритетам:
 * - А: жёсткие правила по контексту
 * - Б: поведенческая адаптация
 * 
 * © 2025 — Максим Кырченков | t.me/kyrchenkov
 */

// ========================================
// УРОВЕНЬ 1: Сбор поведения — tracker.js.track + log-stats.php
// ========================================
// Источник: tracker.js.track, log-stats.php

/**
 * Формат данных в localStorage
 * 
 * localStorage.nav_stats = {
 *   "/ → /category": 5,
 *   "/category → /wellness": 4
 * };
 */

function log_user_path($device_id, $chain, $referer_host) {
    $logFile = '/home/user/project/storage/logs/user-paths.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    if (!is_writable($logDir)) {
        error_log("Logger: log dir not writable");
        return false;
    }

    $line = sprintf(
        "[%s] %s | %s | %s\n",
        date('Y-m-d H:i:s'),
        $device_id,
        $chain,
        $referer_host
    );

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}


// ========================================
// УРОВЕНЬ 2: Анализ цепочек — analyze-paths.php
// ========================================
// Источник: analyze-paths.php

function analyze_paths() {
    $logFile = '/home/user/project/storage/logs/user-paths.log';
    $outputFile = '/home/user/project/site-root/cron/top-paths.json';
    $statsLogFile = '/home/user/project/site-root/cron/paths-analyze.log';

    $timestamp = date('Y-m-d H:i:s');

    file_put_contents($statsLogFile, "[$timestamp] Запущен анализ путей\n", FILE_APPEND | LOCK_EX);

    if (!file_exists($logFile)) {
        $msg = "[$timestamp] Файл $logFile не найден\n";
        file_put_contents($statsLogFile, $msg, FILE_APPEND | LOCK_EX);
        return;
    }

    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    $lines = array_slice($lines, -500);

    $stats = [];
    foreach ($lines as $line) {
        preg_match_all('!(/[^\s?&"\'<>]+)!u', $line, $matches);
        foreach ($matches[1] as $url) {
            if (!in_array($url, ['/', '/index.php'])) {
                $stats[$url] = ($stats[$url] ?? 0) + 1;
            }
        }
    }

    if (empty($stats)) {
        $msg = "[$timestamp] Нет данных для анализа\n";
        file_put_contents($statsLogFile, $msg, FILE_APPEND | LOCK_EX);
        return;
    }

    arsort($stats);
    $top = array_slice(array_keys($stats), 0, 5);

    $result = file_put_contents($outputFile, json_encode(['urls' => $top], JSON_UNESCAPED_SLASHES));
    if ($result === false) {
        $msg = "[$timestamp] Не удалось записать $outputFile\n";
        file_put_contents($statsLogFile, $msg, FILE_APPEND | LOCK_EX);
    } else {
        $msg = "[$timestamp] top-paths.json обновлён: [" . implode(', ', $top) . "]\n";
        file_put_contents($statsLogFile, $msg, FILE_APPEND | LOCK_EX);
    }

    file_put_contents($logFile, implode("\n", $lines) . "\n");
}

/**
 * CRON: каждые 30 минут
 * */30 * * * * /usr/bin/php /home/user/project/site-root/cron/analyze-paths.php
 */


// ========================================
// УРОВЕНЬ 3: Прогрев кэша — warm-cache.php
// ========================================
// Источник: warm-cache.php

function warm_cache() {
    $domain = 'https://example-store.com';
    $file = '/home/user/project/site-root/cron/top-paths.json';
    $logFile = '/home/user/project/site-root/cron/cache-warm.log';

    set_time_limit(120);
    $startTime = microtime(true);
    $timestamp = date('Y-m-d H:i:s');

    if (!file_exists($file)) {
        $msg = "[$timestamp] Файл $file не найден\n";
        file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
        return;
    }

    $urls = json_decode(file_get_contents($file), true)['urls'] ?? [];
    if (empty($urls)) {
        $msg = "[$timestamp] Файл $file пустой\n";
        file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
        return;
    }

    $msg = "[$timestamp] Прогрев для " . count($urls) . " URL\n";
    file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);

    $USER_AGENTS = [
        'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'mobile'  => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1'
    ];

    $multi = curl_multi_init();
    $curls = [];
    $requests = [];

    foreach ($urls as $path) {
        foreach ($USER_AGENTS as $type => $ua) {
            $url = $domain . $path;
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7'
                ],
            ]);
            curl_multi_add_handle($multi, $ch);
            $curls[] = $ch;
            $requests[] = ['url' => $url, 'ua' => $type];
        }
    }

    do {
        curl_multi_exec($multi, $running);
        if ($running) curl_multi_select($multi, 1.0);
    } while ($running > 0);

    $desktop_ok = $mobile_ok = 0;
    foreach ($curls as $i => $ch) {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $url = $requests[$i]['url'];
        $ua_type = $requests[$i]['ua'];
        if ($http_code === 200) {
            if ($ua_type === 'desktop') $desktop_ok++;
            else $mobile_ok++;
        } else {
            $msg = "[$timestamp] Ошибка ($http_code): $url (UA: $ua_type)\n";
            file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
        }
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);

    $duration = round(microtime(true) - $startTime);
    $msg = "[$timestamp] Прогрев завершён\n";
    $msg .= "   ПК: $desktop_ok / " . count($urls) . "\n";
    $msg .= "   Мобильные: $mobile_ok / " . count($urls) . "\n";
    $msg .= "   Время: $duration сек\n";
    file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
}

/**
 * CRON: каждые 15 минут
 * */15 * * * * /usr/bin/php /home/user/project/site-root/cron/warm-cache.php
 */


// ========================================
// УРОВЕНЬ 4: Предзагрузка — prefetch.js.pref
// ========================================
// Источник: prefetch.js.pref

function get_urls_to_prefetch() {
    $current = $_SERVER['REQUEST_URI'] ?? '/';
    $current = rtrim($current, '/') ?: '/';

    $excluded_patterns = ['/cart', '/checkout', '/api', '/account', '/register', '/search'];
    foreach ($excluded_patterns as $pattern) {
        if (strpos($current, $pattern) !== false) {
            return [];
        }
    }

    $priorityA = [];
    switch ($current) {
        case '/':
            $priorityA = ['/category', '/for-women', '/products', '/specials', '/latest'];
            break;
        case '/category':
            $priorityA = ['/products', '/wellness', '/lifestyle'];
            break;
    }

    $priorityB = [];
    $topFile = '/home/user/project/site-root/cron/top-paths.json';
    if (file_exists($topFile)) {
        $top = json_decode(file_get_contents($topFile), true)['urls'] ?? [];
        $priorityB = array_values(array_diff($top, $priorityA));
    }

    return array_slice(array_unique(array_merge($priorityA, $priorityB)), 0, 15);
}


// ========================================
// УРОВЕНЬ 5: Service Worker (две версии)
// ========================================
// Источник: service-worker.js, browser-service-worker.js

/*
 * service-worker.js — для PWA
 *   - Кэширует ресурсы PWA
 *   - Отправляет сигнал активации
 *   - Использует DYNAMIC_PAGE_CACHE
 *
 * browser-service-worker.js — для браузера
 *   - Упрощённый кэш
 *   - Без PWA-зависимостей
 *
 * Общее:
 *   - Получают nav_stats через postMessage
 *   - Выполняют precacheDynamicPage(url)
 *   - Фильтруют через isExcluded()
 */

// ========================================
// PerfGuard — защита производительности
// ========================================
// Источник: prefetch.js.pref

/*
 * Условия отключения предзагрузки:
 * - /cart, /checkout
 * - /search?query=... (без page=)
 * - route=product/search, route=product/category, journal3, route=checkout/
 *
 * Проверка:
 *   const p = location.pathname;
 *   const q = location.search;
 *   if (
 *     p.includes('/cart') ||
 *     p.includes('/checkout') ||
 *     (p.includes('/search') && !q.includes('page=')) ||
 *     q.includes('route=product/search') ||
 *     q.includes('route=product/category') ||
 *     q.includes('route=journal3/filter') ||
 *     q.includes('route=journal3/blog') ||
 *     q.includes('route=checkout/')
 *   ) {
 *     window.__perfGuard_criticalAnimation = true;
 *   }
 *
 * Мониторинг:
 * - FPS < 40 → отключение
 * - Батарея < 15% и не на зарядке → отключение
 * - Вкладка свёрнута → отключение
 * - Очистка: setInterval, setTimeout, MutationObserver, WebSocket, requestAnimationFrame
 */

// ========================================
// ТОЧКА ЗАПУСКА
// ========================================

// analyze_paths();
// warm_cache();
// $urls = get_urls_to_prefetch();
// foreach ($urls as $url) {
//     echo "<link rel='prefetch' href='https://example-store.com$url'>";
// }