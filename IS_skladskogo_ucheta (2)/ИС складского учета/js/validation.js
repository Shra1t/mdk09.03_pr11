// Система валидации форм
class FormValidator {
    constructor() {
        this.rules = {
            required: {
                message: 'Это поле обязательно для заполнения',
                validate: (value) => value.trim() !== ''
            },
            email: {
                message: 'Введите корректный email адрес',
                validate: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
            },
            password: {
                message: 'Пароль должен содержать минимум 6 символов',
                validate: (value) => value.length >= 6
            },
            phone: {
                message: 'Введите корректный номер телефона',
                validate: (value) => /^[\+]?[0-9\s\-\(\)]{10,}$/.test(value)
            },
            number: {
                message: 'Введите корректное число',
                validate: (value) => !isNaN(value) && value !== ''
            },
            positive: {
                message: 'Значение должно быть больше 0',
                validate: (value) => parseFloat(value) > 0
            },
            date: {
                message: 'Введите корректную дату',
                validate: (value) => {
                    if (!value) return true; // Пустые даты разрешены
                    const date = new Date(value);
                    return !isNaN(date.getTime()) && date.getFullYear() >= 1900 && date.getFullYear() <= 2100;
                }
            },
            reasonableNumber: {
                message: 'Число слишком большое (максимум 999,999,999)',
                validate: (value) => {
                    if (!value) return true;
                    const num = parseFloat(value);
                    return !isNaN(num) && num <= 999999999 && num >= 0;
                }
            },
            minLength: (min) => ({
                message: `Минимум ${min} символов`,
                validate: (value) => value.length >= min
            }),
            maxLength: (max) => ({
                message: `Максимум ${max} символов`,
                validate: (value) => value.length <= max
            }),
            // Правила на основе структуры БД
            // Categories
            categoryName: {
                message: 'Название категории не должно превышать 50 символов',
                validate: (value) => value.length <= 50
            },
            // Deliveries
            deliveryCode: {
                message: 'Код поставки не должен превышать 20 символов',
                validate: (value) => value.length <= 20
            },
            // Delivery Items
            productCode: {
                message: 'Код товара не должен превышать 20 символов',
                validate: (value) => value.length <= 20
            },
            productName: {
                message: 'Название товара не должно превышать 100 символов',
                validate: (value) => value.length <= 100
            },
            unit: {
                message: 'Единица измерения не должна превышать 20 символов',
                validate: (value) => value.length <= 20
            },
            address: {
                message: 'Адрес не должен превышать 255 символов',
                validate: (value) => value.length <= 255
            },
            notes: {
                message: 'Примечания не должны превышать 1000 символов',
                validate: (value) => value.length <= 1000
            },
            // Suppliers
            supplierCode: {
                message: 'Код поставщика не должен превышать 20 символов',
                validate: (value) => value.length <= 20
            },
            supplierName: {
                message: 'Название поставщика не должно превышать 100 символов',
                validate: (value) => value.length <= 100
            },
            companyName: {
                message: 'Название компании не должно превышать 100 символов',
                validate: (value) => value.length <= 100
            },
            supplierPhone: {
                message: 'Телефон не должен превышать 20 символов',
                validate: (value) => value.length <= 20
            },
            supplierEmail: {
                message: 'Email не должен превышать 100 символов',
                validate: (value) => value.length <= 100
            },
            paymentTerms: {
                message: 'Условия оплаты не должны превышать 50 символов',
                validate: (value) => value.length <= 50
            },
            deliveryTerms: {
                message: 'Условия поставки не должны превышать 50 символов',
                validate: (value) => value.length <= 50
            },
            contactPerson: {
                message: 'Контактное лицо не должно превышать 100 символов',
                validate: (value) => value.length <= 100
            },
            // Users
            username: {
                message: 'Имя пользователя не должно превышать 50 символов',
                validate: (value) => value.length <= 50
            },
            fullName: {
                message: 'Полное имя не должно превышать 100 символов',
                validate: (value) => value.length <= 100
            },
            userEmail: {
                message: 'Email не должен превышать 100 символов',
                validate: (value) => value.length <= 100
            }
        };
        
        this.init();
    }
    
    init() {
        // Добавляем обработчики для всех форм
        document.addEventListener('DOMContentLoaded', () => {
            this.setupFormValidation();
            this.preventFormDragIssue();
        });
    }
    
    setupFormValidation() {
        // Находим все формы с классом validated-form
        const forms = document.querySelectorAll('.validated-form');
        
        forms.forEach(form => {
            this.setupForm(form);
        });
        
        // Также добавляем валидацию к существующим формам
        this.addValidationToExistingForms();
    }
    
    setupForm(form) {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            this.setupInput(input);
        });
        
        // Валидация при отправке формы
        form.addEventListener('submit', (e) => {
            // Валидируем все поля перед отправкой
            const inputs = form.querySelectorAll('input, textarea, select');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!this.validateInput(input)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                
                // Показываем уведомление пользователю
                this.showValidationMessage('Пожалуйста, исправьте ошибки в форме перед отправкой', 'error');
                return false;
            }
        });
    }
    
    setupInput(input) {
        // Проверяем, что валидация еще не применена
        if (input.hasAttribute('data-validated')) {
            return;
        }
        
        // Не валидируем скрытые поля и поля файлов
        if (input.type === 'hidden' || input.type === 'file') {
            return;
        }
        
        // Отмечаем поле как "не тронутое" пользователем
        input.setAttribute('data-user-touched', 'false');
        
        // Добавляем валидацию при потере фокуса (только если пользователь взаимодействовал с полем)
        input.addEventListener('blur', () => {
            if (input.getAttribute('data-user-touched') === 'true') {
                this.validateInput(input);
            }
        });
        
        // Добавляем валидацию при вводе (только если пользователь начал печатать)
        input.addEventListener('input', () => {
            input.setAttribute('data-user-touched', 'true');
            // Валидируем только для email и password полей при вводе
            if (input.type === 'email' || input.type === 'password' || input.name.includes('password')) {
                this.validateInput(input);
            }
        });

        // Универсально обновляем при change (полезно для селектов и программных изменений)
        input.addEventListener('change', () => {
            input.setAttribute('data-user-touched', 'true');
            this.validateInput(input);
            if (typeof input._updateCharacterCounter === 'function') {
                input._updateCharacterCounter();
            }
        });
        
        // Добавляем обработчик клика для всех полей
        input.addEventListener('click', () => {
            input.setAttribute('data-user-touched', 'true');
        });
        
        // Добавляем обработчик фокуса для всех полей
        input.addEventListener('focus', () => {
            input.setAttribute('data-user-touched', 'true');
        });
        
        // Добавляем счетчик символов для полей с ограничениями
        this.addCharacterCounter(input);
        
        // Отмечаем поле как обработанное
        input.setAttribute('data-validated', 'true');
    }
    
    addValidationToExistingForms() {
        // Добавляем валидацию к формам авторизации
        const loginForm = document.querySelector('form[action*="login"], form input[name="username"]')?.closest('form');
        if (loginForm) {
            this.setupForm(loginForm);
        }
        
        // Добавляем валидацию к формам создания товаров
        const productForms = document.querySelectorAll('form input[name*="product"]')?.forEach(input => {
            const form = input.closest('form');
            if (form && !form.classList.contains('validated-form')) {
                this.setupForm(form);
            }
        });
        
        // Добавляем валидацию к формам поставок
        const deliveryForms = document.querySelectorAll('form input[name*="delivery"]')?.forEach(input => {
            const form = input.closest('form');
            if (form && !form.classList.contains('validated-form')) {
                this.setupForm(form);
            }
        });
    }
    
    validateForm(form) {
        const inputs = form.querySelectorAll('input, textarea, select');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateInput(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    validateInput(input) {
        const value = input.value;
        const rules = this.getInputRules(input);
        let isValid = true;
        let errorMessage = '';
        
        // Не показываем ошибки для полей, которые пользователь еще не трогал
        const userTouched = input.getAttribute('data-user-touched') === 'true';
        if (!userTouched) {
            // Если поле не тронуто, просто убираем все индикаторы и не валидируем
            this.removeError(input);
            return true;
        }
        
        // Отладочная информация
        if (input.name === 'notes') {
            console.log('Validating notes field:', {
                value: value,
                valueLength: value.length,
                rules: rules.map(r => r.message),
                fieldName: input.name
            });
        }
        
        for (const rule of rules) {
            if (!rule.validate(value)) {
                isValid = false;
                errorMessage = rule.message;
                
                // Отладочная информация для поля notes
                if (input.name === 'notes') {
                    console.log('Notes validation failed:', {
                        rule: rule.message,
                        value: value,
                        valueLength: value.length
                    });
                }
                
                break;
            }
        }
        
        this.showValidationResult(input, isValid, errorMessage);
        return isValid;
    }
    
    getInputRules(input) {
        const rules = [];
        const ruleNames = new Set(); // Для отслеживания уже добавленных правил
        
        // Проверяем атрибуты
        if (input.hasAttribute('required')) {
            rules.push(this.rules.required);
            ruleNames.add('required');
        }
        
        if (input.type === 'email' && !ruleNames.has('email')) {
            rules.push(this.rules.email);
            ruleNames.add('email');
        }
        
        if ((input.type === 'password' || input.name.includes('password')) && !ruleNames.has('password')) {
            rules.push(this.rules.password);
            ruleNames.add('password');
        }
        
        if ((input.type === 'tel' || input.name.includes('phone')) && !ruleNames.has('phone')) {
            rules.push(this.rules.phone);
            ruleNames.add('phone');
        }
        
        if (input.type === 'number') {
            if (!ruleNames.has('number')) {
                rules.push(this.rules.number);
                ruleNames.add('number');
            }
            if (!ruleNames.has('reasonableNumber')) {
                rules.push(this.rules.reasonableNumber);
                ruleNames.add('reasonableNumber');
            }
            if (input.hasAttribute('min') && parseFloat(input.getAttribute('min')) > 0 && !ruleNames.has('positive')) {
                rules.push(this.rules.positive);
                ruleNames.add('positive');
            }
        }
        
        if (input.type === 'date' && !ruleNames.has('date')) {
            rules.push(this.rules.date);
            ruleNames.add('date');
        }
        
        // Правила на основе имени поля
        const fieldName = input.name;
        
        // Простые правила для основных полей
        if (fieldName === 'delivery_code' && !ruleNames.has('deliveryCode')) {
            rules.push(this.rules.deliveryCode);
            ruleNames.add('deliveryCode');
        }
        
        if ((fieldName === 'product_code' || fieldName.includes('product_code')) && !ruleNames.has('productCode')) {
            rules.push(this.rules.productCode);
            ruleNames.add('productCode');
        }
        
        if ((fieldName === 'product_name' || fieldName.includes('product_name')) && !ruleNames.has('productName')) {
            rules.push(this.rules.productName);
            ruleNames.add('productName');
        }
        
        if ((fieldName === 'unit' || fieldName.includes('unit')) && !ruleNames.has('unit')) {
            rules.push(this.rules.unit);
            ruleNames.add('unit');
        }
        
        if ((fieldName === 'address' || fieldName.includes('address')) && !ruleNames.has('address')) {
            rules.push(this.rules.address);
            ruleNames.add('address');
        }
        
        if ((fieldName === 'notes' || fieldName.includes('notes')) && !ruleNames.has('notes')) {
            rules.push(this.rules.notes);
            ruleNames.add('notes');
            
            // Отладочная информация
            if (fieldName === 'notes') {
                console.log('Added notes rule:', this.rules.notes.message);
            }
        }
        
        // Не валидируем поля файлов и скрытые поля
        if (input.type === 'file' || input.type === 'hidden') {
            return rules;
        }
        
        
        // Suppliers
        if (fieldName === 'supplier_code' && !ruleNames.has('supplierCode')) {
            rules.push(this.rules.supplierCode);
            ruleNames.add('supplierCode');
        }
        
        if (fieldName === 'supplier_name' && !ruleNames.has('supplierName')) {
            rules.push(this.rules.supplierName);
            ruleNames.add('supplierName');
        }
        
        if (fieldName === 'company_name' && !ruleNames.has('companyName')) {
            rules.push(this.rules.companyName);
            ruleNames.add('companyName');
        }
        
        if (fieldName === 'phone' && !ruleNames.has('supplierPhone')) {
            rules.push(this.rules.supplierPhone);
            ruleNames.add('supplierPhone');
        }
        
        if (fieldName === 'payment_terms' && !ruleNames.has('paymentTerms')) {
            rules.push(this.rules.paymentTerms);
            ruleNames.add('paymentTerms');
        }
        
        if (fieldName === 'delivery_terms' && !ruleNames.has('deliveryTerms')) {
            rules.push(this.rules.deliveryTerms);
            ruleNames.add('deliveryTerms');
        }
        
        if (fieldName === 'contact_person' && !ruleNames.has('contactPerson')) {
            rules.push(this.rules.contactPerson);
            ruleNames.add('contactPerson');
        }
        
        // Users
        if (fieldName === 'username' && !ruleNames.has('username')) {
            rules.push(this.rules.username);
            ruleNames.add('username');
        }
        
        if (fieldName === 'full_name' && !ruleNames.has('fullName')) {
            rules.push(this.rules.fullName);
            ruleNames.add('fullName');
        }
        
        // Проверяем data-атрибуты для кастомных правил
        if (input.dataset.minLength && !ruleNames.has('minLength')) {
            rules.push(this.rules.minLength(parseInt(input.dataset.minLength)));
            ruleNames.add('minLength');
        }
        
        if (input.dataset.maxLength && !ruleNames.has('maxLength')) {
            rules.push(this.rules.maxLength(parseInt(input.dataset.maxLength)));
            ruleNames.add('maxLength');
        }
        
        return rules;
    }
    
    showValidationResult(input, isValid, errorMessage) {
        // Удаляем предыдущие сообщения об ошибках
        this.removeError(input);
        
        if (!isValid) {
            this.showError(input, errorMessage);
        } else {
            this.showSuccess(input);
        }
    }
    
    showError(input, message) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        
        // Убираем старое сообщение, если есть
        const existingError = input.parentNode.querySelector('.invalid-feedback');
        if (existingError) {
            existingError.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        
        input.parentNode.appendChild(errorDiv);
    }
    
    showSuccess(input) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    }
    
    removeError(input) {
        input.classList.remove('is-invalid', 'is-valid');
        
        const existingError = input.parentNode.querySelector('.invalid-feedback');
        if (existingError) {
            existingError.remove();
        }
    }
    
    addCharacterCounter(input) {
        // Проверяем, есть ли уже счетчик для этого поля
        if (input.parentNode.querySelector('.character-counter')) {
            return;
        }
        
        // Не добавляем счетчик для полей файлов
        if (input.type === 'file') {
            return;
        }
        
        // Определяем максимальную длину для поля на основе БД
        let maxLength = null;
        const fieldName = input.name;
        
        // Простые правила для основных полей
        if (fieldName === 'delivery_code') {
            maxLength = 20;
        }
        else if (fieldName === 'product_code' || fieldName.includes('product_code')) {
            maxLength = 20;
        }
        else if (fieldName === 'product_name' || fieldName.includes('product_name')) {
            maxLength = 100;
        }
        // Убираем счетчик символов для полей единиц измерения (это select, не input)
        // else if (fieldName === 'unit' || fieldName.includes('unit')) {
        //     maxLength = 20;
        // }
        else if (fieldName === 'supplier_code') {
            maxLength = 20;
        }
        else if (fieldName === 'supplier_name') {
            maxLength = 100;
        }
        else if (fieldName === 'company_name') {
            maxLength = 100;
        }
        else if (fieldName === 'phone') {
            maxLength = 20;
        }
        else if (fieldName === 'payment_terms') {
            maxLength = 50;
        }
        else if (fieldName === 'delivery_terms') {
            maxLength = 50;
        }
        else if (fieldName === 'contact_person') {
            maxLength = 100;
        }
        else if (fieldName === 'username') {
            maxLength = 50;
        }
        else if (fieldName === 'full_name') {
            maxLength = 100;
        }
        else if (fieldName === 'email') {
            maxLength = 100;
        }
        // Проверяем data-атрибуты
        else if (input.dataset.maxLength) {
            maxLength = parseInt(input.dataset.maxLength);
        }
        
        if (maxLength) {
            // Создаем счетчик символов
            const counter = document.createElement('small');
            counter.className = 'form-text text-muted character-counter';
            counter.style.fontSize = '0.875rem';
            counter.style.marginTop = '0.25rem';
            
            // Добавляем счетчик после поля
            input.parentNode.appendChild(counter);
            
            // Обновляем счетчик при вводе
            const updateCounter = () => {
                const currentLength = input.value.length;
                const remaining = maxLength - currentLength;
                
                // Убираем предыдущие классы
                input.classList.remove('over-limit', 'near-limit');
                
                if (remaining < 0) {
                    counter.textContent = `Превышено на ${Math.abs(remaining)} символов`;
                    counter.className = 'form-text text-danger character-counter';
                    input.classList.add('over-limit');
                } else if (remaining <= 5) {
                    counter.textContent = `Осталось ${remaining} символов`;
                    counter.className = 'form-text text-warning character-counter';
                    input.classList.add('near-limit');
                } else {
                    counter.textContent = `${currentLength}/${maxLength} символов`;
                    counter.className = 'form-text text-muted character-counter';
                }
            };
            
            // Сохраняем функцию для внешнего вызова
            input._updateCharacterCounter = updateCounter;

            // Обновляем счетчик при загрузке и вводе
            updateCounter();
            input.addEventListener('input', updateCounter);
            input.addEventListener('blur', updateCounter);
            input.addEventListener('change', updateCounter);
        }
    }
    
    showValidationMessage(message, type = 'error') {
        // Удаляем предыдущие уведомления
        const existingAlert = document.querySelector('.validation-alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        // Создаем уведомление
        const alert = document.createElement('div');
        alert.className = `alert alert-${type === 'error' ? 'danger' : 'success'} validation-alert`;
        alert.style.position = 'fixed';
        alert.style.top = '20px';
        alert.style.right = '20px';
        alert.style.zIndex = '9999';
        alert.style.minWidth = '300px';
        alert.innerHTML = `
            <i class="bi bi-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i>
            ${message}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Автоматически удаляем через 5 секунд
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
    
    preventFormDragIssue() {
        // Убираем все проблемные обработчики событий
        // Проблема была в том, что мы блокировали слишком много событий
        
        // Предотвращаем только перетаскивание изображений в формах
        document.addEventListener('dragstart', (e) => {
            if (e.target.tagName === 'IMG' && e.target.closest('form')) {
                e.preventDefault();
            }
        });
        
        // Предотвращаем выделение при перетаскивании, но разрешаем обычное выделение
        let isDragging = false;
        let startX, startY;
        
        document.addEventListener('mousedown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                isDragging = false;
                startX = e.clientX;
                startY = e.clientY;
            }
        });
        
        document.addEventListener('mousemove', (e) => {
            if (e.buttons === 1) { // Левая кнопка мыши нажата
                const deltaX = Math.abs(e.clientX - startX);
                const deltaY = Math.abs(e.clientY - startY);
                
                // Считаем перетаскиванием только если мышь сдвинулась больше чем на 5 пикселей
                if (deltaX > 5 || deltaY > 5) {
                    isDragging = true;
                }
            }
        });
        
        document.addEventListener('selectstart', (e) => {
            if (isDragging && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) {
                e.preventDefault();
            }
        });
        
        // Сбрасываем флаг перетаскивания при отпускании мыши
        document.addEventListener('mouseup', () => {
            isDragging = false;
        });
    }
}

// Инициализируем валидатор
const formValidator = new FormValidator();

// Дополнительные утилиты
window.FormValidation = {
    // Принудительно обновить валидацию и счетчик для указанного элемента или селектора
    forceUpdate: (target) => {
        const elements = typeof target === 'string' ? document.querySelectorAll(target) : (target instanceof Element ? [target] : target);
        if (!elements) return;
        elements.forEach(el => {
            if (typeof formValidator.validateInput === 'function') {
                formValidator.validateInput(el);
            }
            if (typeof el._updateCharacterCounter === 'function') {
                el._updateCharacterCounter();
            }
        });
    },
    validatePassword: (password) => {
        return password.length >= 6;
    },
    
    validateEmail: (email) => {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },
    
    validatePhone: (phone) => {
        return /^[\+]?[0-9\s\-\(\)]{10,}$/.test(phone);
    },
    
    showFieldError: (fieldId, message) => {
        const field = document.getElementById(fieldId);
        if (field) {
            formValidator.showError(field, message);
        }
    },
    
    clearFieldError: (fieldId) => {
        const field = document.getElementById(fieldId);
        if (field) {
            formValidator.removeError(field);
        }
    }
};

// Обработка модальных окон для валидации
document.addEventListener('shown.bs.modal', (e) => {
    const modal = e.target;
    const form = modal.querySelector('.validated-form');
    if (form && !form.hasAttribute('data-validated')) {
        formValidator.setupForm(form);
    }
    
    // Сбрасываем все индикаторы валидации при открытии модалки
    const inputs = modal.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        // Сбрасываем флаг "тронутости" пользователем
        input.setAttribute('data-user-touched', 'false');
        
        // Убираем все индикаторы ошибок и успеха
        formValidator.removeError(input);
        
        // Обновляем только счетчики символов, без валидации
        if (typeof input._updateCharacterCounter === 'function') {
            input._updateCharacterCounter();
        }
    });
});

// Обработка динамически добавляемых полей
document.addEventListener('DOMNodeInserted', (e) => {
    if (e.target.nodeType === 1) { // Element node
        const inputs = e.target.querySelectorAll ? e.target.querySelectorAll('input, textarea, select') : [];
        inputs.forEach(input => {
            if (!input.hasAttribute('data-validated') && input.closest('.validated-form')) {
                formValidator.setupInput(input);
            }
        });
    }
});
