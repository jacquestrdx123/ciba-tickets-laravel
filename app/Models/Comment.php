<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $fillable = [
        'ticket_id',
        'vendor_id',
        'author_name',
        'body',
        'comment_type',
        'commented_at',
        'raw',
    ];

    protected $casts = [
        'commented_at' => 'datetime',
        'raw' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'author' => $this->author_name,
            'author_name' => $this->author_name,
            'body' => $this->body,
            'content' => $this->body,
            'comment_type' => $this->comment_type,
            'created_at' => $this->commented_at?->toDateTimeString(),
        ];
    }
}
