    </main>

    <footer>
        <p><?= e(SAIT_GOROD) ?> ·
           <a href="tel:<?= preg_replace('/\D/', '', SAIT_TELEFON) ?>"><?= e(SAIT_TELEFON) ?></a> ·
           <a href="mailto:<?= e(SAIT_POCHTA) ?>"><?= e(SAIT_POCHTA) ?></a></p>
        <p>&copy; <?= date('Y') ?> <?= e(SAIT_NAZVANIE) ?></p>
    </footer>
</div>
</body>
</html>
