<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\SpamPredictionService;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable=[
        'body',
        'sender_id',
        'receiver_id',
        'conversation_id',
        'read_at',
        'is_spam',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'is_spam' => 'boolean'
    ];


    /* relationship */

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
    public function isLikelySpam(): bool
    {
        $predictionService = new SpamPredictionService();
        $prediction = $predictionService->getPrediction($this->id);
        return $prediction ? $prediction['is_spam'] : false;
    }

    public function isRead():bool
    {

         return $this->read_at != null;
    }
}
