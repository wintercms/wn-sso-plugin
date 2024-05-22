<?php

$buttonsHtml = '';
foreach ($providers as $provider) {
    $providerName = Lang::get("winter.sso::lang.providers.$provider");

    $buttonsHtml .= View::make("winter.sso::buttons.provider", [
        'logoUrl' => Url::asset('/plugins/winter/sso/assets/images/providers/' . $provider . '.svg'),
        'logoAlt' => Lang::get('winter.sso::lang.provider_btn.alt_text', ['provider' => $providerName]),
        'url' => Backend::url('winter/sso/handle/redirect/' . $provider),
        'label' => Lang::get('winter.sso::lang.provider_btn.label', ['provider' => $providerName]),
    ]);
} ?>

<?= $buttonsHtml; ?>
