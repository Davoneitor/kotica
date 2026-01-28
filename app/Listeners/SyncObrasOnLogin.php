<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Services\SyncObrasDesdeErp;

class SyncObrasOnLogin
{
    public function __construct(private SyncObrasDesdeErp $sync) {}

  public function handle(\Illuminate\Auth\Events\Login $event): void
{
    $this->sync->sync();
}
}



