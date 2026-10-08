<?php

declare(strict_types=1);

use Pest\Mutate\Repositories\ConfigurationRepository;
use Pest\Support\Container;

require_once __DIR__.'/Helpers.php';


Container::getInstance()
    ->get(ConfigurationRepository::class)
    ->globalConfiguration()
    ->coveredOnly()
    ->min(50.0, failOnZeroMutations: true);
