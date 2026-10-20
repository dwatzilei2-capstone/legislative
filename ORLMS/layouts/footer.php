</main>
</div>
<script src="<?= e(vendorAsset('bootstrap/bootstrap.bundle.min.js','https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(vendorAsset('jquery/jquery-3.7.1.min.js','https://code.jquery.com/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= e(vendorAsset('datatables/jquery.dataTables.min.js','https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= e(vendorAsset('datatables/dataTables.bootstrap5.min.js','https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js')) ?>"></script>
<script src="<?= e(vendorAsset('sweetalert2/sweetalert2.min.js','https://cdn.jsdelivr.net/npm/sweetalert2@11')) ?>"></script>
<script src="<?= e(vendorAsset('chartjs/chart.umd.min.js','https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js')) ?>"></script>
<script>
window.APP_URL = <?= json_encode(rtrim(APP_URL,'/')) ?>;
window.APP_CSRF_TOKEN = <?= json_encode(function_exists('csrfToken') ? csrfToken() : '') ?>;
</script>
<script src="<?= e(APP_URL . '/assets/js/orlms-navigation.js?v=' . time()) ?>"></script>
<?php foreach ($extraJs as $js): ?><script src="<?= e($js) ?>"></script><?php endforeach; ?>
</body>
</html>
