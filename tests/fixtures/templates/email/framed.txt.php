<?php namespace ProcessWire;
/**
 * @var string $content
 * @var string|null $subject
 */
?>
TEXT-WRAP-START <?= $subject ?? '' ?> <?= $user->language ? $user->language->name : 'none' ?>


<?= $content ?>


TEXT-WRAP-END
