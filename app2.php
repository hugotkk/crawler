<?php

require_once('vendor/autoload.php');

$client = new GuzzleHttp\Client();

$folder = '/folder/archive2/';
is_dir($folder) or mkdir($folder);

foreach (range(2002, 2002) as $year) {
    $url_file = $folder.$year.'.txt';
    $urls = explode("\n", trim(file_get_contents($url_file)));

    $requests = function ($total) use ($urls) {
        foreach ($urls as $url) {
            $base = 'https://hk.appledaily.com';
            $url = $base.$url;
            yield new GuzzleHttp\Psr7\Request('GET', $url);
        }
    };

    $folder_year = $folder.$year.'/';
    is_dir($folder_year) or mkdir($folder_year);

    $pool = new GuzzleHttp\Pool($client, $requests(100), [
        'concurrency' => 5,
        'fulfilled' => function ($response, $index) use (&$urls, $folder_year) {
            echo "OK: ".$urls[$index]."\n";
            $html = $response->getBody()->getContents();
            preg_match_all('#Fusion\.globalContent=(.*?);Fusion\.#m', $html, $matches);
            $filename = $folder_year.str_replace('/', '_', trim($urls[$index], '/')).'.json';
            file_put_contents($filename, $matches[1] ?? '');
        },
        'rejected' => function ($reason, $index) use (&$urls) {
            echo "Fail: ".$urls[$index].' '.$reason->getMessage()."\n";
            sleep(5);
        },
    ]);

    $pool->promise()->wait();
}
