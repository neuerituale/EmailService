<?php namespace ProcessWire;
/** @var string $name */
?><h1>Hello <?= $sanitizer->entities($name ?? '') ?></h1><p>Line one<br>Line two</p><p>Tom &amp; Jerry</p>
