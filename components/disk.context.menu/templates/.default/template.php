<div id="contextMenu" class="context-menu d-none">
    <form action="/disk/download" method="POST" class="px-3">
        <button type="submit">
            <i class="bi bi-download me-2"></i> Скачать
        </button>
    </form>
    <form action="/disk/share" method="POST" class="px-3 context-ajax-form">
        <button type="submit">
            <i class="bi bi-share me-2"></i> Поделиться
        </button>
    </form>
    <div class="px-3">
        <button type="button" data-context-action="move">
            <i class="bi bi-clipboard me-2"></i> Переместить
        </button>
    </div>
    <form action="/disk/copy" method="POST" class="px-3 context-ajax-form">
        <button type="submit">
            <i class="bi bi-files me-2"></i> Копировать
        </button>
    </form>
    <form action="/disk/delete" method="POST" class="px-3 context-ajax-form">
        <button type="submit">
            <i class="bi bi-trash me-2"></i> Удалить
        </button>
    </form>
    <div class="px-3">
        <button type="button" data-context-action="info">
            <i class="bi bi-info-circle me-2"></i> Подробнее
        </button>
    </div>
</div>

<div class="modal fade" id="moveModal" tabindex="-1" aria-labelledby="moveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Переместить в папку</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                <div id="moveFolderList" class="list-group"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="confirmMove" disabled>Переместить</button>
            </div>
        </div>
    </div>
</div>

