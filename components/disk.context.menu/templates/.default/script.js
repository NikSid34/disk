let contextMenu = document.getElementById('contextMenu');
let currentContextTarget = null;
let selectedFolderId = null;
const confirmMoveButton = document.getElementById('confirmMove');

document.querySelectorAll('#contextMenu .context-ajax-form').forEach(form => {
    form.addEventListener('submit', event => submitContextForm(event, form));
});

document.querySelectorAll('#contextMenu [data-context-action]').forEach(button => {
    button.addEventListener('click', () => {
        handleContextAction(button.dataset.contextAction || '');
    });
});

document.addEventListener('contextmenu', function (event) {
    const item = event.target.closest('.table-item');
    if (!item || !contextMenu) {
        contextMenu?.classList.add('d-none');
        return;
    }

    event.preventDefault();
    currentContextTarget = item;

    const hash = item.dataset.hash;
    contextMenu.querySelectorAll('form').forEach(form => {
        let input = form.querySelector('input[name="hash"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'hash';
            form.prepend(input);
        }
        input.value = hash;
    });

    contextMenu.style.left = event.pageX + 'px';
    contextMenu.style.top = event.pageY + 'px';
    contextMenu.classList.remove('d-none');
});

document.addEventListener('click', function (event) {
    if (!event.target.closest('#contextMenu')) {
        contextMenu?.classList.add('d-none');
        currentContextTarget = null;
    }
});

function handleContextAction(action) {
    if (!currentContextTarget) {
        return;
    }

    if (action === 'info') {
        showItemDetails(currentContextTarget);
    }

    if (action === 'move') {
        openMoveModal(currentContextTarget.dataset.hash);
    }

    contextMenu?.classList.add('d-none');
}

async function submitContextForm(event, form) {
    event.preventDefault();
    const formData = new FormData(form);

    try {
        const response = await fetch(form.action, {
            method: form.method,
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        });
        const data = await parseResponseData(response);

        if (!response.ok) {
            showContextError(data?.error || 'Ошибка при выполнении действия');
            return false;
        }

        if (isShareForm(form)) {
            const publicLink = data?.absoluteLink || data?.link;
            if (!publicLink) {
                showContextError('Сервер не вернул публичную ссылку');
                return false;
            }

            try {
                await copyToClipboard(toAbsoluteLink(publicLink));
            } catch (copyError) {
                showContextError('Не удалось скопировать ссылку: ' + copyError.message);
                return false;
            }

            showContextMessage('Публичная ссылка скопирована в буфер обмена');
            window.setTimeout(() => location.reload(), 900);
            return false;
        }

        location.reload();
    } catch (error) {
        console.error('Ошибка запроса:', error);
        showContextError('Сетевая ошибка: ' + error.message);
    } finally {
        contextMenu?.classList.add('d-none');
    }

    return false;
}

async function parseResponseData(response) {
    const contentType = response.headers.get('Content-Type') || '';
    if (!contentType.includes('application/json')) {
        return {};
    }

    try {
        return await response.json();
    } catch {
        return {};
    }
}

function isShareForm(form) {
    return new URL(form.action, window.location.origin).pathname === '/disk/share';
}

function toAbsoluteLink(link) {
    return new URL(link, window.location.origin).toString();
}

async function copyToClipboard(text) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    if (!document.execCommand('copy')) {
        textarea.remove();
        throw new Error('буфер обмена недоступен');
    }

    textarea.remove();
}

function showItemDetails(item) {
    const type = item.dataset.itemType === 'folder' ? 'Папка' : 'Файл';
    const isPublic = item.dataset.public === '1' ? 'Да' : 'Нет';
    const details = [
        ['Название', item.dataset.name || 'Без названия'],
        ['Тип', type],
        ['Размер', item.dataset.size || '—'],
        ['Дата создания', item.dataset.createdAt || '—'],
        ['Публичный доступ', isPublic],
        ['Просмотров', item.dataset.visits || '0'],
        ['Хеш', item.dataset.hash || '—']
    ];

    alert(details.map(([label, value]) => `${label}: ${value}`).join('\n'));
}

function showContextMessage(message) {
    const toastElement = document.getElementById('copyToast');
    const toastBody = toastElement?.querySelector('.toast-body');
    if (toastElement && toastBody && window.bootstrap?.Toast) {
        toastBody.textContent = message;
        new bootstrap.Toast(toastElement).show();
        return;
    }

    alert(message);
}

function showContextError(message) {
    alert(message);
}

async function openMoveModal(fileHash) {
    selectedFolderId = null;
    if (confirmMoveButton) {
        confirmMoveButton.disabled = true;
    }

    const list = document.getElementById('moveFolderList');
    if (!list) {
        return;
    }

    list.innerHTML = '<div class="text-muted px-3 py-2">Загрузка...</div>';

    const response = await fetch('/disk/folders', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    });

    let folders = await response.json();
    if (!Array.isArray(folders)) {
        folders = Object.values(folders);
    }

    list.innerHTML = '';

    folders.forEach(folder => {
        const item = document.createElement('a');
        item.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between text-decoration-none';
        item.style.cursor = 'pointer';
        item.dataset.id = folder.id;
        item.innerHTML = `
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-folder-fill fs-4 text-warning"></i>
                <div class="fw-bold">${folder.name}</div>
            </div>
        `;

        item.addEventListener('click', () => {
            document.querySelectorAll('#moveFolderList .list-group-item').forEach(element => element.classList.remove('active'));
            item.classList.add('active');
            selectedFolderId = folder.id;
            if (confirmMoveButton) {
                confirmMoveButton.disabled = false;
            }
        });

        list.appendChild(item);
    });

    const modalElement = document.getElementById('moveModal');
    const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
    modal?.show();

    if (confirmMoveButton) {
        confirmMoveButton.onclick = async () => {
            if (!selectedFolderId || !fileHash) {
                return;
            }

            const moveResponse = await fetch('/disk/move', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({hash: fileHash, targetFolderId: selectedFolderId})
            });

            if (moveResponse.ok) {
                modal?.hide();
                location.reload();
            } else {
                alert('Не удалось переместить файл');
            }
        };
    }
}
