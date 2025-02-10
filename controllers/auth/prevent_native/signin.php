<?php if ($this->layout === 'auth') : ?>
    <h2 class="text-center"><?= e(Backend\Models\BrandSetting::get('app_tagline')) ?></h2>
<?php endif; ?>
<?= $this->fireViewEvent('backend.auth.extendSigninView') ?>
