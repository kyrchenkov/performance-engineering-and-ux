# OPS-UX: Операционная система поддержки для e-commerce

> Реактивная система автоматизации для операторов на базе Chrome Extensions  
> © 2025 — Максим Кырченков | [t.me/kyrchenkov](https://t.me/kyrchenkov)

---

## Назначение

Файлы демонстрируют систему:

- Ускоряет ввод шаблонных текстов
- Автоматизирует форматирование
- Сохраняет состояние между сессиями
- Устраняет рутину ввода адреса и телефона
- Работает в браузере без сервера
- Основана на Manifest V3

---

## Архитектура: расширение как система

```mermaid
graph TD
    A[Пользователь] --> B[Browser]
    B --> C{Контекст?}
    C -->|service-a.domain| D[content_service_a.js]
    C -->|service-b.domain| E[content_service_b.js]

    D --> F[Комментарии для курьеров]
    E --> G[Шаблоны для чата]
    E --> H[Ввод: улица, кв, телефон]
    E --> I[Реактивное обновление]

    D & E --> J[Сохранение в localStorage]
    D & E --> K[formatPhone()]
    E --> L[Вставка в [data-qa-id="main-input-element"]]

    E --> M[MutationObserver]
    M --> N[Скрытие: [data-qa-id="warning-license"]]
```

---

## Компоненты

### 1. manifest.json
- manifest_version: 3
- permissions: ["activeTab", "scripting"]
- host_permissions — для https://service-a.domain/*, https://service-b.domain/*
- content_scripts — подключение по matches
- Подключает:
  - content_service_a.js — на service-a.domain
  - content_service_b.js — на service-b.domain

### 2. content_service_a.js
- Позиция: fixed, bottom: 80px, left: 10px
- Контейнер: белый, скруглённый, с тенью
- Два шаблона:
  - "Позвонить получателю за пару минут до приезда. Спасибо!"
  - "Оставить посылку у двери и позвонить получателю. Спасибо!"
- Подписи: «До подъезда», «Оставить у двери»
- События:
  - click → копирование в буфер
  - mouseover → выделение текста
  - mouseout → снятие выделения
- Использует fallbackCopyText при ошибке копирования

### 3. content_service_b.js
- Два UI-блока:
  - mainContainer — шаблоны ответов (bottom: 0, right: 0)
  - receiverContainer — ввод данных (top: 105px, right: 0)
- Шаблоны:
  - 16 текстов в texts
  - 16 подписей в comments
- Поля ввода:
  - street — улица
  - apartment — квартира
  - phone — телефон
- Флаги:
  - noBuilding — без корпуса
  - leaveAtDoor — оставить у двери
  - callUponArrival — позвонить по приезду
- Кнопка "Очистить" — сброс всех полей и состояния

### 4. localStorage
- Ключ: opsux_deliveryData
- Сохраняет:
  - street, apartment, phone
  - noBuilding, leaveAtDoor, callUponArrival
- saveData() — вызывается при изменении
- loadData() — вызывается при запуске

### 5. formatPhone()
- Чистит строку: replace(/\D/g, '')
- Заменяет 8 на 7
- Поддерживает +7
- Форматирует:
  - +7 XXX XXX XX XX
  - XXX XXX XX XX
- Используется в шаблонах и при вставке

### 6. updateTextElements()
- Обновляет три шаблона в реальном времени
- Формирует:
  - street с [без корпуса] при noBuilding
  - apartment
  - formattedPhone
  - дополнительно: оставить у двери, позвонить по приезду
- Выводит полный текст с переносами

### 7. MutationObserver
- Отслеживает добавление узлов
- Удаляет элементы с data-qa-id="warning-license"
- Использует hideElement() с display: none !important
- Наблюдение: childList: true, subtree: true

### 8. Вставка в интерфейс
- Находит: [data-qa-id="main-input-element"]
- При клике:
  - Добавляет текст в value
  - Генерирует событие input с { bubbles: true }
- Для первых трёх шаблонов — вставляется динамически сформированный текст

---

## Файловая структура

```
/project-root/
├── manifest.json
├── content_service_a.js
├── content_service_b.js
└── images/
    └── icon-128.png
```
