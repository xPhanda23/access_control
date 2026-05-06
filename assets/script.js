// Interações básicas e confirmações

document.addEventListener('DOMContentLoaded', function() {
    // Confirmar exclusões
    const deleteLinks = document.querySelectorAll('.btn-danger');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Tem certeza que deseja excluir este item?')) {
                e.preventDefault();
            }
        });
    });
});