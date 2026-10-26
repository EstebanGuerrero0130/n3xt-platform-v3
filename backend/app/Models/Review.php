<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Review extends Model
{
    protected $fillable = ['customer_id', 'order_id', 'product_ids', 'author_name', 'rating', 'comment', 'photo_url', 'status'];

    protected $casts = ['product_ids' => 'array', 'rating' => 'integer'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** Para el sitio: nombre corto ("Laura G."), sin correo ni pedido. */
    public function toPublic(): array
    {
        $parts = preg_split('/\s+/', trim($this->author_name));
        $name = $parts[0] . (isset($parts[1]) ? ' ' . Str::upper(Str::substr($parts[1], 0, 1)) . '.' : '');

        return [
            'id' => $this->id,
            'name' => $name,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'photo_url' => $this->photo_url,
            'product_ids' => $this->product_ids ?? [],
            'date' => $this->created_at?->toDateString(),
        ];
    }
}
