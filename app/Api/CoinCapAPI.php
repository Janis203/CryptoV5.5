<?php

namespace App\Api;

class CoinCapAPI implements ApiClient
{
    public function getList(int $start, int $limit, string $convert): array
    {
        $url = "https://api.coincap.io/v2/assets?limit=$limit";
        $data = file_get_contents($url);
        return json_decode($data, true);
    }

    public function getSymbol(string $symbol, string $convert): array
    {
        $url = "https://api.coincap.io/v2/assets?search=$symbol";
        $context = file_get_contents($url);
        $data = json_decode($context, true);
        $format["data"][$symbol] = $data["data"][0];
        return $format;
    }
}