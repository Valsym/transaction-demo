<?php
// sail artisan transfer:reserve 1 2 200

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransferWithReservation extends Command
{
    protected $signature = 'transfer:reserve {from_id} {to_id} {amount}';
    protected $description = 'Transfer with nested transaction (reservation)';

    public function handle()
    {
        $fromId = $this->argument('from_id');
        $toId = $this->argument('to_id');
        $amount = (float) $this->argument('amount');

        try {
            // Внешняя транзакция
            DB::transaction(function () use ($fromId, $toId, $amount) {
                $from = Account::lockForUpdate()->find($fromId);
                $to = Account::lockForUpdate()->find($toId);

                if (!$from || !$to) {
                    throw new \Exception('Account not found');
                }

                // Вложенная транзакция — резервирование
                DB::transaction(function () use ($from, $amount) {
                    if ($from->balance < $amount) {
                        throw new \Exception('Insufficient funds for reservation');
                    }
                    // Условно "резервируем" — можно создать отдельную запись в таблице reservations,
                    // но для демонстрации просто проверим баланс и, например, установим флаг.
                    // Здесь имитация резервирования.
                    $from->reserved = $amount; // предположим, добавили колонку reserved
                    $from->save();
                    $this->info("Reserved {$amount} from account {$from->id}");
                });

                // Списание и зачисление (всё ещё во внешней транзакции)
                $from->balance -= $amount;
                $to->balance += $amount;
                $from->reserved = 0; // снимаем резерв
                $from->save();
                $to->save();

                Transaction::create([
                    'from_account_id' => $fromId,
                    'to_account_id' => $toId,
                    'amount' => $amount,
                    'status' => 'completed',
                ]);

                $this->info("Transfer completed with reservation.");
            });
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Transfer failed: " . $e->getMessage());
            return Command::FAILURE;
        }

//        return Command::SUCCESS;
    }
}
