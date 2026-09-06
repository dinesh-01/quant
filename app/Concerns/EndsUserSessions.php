<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait EndsUserSessions
{
    /**
     * Drop stored sessions and cycle the remember token so a remembered
     * device cannot keep the old access.
     *
     * Session rows are only authoritative on the `database` driver. The suite
     * uses `array`, so a test that wants the sweep must set the driver.
     *
     * @return int how many session rows were dropped
     */
    protected function endUserSessions(User $user): int
    {
        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
