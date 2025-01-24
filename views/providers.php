<?php

$buttonsHtml = '';
foreach ($providers as $provider => $data) {
    $buttonsHtml .= View::make($data['view'] ?? "winter.sso::buttons.provider", [
        'logoUrl' => $data['logoUrl'],
        'logoAlt' => $data['logoAlt'],
        'url' => $data['url'],
        'label' => $data['label'],
    ]);
} ?>

<?= $buttonsHtml; ?>
