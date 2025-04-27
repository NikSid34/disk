let shareContextMenu = document.getElementById('contextMenu');

document.addEventListener('contextmenu', function (event) {
    const item = event.target.closest('.list-group-item');
    if (!item || !shareContextMenu) {
        shareContextMenu?.classList.add('d-none');
        return;
    }

    event.preventDefault();

    const hash = item.dataset.hash;
    shareContextMenu.querySelectorAll('form').forEach(form => {
        let input = form.querySelector('input[name="hash"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'hash';
            form.prepend(input);
        }
        input.value = hash;
    });

    shareContextMenu.style.left = event.pageX + 'px';
    shareContextMenu.style.top = event.pageY + 'px';
    shareContextMenu.classList.remove('d-none');
});

document.addEventListener('click', function (event) {
    if (!event.target.closest('#contextMenu')) {
        shareContextMenu?.classList.add('d-none');
    }
});
