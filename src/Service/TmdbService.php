<?php

declare(strict_types=1);

namespace App\Service;

class TmdbService
{
    private const BASE_URL = 'https://api.themoviedb.org/3';

    public function __construct(private string $apiKey)
    {
    }

    private function request(string $path): array
    {
        $url = self::BASE_URL . $path . (str_contains($path, '?') ? '&' : '?')
            . 'api_key=' . rawurlencode($this->apiKey);

        $context = stream_context_create([
            'http' => ['timeout' => 8, 'ignore_errors' => true],
        ]);
        $json = @file_get_contents($url, false, $context);

        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public function getPopularMovies(): array
    {
        return $this->request('/movie/popular?language=fr-FR')['results'] ?? [];
    }

    public function getMovie(int $id): array
    {
        return $this->request('/movie/' . $id . '?language=fr-FR');
    }

    public function getMovieWithCredits(int $id): array
    {
        return $this->request('/movie/' . $id . '?language=fr-FR&append_to_response=credits');
    }

    public function getProviders(int $id): array
    {
        return $this->request('/movie/' . $id . '/watch/providers')['results']['FR'] ?? [];
    }

    public function getGenres(): array
    {
        return $this->request('/genre/movie/list?language=fr-FR')['genres'] ?? [];
    }

    public function discover(array $genreIds, array $withoutGenreIds = []): array
    {
        if ($genreIds === []) {
            return [];
        }

        $genres = rawurlencode(implode('|', array_map('intval', $genreIds)));
        $today = date('Y-m-d');

        $path = '/discover/movie?language=fr-FR'
            . '&sort_by=popularity.desc'
            . '&vote_count.gte=200'
            . '&with_genres=' . $genres
            . '&release_date.lte=' . $today
            . '&watch_region=FR'
            . '&with_watch_monetization_types=flatrate';

        if ($withoutGenreIds !== []) {
            $path .= '&without_genres=' . rawurlencode(implode('|', array_map('intval', $withoutGenreIds)));
        }

        return $this->request($path)['results'] ?? [];
    }
}