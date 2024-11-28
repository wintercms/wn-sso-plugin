<h2 class="text-center"><?= e(Backend\Models\BrandSetting::get('app_tagline')) ?></h2>
<?= $this->fireViewEvent('backend.auth.extendSigninView') ?>
