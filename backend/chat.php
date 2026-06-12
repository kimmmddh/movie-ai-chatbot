<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['response' => 'POST 요청만 지원합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiKey = getenv('TMDB_API_KEY');

if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['response' => 'TMDB_API_KEY가 설정되어 있지 않습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userPrompt = trim($input['prompt'] ?? '');

if ($userPrompt === '') {
    echo json_encode(['response' => '질문을 입력해 주세요.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function requestJson(string $url): ?array {
    $json = @file_get_contents($url);

    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : null;
}

function extractMovieKeyword(string $prompt): string {
    $removeWords = [
        '줄거리',
        '내용',
        '알려줘',
        '추천',
        '해줘',
        '영화',
        '뭐야',
        '어떤',
        '대한',
        '정보',
        '?',
        '!',
    ];

    $keyword = $prompt;

    foreach ($removeWords as $word) {
        $keyword = str_replace($word, '', $keyword);
    }

    return trim($keyword);
}

function getFallbackKeywords(string $keyword): array {
    $map = [
        '주토피아' => ['주토피아', 'Zootopia'],
        '인셉션' => ['인셉션', 'Inception'],
        '인터스텔라' => ['인터스텔라', 'Interstellar'],
        '어벤져스' => ['어벤져스', 'Avengers'],
        '타이타닉' => ['타이타닉', 'Titanic'],
    ];

    return $map[$keyword] ?? [$keyword];
}

function searchTmdbMovies(string $apiKey, string $query): array {
    $baseUrl = 'https://api.themoviedb.org/3/search/movie';
    $languages = ['ko-KR', 'en-US'];

    foreach ($languages as $language) {
        $url = $baseUrl
            . '?api_key=' . urlencode($apiKey)
            . '&language=' . urlencode($language)
            . '&query=' . urlencode($query)
            . '&include_adult=false'
            . '&page=1';

        $data = requestJson($url);

        if ($data && isset($data['results']) && count($data['results']) > 0) {
            return array_slice($data['results'], 0, 5);
        }
    }

    return [];
}

function fetchMovieDetail(string $apiKey, int $movieId): ?array {
    $url = 'https://api.themoviedb.org/3/movie/' . $movieId
        . '?api_key=' . urlencode($apiKey)
        . '&language=ko-KR'
        . '&append_to_response=credits';

    return requestJson($url);
}

function buildMovieContext(array $movies, string $apiKey): string {
    if (empty($movies)) {
        return '';
    }

    $parts = [];

    foreach ($movies as $movie) {
        $movieId = $movie['id'] ?? null;

        if (!$movieId) {
            continue;
        }

        $detail = fetchMovieDetail($apiKey, (int) $movieId);

        if (!$detail) {
            continue;
        }

        $genres = [];

        foreach ($detail['genres'] ?? [] as $genre) {
            $genres[] = $genre['name'];
        }

        $director = '정보 없음';

        foreach ($detail['credits']['crew'] ?? [] as $crew) {
            if (($crew['job'] ?? '') === 'Director') {
                $director = $crew['name'];
                break;
            }
        }

        $title = $detail['title'] ?? $detail['original_title'] ?? '정보 없음';
        $year = substr($detail['release_date'] ?? '', 0, 4);
        $summary = $detail['overview'] ?? '';

        $parts[] = sprintf(
            "제목: %s\n연도: %s\n장르: %s\n감독: %s\n줄거리: %s",
            $title,
            $year !== '' ? $year : '정보 없음',
            !empty($genres) ? implode(', ', $genres) : '정보 없음',
            $director,
            $summary !== '' ? $summary : '줄거리 정보 없음'
        );
    }

    return implode("\n\n", $parts);
}

$keyword = extractMovieKeyword($userPrompt);

if ($keyword === '') {
    $keyword = $userPrompt;
}

$movies = [];
$usedKeyword = $keyword;

foreach (getFallbackKeywords($keyword) as $searchKeyword) {
    $movies = searchTmdbMovies($apiKey, $searchKeyword);

    if (!empty($movies)) {
        $usedKeyword = $searchKeyword;
        break;
    }
}

$contextText = buildMovieContext($movies, $apiKey);

if ($contextText === '') {
    echo json_encode([
        'response' => "TMDB에서 '{$keyword}'에 대한 영화 정보를 찾지 못했습니다."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$finalPrompt = <<<PROMPT
너는 한국어로 답변하는 영화 정보 도우미야.

아래 영화 정보만 참고해서 사용자의 질문에 답해줘.
정보에 없는 내용은 절대 지어내지 마.

[영화 정보]
{$contextText}

[사용자 질문]
{$userPrompt}

[답변 규칙]
- 줄거리 질문이면 관련 영화 1개를 중심으로 2~3문장으로 요약해.
- 추천 질문이면 영화 제목과 짧은 추천 이유를 함께 답변해.
- 여러 영화가 검색되면 가장 관련 있어 보이는 영화부터 설명해.
- 정보가 부족하면 정확히 알 수 없다고 말해.
PROMPT;

$payload = json_encode([
    'model' => 'llama3.2',
    'prompt' => $finalPrompt,
    'stream' => false,
], JSON_UNESCAPED_UNICODE);

$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 120,
    ],
];

$ollamaContext = stream_context_create($options);

$ollamaResponse = @file_get_contents(
    'http://ollama:11434/api/generate',
    false,
    $ollamaContext
);

if ($ollamaResponse === false) {
    echo json_encode([
        'response' => 'Ollama 서버 호출 중 오류가 발생했습니다.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ollamaJson = json_decode($ollamaResponse, true);
$answerText = $ollamaJson['response'] ?? '응답을 읽는 데 실패했습니다.';

echo json_encode([
    'response' => $answerText
], JSON_UNESCAPED_UNICODE);