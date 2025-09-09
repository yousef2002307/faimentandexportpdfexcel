<?php
// app/Pipes/AddCheese.php

namespace App\Pipes;

use Closure;

class AddCheese
{
    public function handle($sandwich, Closure $next)
    {
        // Perform the task
        $sandwich->cheese = true;
        \App\Models\User::create([
            'name' => 'John Doe',
            'email' => 'zayed22@example.com',
            'password' => 'password',
        ]);
     
        // Pass the object to the next pipe
        return $next($sandwich);
    }
}