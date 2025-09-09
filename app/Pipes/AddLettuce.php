<?php
// app/Pipes/AddLettuce.php

namespace App\Pipes;

use Closure;

class AddLettuce
{
    public function handle($sandwich, Closure $next)
    {
        // Perform the task
        $sandwich->lettuce = true;
        \App\Models\User::where('email','zayed22@example.com')->update([
            'name' => 'modified 2',
            'email' => 'zayed@example.com',
           
        ]);
     
        // Pass the object to the next pipe
        return $next($sandwich);
    }
}