<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BooknowAirport extends Model
{
    use HasFactory;

    protected $table = 'booknow_airports';

    protected $fillable = [
        'name',
        'code',
        'city',
        'country',
    ];
}
