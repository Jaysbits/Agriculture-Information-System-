    </main>

    <footer class="app-footer">
        <span><?= e(APP_NAME) ?> &middot; <?= e(APP_TAGLINE) ?></span>
        <span class="text-muted">CTU AP3 Midterm Project &middot; <?= date('Y') ?></span>
    </footer>
</div>

<!-- Flash messages -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <?php foreach ($flashes as $index => $flash): ?>
        <div class="toast align-items-center text-bg-<?= e($flash['type']) ?> border-0 shadow"
             role="status" aria-live="polite" aria-atomic="true"
             data-bs-delay="<?= $flash['type'] === 'danger' ? 8000 : 4500 ?>">
            <div class="d-flex">
                <div class="toast-body d-flex gap-2 align-items-start">
                    <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : ($flash['type'] === 'danger' ? 'exclamation-octagon-fill' : ($flash['type'] === 'warning' ? 'exclamation-triangle-fill' : 'info-circle-fill')) ?> mt-1"></i>
                    <span><?= e($flash['message']) ?></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?= $pageScripts ?? '' ?>
</body>
</html>









