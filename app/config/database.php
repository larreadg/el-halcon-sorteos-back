<?php

declare(strict_types=1);

use flight\database\SimplePdo;

Flight::register('db', SimplePdo::class, ['sqlite:' . APP_ROOT . '/el_halcon_sorteos.db']);
