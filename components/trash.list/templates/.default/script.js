document.querySelectorAll('[data-confirm]').forEach(button => {
    button.addEventListener('click', event => {
        if (!confirm(button.dataset.confirm || '')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('.table-view-row[data-url]').forEach(row => {
    row.addEventListener('click', function (event) {
        if (event.target.closest('button, form, a, input')) {
            return;
        }

        window.location.href = row.dataset.url;
    });
});
