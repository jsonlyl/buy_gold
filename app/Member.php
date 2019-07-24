<?php

namespace App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $table = 'member';

    public function setPasswordAttribute($value)
    {
        return Hash::make($value);
    }
}