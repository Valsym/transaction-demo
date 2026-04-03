<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'amount',
        'status',
    ];

    // Аккаунт-отправитель
    public function fromAccount()
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    // Аккаунт-получатель
    public function toAccount()
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }
}
