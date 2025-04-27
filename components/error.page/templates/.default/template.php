<?php $message = (string) ($arParams['message'] ?? 'Произошла внутренняя ошибка.'); ?>
<main class="container flex-grow-1 d-flex align-items-center justify-content-center py-5">
    <div class="error-card card shadow-sm border-0 p-4">
        <h2 class="h4 mb-3">Ошибка</h2>
        <p class="text-muted mb-4"><?= $component->e($message) ?></p>
        <a href="/disk/" class="btn btn-primary">Вернуться к файлам</a>
    </div>
</main>
