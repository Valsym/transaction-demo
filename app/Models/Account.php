<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['name', 'balance', 'reserved', 'version'];

    // Транзакции, где этот аккаунт был отправителем
    public function sentTransactions()
    {
        return $this->hasMany(Transaction::class, 'from_account_id');
    }

    // Транзакции, где этот аккаунт был получателем
    public function receivedTransactions()
    {
        return $this->hasMany(Transaction::class, 'to_account_id');
    }

    // Если нужны все транзакции (и туда, и обратно) — можно через отношение
    // "много ко многим" самому себе, но для пет-проекта пока не обязательно.
}
