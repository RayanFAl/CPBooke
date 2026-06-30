<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BooknowAirport extends Model
{
    use HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'booknow_airports';

    protected $primaryKey = 'iata_code';

    protected $keyType = 'string';

    protected $fillable = [
        'iata_code',
        'icao_code',
        'name_en',
        'name_ar',
        'name_fr',
        'city_en',
        'city_ar',
        'city_fr',
        'country_iso2',
        'country_name_en',
        'country_name_ar',
        'country_name_fr',
        'type',
        'scheduled_service',
        'latitude_deg',
        'longitude_deg',
        'translation_status',
    ];
}
