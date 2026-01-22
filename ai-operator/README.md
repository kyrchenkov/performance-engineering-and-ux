# AI-Оператор — живое UX-присутствие на OpenCart

> Визуальная иллюзия сознания через техническую архитектуру  
> © 2025 — Максим Кырченков | [t.me/kyrchenkov](https://t.me/kyrchenkov)

---

## Назначение

Файлы демонстрируют систему:

- Работает на OpenCart
- 80% пользователей — мобильные
- Внедряет **иллюзию живого AI-оператора**
- Через визуальные, звуковые, поведенческие эффекты
- Без изменения ядра CMS
- Через модульные JS-скрипты в `footer.twig`
- С защитой производительности (`PerfGuard`)

---

## Архитектура: 4 слоя

| Слой | Технология | Назначение |
|------|-----------|-----------|
| **1. Визуальный** | CSS, canvas, WebGL-подобные эффекты | Создание «живого глаза» |
| **2. Интерактивный** | JS, MutationObserver, IntersectionObserver | Реакция на действия |
| **3. Коммуникационный** | `showNotification()`, `typing`, уведомления | Диалог от первого лица |
| **4. Защитный** | PerfGuard, FPS-мониторинг, battery API | Автоотключение при нагрузке |

---

## Схема подключения

Файлы подключаются в `footer.twig` с условием по роуту:

```twig
<script src="catalog/view/theme/{{ theme_path }}/js/surface/rise-footer.js.sfc?r=1.0.8" defer></script>

{% if route == 'product/product' %}
	<script src="catalog/view/theme/{{ theme_path }}/js/surface/rise-product.js.sfc?r=1.0.8" defer></script>
{% endif %}

{% if route == 'product/search' %}
	<script src="catalog/view/theme/{{ theme_path }}/js/surface/rise-search.js.sfc?r=1.1.3" defer></script>
{% endif %}

{% if route == 'checkout/cart' %}
	<script src="catalog/view/theme/{{ theme_path }}/js/surface/rise-cart.js.sfc?r=1.0.8" defer></script>
{% endif %}

{% if route == 'checkout/simplecheckout' %}
	<script src="catalog/view/theme/{{ theme_path }}/js/surface/rise-checkout.js.sfc?r=1.0.8" defer></script>
{% endif %}

{% if route == 'checkout/success' %}
	<script src="catalog/view/theme/{{ theme_path }}/js/surface/rise-success.js.sfc?r=1.0.8" defer></script>
{% endif %}
```

---

## Компоненты по файлам

### 1. `rise-home.js` — ленивая загрузка баннеров
- Заменяет исходные изображения на плейсхолдер
- Через 600 мс подгружает оригиналы
- Добавляет анимацию `sharp-flash-ultra-fast`
- Цель: визуальная "вспышка" при старте

### 2. `rise-search.js` — иллюзия анализа поиска
- При фокусе на поиске:
  - Звук, вибрация, canvas-разряды
  - Электрификация поля ввода
- При клике:
  - Full-screen анимация «вычислений»
  - Matrix-эффект с падающими символами
  - Слова: "ПЛАТЬЕ", "ВЕЧЕРНЕЕ" — как "анализ"
- Автоотключение при низком FPS

### 3. `rise-product.js` — автоматизация просмотра
- При прокрутке к галерее:
  - Запуск `Auto Slider` с нотификацией
  - Остановка при касании
- При двойном тапе на изображении:
  - Активация `Image Slider`
  - Автолистинг изображений > 500px
- При удержании — иллюзия "захвата" изображения

### 4. `rise-cart.js` — скрытие купонов
- После добавления товара:
  - Сворачивание аккордеона `#collapse-coupon`
  - Блокировка повторного раскрытия
- Через MutationObserver

### 5. `rise-checkout.js` — сканирование формы
- При клике на кнопку:
  - Full-screen анимация сканирования
  - Движущаяся линия + свечение
  - Уведомление: "Проверяю введённую информацию..."
- Автокрытие через 1.5 сек

### 6. `rise-footer.js` — персонализация и поддержка
- Уведомление с приветствием по времени суток
  - "Доброе утро!", "Добрый вечер!"
  - Показывается один раз в день
- `Auto Swiper`:
  - Автоматическая прокрутка галерей
  - Остановка при касании
- `Split Pay`:
  - Появление калькулятора рассрочки
  - Анимация "дрожания" цены
  - Иконка "пакмана"

### 7. `rise-success.js` — подтверждение заказа
- На странице `/success-order`:
  - Уведомление: "Передаю заказ в обработку..."
  - Только на мобильных
  - Через 1.5 сек после загрузки

### 8. `footer.twig` — интеграция и защита
- Подключение всех `rise-*.js` через `footer.twig`
- `PerfGuard`:
  - Мониторинг FPS
  - Отключение всех эффектов при:
    - FPS < 40
    - Батарея < 15% и не заряжается
    - Страница не в фокусе
  - Восстановление при возврате
- Аварийное торможение:
  - Очистка `setInterval`, `setTimeout`, `MutationObserver`, `WebSocket`, `requestAnimationFrame`

---

## Описание реализации проекта

![AI-Operator](ai-operator-img.png)
