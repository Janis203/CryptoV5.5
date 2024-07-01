<?php

namespace App;

use App\Api\ApiClient;
use Exception;
use SQLite3;

class Trade
{
    private ApiClient $apiClient;
    private SQLite3 $db;
    private string $userName;

    public function __construct(ApiClient $apiClient, string $userName)
    {
        $this->apiClient = $apiClient;
        $this->db = new SQLite3(__DIR__ . '/../storage/database.sqlite');
        $this->userName = $userName;
    }

    public function list(): ?array
    {
        try {
            $data = $this->apiClient->getList(1, 10, 'USD');
            $currencyArray = [];
            if (isset($data["data"])) {
                foreach ($data["data"] as $crypto) {
                    $currency = new Currency(
                        $crypto["name"],
                        $crypto["symbol"],
                        $crypto["cmc_rank"] ?? $crypto["rank"],
                        $crypto["quote"]["USD"]["price"] ?? $crypto["priceUsd"]
                    );
                    $currencyArray[] = $currency->jsonSerialize();
                }
                return $currencyArray;
            } else {
                return null;
            }
        } catch (Exception $e) {
            return [$e->getMessage()];
        }
    }

    public function search(string $symbol): ?Currency
    {
        try {
            $data = $this->apiClient->getSymbol($symbol, 'USD');
            if (isset($data["data"])) {
                $crypto = $data["data"][$symbol];
                return new Currency(
                    $crypto["name"],
                    $crypto["symbol"],
                    $crypto["cmc_rank"] ?? $crypto["rank"],
                    $crypto["quote"]["USD"]["price"] ?? $crypto["priceUsd"]
                );
            } else {
                return null;
            }
        } catch (Exception $e) {
            return null;
        }
    }

    private function getBalance(): float
    {
        $userBalance = $this->db->prepare("SELECT balance FROM wallet WHERE username = :username LIMIT 1");
        $userBalance->bindValue(':username', $this->userName);
        $result = $userBalance->execute()->fetchArray(SQLITE3_ASSOC);
        return $result["balance"];
    }

    private function updateBalance(float $amount): void
    {
        $update = $this->db->prepare("UPDATE wallet SET balance = :amount WHERE username = :username");
        $update->bindValue(':amount', $amount, SQLITE3_FLOAT);
        $update->bindValue(':username', $this->userName);
        $update->execute();
    }

    private function getTransactions(): array
    {
        $actions = $this->db->prepare("SELECT * FROM transactions WHERE username = :username");
        $actions->bindValue(':username', $this->userName);
        $result = $actions->execute();
        $transactions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $transactions[] = $row;
        }
        return $transactions;
    }

    private function saveTransactions(array $transaction): void
    {
        $save = $this->db->prepare("INSERT INTO transactions (username, type, symbol, amount, price, value, time) 
VALUES (:username, :type, :symbol, :amount, :price, :value, :time)");
        $save->bindValue(':username', $this->userName);
        $save->bindValue(':type', $transaction['type']);
        $save->bindValue(':symbol', $transaction['symbol']);
        $save->bindValue(':amount', $transaction['amount'], SQLITE3_FLOAT);
        $save->bindValue(':price', $transaction['price'], SQLITE3_FLOAT);
        $save->bindValue(':value', $transaction['value'], SQLITE3_FLOAT);
        $save->bindValue(':time', $transaction['time']);
        $save->execute();
    }

    public function purchase(string $symbol, float $amount): string
    {
        try {
            $data = $this->apiClient->getSymbol($symbol, "USD");
            if (isset($data['data'][$symbol])) {
                if ($amount <= 0) {
                    return "Enter positive amount ";
                }
                $price = $data["data"][$symbol]["quote"]["USD"]["price"] ?? $data["data"][$symbol]["priceUsd"];
                $cost = $price * $amount;
                $balance = $this->getBalance();
                if ($balance < $cost) {
                    return "Insufficient funds to buy $amount $symbol ";
                }
                $this->updateBalance($balance - $cost);
                $this->saveTransactions([
                    'type' => 'purchase',
                    'symbol' => $symbol,
                    'amount' => $amount,
                    'price' => $price,
                    'value' => $cost,
                    'time' => date("Y-m-d H:i:s")
                ]);
                return "Purchased $amount $symbol for \$$cost";
            } else {
                return $symbol . " not found";
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function sell(string $symbol, float $amount): string
    {
        try {
            $data = $this->apiClient->getSymbol($symbol, "USD");
            if (isset($data['data'][$symbol])) {
                if ($amount <= 0) {
                    return "Enter positive amount ";
                }
                $price = $data["data"][$symbol]["quote"]["USD"]["price"] ?? $data["data"][$symbol]["priceUsd"];
                $value = $price * $amount;
                $bought = 0;
                $sold = 0;
                $transactions = $this->getTransactions();
                foreach ($transactions as $transaction) {
                    if ($transaction['type'] === "purchase" && $transaction['symbol'] === $symbol) {
                        $bought += $transaction['amount'];
                    } elseif ($transaction['type'] === "sell" && $transaction['symbol'] === $symbol) {
                        $sold += $transaction['amount'];
                    }
                }
                $availableAmount = $bought - $sold;
                if ($amount > $availableAmount) {
                    return "Insufficient amount of $symbol to sell ";
                }
                $this->updateBalance($this->getBalance() + $value);
                $this->saveTransactions([
                    'type' => 'sell',
                    'symbol' => $symbol,
                    'amount' => $amount,
                    'price' => $price,
                    'value' => $value,
                    'time' => date('Y-m-d H:i:s')
                ]);
                return "Sold $amount $symbol for \$$value";
            } else {
                return $symbol . " not found";
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function displayWallet(): array
    {
        $walletData = [];
        $balance = $this->getBalance();
        $walletData['balance'] = $balance;
        $holding = [];
        $transactions = $this->getTransactions();
        foreach ($transactions as $transaction) {
            $symbol = $transaction['symbol'];
            if (!isset($holding[$symbol])) {
                $holding[$symbol] = ['amount' => 0, 'totalSpent' => 0];
            }
            if ($transaction['type'] === 'purchase') {
                $holding[$symbol]['amount'] += $transaction['amount'];
                $holding[$symbol]['totalSpent'] += $transaction['amount'] * $transaction['price'];
            } elseif ($transaction['type'] === "sell") {
                $holding[$symbol]['amount'] -= $transaction['amount'];
                $holding[$symbol]['totalSpent'] -= $transaction['amount'] * $transaction['price'];
            }
        }
        $walletData['holdings'] = [];
        foreach ($holding as $symbol => $amount) {
            if ($amount['amount'] > 0) {
                $average = $amount['totalSpent'] / $amount['amount'];
                $currentData = $this->apiClient->getSymbol($symbol, "USD");
                $currentPrice = $currentData['data'][$symbol]['quote']['USD']['price'] ?? $currentData['data'][$symbol]['priceUsd'];
                $profit = (($currentPrice - $average) / $average) * 100;
                $walletData['holdings'][] = [
                    'symbol' => $symbol,
                    'amount' => $amount['amount'],
                    'average' => $average,
                    'current' => $currentPrice,
                    'profit' => number_format($profit, 2)
                ];
            }
        }
        return $walletData;
    }

    public function displayTransactions(): array
    {
        $transactions = $this->getTransactions();
        $table = [];
        foreach ($transactions as $transaction) {
            $table[] = [
                ucfirst($transaction['type']),
                $transaction['symbol'],
                $transaction['amount'],
                $transaction['price'],
                $transaction['value'],
                $transaction['time']
            ];
        }
        return $table;
    }
}