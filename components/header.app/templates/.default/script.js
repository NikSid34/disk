document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('smartToggle');
    const input = document.getElementById('smartInput');
    const search = document.getElementById('searchInput');
    const form = document.getElementById('searchForm');

    if (!toggle || !input || !search || !form) {
        return;
    }

    search.addEventListener('search', function () {
        if (this.value === '') {
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            window.location.href = url.toString();
        }
    });

    toggle.addEventListener('change', () => {
        input.value = toggle.checked ? '1' : '0';

        if (search.value.trim() !== '') {
            form.submit();
        }
    });
});
