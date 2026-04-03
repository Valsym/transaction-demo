<?php
// sail artisan transfer:optimistic 1 2 200 3

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransferOptimistic extends Command
{
    protected $signature = 'transfer:optimistic {from_id} {to_id} {amount} {max_retries=3}';
    protected $description = 'Transfer with optimistic locking (version field)';

    public function handle()
    {
        $fromId = $this->argument('from_id');
        $toId = $this->argument('to_id');
        $amount = (float) $this->argument('amount');
        $maxRetries = (int) $this->argument('max_retries');

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->doTransfer($fromId, $toId, $amount);
                $this->info("Transfer completed on attempt {$attempt}");
                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->warn("Attempt {$attempt} failed: " . $e->getMessage());
                if ($attempt == $maxRetries) {
                    $this->error("Transfer failed after {$maxRetries} attempts.");
                    return Command::FAILURE;
                }
                // Небольшая задержка перед повтором (опционально)
                usleep(50000); // 50 мс
            }
        }
    }

    private function doTransfer($fromId, $toId, $amount)
    {
        DB::transaction(function () use ($fromId, $toId, $amount) {
            // Читаем аккаунты с их текущей версией
            $from = Account::find($fromId);
            $to = Account::find($toId);

            if (!$from || !$to) {
                throw new \Exception('Account not found');
            }

            if ($from->balance < $amount) {
                throw new \Exception('Insufficient funds');
            }

            $oldFromVersion = $from->version;
            $oldToVersion = $to->version;

            // Вычисляем новые балансы и версии
            $newFromBalance = $from->balance - $amount;
            $newToBalance = $to->balance + $amount;
            $newFromVersion = $oldFromVersion + 1;
            $newToVersion = $oldToVersion + 1;

            // Оптимистичное обновление: обновляем только если версия не изменилась
            $updatedFrom = Account::where('id', $fromId)
                ->where('version', $oldFromVersion)
                ->update([
                    'balance' => $newFromBalance,
                    'version' => $newFromVersion,
                ]);

            $updatedTo = Account::where('id', $toId)
                ->where('version', $oldToVersion)
                ->update([
                    'balance' => $newToBalance,
                    'version' => $newToVersion,
                ]);

            // Если хотя бы один не обновился — значит, была параллельная модификация
            if ($updatedFrom == 0 || $updatedTo == 0) {
                throw new \Exception('Optimistic lock conflict, retrying...');
            }

            Transaction::create([
                'from_account_id' => $fromId,
                'to_account_id'   => $toId,
                'amount'          => $amount,
                'status'          => 'completed',
            ]);
        });
    }
}
