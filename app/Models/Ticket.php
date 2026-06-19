<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    protected $fillable = [
        'vendor_id',
        'ticket_number',
        'subject',
        'description',
        'client_name',
        'status',
        'github_branches',
        'last_comment_at',
        'raw',
        'synced_at',
        'category_id',
    ];

    protected $casts = [
        'github_branches' => 'array',
        'raw' => 'array',
        'synced_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('commented_at');
    }

    public function latestComment(): HasOne
    {
        return $this->hasOne(Comment::class)->latestOfMany('commented_at');
    }
}
