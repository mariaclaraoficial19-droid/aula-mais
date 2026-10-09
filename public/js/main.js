/**
 * JavaScript Principal — Sistema Aula+ (IFRO)
 * Interações no Front-end: confirmações seguras e feedback
 */

document.addEventListener('DOMContentLoaded', () => {
    // Intercepta formulários e botões de exclusão para exigir confirmação explícita
    const deleteTriggers = document.querySelectorAll('[data-confirm]');
    deleteTriggers.forEach((trigger) => {
        trigger.addEventListener('click', (e) => {
            const mensagem = trigger.getAttribute('data-confirm') || 'Tem certeza de que deseja realizar esta exclusão? Esta ação não pode ser desfeita.';
            if (!confirm(mensagem)) {
                e.preventDefault();
            }
        });
    });

    // Auto-fechamento ou efeito visual para alertas
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach((alert) => {
        alert.addEventListener('click', () => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });
    });
});
