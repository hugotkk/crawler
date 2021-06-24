<?php

require_once('vendor/autoload.php');

$client = new GuzzleHttp\Client();

$folder = '/folder/archive2/';
is_dir($folder) or mkdir($folder);

foreach (range(2002, 2020) as $year) {
    $begin = new Datetime($year.'-01-01');
    $end = new Datetime(($year + 1).'-01-01'); // this date is excluded
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($begin, $interval, $end);

    $requests = function ($total) use ($period) {
        foreach ($period as $dt) {
            $base = 'https://hk.appledaily.com/';
            $url = $base.'archive/'.$dt->format('Ymd');
            yield new GuzzleHttp\Psr7\Request('GET', $url);
        }
    };

    $result = [];

    $pool = new GuzzleHttp\Pool($client, $requests(100), [
        'concurrency' => 10,
        'fulfilled' => function ($response, $index) use (&$result) {
            echo "OK: ". $index."\n";
            $html = $response->getBody()->getContents();
            preg_match_all('#<a href="([^"]+/\d{8}/[^"]+)"#', $html, $matches);
            $result = array_merge($result, array_unique(($matches[1])));
        },
        'rejected' => function ($reason, $index) {
            echo "Fail: ".$index."\n";
        },
    ]);

    $pool->promise()->wait();

    $filename = $folder.$begin->format('Y').'.txt';
    file_put_contents($filename, implode("\n", $result));
    echo $filename."\n";
}