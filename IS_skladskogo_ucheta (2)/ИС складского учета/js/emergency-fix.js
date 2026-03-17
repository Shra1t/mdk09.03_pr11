// Экстренное исправление проблемы с backdrop
// Этот скрипт можно вызвать из консоли браузера для очистки backdrop

function clearBackdrop() {
    // Удаляем все backdrop элементы
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => {
        backdrop.remove();
    });
    
    // Убираем класс modal-open с body
    document.body.classList.remove('modal-open');
    
    // Восстанавливаем padding-right
    document.body.style.paddingRight = '';
    
    // Убираем overflow: hidden
    document.body.style.overflow = '';
    
    console.log('Backdrop очищен!');
}

// Автоматическая очистка при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Очищаем backdrop при загрузке
    clearBackdrop();
    
    // Добавляем обработчик для очистки при клике на backdrop
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-backdrop')) {
            clearBackdrop();
        }
    });
    
    // Добавляем обработчик для очистки при нажатии Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            clearBackdrop();
        }
    });
});

// Делаем функцию доступной глобально
window.clearBackdrop = clearBackdrop;

