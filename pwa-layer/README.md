# PWA-Layer: Архитектура изолированного прикладного слоя

> Создание нативно воспринимаемого интерфейса на базе веб-платформы  
> © 2025 — Максим Кырченков | [t.me/kyrchenkov](https://t.me/kyrchenkov)

---

## Назначение

Файлы в этом репозитории демонстрируют систему:

- Разделяет контексты: PWA и браузер
- Регистрирует два независимых Service Worker
- Обеспечивает оффлайн-доступ и splash-экран
- Предзагружает HTML-страницы на основе поведения
- Учитывает установки и активацию
- Работает фоном, без вмешательства в UX

---

## Архитектура: два Service Worker

```mermaid
graph TD
    A[Пользователь] --> B{Контекст?}
    B -->|PWA| C[service-worker.js]
    B -->|Браузер| D[browser-service-worker.js]

    C --> E[PWA Cache]
    D --> F[Browser Cache]

    E --> G[Оффлайн]
    E --> H[Splash-экран]
    E --> I[Активация → /pwa/activated]

    C & D --> J[DYNAMIC_PAGE_CACHE]
    J --> K["precacheDynamicPage(url)"]
    J --> L[nav_stats → postMessage]

    C & D --> M[fetch → кэш или сеть]
    M --> N[Мгновенные переходы]

    O[log-install.php] --> P[device_id]
    P --> Q[Таблица pwa_install]
```

---

## Компоненты


### 1. `service-worker.js` — для PWA
- Регистрируется в `standalone` режиме
- Кэширует статику: `app-pwa-cache-v1`
- Поддерживает оффлайн: `offline.html`
- Показывает splash-экран на iOS
- Отправляет сигнал активации: `/pwa/activated`

### 2. `browser-service-worker.js` — для браузера
- Регистрируется в браузерном режиме
- Кэширует статику: `app-browser-cache-v1`
- Без оффлайна, без splash
- Только фоновое кэширование
- Scope: `/browser/`

### 3. `manifest.json` — метаданные приложения
- `name`, `short_name`
- `display: standalone`
- Иконки: 192x192, 512x512, maskable
- Splash-экран: `custom_splash.webp`
- Скриншоты: `screenshot_1.webp`, `screenshot_2.webp`

### 4. `offline.html` — оффлайн-страница
- Простой интерфейс
- Сообщение: "Вы сейчас оффлайн"
- Кнопка: "Обновить"
- Локальная разметка и стили

### 5. `activated.php` — активация PWA
- Принимает POST с `activated: true`
- Устанавливает в сессию:
  - `is_pwa_user = true`
  - `pwa_activated = true`
  - `pwa_activated_at = timestamp`
  - `pwa_sw_version`

### 6. `installation_count.php` — счётчик установок
- Запрос: `SELECT COUNT(*) AS total FROM pwa_install`
- Форматирует дату: "5 июня"
- Передаёт в шаблон:
  - `count` — общее число установок
  - `today` — текущая дата
- Выводит через `pwa/counter`

### 7. `header.twig` — интеграция с интерфейсом
- Подключает `manifest.json`
- Добавляет `apple-touch-startup-image` для iOS
- Динамически создаёт splash-экран
- Регистрирует нужный SW по контексту
- Отображает модалки установки

### 8. `footer.twig` — логика установки
- Обрабатывает `beforeinstallprompt`
- Показывает модалку установки через 90 сек
- Генерирует `device_id`
- Отправляет установку в `log-install.php`

---

## Жизненный цикл PWA-пользователя

```mermaid
    sequenceDiagram
      participant П as Пользователь
      participant B as Браузер
      participant SW as SW (PWA)
      participant S as Сервер

      П->>B: Переход на сайт
      B->>B: isPWA = matchMedia(standalone)
      alt Если PWA
        B->>SW: register('/service-worker.js')
        SW->>SW: install → precache static
        SW->>SW: activate → cleanup + POST /pwa/activated
        S->>S: session[is_pwa_user] = true
      end
      П->>П: Навигация
      B->>SW: postMessage(nav_stats)
      SW->>SW: precacheDynamicPage()
      SW->>П: fetch → кэш или сеть
```

---

## Файловая структура

```
/project-root/
├── manifest.json
├── offline.html
├── service-worker.js
├── browser/
│   └── browser-service-worker.js
├── log-install.php
├── pwa/
│   └── controller/
│       ├── activated.php
│       └── installation_count.php
├── image/app_icon/
│   ├── web_app_logo_*.png
│   ├── ios-icon-*.png
│   ├── startup-*.webp
│   └── custom_splash.webp
└── theme/
    └── common/
        ├── header.twig
        └── footer.twig
```
