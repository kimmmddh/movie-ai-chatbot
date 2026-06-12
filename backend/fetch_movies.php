<?php
$apiKey = getenv('TMDB_API_KEY');

if (!$apiKey) {
    echo "TMDB_API_KEY가 설정되어 있지 않습니다.\n";
    exit;
}

$baseUrl = 'https://api.themoviedb.org/3';
$language = 'ko-KR';
$movies = [];

function requestJson(string $url): ?array {
    $json = @file_get_contents($url);
    if ($json === false) return null;

    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function fetchMovieDetails(string $apiKey, int $movieId, string $language): ?array {
    $url = 'https://api.themoviedb.org/3/movie/' . $movieId
        . '?api_key=' . urlencode($apiKey)
        . '&language=' . urlencode($language)
        . '&append_to_response=credits';

    return requestJson($url);
}

for ($page = 1; $page <= 2; $page++) {
    $url = $baseUrl . '/movie/popular?api_key=' . urlencode($apiKey)
        . '&language=' . urlencode($language)
        . '&page=' . $page;

    $data = requestJson($url);
    if (!$data || !isset($data['results'])) continue;

    foreach ($data['results'] as $item) {
        $movies[] = [
            'tmdb_id' => $item['id'],
            'title' => $item['title'] ?? $item['original_title'] ?? '',
            'year' => (int) substr($item['release_date'] ?? '0', 0, 4),
            'summary' => $item['overview'] ?? '',
            'genre' => [],
            'director' => null,
        ];
    }
}

foreach ($movies as $i => $movie) {
    $details = fetchMovieDetails($apiKey, $movie['tmdb_id'], $language);
    if (!$details) continue;

    $movies[$i]['genre'] = array_map(
        fn($genre) => $genre['name'],
        $details['genres'] ?? []
    );

    $director = null;
    foreach ($details['credits']['crew'] ?? [] as $crew) {
        if (($crew['job'] ?? '') === 'Director') {
            $director = $crew['name'];
            break;
        }
    }

    $movies[$i]['director'] = $director;
}

$cleanMovies = array_map(function ($movie) {
    return [
        'title' => $movie['title'],
        'year' => $movie['year'],
        'genre' => $movie['genre'],
        'director' => $movie['director'],
        'summary' => $movie['summary'],
    ];
}, $movies);

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

file_put_contents(
    $dataDir . '/movies.json',
    json_encode($cleanMovies, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

echo "총 " . count($cleanMovies) . "편의 영화 정보를 저장했습니다.\n";