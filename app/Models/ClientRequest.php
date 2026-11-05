<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A write already applied for a client-generated request id, so a phone replaying
 * its offline queue (or a double tap) never saves the same thing twice.
 */
class ClientRequest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'request_id',
        'method',
        'path',
    ];
}
