<?php use App\Support\View; /** @var int $rating @var string $size */ ?>
<span class="stars" role="img" aria-label="<?= (int) $rating ?> out of 5"><?php
for ($i = 1; $i <= 5; $i++) {
    echo '<span class="stars__s', $i <= (int) $rating ? ' is-on' : '', '">&#9733;</span>';
} ?></span>
