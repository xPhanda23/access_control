// Modal de confirmação de exclusão, compartilhado por todas as telas com
// botão "Excluir" (cartões, dispositivos, usuários). Cria o modal uma vez
// e reaproveita para qualquer botão com data-delete-url + data-name.

function confirmarExclusao(url, mensagem) {
    let modal = document.getElementById('confirmModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'confirmModal';
        modal.className = 'modal';
        modal.innerHTML =
            '<div class="modal-content">' +
                '<h3>Confirmar exclusão</h3>' +
                '<p id="confirmModalMsg"></p>' +
                '<div class="modal-buttons">' +
                    '<button type="button" class="modal-cancel">Cancelar</button>' +
                    '<button type="button" class="modal-confirm">Excluir</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(modal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.classList.remove('active');
        });
        modal.querySelector('.modal-cancel').addEventListener('click', function () {
            modal.classList.remove('active');
        });
    }
    modal.querySelector('#confirmModalMsg').textContent = mensagem;
    modal.querySelector('.modal-confirm').onclick = function () {
        window.location.href = url;
    };
    modal.classList.add('active');
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-delete-url]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const nome = btn.dataset.name || 'este item';
            confirmarExclusao(btn.dataset.deleteUrl, 'Excluir "' + nome + '"? Esta ação não pode ser desfeita.');
        });
    });
});
