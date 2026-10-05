<?php

namespace App\Models;

use Database\Factories\SavedCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'method', 'path', 'model', 'body_mode', 'state', 'questions', 'body'])]
class SavedCall extends Model
{
    /** @use HasFactory<SavedCallFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'questions' => 'array',
        ];
    }
}
