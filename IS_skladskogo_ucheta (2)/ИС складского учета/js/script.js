// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    console.log('🏢 Склад система загружена!');
    
    // Добавляем анимации для карточек
    addCardAnimations();
    
    // Инициализируем модальные окна
    initModals();
    
    // Добавляем интерактивность к таблицам
    initTableInteractivity();
    
    // Инициализируем формы
    initForms();
    
    // Добавляем обработчики для вкладок
    initTabs();
    
    // Защита от исчезновения форм
    preventFormDisappearance();
});

// Анимации для карточек
function addCardAnimations() {
    const cards = document.querySelectorAll('.stat-card, .product, .order, .supplier-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
            this.style.transition = 'all 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
        });
    });
}

// Модальные окна
function initModals() {
    // Bootstrap 5 модалы
    const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
    const modals = document.querySelectorAll('.modal');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-bs-target');
            const modal = document.querySelector(modalId);
            if (modal) {
                showModal(modal);
            }
        });
    });
    
    modals.forEach(modal => {
        const closeButtons = modal.querySelectorAll('.btn-close, [data-bs-dismiss="modal"]');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                hideModal(modal);
            });
        });
        
        // Переменные для отслеживания перетаскивания мыши
        let isDragging = false;
        let mouseDownTime = 0;
        
        // Отслеживаем начало перетаскивания
        modal.addEventListener('mousedown', function(e) {
            if (e.target === modal) {
                isDragging = false;
                mouseDownTime = Date.now();
            }
        });
        
        // Отслеживаем движение мыши
        modal.addEventListener('mousemove', function(e) {
            if (e.target === modal && e.buttons === 1) { // левая кнопка зажата
                isDragging = true;
            }
        });
        
        // Отслеживаем отпускание мыши
        modal.addEventListener('mouseup', function(e) {
            if (e.target === modal) {
                const mouseUpTime = Date.now();
                const dragDuration = mouseUpTime - mouseDownTime;
                
                // Если мышь была зажата больше 100мс, считаем это перетаскиванием
                if (dragDuration > 100) {
                    isDragging = true;
                }
            }
        });
        
        // Полностью отключаем закрытие по клику на backdrop
        modal.addEventListener('click', function(e) {
            // Не закрываем модал при клике на backdrop
            // Закрываем только кнопками
            return false;
        });
        
        // Дополнительная защита от закрытия Bootstrap модала
        modal.addEventListener('hide.bs.modal', function(e) {
            if (isDragging) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
        
        // Еще более агрессивная защита - перехватываем все события мыши
        modal.addEventListener('mouseleave', function(e) {
            if (e.buttons === 1) { // левая кнопка все еще зажата
                isDragging = true;
            }
        });
        
        // Предотвращаем закрытие при любом движении мыши с зажатой кнопкой
        document.addEventListener('mouseup', function(e) {
            if (isDragging) {
                setTimeout(() => {
                    isDragging = false;
                }, 200); // увеличиваем время сброса
            }
        });
    });
}

// Показать модальное окно
function showModal(modal) {
    modal.style.display = 'block';
    modal.classList.add('show');
    document.body.classList.add('modal-open');
    
    setTimeout(() => {
        modal.style.opacity = '1';
    }, 10);
}

// Скрыть модальное окно
function hideModal(modal) {
    modal.style.opacity = '0';
    document.body.classList.remove('modal-open');
    
    setTimeout(() => {
        modal.style.display = 'none';
        modal.classList.remove('show');
    }, 300);
}

// Интерактивность таблиц
function initTableInteractivity() {
    const tables = document.querySelectorAll('.table');
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('click', function() {
                // Снимаем выделение с других строк
                rows.forEach(r => r.classList.remove('selected'));
                // Выделяем текущую строку
                this.classList.add('selected');
            });
        });
    });
}

// Улучшенная валидация форм (ОТКЛЮЧЕНО - используется новая система валидации)
function initForms() {
    // Отключаем старую валидацию, чтобы избежать дублирования
    return;
    
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            // Валидация в реальном времени
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    validateField(this);
                }
            });
        });
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            inputs.forEach(input => {
                if (!validateField(input)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('❌ Пожалуйста, исправьте ошибки в форме', 'error');
            } else {
                showNotification('✅ Форма отправляется...', 'success');
            }
        });
    });
}

// Валидация отдельного поля (ОТКЛЮЧЕНО - используется новая система валидации)
function validateField(field) {
    // Отключаем старую валидацию, чтобы избежать дублирования
    return true;
    
    // Проверка email
    if (field.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Введите корректный email адрес';
        }
    }
    
    // Проверка чисел
    if (field.type === 'number' && value) {
        if (isNaN(value) || parseFloat(value) < 0) {
            isValid = false;
            errorMessage = 'Введите корректное положительное число';
        }
    }
    
    // Проверка длины
    if (field.minLength && value.length < field.minLength) {
        isValid = false;
        errorMessage = `Минимальная длина: ${field.minLength} символов`;
    }
    
    if (!isValid) {
        field.classList.add('error');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = errorMessage;
        errorDiv.style.color = '#dc3545';
        errorDiv.style.fontSize = '12px';
        errorDiv.style.marginTop = '5px';
        field.parentNode.appendChild(errorDiv);
    }
    
    return isValid;
}

// Вкладки
function initTabs() {
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Убираем активный класс со всех вкладок
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Добавляем активный класс к текущей вкладке
            this.classList.add('active');
            const activeContent = document.getElementById(tabId);
            if (activeContent) {
                activeContent.classList.add('active');
            }
        });
    });
}

// Уведомления
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.style.animation = 'slideInRight 0.3s ease';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// AJAX функции для динамического обновления
function loadData(url, callback) {
    fetch(url)
        .then(response => response.json())
        .then(data => callback(data))
        .catch(error => {
            console.error('Ошибка загрузки данных:', error);
            // Убираем уведомление об ошибке
        });
}

// Функция для обновления статистики
function updateStats() {
    loadData('api/stats.php', function(stats) {
        const statCards = document.querySelectorAll('.stat-number');
        statCards.forEach((card, index) => {
            if (stats[index]) {
                animateNumber(card, parseInt(card.textContent), stats[index]);
            }
        });
    });
}

// Анимация чисел
function animateNumber(element, start, end) {
    const duration = 1000;
    const stepTime = 50;
    const steps = duration / stepTime;
    const increment = (end - start) / steps;
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        element.textContent = Math.round(current);
        
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            element.textContent = end;
            clearInterval(timer);
        }
    }, stepTime);
}

// Поиск и фильтрация
function initSearch() {
    const searchInputs = document.querySelectorAll('[data-search]');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const targetTable = document.querySelector(this.getAttribute('data-search'));
            const rows = targetTable.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
}

// Сортировка таблиц
function initTableSorting() {
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const cellIndex = Array.from(this.parentNode.children).indexOf(this);
            const isAscending = !this.classList.contains('sorted-desc');
            
            // Удаляем классы сортировки с других заголовков
            sortableHeaders.forEach(h => {
                h.classList.remove('sorted-asc', 'sorted-desc');
            });
            
            // Добавляем класс к текущему заголовку
            this.classList.add(isAscending ? 'sorted-asc' : 'sorted-desc');
            
            // Сортируем строки
            rows.sort((a, b) => {
                const aText = a.cells[cellIndex].textContent.trim();
                const bText = b.cells[cellIndex].textContent.trim();
                
                if (isAscending) {
                    return aText.localeCompare(bText, 'ru', { numeric: true });
                } else {
                    return bText.localeCompare(aText, 'ru', { numeric: true });
                }
            });
            
            // Обновляем таблицу
            rows.forEach(row => tbody.appendChild(row));
        });
    });
}

// Добавляем обработчики для динамических элементов
document.addEventListener('click', function(e) {
    // Кнопки удаления с подтверждением
    if (e.target.classList.contains('btn-danger')) {
        if (!confirm('🗑️ Вы уверены, что хотите удалить этот элемент?')) {
            e.preventDefault();
        }
    }
    
    // Кнопки экспорта
    if (e.target.classList.contains('btn-export')) {
        showNotification('📊 Экспорт данных начинается...', 'info');
    }
});

// Обработка клавиатурных сокращений
document.addEventListener('keydown', function(e) {
    // Ctrl+S для сохранения формы
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const activeForm = document.querySelector('form:focus-within');
        if (activeForm) {
            activeForm.submit();
        }
    }
    
    // Escape для закрытия модальных окон
    if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal[style*="block"]');
        if (openModal) {
            openModal.style.display = 'none';
        }
    }
});

// Функции для старых обработчиков (обратная совместимость)
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Инициализация дополнительных функций при загрузке
window.addEventListener('load', function() {
    initSearch();
    initTableSorting();
    
    // Автообновление статистики каждые 30 секунд
    if (document.querySelector('.stats-grid')) {
        setInterval(updateStats, 30000);
    }
    
    console.log('✅ Все системы склада инициализированы!');
});

// CSS анимации (добавляем в JavaScript)
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .selected {
        background-color: #e3f2fd !important;
    }
    
    .error {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }
    
    .sortable {
        cursor: pointer;
        position: relative;
    }
    
    .sortable:hover {
        background-color: #e9ecef;
    }
    
    .sorted-asc::after {
        content: ' ↑';
        color: #4CAF50;
    }
    
    .sorted-desc::after {
        content: ' ↓';
        color: #4CAF50;
    }
`;
document.head.appendChild(style);

// Защита от исчезновения форм
function preventFormDisappearance() {
    let isDragging = false;
    let dragStartTime = 0;
    
    // Очистка backdrop при закрытии модального окна
    document.addEventListener('hidden.bs.modal', function(e) {
        // Удаляем все backdrop элементы
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => {
            backdrop.remove();
        });
        
        // Убираем класс modal-open с body
        document.body.classList.remove('modal-open');
        
        // Восстанавливаем padding-right
        document.body.style.paddingRight = '';
    });
    
    // Отслеживаем начало перетаскивания
    document.addEventListener('dragstart', function(e) {
        if (e.target.closest('.modal input, .modal textarea')) {
            isDragging = true;
            dragStartTime = Date.now();
        }
    });
    
    // Отслеживаем конец перетаскивания
    document.addEventListener('dragend', function(e) {
        if (isDragging) {
            isDragging = false;
            // Небольшая задержка для предотвращения случайного закрытия
            setTimeout(() => {
                isDragging = false;
            }, 100);
        }
    });
    
    // Защита от закрытия модального окна при перетаскивании
    document.addEventListener('click', function(e) {
        if (isDragging && e.target.classList.contains('modal-backdrop')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });
    
    // Защита от закрытия при mouseup после перетаскивания
    document.addEventListener('mouseup', function(e) {
        if (isDragging && e.target.classList.contains('modal-backdrop')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });
}