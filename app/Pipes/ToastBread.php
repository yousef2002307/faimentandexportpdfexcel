<?php

// app/Pipes/ToastBread.php

namespace App\Pipes;

use Closure;

class ToastBread
{
    public function handle($sandwich, Closure $next)
    {
        // Perform the task
        $sandwich->toasted = true;
        \App\Models\User::where('email','zayed@example.com')->update([
            'name' => 'modified 3',
            'email' => 'zayed333@example.com',
           
        ]);
        // Pass the object to the next pipe
        return $next($sandwich);
    }
}