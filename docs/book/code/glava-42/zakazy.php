<?php
require_once __DIR__ . '/includes/bootstrap.php';

function status_zakaza_podpis(string $s): string {
    return match ($s) {
        'novyi' => 'Новый', 'podtverzhden' => 'Подтверждён', 'sobran' => 'Собран',
        'otpravlen' => 'Отправлен', 'dostavlen' => 'Доставлен', 'otmenen' => 'Отменён',
        default => 'Неизвестно',
    };
}

$statusy = ['novyi','podtverzhden','sobran','otpravlen','dostavlen','otmenen'];
$zakazy = zapros('SELECT * FROM zakazy_admin ORDER BY sozdan DESC');
$vsego = count($zakazy);

$zagolovok = 'Заказы';
require __DIR__ . '/includes/header.php';
?>
<h1>Заказы <span class="tihoe"><?= $vsego ?></span></h1>

<form class="filtry-admin" method="GET">
    <input type="search" name="q" placeholder="номер, имя или телефон">
    <select name="status">
        <option value="">Все статусы</option>
        <?php foreach ($statusy as $s): ?>
            <option value="<?= $s ?>"><?= e(status_zakaza_podpis($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Найти</button>
</form>

<table class="spisok plotnaya">
    <thead>
        <tr><th>Номер</th><th>Дата</th><th>Клиент</th><th>Телефон</th>
            <th>Поз.</th><th>Сумма</th><th>Статус</th></tr>
    </thead>
    <tbody>
    <?php foreach ($zakazy as $z): ?>
        <tr>
            <td class="mono"><a href="#"><?= e($z['nomer']) ?></a></td>
            <td class="tihoe"><?= date('d.m H:i', strtotime($z['sozdan'])) ?></td>
            <td><?= e($z['klient_imya']) ?></td>
            <td class="mono"><a href="tel:<?= e(preg_replace('/\D/', '', $z['klient_telefon'])) ?>"><?= e($z['klient_telefon']) ?></a></td>
            <td class="chislo-yacheyka"><?= (int) $z['pozicij'] ?></td>
            <td class="chislo-yacheyka mono"><?= somoni((int) $z['summa_itogo']) ?></td>
            <td>
                <form method="POST" action="#" class="vstroennaya">
                    <select class="status-vybor s-<?= e($z['status']) ?>">
                        <?php foreach ($statusy as $s): ?>
                            <option value="<?= $s ?>" <?= $z['status'] === $s ? 'selected' : '' ?>>
                                <?= e(status_zakaza_podpis($s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
