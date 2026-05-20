<?php
$u = \App\Models\User::first();
$u->password = \Illuminate\Support\Facades\Hash::make('admin123');
$u->save();
echo "OK";
