<?php
declare(strict_types=1);
$pageHeading=$pageHeading??($pageTitle??'ORLMS');
$pageDescription=$pageDescription??'';
?>
<div class="of-head">
<div>
<div class="of-eyebrow"><?= e(APP_SHORT_NAME) ?></div>
<h1><?= e($pageHeading) ?></h1>
<?php if($pageDescription!==''): ?><p><?= e($pageDescription) ?></p><?php endif; ?>
</div>
</div>
