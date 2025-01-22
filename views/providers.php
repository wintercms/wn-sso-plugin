<?php

$buttonsHtml = '';
foreach ($providers as $provider => $data) {
    $buttonsHtml .= View::make("winter.sso::buttons.provider", $data['view'], [
        'logoUrl' => $data['logoUrl'],
        'logoAlt' => $data['logoAlt'],
        'url' => $data['url'],
        'label' => $data['label'],
    ]);
} ?>

<?= $buttonsHtml; ?>
