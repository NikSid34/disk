const sortForm = document.getElementById('sortForm');
const sortSelect = document.getElementById('sortSelect');
const directionInput = document.getElementById('sortDirection');
const toggleButton = document.getElementById('toggleSortDirection');
const viewModeInput = document.getElementById('viewModeInput');

sortSelect?.addEventListener('change', () => {
    sortForm?.submit();
});

toggleButton?.addEventListener('click', () => {
    if (!directionInput || !sortForm) {
        return;
    }

    directionInput.value = directionInput.value === 'asc' ? 'desc' : 'asc';
    sortForm.submit();
});

document.querySelectorAll('[data-view-mode]').forEach(button => {
    button.addEventListener('click', () => {
        if (!viewModeInput || !sortForm) {
            return;
        }

        viewModeInput.value = button.dataset.viewMode;
        sortForm.submit();
    });
});

function showCopyToast() {
    const toastElement = document.getElementById('copyToast');
    if (!toastElement) {
        return;
    }

    const toast = new bootstrap.Toast(toastElement);
    toast.show();
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.copy-link').forEach(element => {
        element.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const link = event.currentTarget.dataset.link;
            navigator.clipboard.writeText(new URL(link, window.location.origin).toString())
                    .then(showCopyToast)
                    .catch(() => {
                        alert('Не удалось скопировать ссылку');
                    });
        });
    });

    document.querySelectorAll('.table-view-row[data-url]').forEach(row => {
        row.addEventListener('click', function (event) {
            if (event.target.closest('.copy-link')) {
                return;
            }

            window.location.href = row.dataset.url;
        });
    });
});
