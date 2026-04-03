<?php
// sail artisan transfer 1 2 200

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransferMoney extends Command
{
    protected $signature = 'transfer {from_id} {to_id} {amount}';
    protected $description = 'Transfer money between two accounts within a transaction';

    public function handle()
    {
        $fromId = $this->argument('from_id');
        $toId = $this->argument('to_id');
        $amount = (float) $this->argument('amount');

        // Стартуем транзакцию вручную для полного контроля
        DB::beginTransaction();

        try {
            // Блокируем строку отправителя для обновления (FOR UPDATE)
            $from = Account::lockForUpdate()->find($fromId);
            if (!$from) {
                throw new \Exception("Account from_id={$fromId} not found");
            }

            // Получателя блокировать необязательно (если не боимся гонки на остатке),
            // но для надёжности тоже залочим.
            $to = Account::lockForUpdate()->find($toId);
            if (!$to) {
                throw new \Exception("Account to_id={$toId} not found");
            }

            if ($from->balance < $amount) {
                throw new \Exception("Insufficient funds: {$from->balance} < {$amount}");
            }

            // Выполняем перевод
            $from->balance -= $amount;
            $to->balance += $amount;

            $from->save();
            $to->save();

            // Регистрируем транзакцию
            Transaction::create([
                'from_account_id' => $fromId,
                'to_account_id'   => $toId,
                'amount'          => $amount,
                'status'          => 'completed',
            ]);

            DB::commit();

            $this->info("Transferred {$amount} from account {$fromId} to {$toId} successfully.");
            return Command::SUCCESS;

        } catch (Throwable $e) {
            DB::rollBack();

            // Опционально: записать failed-транзакцию, но вне транзакции
            Transaction::create([
                'from_account_id' => $fromId,
                'to_account_id'   => $toId,
                'amount'          => $amount,
                'status'          => 'failed',
            ]);

            $this->error("Transfer failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
