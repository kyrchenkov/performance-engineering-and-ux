<?php
/**
 * OPS-UX: Операционная система поддержки для e-commerce
 * 
 * Реактивная система автоматизации для операторов на базе Chrome Extensions
 * 
 * Уровни:
 * 1. Manifest V3
 * 2. Контент-скрипты
 * 3. Реактивность и состояние
 * 4. Форматирование и вставка
 * 5. Наблюдение за DOM
 * 
 * © 2025 — Максим Кырченков | t.me/kyrchenkov
 */

// ========================================
// УРОВЕНЬ 1: Manifest V3
// ========================================
// Источник: manifest.json

/*
 * {
 *   "manifest_version": 3,
 *   "name": "Шаблоны для доставки и комментариев",
 *   "version": "2.1",
 *   "description": "Отображает шаблоны на service-a.domain и комментарии на service-b.domain",
 *   "permissions": ["activeTab", "scripting"],
 *   "host_permissions": [
 *     "https://service-a.domain/*",
 *     "https://service-b.domain/*"
 *   ],
 *   "content_scripts": [
 *     {
 *       "matches": ["https://service-a.domain/*"],
 *       "js": ["content_service_a.js"]
 *     },
 *     {
 *       "matches": ["https://service-b.domain/*"],
 *       "js": ["content_service_b.js"]
 *     }
 *   ]
 * }
 */

// ========================================
// УРОВЕНЬ 2: Контент-скрипты
// ========================================
// Источник: content_service_a.js, content_service_b.js

/*
 * content_service_a.js
 * 
 * - Позиция: fixed, bottom: 80px, left: 10px
 * - Контейнер: фон, тень, радиус, паддинг
 * - Шаблоны: 2 текста
 * - Подписи: 2 комментария
 * - Клик → navigator.clipboard.writeText()
 * - Ошибка → fallbackCopyText (textarea + execCommand)
 * - Наведение → выделение текста
 * - Покидание → снятие выделения
 */

/*
 * content_service_b.js
 * 
 * mainContainer:
 *   - bottom: 0, right: 0
 *   - Заголовок: "Ответы в чате"
 *   - 16 шаблонов (texts + comments)
 *   - Вставка в [data-qa-id="main-input-element"]
 * 
 * receiverContainer:
 *   - top: 105px, right: 0
 *   - Поля: streetInput, apartmentInput, phoneInput
 *   - Чекбоксы: noBuilding, leaveAtDoor, callUponArrival
 *   - Кнопка "Очистить" — сброс полей и updateTextElements()
 */

// ========================================
// УРОВЕНЬ 3: Реактивность и состояние
// ========================================
// Источник: content_service_b.js

/*
 * Состояние:
 * 
 * let street = '';
 * let apartment = '';
 * let phone = '';
 * let noBuilding = false;
 * let leaveAtDoor = false;
 * let callUponArrival = false;
 */

/*
 * Реактивность:
 * 
 * const updateTextElements = () => {
 *   if (textElements.length >= 3) {
 *     const streetWithBuilding = noBuilding ? `${street} [без корпуса]` : street;
 *     const additionalInstructions = [];
 *     if (leaveAtDoor) additionalInstructions.push("Оставить у двери");
 *     if (callUponArrival) additionalInstructions.push("Позвонить по приезду");
 *     
 *     const messages = [
 *       "Ваш заказ готовят к доставке по адресу",
 *       "Ваш заказ собран и ожидает назначения курьера, с доставкой по адресу",
 *       "В ближайшее время удобно будет принять курьера по адресу"
 *     ];
 *     
 *     for (let i = 0; i < 3; i++) {
 *       let fullMessage = messages[i];
 *       const details = [];
 *       if (streetWithBuilding) details.push(`Улица: ${streetWithBuilding}`);
 *       if (apartment) details.push(`Квартира: ${apartment}`);
 *       if (phone) details.push(`Телефон получателя: ${formatPhone(phone)}`);
 *       if (additionalInstructions.length > 0) details.push(`Дополнительно: ${additionalInstructions.join(', ')}`);
 *       if (details.length > 0) fullMessage += ':\n' + details.join('\n');
 *       textElements[i].textContent = fullMessage;
 *     }
 *   }
 * };
 * 
 * // Вызов при любом изменении
 */

/*
 * Сохранение:
 * 
 * const saveData = () => {
 *   localStorage.setItem('opsux_deliveryData', JSON.stringify({
 *     street, apartment, phone, noBuilding, leaveAtDoor, callUponArrival
 *   }));
 * };
 * 
 * const loadData = () => {
 *   const saved = localStorage.getItem('opsux_deliveryData');
 *   if (saved) {
 *     const data = JSON.parse(saved);
 *     street = data.street || '';
 *     apartment = data.apartment || '';
 *     phone = data.phone || '';
 *     noBuilding = data.noBuilding || false;
 *     leaveAtDoor = data.leaveAtDoor || false;
 *     callUponArrival = data.callUponArrival || false;
 *     updateTextElements();
 *   }
 * };
 * 
 * loadData();
 */

// ========================================
// УРОВЕНЬ 4: Форматирование и вставка
// ========================================
// Источник: content_service_b.js

/*
 * formatPhone:
 * 
 * const formatPhone = (rawPhone) => {
 *   let cleaned = rawPhone.replace(/\D/g, '');
 *   if (cleaned.startsWith('8')) cleaned = '7' + cleaned.slice(1);
 *   let isInternational = false;
 *   if (cleaned.startsWith('+7')) {
 *     isInternational = true;
 *     cleaned = cleaned.slice(1);
 *   } else if (cleaned.startsWith('7')) {
 *     isInternational = true;
 *   }
 *   if (isInternational && cleaned.length === 11) {
 *     return '+' + cleaned[0] + ' ' + cleaned.slice(1,4) + ' ' + 
 *            cleaned.slice(4,7) + ' ' + cleaned.slice(7,9) + ' ' + cleaned.slice(9);
 *   } else if (!isInternational && cleaned.length === 10) {
 *     const reversed = cleaned.split('').reverse().join('');
 *     const groups = [reversed.slice(0,2), reversed.slice(2,4), reversed.slice(4,7), reversed.slice(7,10)];
 *     const formattedGroups = groups.map(g => g.split('').reverse().join(''));
 *     return formattedGroups.reverse().join(' ');
 *   }
 *   return rawPhone;
 * };
 */

/*
 * Вставка в чат:
 * 
 * item.addEventListener('click', () => {
 *   const textarea = document.querySelector('[data-qa-id="main-input-element"]');
 *   if (textarea) {
 *     let message = text;
 *     if (index < 3) {
 *       // Формируется как в updateTextElements
 *       message = fullMessage;
 *     } else if (index === 7) {
 *       if (phone) message += '\n' + formatPhone(phone);
 *     }
 *     textarea.value += message + '\n';
 *     textarea.dispatchEvent(new Event('input', { bubbles: true }));
 *   }
 * });
 */

// ========================================
// УРОВЕНЬ 5: Наблюдение за DOM
// ========================================
// Источник: content_service_b.js

/*
 * const hideElement = (el) => {
 *   if (!el) return;
 *   el.style.setProperty('display', 'none', 'important');
 *   el.style.setProperty('visibility', 'hidden', 'important');
 *   el.style.setProperty('opacity', '0', 'important');
 *   el.style.setProperty('position', 'absolute', 'important');
 *   el.style.setProperty('width', '1px', 'important');
 *   el.style.setProperty('height', '1px', 'important');
 *   el.style.setProperty('overflow', 'hidden', 'important');
 *   if (el.parentNode) el.parentNode.removeChild(el);
 * };
 * 
 * document.querySelectorAll('[data-qa-id="warning-license"]').forEach(hideElement);
 * 
 * const observer = new MutationObserver((mutations) => {
 *   mutations.forEach(mutation => {
 *     Array.from(mutation.addedNodes).forEach(node => {
 *       if (node.nodeType === Node.ELEMENT_NODE &&
 *           node.getAttribute('data-qa-id') === 'warning-license') {
 *         hideElement(node);
 *       }
 *     });
 *   });
 * });
 * 
 * observer.observe(document.body, { childList: true, subtree: true });
 */

// ========================================
// ТОЧКА ЗАПУСКА
// ========================================

// manifest.json → content_scripts → DOM → UI → localStorage → updateTextElements → formatPhone → вставка