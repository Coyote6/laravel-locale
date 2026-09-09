<?php

namespace Coyote6\LaravelLocale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// No Concerns\HasAbbreviation / HasAbbr here — see config/locale.php's
// 'abbreviations' block: subregions have no natural code/iso field for an
// abbreviation to mean anything.
class Subregion extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'translations' => 'array',
        'synced_at' => 'datetime',
    ];


    // Get Table
    //
    // Overrides Eloquent's default table-name guess with the configured
    // name, so consuming apps can rename the table without extending this
    // model.
    //
    // @return string
    //
    public function getTable(): string
    {
        return config('locale.table_names.subregions');
    }


    //
    // Relationships
    //


    // Region
    //
    // @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    //
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
