// Funcionalidad de acordeones
document.addEventListener('DOMContentLoaded', function() {
    
    // Acordeones
    const accordionHeaders = document.querySelectorAll('.accordion-header');
    
    accordionHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const accordionItem = this.parentElement;
            const isActive = accordionItem.classList.contains('active');
            
            // Cerrar todos los acordeones
            document.querySelectorAll('.accordion-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Abrir el acordeón clickeado si no estaba activo
            if (!isActive) {
                accordionItem.classList.add('active');
            }
        });
    });
    
    // Flip cards para dispositivos táctiles
    const flipCards = document.querySelectorAll('.flip-card');
    
    flipCards.forEach(card => {
        card.addEventListener('click', function() {
            this.classList.toggle('active');
        });
    });
    
    // Animación de entrada escalonada para elementos
    const animatedElements = document.querySelectorAll('.nivel-card, .benefits-list li, .accordion-item');
    
    animatedElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });
    
});
