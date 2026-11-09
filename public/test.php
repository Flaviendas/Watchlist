<?php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

$tmdb = new App\Service\TmdbService($_ENV['TMDB_API_KEY']);
var_dump($tmdb->getGenres());