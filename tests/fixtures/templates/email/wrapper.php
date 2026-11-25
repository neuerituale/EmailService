<?php namespace ProcessWire;
/**
 * @var string $content
 * @var string|null $subject
 */
?><!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title><?= $sanitizer->entities($subject ?? '') ?></title></head>
<body><div class="wrap"><?= $content ?></div><p class="footer">WRAPPER-FOOTER</p></body>
</html>
