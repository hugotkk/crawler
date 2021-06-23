<?php

require_once('vendor/autoload.php');

$client = new GuzzleHttp\Client();

$begin = new Datetime('2002-01-02');
$end = new Datetime('2021-06-23');
$end = new Datetime('2003-01-01');
$interval = DateInterval::createFromDateString('1 day');
$period = new DatePeriod($begin, $interval, $end);

$folder = 'archive/';
mkdir($folder);
$base = 'https://hk.appledaily.com/';
foreach ($period as $dt) {
    $url = $base.'/archive/'.$dt->format('Ymd');
    $request = new \GuzzleHttp\Psr7\Request('GET', $url);
    $promise = $client->sendAsync($request)->then(function ($response) use ($folder, $dt, $base) {
        echo $dt->format('Ymd')."\n";
        $html = $response->getBody()->getContents();
        $filename = $folder.str_replace('/', '_', trim($dt->format('Ymd'), '/')).'.html';
        file_put_contents($filename, $html);
        preg_match_all('#<a href="(/local/[^"]+?)" class="archive-story">#', $html, $matches);
        $promises = [];
        foreach($matches[1] as $detail) {
            $url = $base.$detail;
            $client = new GuzzleHttp\Client();
            $request = new \GuzzleHttp\Psr7\Request('GET', $url);
            $promises[] = $client->sendAsync($request)->then(function ($response) use ($folder, $base, $detail) {
                echo $detail."\n";
                $html = $response->getBody()->getContents();
                $filename = $folder.str_replace('/', '_', trim($detail, '/')).'.html';
                file_put_contents($filename, $html);
            });
        }
        GuzzleHttp\Promise\Utils::settle($promises)->wait();
    });
    $promise->wait();
}
