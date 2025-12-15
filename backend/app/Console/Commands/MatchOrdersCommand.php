<?php

namespace App\Console\Commands;

use App\Enums\SymbolEnum;
use App\Jobs\ProcessBatchMatching;
use Illuminate\Console\Command;

class MatchOrdersCommand extends Command
{
    protected $signature = 'orders:match
                            {symbol? : The symbol to match orders for (BTC, ETH)}
                            {--all : Match all symbols}
                            {--max=50 : Maximum orders to process per symbol}';

    protected $description = 'Process order matching for specified symbol(s)';

    public function handle(): int
    {
        $symbol = SymbolEnum::tryFrom($this->argument('symbol'));
        $matchAll = $this->option('all');
        $maxOrders = (int)$this->option('max');

        $symbols = $matchAll
            ? SymbolEnum::values()
            : [$symbol ?: SymbolEnum::BTC->value];

        foreach ($symbols as $symbol) {
            ProcessBatchMatching::dispatch($symbol, $maxOrders);
        }

        return Command::SUCCESS;
    }
}
