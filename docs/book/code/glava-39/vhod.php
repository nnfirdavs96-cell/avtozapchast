<?php
require_once __DIR__ . '/includes/bootstrap.php';

$oshibka = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_proverit();
    $email = trim($_POST['email'] ?? '');
    // Демонстрация: одно сообщение и при неверной почте, и при неверном пароле
    $oshibka = 'Неверная почта или пароль';
}

$zagolovok = 'Вход';
require __DIR__ . '/includes/header.php';
?>
<h1>Вход</h1>

<?php if ($oshibka !== null): ?>
    <div class="oshibka-obshaya"><?= e($oshibka) ?></div>
<?php endif; ?>

<form method="POST" class="forma">
    <?= csrf_pole() ?>
    <div class="pole">
        <label for="email">Почта</label>
        <input type="email" id="email" name="email" required
               value="<?= e($email) ?>" autocomplete="username">
    </div>
    <div class="pole">
        <label for="parol">Пароль</label>
        <input type="password" id="parol" name="parol" required autocomplete="current-password">
    </div>
    <button type="submit">Войти</button>
</form>

<p><a href="#">Забыли пароль?</a> · <a href="#">Регистрация</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
