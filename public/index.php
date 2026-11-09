<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Service\TmdbService;

$tmdb = new TmdbService($_ENV['TMDB_API_KEY']);
$movies = $tmdb->getPopularMovies();

foreach ($movies as $movie) {

   echo '<h2>'.$movie['title'].'</h2>';
   echo '<img src="https://image.tmdb.org/t/p/w500' . $movie['poster_path'] . '">';
}