<?php

namespace App\Controller;

//use App\Api\CoinCapAPI;
use App\Api\CoinMarketAPI;
use App\Trade;
use Dotenv\Dotenv;

class CryptoController
{

    private string $userName;
    private CoinMarketAPI $apiClient;
//private CoinCapAPI $apiClient;
    private Trade $trade;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        $key = $_ENV['APIKEY'];
        $this->userName = "Steve";
        $this->apiClient = new CoinMarketAPI($key);
        //$this->apiClient = new CoinCapAPI();
        $this->trade = new Trade($this->apiClient, $this->userName);
    }


    public function index(): array
    {
        return $this->trade->list();
    }

    public function show(array $vars): ?array
    {
        $symbol = $vars['symbol'] ?? null;
        if ($symbol) {
            $currency = $this->trade->search($symbol);
            if ($currency) {
                return $currency->jsonSerialize();
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    public function transactions(): ?array
    {
        $transactions = $this->trade->displayTransactions();
        $table = [];
        foreach ($transactions as $transaction) {
            $table[] = [
                'type' => ucfirst($transaction[0]),
                'symbol' => $transaction[1],
                'amount' => $transaction[2],
                'price' => $transaction[3],
                'value' => $transaction[4],
                'time' => $transaction[5]
            ];
        }
        return $table;
    }

    public function purchase(array $vars): string
    {
        $symbol = $vars['symbol'] ?? null;
        $amount = (float)($_POST['amount'] ?? 0);
        if ($symbol && $amount > 0) {
            $this->trade->purchase($symbol, $amount);
            header('Location: /transactions');
            exit;
        }
        return "Invalid symbol or amount";
    }

    public function sell(array $vars): string
    {
        $symbol = $vars['symbol'] ?? null;
        $amount = (float)($_POST['amount'] ?? 0);
        if ($symbol && $amount > 0) {
            $this->trade->sell($symbol, $amount);
            header('Location: /transactions');
            exit;
        }
        return "Invalid symbol or amount";
    }

    public function wallet(): array
    {
        return $this->trade->displayWallet();
    }
}