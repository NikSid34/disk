<?php
$showRegister = !empty($arParams['registration']);
$hasErrors = !empty($arParams['invalidLogin']) || !empty($arParams['unmatchedPassword']) || !empty($arParams['loginAlreadyExists']) || !empty($arParams['emailAlreadyExists']);
?>
<div class="container d-flex justify-content-center align-items-center flex-grow-1 py-5">
    <div class="card shadow-lg auth-card bg-white p-4">
        <?php if ($hasErrors || !empty($arParams['registration'])): ?>
            <div class="mb-4">
                <?php if (!empty($arParams['invalidLogin'])): ?>
                    <div class="alert alert-danger mb-2">Неверный логин или пароль.</div>
                <?php endif; ?>
                <?php if (!empty($arParams['unmatchedPassword'])): ?>
                    <div class="alert alert-warning mb-2">Пароли не совпадают.</div>
                <?php endif; ?>
                <?php if (!empty($arParams['loginAlreadyExists'])): ?>
                    <div class="alert alert-warning mb-2">Пользователь с таким логином уже существует.</div>
                <?php endif; ?>
                <?php if (!empty($arParams['emailAlreadyExists'])): ?>
                    <div class="alert alert-warning mb-0">Пользователь с таким email уже существует.</div>
                <?php endif; ?>
                <?php if (!empty($arParams['registration']) && !$hasErrors): ?>
                    <div class="alert alert-info mb-0">Завершите регистрацию, чтобы продолжить.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4" id="authTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $showRegister ? '' : 'active' ?>"
                        id="login-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#login"
                        type="button"
                        role="tab">
                    Вход
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $showRegister ? 'active' : '' ?>"
                        id="register-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#register"
                        type="button"
                        role="tab">
                    Регистрация
                </button>
            </li>
        </ul>

        <div class="tab-content" id="authTabsContent">
            <div class="tab-pane fade <?= $showRegister ? '' : 'show active' ?>" id="login" role="tabpanel">
                <form method="post" action="/user/authorize">
                    <div class="mb-3">
                        <label for="loginLogin" class="form-label">Логин</label>
                        <input type="text" class="form-control" id="loginLogin" name="login" required>
                    </div>
                    <div class="mb-3">
                        <label for="loginPassword" class="form-label">Пароль</label>
                        <input type="password" class="form-control" id="loginPassword" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Войти</button>
                    </div>
                </form>
            </div>

            <div class="tab-pane fade <?= $showRegister ? 'show active' : '' ?>" id="register" role="tabpanel">
                <form method="post" action="/user/register">
                    <div class="mb-3">
                        <label for="registerLogin" class="form-label">Логин</label>
                        <input type="text" class="form-control" id="registerLogin" name="login" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="registerEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerPassword" class="form-label">Пароль</label>
                        <input type="password" class="form-control" id="registerPassword" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerConfirm" class="form-label">Повторите пароль</label>
                        <input type="password" class="form-control" id="registerConfirm" name="password_confirm" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

