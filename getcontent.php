<?php

require_once('vendor/autoload.php');

// $stack = GuzzleHttp\HandlerStack::create();
// $stack->push(Spatie\GuzzleRateLimiterMiddleware\RateLimiterMiddleware::perSecond(2));

$client = new GuzzleHttp\Client([
    // 'handler' => $stack,
]);

$folder = 'folder/archive2/';
is_dir($folder) or mkdir($folder);

foreach (range(2014, 2015) as $year) {
    $folder_year = $folder.$year.'/';
    is_dir($folder_year) or mkdir($folder_year);

    $url_file = $folder.$year.'.txt';
    $all_urls = explode("\n", trim(file_get_contents($url_file)));
    $urls = [];
    foreach ($all_urls as $uri) {
        $filename = $folder_year.str_replace('/', '_', trim($uri, '/')).'.json';
        if (file_exists($filename) /*&& filesize($filename)*/) continue; // skip if url already saved
        $urls[] = [
            'uri' => $uri,
            'filename' => $filename,
        ];
    }

    $requests = function () use ($urls) {
        foreach ($urls as $url) {
            $base = 'https://hk.appledaily.com';
            yield new GuzzleHttp\Psr7\Request('GET', $base.$url['uri']);
        }
    };

    $pool = new GuzzleHttp\Pool($client, $requests(), [
        'concurrency' => 1,
        'fulfilled' => function ($response, $index) use ($urls) {
            $html = $response->getBody()->getContents();
            preg_match('#Fusion\.globalContent=(.*?);Fusion\.#m', $html, $matches);
            $filename = $urls[$index]['filename'];
            if (!empty($matches[1])) {
                file_put_contents($filename, $matches[1]);
                echo "OK: ".$urls[$index]['uri']."\n";
            } else {
                echo "Empty: ".$urls[$index]['uri']."\n";
            }
        },
        'rejected' => function ($reason, $index) use ($urls) {
            echo "Fail: ".$urls[$index]['uri'].' '.$reason->getMessage()."\n";
            sleep(300);
        },
    ]);

    $pool->promise()->wait();
}
