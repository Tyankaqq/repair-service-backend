<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairRequest extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'clientName',
        'phone',
        'address',
        'problemText',
        'status',
        'assignedTo',
    ];

    public function master()
    {
        return $this->belongsTo(User::class, 'assignedTo');
    }
}
