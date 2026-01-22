# ML-Cache — Многоуровневая система кэширования

> Архитектура производительности для унаследованной платформы  
> © 2025 — Максим Кырченков | [t.me/kyrchenkov](https://t.me/kyrchenkov)

---

## Назначение

Файлы демонстрируют систему:

- Снижает TTFB до минимума
- Уменьшает нагрузку на CPU, RAM, I/O
- Создаёт устойчивость к пикам нагрузки
- Работает без внешних зависимостей
- Не требует миграции или замены ядра
- Оптимизирована под 12 ГБ RAM и NVMe

---

## Архитектура: 4 основных уровня

```mermaid
graph TD
    A[Браузер] --> B[Service Worker]
    B --> C[(DYNAMIC_PAGE_CACHE)]
    C --> A
    A --> D[Apache]
    D --> E[PHP-FPM]
    E --> F[OPcache]
    D --> G[OpenCart]
    G --> H[MySQL InnoDB]
    H --> I[InnoDB Buffer Pool]
    I --> F
    C --> D
```

## Основные уровни системы ML-Cache

### Уровень 1: OPcache — кэширование байткода PHP в RAM
- Хранит скомпилированный PHP-байткод в памяти
- Исключает перекомпиляцию при каждом запросе
- Ускоряет исполнение в 3–5 раз
- Ключевые настройки:
  - `opcache.memory_consumption=2048`
  - `opcache.max_accelerated_files=100000`
  - `opcache.validate_timestamps=0`
  - `opcache.fast_shutdown=1`
  - `opcache.huge_code_pages=1`
  - `opcache.interned_strings_buffer=32`
  - `opcache.revalidate_freq=0`
- Источник: `10-opcache.ini`

### Уровень 2: OpenCart Lightning + APCu — кэширование ответов на уровне приложения
- Кэширует HTML-ответы до запуска контроллеров
- Поддерживает: категории, товары, корзину, сессии
- Использует APCu как бэкенд хранения
- Умная инвалидация при изменении данных
- Не заменяет OPcache — дополняет его
- Работает в цепочке: OPcache → Lightning → Apache

### Уровень 3: Brotli + GZIP + HTTP-заголовки — сжатие и клиентское кэширование
- Brotli — для статики: JS, CSS, JSON
- GZIP — для HTML (через Lightning, предварительно сжато; AddOutputFilterByType DEFLATE text/html)
- Причина:
  - Brotli даёт лучшее сжатие, но медленнее сжимает
  - Выигрыш по объёму не перекрывает проигрыш по времени
  - Главное — скорость отдачи кэшированного контента, а не степень сжатия
- Заголовки:
  - Cache-Control: public, max-age=31536000, immutable
  - ExpiresByType — для статики
  - HSTS, X-Frame-Options, X-Content-Type-Options
  - X-XSS-Protection, Set-Cookie (HttpOnly, Secure)
- Запрет доступа к /src, /conf, /lib, /plugins
- Источник: `.htaccess`

### Уровень 4: Service Worker — оффлайн и кэширование на устройстве
- Два файла:
  - `/service-worker.js` — для PWA
  - `/browser/service-worker.js` — для браузеров
- Стратегия: cache-first
- Кэширует:
  - Статику: CSS, JS, изображения
  - Предзагруженные HTML-страницы
  - Поддерживает оффлайн
- Разделение позволяет избежать конфликтов между контекстами

## Глубинная структура: 7 технических уровней
Реализация архитектуры на уровне кода и инфраструктуры.

### Уровень 5: InnoDB Buffer Pool — оптимизация MySQL
- Таблицы на MyISAM — неэффективны
- Переведены на InnoDB — для поддержки буферизации в RAM
  - `innodb_buffer_pool_size = 8G`
  - `innodb_buffer_pool_instances = 6`
  - `innodb_flush_method = O_DIRECT`
  - `innodb_log_file_size = 2G`
  - `innodb_io_capacity = 2000`
  - `innodb_read_io_threads = 8`
  - `innodb_write_io_threads = 8`
  - `tmp_table_size = 512M`, `max_heap_table_size = 512M`
- Всё, что можно — остаётся в RAM
- Query Cache отключён — неэффективен при нагрузке
- Источник: `my.cnf`

### Уровень 6: Прогрев кэша через CRON
- Скрипты: `preload_pages.sh`, `preload_pages_2.sh`, `preload_pages_3.sh`
- Частота: каждые 5, 15, 60 минут
- Использует curl с разными User-Agent
- Цель: прогрев "холодных" страниц до обращения пользователя
- Списки URL: `pages_to_warm.txt`, `pages_to_warm_2.txt`, `pages_to_warm_3.txt`
- Источник: CRON + bash-скрипты

### Уровень 7: Brotli vs GZIP — баланс сжатия и скорости
- Brotli:
  - Лучшее сжатие
  - Используется для статики
- GZIP:
  - Используется для HTML
  - Lightning кэширует HTML уже в GZIP
  - Пересжатие — избыточная нагрузка
- Вывод:
  - Степень сжатия — вторична
  - Главное — скорость отдачи кэшированного контента
  - Поэтому: Brotli для статики, GZIP для HTML — оптимальный баланс

## Анализ альтернатив

### Redis: развертывание и обоснованный отказ
- Redis был развёрнут в Docker
- Реализован класс Cache\Redis с pconnect, таймаутами, сериализацией
- Проведён прогрев через:
  - `warm-cache.php` — наполнение кэша
  - `preload_pages.sh` — имитация трафика
- При этом:
  - Кэш оставался "холодным"
  - Hit rate — низкий
  - Выигрыш в скорости — не статистически значим
- Причина:
  - При небольшом трафике основная нагрузка — на "холодных" страницах
  - Они уже покрываются OPcache + Lightning + APCu
  - Redis добавляет издержки, но не ускоряет доставку
- Вывод: избыточен
- Решение: не внедрять
- Код оставлен как R&D

## Файловая структура

```
/home/user/site/
├── scripts/
│   ├── preload_pages.sh
│   ├── preload_pages_2.sh
│   ├── preload_pages_3.sh
│   └── txt/
│       ├── pages_to_warm.txt
│       ├── pages_to_warm_2.txt
│       └── pages_to_warm_3.txt
│
├── .htaccess                     # Безопасность, Brotli, заголовки
├── service-worker.js             # Для PWA
└── browser/
    └── service-worker.js         # Для браузеров

/etc/
├── my.cnf                        # InnoDB, buffer pool, логи
├── php71w/
│   └── php.d/10-opcache.ini      # Настройка OPcache
└── httpd/conf/                   # Apache: OpenCart, SEO, сжатие, заголовки
```

## Пример работы: OPcache hit rate
Реальные данные с рабочего сервера:

```php
[opcache_statistics] => Array
    (
        [hits] => 17155079
        [misses] => 1202
        [opcache_hit_rate] => 99.992993819581
    )
```

> **Интерпретация**:  
> - Попадание в кэш: >99.99%  
> - Повторная компиляция PHP — почти отсутствует  
> - Система стабильна, нагрузка на CPU — минимальна

---

## Описание реализации проекта

![ML-Cache](ml-cache-img.png)
