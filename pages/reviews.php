<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = t('shop_reviews') . ' — ' . getSetting('site_name');

$db   = getDB();
$csrf = generateCsrfToken();

$summary  = getShopRatingSummary();
$reviews  = [];
$myReview = null;
try {
    $reviews = $db->query(
        "SELECT r.rating, r.comment, r.created_at, u.username
         FROM shop_reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.status = 'approved'
         ORDER BY r.is_featured DESC, r.created_at DESC"
    )->fetchAll();

    if (isLoggedIn()) {
        $mr = $db->prepare("SELECT rating, comment, status FROM shop_reviews WHERE user_id = ? LIMIT 1");
        $mr->execute([(int)$_SESSION['user_id']]);
        $myReview = $mr->fetch() ?: null;
    }
} catch (PDOException $e) {
    // Reviews migration not applied yet
    $reviews = [];
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('shop_reviews')]]) ?>

<div class="blog_bg_area">
    <div class="container" style="padding-top:50px;padding-bottom:60px;">

        <div style="text-align:center;margin-bottom:40px;">
            <h1 style="font-size:2rem;margin-bottom:8px;"><?= t('shop_reviews') ?></h1>
            <p style="color:#888;"><?= t('shop_reviews_desc') ?></p>
            <?php if ($summary['count']): ?>
            <div style="margin-top:18px;font-size:1.6rem;">
                <?= starsHtml($summary['avg']) ?>
                <strong style="margin-left:8px;"><?= $summary['avg'] ?></strong>
                <span style="color:#999;font-size:1rem;">/ 5 — <?= t('based_on_reviews', ['n' => $summary['count']]) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <?php if (empty($reviews)): ?>
                    <p style="text-align:center;color:#999;padding:40px 0;"><?= t('no_shop_reviews') ?></p>
                <?php else: ?>
                    <?php foreach ($reviews as $rv): ?>
                    <div style="border:1px solid #eee;border-radius:8px;padding:18px 20px;margin-bottom:16px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
                            <strong style="font-size:1rem;"><?= sanitize($rv['username']) ?></strong>
                            <span style="font-size:0.8rem;color:#999;"><?= date('d.m.Y', strtotime($rv['created_at'])) ?></span>
                        </div>
                        <div style="margin:8px 0;font-size:1rem;"><?= starsHtml((float)$rv['rating']) ?></div>
                        <p style="margin:0;color:#555;line-height:1.7;"><?= nl2br(sanitize($rv['comment'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div id="form" style="border:1px solid #eee;border-radius:8px;padding:22px;background:#fafafa;position:sticky;top:90px;">
                    <h3 style="font-size:1.15rem;margin-bottom:16px;"><?= t('leave_shop_review') ?></h3>

                    <?php if (!isLoggedIn()): ?>
                        <p style="color:#777;">
                            <a href="<?= APP_URL ?>/auth/login.php?redirect=<?= urlencode('/pages/reviews.php') ?>"
                               style="color:#d32f2f;font-weight:600;"><?= t('login_to_review') ?></a>
                        </p>
                    <?php else: ?>
                        <?php if ($myReview && $myReview['status'] === 'pending'): ?>
                            <div style="background:#fff8e1;border:1px solid #ffe082;color:#795548;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:0.85rem;">
                                <i class="fa fa-clock-o"></i> <?= t('review_pending') ?>
                            </div>
                        <?php elseif ($myReview && $myReview['status'] === 'rejected'): ?>
                            <div style="background:#ffebee;border:1px solid #ffcdd2;color:#c62828;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:0.85rem;">
                                <i class="fa fa-times-circle"></i> <?= t('review_rejected') ?>
                            </div>
                        <?php elseif ($myReview && $myReview['status'] === 'approved'): ?>
                            <p style="color:#888;font-size:0.84rem;margin-bottom:14px;"><?= t('review_edit_hint') ?></p>
                        <?php endif; ?>

                        <form method="POST" action="<?= APP_URL ?>/api/shop_review_submit.php">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="rating" id="ratingInput" value="<?= $myReview ? (int)$myReview['rating'] : 5 ?>">

                            <label style="display:block;font-size:0.86rem;font-weight:600;margin-bottom:6px;"><?= t('your_rating') ?></label>
                            <div id="starPicker" style="font-size:1.7rem;cursor:pointer;display:inline-flex;gap:5px;margin-bottom:16px;">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="fa fa-star" data-val="<?= $s ?>" style="color:#ccc;transition:color .12s;"></i>
                                <?php endfor; ?>
                            </div>

                            <label style="display:block;font-size:0.86rem;font-weight:600;margin-bottom:6px;"><?= t('your_review') ?></label>
                            <textarea name="comment" rows="5" required minlength="10" maxlength="2000"
                                      style="width:100%;border:1px solid #ddd;border-radius:6px;padding:10px 12px;font-size:0.9rem;resize:vertical;margin-bottom:14px;"><?= $myReview ? sanitize($myReview['comment']) : '' ?></textarea>

                            <button type="submit"
                                    style="width:100%;background:#d32f2f;color:#fff;border:none;padding:11px;border-radius:6px;font-weight:600;cursor:pointer;">
                                <?= t('submit_review') ?>
                            </button>
                        </form>

                        <script>
                        (function () {
                            var picker = document.getElementById('starPicker');
                            var input  = document.getElementById('ratingInput');
                            if (!picker || !input) return;
                            var stars = picker.querySelectorAll('i');
                            function paint(v) {
                                stars.forEach(function (st) {
                                    st.style.color = (parseInt(st.dataset.val, 10) <= v) ? '#f5a623' : '#ccc';
                                });
                            }
                            paint(parseInt(input.value, 10) || 5);
                            stars.forEach(function (st) {
                                st.addEventListener('mouseenter', function () { paint(parseInt(st.dataset.val, 10)); });
                                st.addEventListener('click', function () {
                                    input.value = st.dataset.val;
                                    paint(parseInt(st.dataset.val, 10));
                                });
                            });
                            picker.addEventListener('mouseleave', function () {
                                paint(parseInt(input.value, 10) || 5);
                            });
                        })();
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
