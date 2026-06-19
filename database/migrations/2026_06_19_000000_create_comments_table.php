<?php

use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('author_name')->nullable();
            $table->text('body')->nullable();
            $table->string('comment_type')->nullable();
            $table->timestamp('commented_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'vendor_id']);
            $table->index('commented_at');
        });

        DB::table('tickets')
            ->whereNotNull('comments')
            ->orderBy('id')
            ->each(function (object $ticket) {
                $comments = json_decode($ticket->comments, true);
                if (!is_array($comments)) {
                    return;
                }

                foreach ($comments as $comment) {
                    if (!is_array($comment)) {
                        continue;
                    }

                    Comment::create([
                        'ticket_id' => $ticket->id,
                        'vendor_id' => $comment['id'] ?? null,
                        'author_name' => $comment['author_name'] ?? $comment['author'] ?? null,
                        'body' => $comment['body'] ?? $comment['content'] ?? null,
                        'comment_type' => $comment['comment_type'] ?? null,
                        'commented_at' => $comment['created_at'] ?? $comment['createdAt'] ?? null,
                        'raw' => $comment,
                    ]);
                }
            });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('comments');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->json('comments')->nullable();
        });

        Ticket::query()->each(function (Ticket $ticket) {
            $comments = $ticket->comments()
                ->orderBy('commented_at')
                ->get()
                ->map(fn (Comment $comment) => $comment->raw ?? $comment->toArray())
                ->values()
                ->all();

            $ticket->update(['comments' => $comments ?: null]);
        });

        Schema::dropIfExists('comments');
    }
};
