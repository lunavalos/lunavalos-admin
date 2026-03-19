<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItemCost extends Model
{
    protected $fillable = [
        'quote_item_id',
        'title',
        'quantity',
        'price'
    ];

    public function quoteItem()
    {
        return $this->belongsTo(QuoteItem::class);
    }
}
