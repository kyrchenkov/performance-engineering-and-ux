# ISPN — Интеллектуальная система предиктивной навигации

> Предзагрузка на основе поведения пользователей  
> © 2025 — Максим Кырченков | [t.me/kyrchenkov](https://t.me/kyrchenkov)

---

## Назначение

Файлы в этом репозитории демонстрируют систему:

- Анализирует поведение пользователей
- Выявляет популярные цепочки переходов
- Динамически предзагружает контент
- Совмещает серверный прогрев и клиентскую предзагрузку
- Работает фоном, без вмешательства в UX

---

## Архитектура: 5 уровней

```mermaid
graph TD
    A[Пользователь] --> B(tracker.js.track)
    B --> C[nav_stats в localStorage]
    B --> D(sendBeacon → log-stats.php)
    D --> E[user-paths.log]
    E --> F[analyze-paths.php]
    F --> G[top-paths.json]
    G --> H[warm-cache.php] --> I[Серверный кэш]
    G --> J[prefetch.js.pref] --> K[<link prefetch> + ajaxCache]
    G --> L[service-worker.js] --> M[DYNAMIC_PAGE_CACHE]
    G --> N[browser-service-worker.js] --> M
    J --> O[Быстрая навигация]
    K --> O
    M --> O
```

## Компоненты

### 1. tracker.js.track — сбор поведения
- Отслеживает pushState, popstate
- Формирует цепочки: / → /category → /products
- Сохраняет в localStorage.nav_stats
- Отправляет через sendBeacon в log-stats.php

### 2. log-stats.php — приём данных
- Принимает POST с application/json или application/octet-stream
- Проверяет Origin: example-store.com
- Генерирует анонимный device_id
- Пишет в /home/user/project/storage/logs/user-paths.log (вне public_html)
- Формат записи: [timestamp] device_id | /path1 → /path2 | referer_host

### 3. analyze-paths.php — анализ
- Читает последние 500 строк user-paths.log
- Извлекает URL (исключая /, /index.php)
- Формирует top-paths.json — топ-5 URL
- Очищает лог: оставляет последние 500 строк
- Запуск: CRON — каждые 30 минут

### 4. warm-cache.php — прогрев сервера
- Читает top-paths.json
- Отправляет GET-запросы с User-Agent desktop и mobile
- Прогревает OpenCart-кэш
- Логирует в cache-warm.log
- Запуск: CRON — каждые 15 минут

### 5. prefetch.js.pref — клиентская предзагрузка
- Приоритет А: контекст (/ → /category, /specials)
- Приоритет Б: поведение (nav_stats + top-paths.json)
- Использует:
  - `<link rel="prefetch">`
  - `fetch() → ajaxCache[url] = html`
  - `window.preload(url)`
- Учитывает:
  - `navigator.onLine`
  - `saveData`
  - `effectiveType`

### 6. service-worker.js и browser-service-worker.js
- service-worker.js — для PWA:
  - Кэширует ресурсы PWA
  - Поддерживает оффлайн
  - Отправляет сигнал активации
- browser-service-worker.js — для браузера:
  - Упрощённый кэш
  - Только предзагрузка
- Оба:
  - Получают nav_stats через postMessage
  - Выполняют precacheDynamicPage(url)
  - Фильтруют через isExcluded()

### 7. excluded-routes.js.route — фильтрация
- Исключает: /cart, /checkout, /api, /account, статику
- Цель: не тратить трафик, не мешать на ключевых этапах

## Файловая структура (обобщённая)

```
/home/user/project/
├── storage/logs/user-paths.log
├── site-root/
│   ├── log-stats.php
│   ├── cron/
│   │   ├── analyze-paths.php
│   │   ├── warm-cache.php
│   │   └── top-paths.json
│   ├── service-worker.js
│   └── browser/browser-service-worker.js
└── catalog/view/theme/default/js/
    ├── tracker.js.track
    ├── prefetch.js.pref
    └── excluded-routes.js.route
```

##️ PerfGuard — защита производительности

### 10.1. Исключения для критических страниц
На /cart, /checkout — предзагрузка отключена
На первичном поиске (/search?query=..., без page=) — не предзагружается
Активируется при пагинации (/search?query=...&page=2)

```js
const p = location.pathname;
const q = location.search;
if (
  p.includes('/cart') ||
  p.includes('/checkout') ||
  (p.includes('/search') && !q.includes('page=')) ||
  q.includes('route=product/search') ||
  q.includes('route=product/category') ||
  q.includes('route=journal3/filter') ||
  q.includes('route=journal3/blog') ||
  q.includes('route=checkout/')
) {
  window.__perfGuard_criticalAnimation = true;
}
```

### 10.2. Мониторинг производительности
Контроль: FPS, батарея, saveData, effectiveType
Автоотключение при:
- FPS < 40
- Батарея < 15% и не на зарядке
- Вкладка свёрнута

Очистка: setTimeout, setInterval, MutationObserver, WebSocket, requestAnimationFrame
Результат: 95% сессий — 55–60 FPS

---

## Описание реализации проекта

![ISPN](ispn-img.png)
